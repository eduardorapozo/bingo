<?php
session_start();

if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../login.php');
    exit;
}

require_once('../conexao.php');

$usuario_logado = $_SESSION['usuario_logado'];

// Verifica se o usuário logado é um administrador
$stmt = $pdo->prepare("SELECT is_admin FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_logado['id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data || $user_data['is_admin'] != 1) {
    header('Location: ../main.php');
    exit;
}

// Processa o formulário de configuração quando for enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Configurações do Bingo
    $min_cartelas = intval($_POST['min_cartelas']);
    $max_cartelas = intval($_POST['max_cartelas']);
    $intervalo_jogos = intval($_POST['intervalo_jogos']);
    $rtp = intval($_POST['rtp']);
    $velocidade_sorteio = intval($_POST['velocidade_sorteio']);
    $ativar_bots = isset($_POST['ativar_bots']) ? 1 : 0;
    $url_suporte = trim($_POST['url_suporte']);
    $ativar_narracao = isset($_POST['ativar_narracao']) ? 1 : 0;
    $valores_cartela_str = trim($_POST['valores_cartela']);

    // Novas configurações de depósito e saque
    $min_deposito = floatval($_POST['min_deposito']);
    $max_deposito = floatval($_POST['max_deposito']);
    $min_saque = floatval($_POST['min_saque']);
    $max_saque = floatval($_POST['max_saque']);
    $saque_diario_limite = floatval($_POST['saque_diario_limite']);
    $saque_automatico_ativo = isset($_POST['saque_automatico_ativo']) ? 1 : 0;
    $banner_principal_ativo = isset($_POST['banner_principal_ativo']) ? 1 : 0;
    $banner_velocidade = intval($_POST['banner_velocidade']);
    $banner_auto_advance = isset($_POST['banner_auto_advance']) ? 1 : 0;

    // Validação básica
    if ($min_cartelas < 1 || $max_cartelas < $min_cartelas || $intervalo_jogos < 10 || $rtp < 0 || $rtp > 100 || $velocidade_sorteio < 1 || $velocidade_sorteio > 60) {
        $_SESSION['error_message'] = "Valores de configuração do jogo inválidos.";
        header('Location: configuracoes.php');
        exit;
    }
    
    if ($min_deposito <= 0 || $max_deposito < $min_deposito) {
        $_SESSION['error_message'] = "Valores de depósito inválidos.";
        header('Location: configuracoes.php');
        exit;
    }
    
    if ($min_saque <= 0 || $max_saque < $min_saque || $saque_diario_limite <= 0) {
        $_SESSION['error_message'] = "Valores de saque inválidos.";
        header('Location: configuracoes.php');
        exit;
    }
    
    $valores_cartela_json = json_decode($valores_cartela_str);
    if ($valores_cartela_json === null && json_last_error() !== JSON_ERROR_NONE) {
        $_SESSION['error_message'] = "O formato dos valores das cartelas é inválido. Use um formato JSON como: [\"0.10\", \"1.00\"]";
        header('Location: configuracoes.php');
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE configuracoes_jogo SET 
            min_cartelas = ?, 
            max_cartelas = ?, 
            intervalo_jogos = ?, 
            valores_cartela = ?, 
            rtp = ?, 
            velocidade_sorteio = ?, 
            ativar_bots = ?, 
            url_suporte = ?, 
            ativar_narracao = ?,
            min_deposito = ?,
            max_deposito = ?,
            min_saque = ?,
            max_saque = ?,
            saque_diario_limite = ?,
            saque_automatico_ativo = ?,
            banner_principal_ativo = ?,
            banner_velocidade = ?,
            banner_auto_advance = ?
            WHERE id = 1");
        $stmt->execute([
            $min_cartelas, 
            $max_cartelas, 
            $intervalo_jogos, 
            $valores_cartela_str, 
            $rtp, 
            $velocidade_sorteio, 
            $ativar_bots, 
            $url_suporte, 
            $ativar_narracao,
            $min_deposito,
            $max_deposito,
            $min_saque,
            $max_saque,
            $saque_diario_limite,
            $saque_automatico_ativo,
            $banner_principal_ativo,
            $banner_velocidade,
            $banner_auto_advance
        ]);
        $_SESSION['success_message'] = "Configurações atualizadas com sucesso!";
        header('Location: configuracoes.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erro ao atualizar configurações: " . $e->getMessage();
        header('Location: configuracoes.php');
        exit;
    }
}

// Busca as configurações atuais para preencher o formulário
$stmt = $pdo->prepare("SELECT * FROM configuracoes_jogo WHERE id = 1");
$stmt->execute();
$config_jogo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config_jogo) {
    $_SESSION['error_message'] = "Configurações do jogo não encontradas.";
    header('Location: user_list.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Dark Theme Colors */
            --primary-color: #6366f1;
            --primary-hover: #5855eb;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
            
            /* Dark Backgrounds */
            --bg-primary: #0f0f23;
            --bg-secondary: #1a1d35;
            --bg-tertiary: #252845;
            --bg-card: #1e2139;
            --bg-hover: #2d3155;
            
            /* Text Colors */
            --text-primary: #ffffff;
            --text-secondary: #ffffff;
            --text-tertiary: #ffffff;
            --text-muted: #ffffff;
            
            /* Borders */
            --border-color: #334155;
            --border-light: #475569;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.4);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.6);
            
            /* Transitions */
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Legacy variables for compatibility */
            --link-hover-color: #5855eb;
            --active-bg: #2d3155;
            --bg-color: var(--bg-primary);
            --card-bg: var(--bg-card);
            --text-color: var(--text-primary);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            background-attachment: fixed;
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Sidebar Styles - Matching other pages */
        .sidebar {
            position: fixed;
            top: 0;
            left: -100%;
            width: 280px;
            height: 100vh;
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border-right: 1px solid var(--border-color);
            transition: var(--transition);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-xl);
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-secondary);
        }

        .sidebar-header h4 {
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.25rem;
            text-align: center;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .sidebar-header h4 i {
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem 0;
            overflow-y: auto;
        }

        .sidebar-nav a {
            display: flex;
            align-items: center;
            color: var(--text-secondary);
            padding: 0.875rem 1.5rem;
            margin: 0.25rem 1rem;
            border-radius: 12px;
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
        }

        .sidebar-nav a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            transition: var(--transition);
            border-radius: 12px;
            z-index: -1;
        }

        .sidebar-nav a:hover:before {
            width: 100%;
        }

        .sidebar-nav a:hover {
            color: var(--text-primary);
            transform: translateX(4px);
        }

        .sidebar-nav a.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
        }

        .sidebar-nav a.active::before {
            display: none;
        }

        .sidebar-nav a i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
            font-size: 1rem;
            transition: var(--transition);
        }

        .sidebar-nav a:hover i {
            transform: scale(1.1);
        }

        .sidebar-nav a.exit {
            margin-top: auto;
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: var(--text-primary);
            border: none;
        }

        .sidebar-nav a.exit:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Content Styles */
        .content {
            padding: 1rem;
            transition: var(--transition);
            width: 100%;
            min-height: 100vh;
            max-width: calc(100vw - 280px);
            margin-left: 280px;
        }

        /* Toggle Button */
        .toggle-btn {
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: linear-gradient(135deg, var(--bg-card), var(--bg-tertiary));
            border: 1px solid var(--border-color);
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
            transition: var(--transition);
            backdrop-filter: blur(20px);
        }

        .toggle-btn:hover {
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-card));
            transform: scale(1.05);
            color: var(--primary-color);
        }

        /* Backdrop for sidebar */
        .sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
            transition: var(--transition);
        }

        .sidebar-backdrop.active {
            display: block;
        }

        /* Header Styles */
        .header {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(20px);
            border-left: 4px solid var(--primary-color);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary-color), transparent);
            border-radius: 50%;
            opacity: 0.1;
        }

        .header h1 {
            margin: 0;
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }

        .header p {
            margin: 0;
            font-size: 0.875rem;
            color: var(--text-secondary);
            position: relative;
            z-index: 1;
        }

        .form-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            padding: 2rem;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(20px);
            margin: 0 auto;
            max-width: 800px;
        }

        /* Form Styles */
        .form-label {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-control {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            width: 100%;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .form-check-input {
            width: 1.25rem;
            height: 1.25rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            transition: var(--transition);
        }

        .form-check-input:checked {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border-color: var(--primary-color);
        }

        .form-check-label {
            color: var(--text-primary);
            font-weight: 500;
            font-size: 0.875rem;
            margin-left: 0.5rem;
        }

        .form-text {
            color: var(--text-secondary) !important;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            opacity: 0.8;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-hover), #4f46e5);
            border-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-color), #475569);
            border-color: var(--secondary-color);
            color: white;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #475569, #334155);
            border-color: #475569;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Section dividers */
        h5 {
            color: var(--text-primary);
            font-weight: 600;
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        h5 i {
            color: var(--primary-color);
        }

        hr {
            border-color: var(--border-color);
            opacity: 0.3;
            margin-bottom: 1.5rem;
        }

        /* Alerts */
        .alert {
            border-radius: 12px;
            border: none;
            backdrop-filter: blur(20px);
            font-weight: 500;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(34, 197, 94, 0.05));
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger-color);
        }

        /* Media Queries */
        @media (min-width: 768px) {
            .sidebar {
                width: 280px;
                left: 0;
            }
            .toggle-btn {
                display: none;
            }
            .content {
                margin-left: 280px;
                padding: 1.5rem;
                max-width: calc(100vw - 280px);
            }
            
            .form-container {
                padding: 2.5rem;
                max-width: 900px;
            }
        }

        @media (max-width: 767px) {
            .content {
                padding: 1rem;
                margin-left: 0;
                width: 100vw;
                max-width: 100vw;
            }
            
            .sidebar {
                width: 85%;
            }

            .header {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .header h1 {
                font-size: 1.5rem;
                margin-bottom: 0.25rem;
            }

            .header p {
                font-size: 0.8rem;
            }

            .form-container {
                padding: 1.5rem;
                margin: 0.5rem;
                max-width: none;
            }

            .form-control {
                padding: 0.625rem;
                font-size: 0.8rem;
            }

            .form-label {
                font-size: 0.75rem;
            }

            .btn {
                padding: 0.8rem 1.5rem;
                font-size: 0.875rem;
            }

            h5 {
                margin-top: 1.5rem;
                margin-bottom: 0.75rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 575px) {
            .form-container {
                padding: 1rem;
                margin: 0.25rem;
                border-radius: 12px;
            }

            .form-control {
                padding: 0.5rem;
                font-size: 0.75rem;
            }

            .form-label {
                font-size: 0.7rem;
                margin-bottom: 0.25rem;
            }

            .form-check-label {
                font-size: 0.8rem;
            }

            .form-text {
                font-size: 0.65rem;
            }

            .btn {
                padding: 0.75rem 1.25rem;
                font-size: 0.8rem;
            }

            .header {
                padding: 0.75rem;
                margin-bottom: 0.75rem;
            }

            .header h1 {
                font-size: 1.25rem;
            }

            h5 {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <button class="toggle-btn" id="toggleSidebar">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-shield-alt"></i> Painel Administrativo</h4>
        </div>
        <div class="sidebar-nav">
            <a href="user_list.php"><i class="fas fa-users"></i> Usuários</a>
            <a href="banners.php"><i class="fas fa-images"></i> Banners</a>
            <a href="gateway.php"><i class="fas fa-credit-card"></i> Gateway</a>
            <a href="configuracoes.php" class="active"><i class="fas fa-cogs"></i> Configurações</a>
            <a href="depositos.php"><i class="fas fa-money-check-alt"></i> Depósitos</a>
            <a href="saques.php"><i class="fas fa-hand-holding-usd"></i> Saques</a>
            <a href="../" class="exit"><i class="fas fa-sign-out-alt"></i> Voltar</a>
        </div>
    </div>
    <div class="content" id="content">
        <div class="header">
            <h1>Configurações do Jogo</h1>
            <p>Ajuste os parâmetros gerais do bingo.</p>
        </div>
        
        <div class="form-container">
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <form action="configuracoes.php" method="POST">
                
                <h5 class="mt-4 mb-3 text-secondary"><i class="fas fa-dice"></i> Configurações do Jogo</h5>
                <hr class="text-secondary">
                <div class="mb-3">
                    <label for="min_cartelas" class="form-label">Mínimo de Cartelas por Compra</label>
                    <input type="number" class="form-control" id="min_cartelas" name="min_cartelas" value="<?php echo htmlspecialchars($config_jogo['min_cartelas']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="max_cartelas" class="form-label">Máximo de Cartelas por Compra</label>
                    <input type="number" class="form-control" id="max_cartelas" name="max_cartelas" value="<?php echo htmlspecialchars($config_jogo['max_cartelas']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="intervalo_jogos" class="form-label">Intervalo entre os Jogos (em segundos)</label>
                    <input type="number" class="form-control" id="intervalo_jogos" name="intervalo_jogos" value="<?php echo htmlspecialchars($config_jogo['intervalo_jogos']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="rtp" class="form-label">RTP (Retorno ao Jogador) - %</label>
                    <input type="number" class="form-control" id="rtp" name="rtp" min="0" max="100" value="<?php echo htmlspecialchars($config_jogo['rtp']); ?>" required>
                    <div class="form-text text-muted">Ajuste de 0 a 100. Valores mais altos aumentam a chance de vitória.</div>
                </div>
                <div class="mb-3">
                    <label for="velocidade_sorteio" class="form-label">Velocidade do Sorteio (em segundos)</label>
                    <input type="number" class="form-control" id="velocidade_sorteio" name="velocidade_sorteio" min="1" max="60" value="<?php echo htmlspecialchars($config_jogo['velocidade_sorteio']); ?>" required>
                    <div class="form-text text-muted">A cada quantos segundos uma nova bola é sorteada (1 a 60 segundos).</div>
                </div>
                <div class="mb-3">
                    <label for="ativar_bots" class="form-label">Ativar Bots</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="ativar_bots" name="ativar_bots" <?php echo $config_jogo['ativar_bots'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ativar_bots">Simular feedbacks de outros jogadores</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="url_suporte" class="form-label">URL do Suporte</label>
                    <input type="url" class="form-control" id="url_suporte" name="url_suporte" value="<?php echo htmlspecialchars($config_jogo['url_suporte']); ?>">
                </div>
                <div class="mb-3">
                    <label for="ativar_narracao" class="form-label">Ativar Narração de Voz</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="ativar_narracao" name="ativar_narracao" <?php echo $config_jogo['ativar_narracao'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ativar_narracao">Ativar narração de voz das bolas</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="valores_cartela" class="form-label">Valores das Cartelas (JSON)</label>
                    <textarea class="form-control" id="valores_cartela" name="valores_cartela" rows="3" required><?php echo htmlspecialchars($config_jogo['valores_cartela']); ?></textarea>
                    <div class="form-text text-muted">Formato JSON: ["0.10", "1.00", "5.00"]</div>
                </div>
                
                <h5 class="mt-5 mb-3 text-secondary"><i class="fas fa-coins"></i> Configurações Financeiras</h5>
                <hr class="text-secondary">
                <div class="mb-3">
                    <label for="min_deposito" class="form-label">Valor Mínimo de Depósito</label>
                    <input type="number" step="0.01" class="form-control" id="min_deposito" name="min_deposito" value="<?php echo htmlspecialchars($config_jogo['min_deposito']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="max_deposito" class="form-label">Valor Máximo de Depósito</label>
                    <input type="number" step="0.01" class="form-control" id="max_deposito" name="max_deposito" value="<?php echo htmlspecialchars($config_jogo['max_deposito']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="min_saque" class="form-label">Valor Mínimo de Saque</label>
                    <input type="number" step="0.01" class="form-control" id="min_saque" name="min_saque" value="<?php echo htmlspecialchars($config_jogo['min_saque']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="max_saque" class="form-label">Valor Máximo de Saque</label>
                    <input type="number" step="0.01" class="form-control" id="max_saque" name="max_saque" value="<?php echo htmlspecialchars($config_jogo['max_saque']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="saque_diario_limite" class="form-label">Limite de Saque Diário (por usuário)</label>
                    <input type="number" step="0.01" class="form-control" id="saque_diario_limite" name="saque_diario_limite" value="<?php echo htmlspecialchars($config_jogo['saque_diario_limite']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="saque_automatico_ativo" class="form-label">Saque Automático Ativo</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="saque_automatico_ativo" name="saque_automatico_ativo" <?php echo $config_jogo['saque_automatico_ativo'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="saque_automatico_ativo">Processar saques automaticamente via API</label>
                    </div>
                </div>
                
                <h5 class="mt-5 mb-3 text-secondary"><i class="fas fa-images"></i> Banners</h5>
                <hr class="text-secondary">
                <div class="mb-3">
                    <label for="banner_principal_ativo" class="form-label">Ativar Banner Principal</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="banner_principal_ativo" name="banner_principal_ativo" <?php echo $config_jogo['banner_principal_ativo'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="banner_principal_ativo">Ativar a exibição de banners na página principal</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="banner_velocidade" class="form-label">Velocidade do Banner (ms)</label>
                    <input type="number" class="form-control" id="banner_velocidade" name="banner_velocidade" min="1000" value="<?php echo htmlspecialchars($config_jogo['banner_velocidade']); ?>" required>
                    <div class="form-text text-muted">Tempo em milissegundos para a troca automática do banner. (Ex: 5000 = 5 segundos)</div>
                </div>
                <div class="mb-3">
                    <label for="banner_auto_advance" class="form-label">Auto-avançar Banner</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="banner_auto_advance" name="banner_auto_advance" <?php echo $config_jogo['banner_auto_advance'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="banner_auto_advance">Avançar banners automaticamente</label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-4 mb-2">Salvar Configurações</button>
                <a href="user_list.php" class="btn btn-secondary w-100">Voltar</a>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarBackdrop').classList.toggle('active');
        });

        document.getElementById('sidebarBackdrop').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
            this.classList.remove('active');
        });

        // Fechar a sidebar ao clicar em um link (melhora a UX mobile)
        document.querySelectorAll('.sidebar a').forEach(link => {
            link.addEventListener('click', function() {
                // Remove 'active' de todos os links, exceto se for o link "Voltar"
                document.querySelectorAll('.sidebar a').forEach(item => {
                    item.classList.remove('active');
                });
                if (!this.classList.contains('text-danger')) {
                    this.classList.add('active');
                }
                
                // Fecha a sidebar em mobile
                if (window.innerWidth <= 767) {
                    document.getElementById('sidebar').classList.remove('active');
                    document.getElementById('sidebarBackdrop').classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>