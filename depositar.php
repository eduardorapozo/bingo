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
$stmt = $pdo->prepare("SELECT id, nome_completo, email, cpf, saldo, is_admin FROM usuarios WHERE id = ?");
$stmt->execute([$usuario['id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Verificar se existe um depósito pendente
$stmt_pending = $pdo->prepare("SELECT id, valor, external_id, transaction_id_api, data_criacao FROM depositos WHERE usuario_id = ? AND status = 'PENDING' ORDER BY data_criacao DESC LIMIT 1");
$stmt_pending->execute([$user_data['id']]);
$deposito_pendente = $stmt_pending->fetch(PDO::FETCH_ASSOC);

// Buscar credenciais da API e configurações de jogo no banco de dados
$stmt_api = $pdo->prepare("SELECT client_id, client_secret, url_webhook FROM configuracoes_api LIMIT 1");
$stmt_api->execute();
$api_config = $stmt_api->fetch(PDO::FETCH_ASSOC);

$stmt_jogo = $pdo->prepare("SELECT min_deposito, max_deposito FROM configuracoes_jogo WHERE ativo = 1 LIMIT 1");
$stmt_jogo->execute();
$jogo_config = $stmt_jogo->fetch(PDO::FETCH_ASSOC);

$min_deposito = $jogo_config['min_deposito'] ?? 10.00;
$max_deposito = $jogo_config['max_deposito'] ?? 1000.00;

$api_client_id = $api_config['client_id'] ?? '';
$api_client_secret = $api_config['client_secret'] ?? '';
$url_notificacao_webhook = $api_config['url_webhook'] ?? 'https://SEU-SITE.COM/codexpay_webhook.php';

$qrCodeData = null;
$error = null;

// Se existe depósito pendente, gerar novo QR Code através da API CodexPay
if ($deposito_pendente) {
    try {
        // Inicializar API CodexPay
        $codexAPI = new CodexPayAPI($api_client_id, $api_client_secret);
        
        // Preparar dados para o depósito
        $payer = [
            'name' => $user_data['nome_completo'],
            'email' => $user_data['email'],
            'document' => preg_replace('/\D/', '', $user_data['cpf'])
        ];
        
        // Recriar depósito via API CodexPay para obter QR Code atualizado
        $response = $codexAPI->createDeposit(
            $deposito_pendente['valor'],
            $deposito_pendente['external_id'],
            $url_notificacao_webhook,
            $payer
        );
        
        if (isset($response['qrCodeResponse']['qrcode'])) {
            $qrCodeData = [
                'valor' => $deposito_pendente['valor'],
                'external_id' => $deposito_pendente['external_id'],
                'transactionId' => $response['qrCodeResponse']['transactionId'],
                'qrcode' => $response['qrCodeResponse']['qrcode'] // QR Code real da API CodexPay
            ];
        } else {
            $error = "Erro ao recarregar QR Code do depósito pendente";
        }
    } catch (Exception $e) {
        error_log("Erro ao recarregar depósito pendente: " . $e->getMessage());
        $error = "Erro ao recarregar depósito: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['valor']) && !$deposito_pendente) {
    $valor = floatval(str_replace(',', '.', $_POST['valor']));
    
    $nome = htmlspecialchars($user_data['nome_completo']);
    $cpf = preg_replace('/\D/', '', $user_data['cpf']);
    $external_id = uniqid('deposito_');

    if ($valor <= 0) {
        $error = "O valor deve ser positivo.";
    } elseif ($valor < $min_deposito || $valor > $max_deposito) {
        $error = "O valor deve ser entre R$ " . number_format($min_deposito, 2, ',', '.') . " e R$ " . number_format($max_deposito, 2, ',', '.') . ".";
    } elseif (empty($nome) || empty($cpf) || empty($api_client_id) || empty($api_client_secret)) {
        $error = "Dados do usuário ou credenciais da API incompletos.";
    } else {
        try {
            // Inicializar API CodexPay
            $codexAPI = new CodexPayAPI($api_client_id, $api_client_secret);
            
            // Preparar dados para o depósito
            $payer = [
                'name' => $nome,
                'email' => $user_data['email'],
                'document' => $cpf
            ];
            
            // Criar depósito via nova API
            $response = $codexAPI->createDeposit(
                $valor,
                $external_id,
                $url_notificacao_webhook,
                $payer
            );
            
            if (isset($response['qrCodeResponse']['qrcode'])) {
                $qrCodeData = [
                    'valor' => $valor,
                    'external_id' => $external_id,
                    'transactionId' => $response['qrCodeResponse']['transactionId'],
                    'qrcode' => $response['qrCodeResponse']['qrcode']
                ];

                try {
                    $stmt_deposito = $pdo->prepare("INSERT INTO depositos (usuario_id, valor, status, external_id, transaction_id_api) VALUES (?, ?, 'PENDING', ?, ?)");
                    $stmt_deposito->execute([
                        $user_data['id'],
                        $valor,
                        $external_id,
                        $response['qrCodeResponse']['transactionId']
                    ]);
                    
                    // Recarregar a página para mostrar o QR Code
                    header('Location: depositar.php');
                    exit;
                } catch (Exception $e) {
                    error_log("Erro ao salvar depósito pendente: " . $e->getMessage());
                    $error = "Erro ao salvar depósito no banco de dados.";
                }
            } else {
                $error = "Erro ao gerar QR Code: " . ($response['message'] ?? 'Erro desconhecido');
            }
        } catch (Exception $e) {
            error_log("Erro na API CodexPay: " . $e->getMessage());
            $error = "Erro na integração de pagamento: " . $e->getMessage();
        }
    }
}

$is_admin = $user_data['is_admin'] ?? 0;
$url_suporte = '#';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Depositar - Bingo Online</title>
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
        }

        /* Reset e Configurações Base */
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        input[type="number"] { -webkit-user-select: text; -moz-user-select: text; -ms-user-select: text; user-select: text; }
        
        body { 
            font-family: Arial, sans-serif; 
            background-color: #f0f2f5; 
            min-height: 100vh; 
            color: #333; 
            display: flex; 
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

        /* MOBILE HEADER */
        .mobile-header {
            display: none;
            align-items: center;
            justify-content: flex-start;
            padding: 15px 20px;
            background-color: var(--neutral-bg-light); 
            border-bottom: 1px solid var(--neutral-border);
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .menu-toggle { background: none; border: none; font-size: 24px; color: var(--primary-color); cursor: pointer; }
        
        /* DEPOSIT CONTAINER */
        .deposit-container { 
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
        .deposit-container h3 { 
            color: var(--primary-color); 
            font-size: 24px; 
            font-weight: bold; 
            margin-bottom: 25px; 
            text-shadow: none; 
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; color: var(--neutral-text); font-weight: bold; font-size: 15px; }
        
        .form-group input[type="number"] { 
            width: 100%; 
            padding: 12px; 
            border-radius: 8px; 
            border: 1px solid var(--neutral-border); 
            background: var(--neutral-bg-dark); 
            color: var(--neutral-text); 
            font-size: 16px; 
            text-align: center; 
            transition: border-color 0.3s, box-shadow 0.3s; 
        }
        .form-group input:focus { border-color: var(--primary-color); outline: none; box-shadow: 0 0 5px rgba(59, 130, 246, 0.5); }
        .form-group p { font-size: 12px; margin-top: 5px; color: var(--neutral-text); }
        
        /* BOTÃO DE SUBMIT COM SPINNER */
        .btn-submit { 
            width: 100%; 
            padding: 15px; 
            background: linear-gradient(135deg, var(--success-color), #059669); 
            border: none; 
            border-radius: 10px; 
            color: white; 
            font-size: 16px; 
            font-weight: bold; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            box-shadow: 0 5px 15px rgba(46, 213, 115, 0.4); 
            text-transform: uppercase; 
            position: relative;
        }
        .btn-submit:hover:not(:disabled) { background: linear-gradient(135deg, #059669, #047857); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(46, 213, 115, 0.6); }
        .btn-submit:disabled { opacity: 0.8; cursor: not-allowed; }

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
            display: none;
        }
        .btn-submit.loading .spinner { display: block; }
        .btn-submit.loading .btn-text { opacity: 0; }
        @keyframes spin { 0% { transform: translate(-50%, -50%) rotate(0deg); } 100% { transform: translate(-50%, -50%) rotate(360deg); } }

        /* PIX QR CODE SECTION */
        .pix-qr-section { margin-top: 30px; position: relative; }
        .pix-qr-section img { 
            max-width: 250px; 
            height: auto; 
            border-radius: 10px; 
            border: 5px solid var(--secondary-color); 
            box-shadow: 0 0 20px rgba(255,215,0,0.4); 
        }
        
        /* CRONÔMETRO */
        .timer-container {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        .timer-display {
            font-size: 24px;
            font-weight: bold;
            font-family: 'Orbitron', monospace;
        }
        .timer-text {
            font-size: 14px;
            margin-top: 5px;
        }

        /* STATUS INDICATOR */
        .status-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
            padding: 15px;
            border-radius: 10px;
            font-weight: bold;
        }
        .status-pending {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        .status-checking {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        .status-success {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
        }

        /* ANIMAÇÃO DE SUCESSO */
        .success-animation {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(39, 174, 96, 0.95);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: white;
            text-align: center;
        }
        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: successPulse 1s ease-in-out;
        }
        .success-text {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .success-amount {
            font-size: 32px;
            font-weight: bold;
            color: #FFD700;
        }
        @keyframes successPulse {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        .pix-copy-paste { margin-top: 20px; }
        .pix-copy-paste textarea { 
            width: 100%; 
            padding: 10px; 
            border-radius: 8px; 
            border: 1px solid var(--neutral-border); 
            background: var(--neutral-bg-dark); 
            color: var(--neutral-text); 
            font-size: 12px; 
            resize: none; 
            overflow: hidden; 
            height: 50px; 
            user-select: text;
        }
        .btn-copy { 
            margin-top: 10px; 
            padding: 10px 20px; 
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover)); 
            border: none; 
            border-radius: 8px; 
            color: white; 
            font-size: 14px; 
            font-weight: 600; 
            cursor: pointer; 
        }
        .btn-historico { 
            width: 100%; 
            padding: 12px; 
            margin-top: 15px; 
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

        .btn-new-deposit {
            width: 100%;
            padding: 12px;
            margin-top: 15px;
            background: linear-gradient(135deg, var(--success-color), #059669);
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

        /* TOAST */
        .toast { position: fixed; top: 20px; right: 20px; padding: 12px 18px; border-radius: 8px; color: white; font-weight: bold; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transform: translateX(300px); transition: all 0.3s ease-in-out; z-index: 1000; max-width: 300px; border: 1px solid; }
        .toast.show { transform: translateX(0); }
        .toast.success { background-color: var(--success-color); border-color: #059669; }
        .toast.error { background-color: var(--error-color); border-color: #b91c1c; }
        .toast.info { background-color: var(--primary-color); border-color: #2563eb; }
        
        /* OVERLAY */
        .overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.4); z-index: 99; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; pointer-events: none; }
        .overlay.show { opacity: 1; visibility: visible; pointer-events: auto; }

        @media (max-width: 768px) { 
            body { flex-direction: column; }
            .sidebar { transform: translateX(-100%); width: 100%; max-width: 250px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; max-width: 100vw; padding: 15px; }
            .mobile-header { display: flex; justify-content: space-between; }
            .deposit-container { padding: 20px; } 
            
            .mobile-header .user-balance { display: none; }
            .mobile-header .btn-historico-mobile {
                display: block;
                padding: 8px 15px;
                background-color: var(--primary-color);
                color: white;
                border-radius: 20px;
                font-weight: bold;
                font-size: 14px;
                text-decoration: none;
                transition: background-color 0.3s;
                margin-left: auto;
            }
            .mobile-header .btn-historico-mobile:hover {
                background-color: var(--primary-hover);
            }
        }
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
            <div class="user-balance" id="userBalance">Saldo: R$ <?php echo number_format($user_data['saldo'], 2, ',', '.'); ?></div>
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
            <a href="depositar.php" class="menu-item active">
                <img src="assets/icons/depositar.png" alt="Depositar" onerror="this.style.display='none'">
                Depositar
            </a>
            <a href="sacar.php" class="menu-item">
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
            <a href="historico_deposito.php" class="btn-historico-mobile">
                <i class="fas fa-history"></i> Histórico
            </a>
        </div>

        <div class="deposit-container">
            <?php if ($error): ?>
                <script>
                    window.onload = function() {
                        showToast('<?php echo htmlspecialchars($error); ?>', 'error');
                    };
                </script>
            <?php endif; ?>
            
            <?php if ($qrCodeData): ?>
                <div class="pix-qr-section">
                    <h3>Pagar com PIX</h3>
                    
                    <!-- Timer -->
                    <div class="timer-container">
                        <div class="timer-display" id="timer">05:00</div>
                        <div class="timer-text">Tempo restante para pagamento</div>
                    </div>
                    
                    <!-- Status -->
                    <div class="status-indicator status-pending" id="statusIndicator">
                        <i class="fas fa-clock"></i>
                        <span id="statusText">Aguardando pagamento...</span>
                    </div>
                    
                    <p style="margin-bottom: 20px; color: var(--neutral-text);">Use o QR Code ou o código abaixo para pagar o valor de R$ <?php echo number_format($qrCodeData['valor'] ?? 0, 2, ',', '.'); ?>.</p>
                    
                    <!-- QR Code gerado pela API CodexPay -->
                    <?php 
                    // O qrcode vem como string pura da API CodexPay, vamos usar um gerador de QR Code visual
                    $qrCodeData_string = htmlspecialchars($qrCodeData['qrcode']);
                    ?>
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=<?php echo urlencode($qrCodeData_string); ?>" alt="QR Code PIX" id="qrCodeImage">
                    
                    <div class="pix-copy-paste">
                        <textarea id="pix-code" readonly><?php echo htmlspecialchars($qrCodeData['qrcode']); ?></textarea>
                        <button class="btn-copy" onclick="copyPixCode()">Copiar Código</button>
                    </div>
                    
                    <button class="btn-new-deposit" onclick="newDeposit()">
                        <i class="fas fa-plus"></i> Fazer Novo Depósito
                    </button>
                </div>
                
                <script>
                    const depositData = {
                        external_id: '<?php echo $qrCodeData['external_id']; ?>',
                        valor: <?php echo $qrCodeData['valor']; ?>,
                        data_criacao: '<?php echo $deposito_pendente['data_criacao']; ?>'
                    };
                </script>
            <?php else: ?>
                <form method="POST" action="depositar.php" id="depositForm">
                    <h3>Fazer Depósito PIX</h3>
                    <div class="form-group">
                        <label for="valor">Valor do Depósito (R$)</label>
                        <input type="number" id="valor" name="valor" step="0.01" min="<?php echo $min_deposito; ?>" max="<?php echo $max_deposito; ?>" required>
                        <p>Valor mínimo: R$ <?php echo number_format($min_deposito, 2, ',', '.'); ?>, Valor máximo: R$ <?php echo number_format($max_deposito, 2, ',', '.'); ?></p>
                    </div>
                    <button type="submit" class="btn-submit" id="submitBtn">
                        <span class="btn-text"><i class="fas fa-qrcode"></i> Gerar QR Code</span>
                        <div class="spinner"></div>
                    </button>
                    <a href="historico_deposito.php" class="btn-historico">
                        <i class="fas fa-history"></i> Histórico de Depósitos
                    </a>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Animação de Sucesso -->
    <div class="success-animation" id="successAnimation">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="success-text">Pagamento Recebido!</div>
        <div class="success-amount" id="successAmount">R$ 0,00</div>
        <p style="margin-top: 15px; font-size: 16px;">Seu saldo foi atualizado com sucesso!</p>
    </div>
    
    <div class="toast" id="toast"></div>

    <script>
        let timer = null;
        let timeLeft = 300; // 5 minutos em segundos
        let checkInterval = null;

        // Função de Toast
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast show ${type}`;
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Função de Copiar PIX
        function copyPixCode() {
            const pixCode = document.getElementById('pix-code');
            pixCode.select();
            pixCode.setSelectionRange(0, 99999);
            document.execCommand('copy');
            showToast('Código PIX copiado!', 'success');
        }

        // Função para novo depósito
        function newDeposit() {
            if (confirm('Tem certeza que deseja cancelar este PIX e gerar um novo?')) {
                // Cancelar o depósito atual
                fetch('cancelar_deposito.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        external_id: depositData.external_id
                    })
                }).then(() => {
                    window.location.reload();
                });
            }
        }

        // Cronômetro
        function startTimer() {
            timer = setInterval(() => {
                timeLeft--;
                updateTimerDisplay();
                
                if (timeLeft <= 0) {
                    clearInterval(timer);
                    expirePayment();
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            const timerElement = document.getElementById('timer');
            if (timerElement) {
                timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                // Mudar cor quando restam menos de 60 segundos
                if (timeLeft <= 60) {
                    timerElement.style.color = '#ff4757';
                    timerElement.parentElement.style.background = 'linear-gradient(135deg, #ff4757, #c44569)';
                }
            }
        }

        function expirePayment() {
            const statusIndicator = document.getElementById('statusIndicator');
            const statusText = document.getElementById('statusText');
            
            if (statusIndicator && statusText) {
                statusIndicator.className = 'status-indicator status-error';
                statusIndicator.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
                statusText.innerHTML = '<i class="fas fa-times-circle"></i> PIX Expirado';
            }
            
            showToast('PIX expirado! Gere um novo código.', 'error');
            
            // Desabilitar QR Code
            const qrImage = document.getElementById('qrCodeImage');
            if (qrImage) {
                qrImage.style.opacity = '0.5';
                qrImage.style.filter = 'grayscale(100%)';
            }
        }

        // Verificação de pagamento
        function checkPaymentStatus() {
            if (!depositData.external_id) return;
            
            fetch('verificar_pagamento.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    external_id: depositData.external_id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'PAID') {
                    clearInterval(timer);
                    clearInterval(checkInterval);
                    showPaymentSuccess(data.valor, data.novo_saldo);
                } else if (data.status === 'CHECKING') {
                    updateStatusToChecking();
                }
            })
            .catch(error => {
                console.error('Erro ao verificar pagamento:', error);
            });
        }

        function updateStatusToChecking() {
            const statusIndicator = document.getElementById('statusIndicator');
            const statusText = document.getElementById('statusText');
            
            if (statusIndicator && statusText) {
                statusIndicator.className = 'status-indicator status-checking';
                statusText.innerHTML = '<i class="fas fa-sync fa-spin"></i> Verificando pagamento...';
            }
        }

        function showPaymentSuccess(valor, novoSaldo) {
            const successAnimation = document.getElementById('successAnimation');
            const successAmount = document.getElementById('successAmount');
            const userBalance = document.getElementById('userBalance');
            
            if (successAnimation && successAmount) {
                successAmount.textContent = `R$ ${valor.toFixed(2).replace('.', ',')}`;
                successAnimation.style.display = 'flex';
                
                // Atualizar saldo na sidebar
                if (userBalance) {
                    userBalance.textContent = `Saldo: R$ ${novoSaldo.toFixed(2).replace('.', ',')}`;
                }
                
                // Remover animação após 4 segundos e redirecionar
                setTimeout(() => {
                    successAnimation.style.display = 'none';
                    window.location.href = 'main.php';
                }, 4000);
            }
        }

        // Inicializar se há PIX ativo
        if (typeof depositData !== 'undefined') {
            // Calcular tempo restante baseado na data de criação
            const createdAt = new Date(depositData.data_criacao);
            const now = new Date();
            const elapsed = Math.floor((now - createdAt) / 1000);
            timeLeft = Math.max(300 - elapsed, 0); // 5 minutos - tempo decorrido
            
            if (timeLeft > 0) {
                startTimer();
                updateTimerDisplay();
                
                // Verificar pagamento a cada 3 segundos
                checkInterval = setInterval(checkPaymentStatus, 3000);
                
                // Verificação inicial
                checkPaymentStatus();
            } else {
                expirePayment();
            }
        }

        // Lógica do Menu Mobile
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

        function logout() {
            if (confirm('Tem certeza que deseja sair?')) {
                window.location.href = 'libs/logout.php';
            }
        }
        
        // Lógica do Spinner de 1.5s
        document.getElementById('depositForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');

            if (btn.classList.contains('loading')) return;

            // 1. Inicia o loading
            btn.classList.add('loading');
            btn.disabled = true;

            // 2. Espera 1.5 segundos
            setTimeout(() => {
                // 3. Finaliza o loading e envia o formulário
                btn.classList.remove('loading');
                btn.disabled = false;
                e.target.submit(); // Envia o formulário
            }, 1500); // 1.5 segundos
        });
    </script>
</body>
</html>