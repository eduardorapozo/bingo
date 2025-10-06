<?php
// Inclui o arquivo de conexão e inicia a sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_logado'])) {
    header('Location: login.php');
    exit;
}

require_once('conexao.php');
require_once('class/CodexPayAPI.php');
$usuario = $_SESSION['usuario_logado'];

// Buscar dados completos do usuário
$stmt = $pdo->prepare("SELECT id, nome_completo, email, cpf, saldo, is_admin, total_saque_diario, ultima_data_saque FROM usuarios WHERE id = ?");
$stmt->execute([$usuario['id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Buscar credenciais da API e configurações de jogo no banco de dados
$stmt_api = $pdo->prepare("SELECT client_id, client_secret, url_webhook FROM configuracoes_api LIMIT 1");
$stmt_api->execute();
$api_config = $stmt_api->fetch(PDO::FETCH_ASSOC);

$stmt_jogo = $pdo->prepare("SELECT min_saque, max_saque, saque_diario_limite, saque_automatico_ativo FROM configuracoes_jogo WHERE ativo = 1 LIMIT 1");
$stmt_jogo->execute();
$jogo_config = $stmt_jogo->fetch(PDO::FETCH_ASSOC);

$min_saque = $jogo_config['min_saque'] ?? 20.00;
$max_saque = $jogo_config['max_saque'] ?? 5000.00;
$saque_diario_limite = $jogo_config['saque_diario_limite'] ?? 1000.00;
$saque_automatico_ativo = $jogo_config['saque_automatico_ativo'] ?? 1;

$api_client_id = $api_config['client_id'] ?? '';
$api_client_secret = $api_config['client_secret'] ?? '';
$url_notificacao_webhook = $api_config['url_webhook'] ?? 'https://SEU-SITE.COM/codexpay_webhook.php';

$success_message = null;
$error_message = null;

// Função auxiliar para determinar o tipo de chave PIX
function determineKeyType($key) {
    // Verificar padrões de email
    if (filter_var($key, FILTER_VALIDATE_EMAIL)) {
        return 'EMAIL';
    }
    
    // Verificar padrão de CPF (11 dígitos)
    if (preg_match('/^\d{11}$/', preg_replace('/\D/', '', $key))) {
        return 'CPF';
    }
    
    // Verificar padrão de CNPJ (14 dígitos)
    if (preg_match('/^\d{14}$/', preg_replace('/\D/', '', $key))) {
        return 'CNPJ';
    }
    
    // Verificar padrão de telefone celular (11 dígitos começando com 3)
    if (preg_match('/^3\d{10}$/', preg_replace('/\D/', '', $key))) {
        return 'PHONE';
    }
    
    // Por padrão, considerar como EMAIL se contém @, senão CPF
    return (strpos($key, '@') !== false) ? 'EMAIL' : 'CPF';
}

// Lógica de reset do limite diário
$hoje = date('Y-m-d');
if ($user_data['ultima_data_saque'] !== $hoje) {
    $pdo->prepare("UPDATE usuarios SET total_saque_diario = 0, ultima_data_saque = ? WHERE id = ?")->execute([$hoje, $user_data['id']]);
    $user_data['total_saque_diario'] = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valor_saque'])) {
    $valor_saque = floatval(str_replace(',', '.', $_POST['valor_saque']));
    $chave_pix = $_POST['chave_pix'] ?? '';

    // Validações
    if ($valor_saque <= 0) {
        $error_message = "O valor deve ser positivo.";
    } elseif ($valor_saque > $user_data['saldo']) {
        $error_message = "Saldo insuficiente para o saque.";
    } elseif ($valor_saque < $min_saque || $valor_saque > $max_saque) {
        $error_message = "O valor deve ser entre R$ " . number_format($min_saque, 2, ',', '.') . " e R$ " . number_format($max_saque, 2, ',', '.') . ".";
    } elseif (($user_data['total_saque_diario'] + $valor_saque) > $saque_diario_limite) {
        $error_message = "Limite de saque diário de R$ " . number_format($saque_diario_limite, 2, ',', '.') . " excedido.";
    } elseif (empty($chave_pix)) {
        $error_message = "Chave PIX é obrigatória.";
    } else {
        try {
            $pdo->beginTransaction();

            $novo_saldo = $user_data['saldo'] - $valor_saque;
            $novo_total_saque_diario = $user_data['total_saque_diario'] + $valor_saque;

            // Insere o pedido de saque na nova tabela 'saques' com status PENDING
            $stmt_saque = $pdo->prepare("INSERT INTO saques (usuario_id, valor, chave_pix, status) VALUES (?, ?, ?, 'PENDING')");
            $stmt_saque->execute([$user_data['id'], $valor_saque, $chave_pix]);
            $saque_id = $pdo->lastInsertId();

            // Deduz o saldo do usuário imediatamente e atualiza o total diário
            $stmt_user = $pdo->prepare("UPDATE usuarios SET saldo = ?, total_saque_diario = ?, ultima_data_saque = ? WHERE id = ?");
            $stmt_user->execute([$novo_saldo, $novo_total_saque_diario, $hoje, $user_data['id']]);
            
            // Registra a transação de saque na tabela 'transacoes'
            $descricao_transacao = "Saque solicitado, ID interno: {$saque_id}";
            $stmt_transacao = $pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, saldo_anterior, saldo_atual, descricao) VALUES (?, 'saque', ?, ?, ?, ?)");
            $stmt_transacao->execute([$user_data['id'], $valor_saque, $user_data['saldo'], $novo_saldo, $descricao_transacao]);

            $pdo->commit();
            $success_message = "Saque de R$ " . number_format($valor_saque, 2, ',', '.') . " solicitado com sucesso!";
            $user_data['saldo'] = $novo_saldo;
            $user_data['total_saque_diario'] = $novo_total_saque_diario;

            // Se saque automático estiver ativo, faz a chamada para a API
            if ($saque_automatico_ativo) {
                try {
                    // Inicializar API CodexPay
                    $codexAPI = new CodexPayAPI($api_client_id, $api_client_secret);
                    
                    // Preparar dados para o saque
                    $response = $codexAPI->createWithdrawal(
                        $valor_saque,
                        $user_data['nome_completo'],
                        preg_replace('/\D/', '', $user_data['cpf']),
                        $saque_id,
                        $chave_pix,
                        determineKeyType($chave_pix),
                        "Saque de saldo - Bingo Online",
                        $url_notificacao_webhook
                    );
                    
                    if (isset($response['withdrawal']['transaction_id'])) {
                        $stmt_update_saque = $pdo->prepare("UPDATE saques SET transaction_id_api = ? WHERE id = ?");
                        $stmt_update_saque->execute([$response['withdrawal']['transaction_id'], $saque_id]);
                        $success_message .= " Processando pagamento automático...";
                    } else {
                        $error_message = "Erro no saque automático. O valor será processado manualmente. Por favor, entre em contato com o suporte.";
                        error_log("Erro saque automático para o saque {$saque_id}: " . ($response['message'] ?? 'Erro desconhecido'));
                    }
                } catch (Exception $e) {
                    error_log("Erro na API CodexPay para saque {$saque_id}: " . $e->getMessage());
                    $error_message = "Erro no saque automático. O valor será processado manualmente. Por favor, entre em contato com o suporte.";
                }
            }
            // Redireciona para exibir a mensagem de sucesso
            header('Location: sacar.php?success=' . urlencode($success_message));
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Erro ao processar o saque: " . $e->getMessage();
            header('Location: sacar.php?error=' . urlencode($error_message));
            exit;
        }
    }
}

// Pega mensagens do GET (após redirecionamento)
if (isset($_GET['success'])) {
    $success_message = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error_message = $_GET['error'];
}

$is_admin = $user_data['is_admin'] ?? 0;
$url_suporte = '#';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sacar - Bingo Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Cores de Destaque - Tema Claro */
        :root {
            --primary-color: #3b82f6; /* Azul Principal */
            --primary-hover: #2563eb;
            --secondary-color: #FFD700; /* Amarelo/Dourado do Bingo */
            --secondary-dark: #FFA500;
            --success-color: #10b981; /* Verde */
            --error-color: #ef4444; /* Vermelho */
            --neutral-bg-light: #ffffff; /* Fundo dos painéis claro */
            --neutral-bg-dark: #e9ecef; /* Um cinza um pouco mais escuro para elementos de fundo */
            --neutral-border: #dee2e6; /* Cor de borda para tema claro */
            --neutral-text: #495057; /* Texto neutro escuro */
            --secondary-text-light: #6c757d; /* Texto secundário (para limites, etc.) */
        }

        /* Reset e Configurações Base */
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        input { -webkit-user-select: text; -moz-user-select: text; -ms-user-select: text; user-select: text; }
        
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f0f2f5; 
            min-height: 100vh; 
            color: #333; 
            display: flex; 
            /* Manter user-select none no body para evitar seleção indesejada fora de inputs */
            -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; 
        }

        /* SIDEBAR - Cores do tema claro anterior */
        .sidebar { 
            width: 250px; 
            background-color: var(--neutral-bg-light); 
            height: 100vh; 
            position: fixed; 
            left: 0; 
            top: 0; 
            z-index: 100; 
            overflow-y: auto; 
            box-shadow: 2px 0 5px rgba(0,0,0,0.1); 
            border-right: 1px solid var(--neutral-border); 
            transition: transform 0.3s ease; 
        }
        .sidebar-header { 
            padding: 20px; 
            background-color: var(--neutral-bg-dark); 
            border-bottom: 1px solid var(--neutral-border); 
            text-align: center; 
        }
        .logo { width: 100px; height: auto; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; }
        .logo img { width: 100%; height: 100%; border-radius: 8px; }
        .sidebar-header h2 { color: var(--neutral-text); font-size: 20px; margin-bottom: 5px; }
        .user-info { color: var(--neutral-text); font-size: 12px; margin-bottom: 10px; }
        .user-balance { background-color: var(--primary-color); color: white; padding: 10px 15px; border-radius: 20px; font-size: 14px; font-weight: bold; margin-bottom: 15px; }
        .sidebar-menu { padding: 10px 0; }
        .menu-item { display: flex; align-items: center; padding: 12px 20px; color: var(--neutral-text); text-decoration: none; transition: background-color 0.3s ease, color 0.3s ease; border: none; background: none; width: 100%; cursor: pointer; font-size: 14px; }
        .menu-item:hover { background-color: var(--neutral-bg-dark); color: var(--primary-color); }
        .menu-item.active { background-color: var(--primary-color); color: white; border-left: 5px solid var(--secondary-color); }
        .menu-item img { width: 20px; height: 20px; margin-right: 10px; filter: none; }
        .deposit-btn { background-color: var(--success-color) !important; color: white !important; border: none !important; padding: 10px 15px !important; border-radius: 20px !important; font-weight: bold !important; cursor: pointer !important; transition: background-color 0.3s ease !important; text-decoration: none !important; display: flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important; margin: 15px 20px !important; box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important; }
        
        /* MAIN CONTENT AREA */
        .main-content { 
            margin-left: 250px; 
            flex: 1; 
            padding: 20px; 
            min-height: 100vh; 
            background-color: #f0f2f5; 
            max-width: calc(100vw - 250px); 
            overflow-x: hidden; 
        }

        /* MOBILE HEADER - ALINHAR COM O PERFIL.PHP/DEPOSITAR.PHP */
        .mobile-header {
            display: none;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            background-color: var(--neutral-bg-light); 
            border-bottom: 1px solid var(--neutral-border);
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .menu-toggle { background: none; border: none; font-size: 24px; color: var(--primary-color); cursor: pointer; }
        
        /* Adiciona o botão de histórico no topo mobile */
        .btn-historico-mobile {
            display: none;
            padding: 8px 15px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            transition: background-color 0.3s;
            margin-left: auto; /* Para alinhar à direita ao lado do toggle */
        }
        .btn-historico-mobile:hover { background-color: var(--primary-hover); }

        @media (max-width: 768px) {
            .mobile-header { display: flex; justify-content: space-between; }
            .mobile-header .user-balance { display: none; }
            .mobile-header .btn-historico-mobile { display: block; }
            .sidebar { transform: translateX(-100%); width: 100%; max-width: 250px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; max-width: 100vw; padding: 15px; }
            .sacar-container { padding: 20px; }
        }
        
        /* SACAR CONTAINER - TEMA CLARO */
        .sacar-container { 
            max-width: 600px; 
            margin: 20px auto; 
            background: var(--neutral-bg-light); 
            border-radius: 12px; 
            padding: 30px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            border: 1px solid var(--neutral-border); 
            text-align: center; 
            color: var(--neutral-text);
        }
        .sacar-container h3 { 
            color: var(--primary-color); 
            font-size: 24px; 
            font-weight: bold; 
            margin-bottom: 25px; 
            text-shadow: none; 
        }
        .form-group { margin-bottom: 20px; text-align: left; }
        .form-group label { display: block; margin-bottom: 8px; color: var(--neutral-text); font-weight: bold; font-size: 15px; }
        
        .form-group input { 
            width: 100%; 
            padding: 12px; 
            border-radius: 8px; 
            border: 1px solid var(--neutral-border); 
            background: var(--neutral-bg-dark); 
            color: var(--neutral-text); 
            font-size: 16px; 
            transition: border-color 0.3s, box-shadow 0.3s; 
        }
        .form-group input:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 5px rgba(59, 130, 246, 0.5); }
        .form-group p { font-size: 12px; margin-top: 5px; color: var(--secondary-text-light); text-align: center; }

        /* INFO PANEL */
        .info-panel { 
            background: var(--neutral-bg-dark); 
            border-radius: 10px; 
            padding: 20px; 
            margin-bottom: 20px; 
            text-align: left;
            border: 1px solid var(--neutral-border);
        }
        .info-item { margin-bottom: 10px; font-size: 15px; display: flex; justify-content: space-between; align-items: center; }
        .info-item .label { color: var(--secondary-text-light); }
        .info-item .value { color: var(--neutral-text); font-weight: 700; }
        .info-item .balance-value { color: var(--primary-color); font-weight: bold; font-family: Arial, sans-serif; } /* Ajuste a cor do saldo */
        
        /* BOTÃO DE SUBMIT COM SPINNER */
        .btn-submit { 
            width: 100%; 
            padding: 15px; 
            background: linear-gradient(135deg, var(--error-color), #c22b2b); /* Saque em vermelho */
            border: none; 
            border-radius: 10px; 
            color: white; 
            font-size: 16px; 
            font-weight: bold; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4); 
            text-transform: uppercase; 
            position: relative;
        }
        .btn-submit:hover:not(:disabled) { background: linear-gradient(135deg, #c22b2b, #b91c1c); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(239, 68, 68, 0.6); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }

        /* SPINNER STYLES */
        .spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            display: none; /* Escondido por padrão */
        }
        .btn-submit.loading .spinner { display: block; }
        .btn-submit.loading .btn-text { opacity: 0; }
        @keyframes spin { 0% { transform: translate(-50%, -50%) rotate(0deg); } 100% { transform: translate(-50%, -50%) rotate(360deg); } }

        /* HISTÓRICO LINK - Usar cores primárias (Azul) */
        .btn-historico { 
            width: 100%; 
            padding: 12px; 
            margin-top: 10px; 
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); 
            border: none; 
            border-radius: 10px; 
            color: white; 
            font-size: 14px; 
            font-weight: 600; 
            cursor: pointer; 
            text-decoration: none; 
            display: block; 
            transition: all 0.3s ease; 
        }
        .btn-historico:hover { transform: translateY(-1px); box-shadow: 0 5px 15px rgba(59, 130, 246, 0.4); }

        /* TOASTS - Cores do tema claro anterior */
        .toast { position: fixed; top: 20px; right: 20px; padding: 12px 18px; border-radius: 8px; color: white; font-weight: bold; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transform: translateX(300px); transition: all 0.3s ease-in-out; z-index: 1000; max-width: 300px; border: 1px solid; }
        .toast.show { transform: translateX(0); }
        .toast.success { background-color: var(--success-color); border-color: #059669; }
        .toast.error { background-color: var(--error-color); border-color: #b91c1c; }
        .toast.info { background-color: var(--primary-color); border-color: #2563eb; }
        
        /* OVERLAY - Cores do tema claro anterior */
        .overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); z-index: 99; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; pointer-events: none; }
        .overlay.show { opacity: 1; visibility: visible; pointer-events: auto; }
    </style>
</head>
<body>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="main.php" class="logo">
                <img src="assets/images/logo.png" alt="Logo" onerror="this.style.display='none'">
            </a>
            <h2>BINGO ONLINE</h2>
            <div class="user-info">Olá, <?php echo htmlspecialchars($user_data['nome_completo']); ?></div>
            <div class="user-balance">Saldo: R$ <?php echo number_format($user_data['saldo'], 2, ',', '.'); ?></div>
            <a href="depositar.php" class="deposit-btn">
                <i class="fas fa-plus"></i>
                DEPOSITAR
            </a>
            <?php if ($user_data['is_admin']): ?>
            <a href="dash/" class="menu-item">
                <img src="assets/icons/settings.png" alt="Administrativo" onerror="this.style.display='none'">
                Administrativo
            </a>
            <?php endif; ?>
        </div>
        <nav class="sidebar-menu">
            <a href="main.php" class="menu-item">
                <img src="assets/icons/salas.png" alt="Salas" onerror="this.style.display='none'">
                Salas
            </a>
            <a href="perfil.php" class="menu-item">
                <img src="assets/icons/perfil.png" alt="Perfil" onerror="this.style.display='none'">
                Perfil
            </a>
            <a href="depositar.php" class="menu-item">
                <img src="assets/icons/depositar.png" alt="Depositar" onerror="this.style.display='none'">
                Depositar
            </a>
            <a href="sacar.php" class="menu-item active">
                <img src="assets/icons/sacar.png" alt="Sacar" onerror="this.style.display='none'">
                Sacar
            </a>
            <a href="historico.php" class="menu-item">
                <img src="assets/icons/historico.png" alt="Histórico" onerror="this.style.display='none'">
                Histórico
            </a>
            <a href="<?php echo htmlspecialchars($url_suporte); ?>" class="menu-item">
                <img src="assets/icons/suporte.png" alt="Suporte" onerror="this.style.display='none'">
                Suporte
            </a>
            <button class="menu-item" onclick="logout()">
                <img src="assets/icons/sair.png" alt="Sair" onerror="this.style.display='none'">
                Sair
            </button>
        </nav>
    </div>
    
    <div class="overlay" id="overlay"></div>
    
    <div class="main-content">
        <div class="mobile-header">
            <button class="menu-toggle" id="menuToggle">☰</button>
            <a href="historico_saque.php" class="btn-historico-mobile">
                <i class="fas fa-history"></i> Histórico
            </a>
        </div>

        <div class="sacar-container">
            <h3><i class="fas fa-hand-holding-usd"></i> Sacar Dinheiro</h3>
            
            <div class="info-panel">
                <div class="info-item">
                    <span class="label">Saldo Disponível:</span>
                    <span class="value balance-value">R$ <?php echo number_format($user_data['saldo'], 2, ',', '.'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Limite diário restante:</span>
                    <span class="value">R$ <?php echo number_format($saque_diario_limite - $user_data['total_saque_diario'], 2, ',', '.'); ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Saque já realizado hoje:</span>
                    <span class="value">R$ <?php echo number_format($user_data['total_saque_diario'], 2, ',', '.'); ?></span>
                </div>
                <a href="historico_saque.php" class="btn-historico">
                    <i class="fas fa-history"></i> Histórico de Saques
                </a>
            </div>

            <?php if ($success_message || $error_message): ?>
                <div class="toast show <?php echo $success_message ? 'success' : 'error'; ?>" id="initialToast">
                    <?php echo htmlspecialchars($success_message ?: $error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="sacar.php" id="saqueForm">
                <div class="form-group">
                    <label for="valor_saque">Valor do Saque (R$)</label>
                    <input type="number" id="valor_saque" name="valor_saque" step="0.01" min="<?php echo $min_saque; ?>" max="<?php echo $max_saque; ?>" required>
                    <p>
                        Valor mínimo: R$ <?php echo number_format($min_saque, 2, ',', '.'); ?>, Valor máximo: R$ <?php echo number_format($max_saque, 2, ',', '.'); ?>
                    </p>
                </div>
                <div class="form-group">
                    <label for="chave_pix">Chave PIX</label>
                    <input type="text" id="chave_pix" name="chave_pix" placeholder="CPF, Email, Telefone, etc." required>
                </div>
                <button type="submit" class="btn-submit" id="submitBtn">
                    <span class="btn-text"><i class="fas fa-paper-plane"></i> SOLICITAR SAQUE</span>
                    <div class="spinner"></div>
                </button>
            </form>
        </div>
    </div>
    
    <div class="toast" id="toast"></div>

    <script>
        // Função para exibir Toast
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            // Remove toasts existentes
            document.querySelectorAll('.toast.show').forEach(t => t.classList.remove('show'));

            toast.textContent = message;
            toast.className = `toast show ${type}`;
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Lógica de Menu Mobile e Overlay
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                sidebar.classList.toggle('open');
                overlay.classList.toggle('show');
            });
        }
        if (overlay) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
            });
        }
        
        /**
         * CORREÇÃO: Remove os parâmetros GET (success/error) da URL
         * para evitar que o toast reapareça após o refresh manual.
         */
        window.onload = function() {
            const initialToast = document.getElementById('initialToast');
            if (initialToast) {
                // Remove o parâmetro da URL usando History API
                if (window.history.replaceState) {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('success');
                    url.searchParams.delete('error');
                    window.history.replaceState({path: url.href}, '', url.href);
                }
                
                // Faz o toast sumir após 4 segundos (como no código anterior)
                setTimeout(() => {
                    initialToast.classList.remove('show');
                }, 4000);
            }
        };

        function logout() {
            if (confirm('Tem certeza que deseja sair?')) {
                window.location.href = 'libs/logout.php';
            }
        }

        // Lógica do Spinner de 1.5s
        document.getElementById('saqueForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const btn = document.getElementById('submitBtn');

            if (btn.classList.contains('loading')) return;
            
            // Permite o backend validar o valor/limite e retorna se houver erro
            const valor = parseFloat(document.getElementById('valor_saque').value);
            const saldo = parseFloat('<?php echo $user_data['saldo']; ?>');

            if (valor <= 0 || isNaN(valor) || valor > saldo) {
                // Mostra um feedback rápido no front-end para erros óbvios
                showToast('Verifique o valor e o saldo disponíveis.', 'error');
                form.submit(); // Envia para o PHP tratar a mensagem formal
                return;
            }

            // 1. Inicia o loading
            btn.classList.add('loading');
            btn.disabled = true;

            // 2. Espera 1.5 segundos
            setTimeout(() => {
                // 3. Finaliza o loading e envia o formulário
                btn.classList.remove('loading');
                btn.disabled = false;
                form.submit(); // Envia o formulário
            }, 1500); // 1.5 segundos
        });
    </script>
</body>
</html>