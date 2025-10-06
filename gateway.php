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

$success_message = null;
$error_message = null;

// Tenta buscar a ÚNICA configuração (assumimos que a tabela deve ter apenas 1 registro)
$stmt_config = $pdo->prepare("SELECT * FROM configuracoes_api LIMIT 1");
$stmt_config->execute();
$config = $stmt_config->fetch(PDO::FETCH_ASSOC);

// Lógica para SALVAR/ATUALIZAR a configuração de API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $client_id = trim($_POST['client_id']);
    $client_secret = trim($_POST['client_secret']);
    $url_webhook = trim($_POST['url_webhook']);
    
    try {
        // Validar URL do webhook
        if (!filter_var($url_webhook, FILTER_VALIDATE_URL)) {
            throw new Exception('URL do webhook inválida.');
        }
        
        if ($config) {
            // Se a configuração JÁ EXISTE, faz um UPDATE
            $stmt = $pdo->prepare("UPDATE configuracoes_api SET client_id = ?, client_secret = ?, url_webhook = ? WHERE id = ?");
            $stmt->execute([$client_id, $client_secret, $url_webhook, $config['id']]);
            $success_message = "Configuração de API **atualizada** com sucesso!";
        } else {
            // Se a configuração NÃO EXISTE (primeiro uso), faz um INSERT
            $stmt = $pdo->prepare("INSERT INTO configuracoes_api (client_id, client_secret, url_webhook) VALUES (?, ?, ?)");
            $stmt->execute([$client_id, $client_secret, $url_webhook]);
            $success_message = "Configuração de API **criada** com sucesso!";
        }

        // Atualiza a variável $config para refletir a mudança na tela imediatamente
        $stmt_config->execute();
        $config = $stmt_config->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error_message = "Erro ao salvar configuração: " . $e->getMessage();
    }
}

// Verifica se existe algum registro na tabela (após o salvamento/atualização)
$config_exists = $config !== false;

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gateway de Pagamento - Painel Admin</title>
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

        /* Sidebar Styles - Matching user_list.php */
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
            border-radius: 12px !important;
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
            color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .toggle-btn:hover i {
            transform: scale(1.1);
        }

        /* Backdrop for sidebar */
        .sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 15, 35, 0.8);
            backdrop-filter: blur(8px);
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

        /* Card Styles */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-md);
            margin-bottom: 1.25rem;
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
        }

        .card-header {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border-radius: 12px 12px 0 0;
            border: none;
            font-weight: 600;
            padding: 1rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.875rem;
        }

        .card-body {
            padding: 1.25rem;
            color: var(--text-primary);
        }
        
        .info-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.75rem;
            display: block;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Config Display */
        .config-display {
            padding: 0;
        }

        .config-display code {
            display: block;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            padding: 1rem;
            border-radius: 8px;
            white-space: pre-wrap;
            word-break: break-all;
            margin-top: 0.5rem;
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.8rem;
            color: var(--text-primary);
            box-shadow: var(--shadow-sm);
        }

        /* Estilos para a nova seção de anúncio */
        .codexpay-ad-card {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.25rem;
            margin-top: 1.25rem;
            text-align: center;
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
        }

        .codexpay-ad-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-hover));
        }

        .codexpay-ad-card h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 1.125rem;
        }

        .codexpay-ad-card p {
            font-size: 0.875rem;
            margin-bottom: 1rem;
            color: var(--text-secondary);
        }

        .btn-codexpay {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            transition: var(--transition);
            border: none;
            box-shadow: var(--shadow-sm);
            font-size: 0.875rem;
        }

        .btn-codexpay:hover {
            background: linear-gradient(135deg, #fbbf24, var(--warning-color));
            border-color: #fbbf24;
            color: var(--bg-primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Botões Gerais */
        .btn {
            border-radius: 8px;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            box-shadow: var(--shadow-sm);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-hover), #4f46e5);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-color), #475569);
            color: var(--text-primary);
            box-shadow: var(--shadow-sm);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #475569, #334155);
            color: var(--text-primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-light {
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-secondary));
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
        }

        .btn-light:hover {
            background: linear-gradient(135deg, var(--border-color), var(--border-light));
            color: var(--text-primary);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Modal Styles */
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-radius: 16px 16px 0 0;
        }

        .modal-title {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 2rem;
            color: var(--text-primary);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1.5rem;
            background: var(--bg-tertiary);
            border-radius: 0 0 16px 16px;
        }

        .btn-close {
            opacity: 0.7;
            transition: var(--transition);
        }

        .btn-close:hover {
            opacity: 1;
            background: var(--danger-color);
            border-radius: 50%;
        }

        /* Form Styles */
        .form-label {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.75rem;
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
            max-width: 100%;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .form-text {
            color: var(--text-secondary) !important;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            opacity: 0.8;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        /* Responsive form fields */
        @media (max-width: 767px) {
            .form-control {
                padding: 0.625rem;
                font-size: 0.8rem;
            }

            .form-label {
                font-size: 0.7rem;
            }

            .form-text {
                font-size: 0.65rem;
            }
        }

        @media (max-width: 575px) {
            .form-control {
                padding: 0.5rem;
                font-size: 0.75rem;
            }

            .form-label {
                font-size: 0.65rem;
                margin-bottom: 0.25rem;
            }

            .btn-group .btn {
                font-size: 0.7rem;
                padding: 0.4rem 0.6rem;
            }
        }

        /* Alerts */
        .alert {
            border-radius: 12px;
            border: none;
            backdrop-filter: blur(20px);
            font-weight: 500;
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

        .alert-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.05));
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: var(--warning-color);
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

            .card {
                margin-bottom: 1rem;
            }

            .card-header {
                padding: 0.75rem 1rem;
                font-size: 0.8rem;
            }

            .card-header h5 {
                font-size: 0.875rem;
            }

            .card-body {
                padding: 1rem;
            }

            .info-label {
                font-size: 0.75rem;
                margin-bottom: 0.5rem;
            }

            .config-display code {
                padding: 0.75rem;
                font-size: 0.75rem;
            }

            .modal-body {
                padding: 1rem;
            }

            .modal-header {
                padding: 0.75rem 1rem;
            }

            .modal-footer {
                padding: 0.75rem 1rem;
            }

            .codexpay-ad-card {
                padding: 1rem;
                margin-top: 1rem;
            }

            .codexpay-ad-card h4 {
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }

            .codexpay-ad-card p {
                font-size: 0.8rem;
                margin-bottom: 0.75rem;
            }

            .btn-codexpay {
                padding: 0.625rem 1.25rem;
                font-size: 0.8rem;
            }
        }

        /* Extra small devices */
        @media (max-width: 575px) {
            .content {
                padding: 0.75rem;
            }

            .header {
                padding: 0.75rem;
                margin-bottom: 0.75rem;
            }

            .header h1 {
                font-size: 1.25rem;
            }

            .card-header {
                padding: 0.5rem 0.75rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .card-header h5 {
                font-size: 0.8rem;
            }

            .card-body {
                padding: 0.75rem;
            }

            .info-label {
                font-size: 0.7rem;
            }

            .config-display code {
                word-break: break-word;
                font-size: 0.7rem;
                padding: 0.5rem;
            }

            .codexpay-ad-card {
                padding: 0.75rem;
            }

            .codexpay-ad-card h4 {
                font-size: 0.9rem;
            }

            .codexpay-ad-card p {
                font-size: 0.75rem;
                line-height: 1.4;
            }

            .btn-codexpay {
                padding: 0.5rem 1rem;
                font-size: 0.75rem;
                width: 100%;
                text-align: center;
            }

            .modal-dialog {
                margin: 0.5rem;
            }

            .modal-body {
                padding: 0.75rem;
            }

            .btn {
                font-size: 0.8rem;
                padding: 0.5rem 0.75rem;
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
            <a href="gateway.php" class="active"><i class="fas fa-credit-card"></i> Gateway</a>
            <a href="configuracoes.php"><i class="fas fa-cogs"></i> Configurações</a>
            <a href="depositos.php"><i class="fas fa-money-check-alt"></i> Depósitos</a>
            <a href="saques.php"><i class="fas fa-hand-holding-usd"></i> Saques</a>
            <a href="../" class="exit"><i class="fas fa-sign-out-alt"></i> Voltar</a>
        </div>
    </div>

    <div class="content" id="content">
        <div class="header">
            <h1>Gateway de Pagamento</h1>
            <p>Gerencie a única configuração de integração com a API de pagamento.</p>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-cogs"></i> Configuração Atual da API</h5>
                <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#configModal">
                    <i class="fas fa-edit"></i> <?php echo $config_exists ? 'Editar' : 'Adicionar'; ?>
                </button>
            </div>
                    <div class="card-body config-display">
                        <?php if ($config_exists): ?>
                            <div class="mb-3">
                                <span class="info-label">Client ID:</span>
                                <code><?php echo htmlspecialchars($config['client_id']); ?></code>
                            </div>
                            <div class="mb-3">
                                <span class="info-label">Client Secret:</span>
                                <code><?php echo str_repeat('*', 25) . substr(htmlspecialchars($config['client_secret']), -5); ?></code>
                                <small class="text-muted d-block">***O Client Secret é ocultado por segurança.***</small>
                            </div>
                            <div class="mb-3">
                                <span class="info-label">URL Webhook:</span>
                                <code><?php echo htmlspecialchars($config['url_webhook']); ?></code>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Nenhuma configuração de API encontrada. Por favor, clique em <strong>Adicionar</strong> para começar.
                            </div>
                        <?php endif; ?>
                    </div>
        </div>
        
        <div class="codexpay-ad-card">
            <h4><i class="fas fa-rocket"></i> Solução de Pagamento Integrada!</h4>
            <p>
                <strong>Gateway vinculado CodexPay!</strong> Sistema integrado funcionando perfeitamente para processamento de pagamentos via PIX com taxas competitivas.
            </p>
            <a href="https://codexpay.app" target="_blank" class="btn btn-codexpay">
                <i class="fas fa-hand-point-right"></i> Visitar CodexPay
            </a>
        </div>
        </div>

    <div class="modal fade" id="configModal" tabindex="-1" aria-labelledby="configModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="configModalLabel">
                        <?php echo $config_exists ? 'Editar Configuração da API' : 'Adicionar Configuração da API'; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        
                        <div class="mb-3">
                            <label for="client_id" class="form-label">Client ID *</label>
                            <input type="text" class="form-control" id="client_id" name="client_id" required 
                                placeholder="Digite o Client ID da API" 
                                value="<?php echo $config_exists ? htmlspecialchars($config['client_id']) : ''; ?>">
                            <small class="form-text text-muted">Identificador público da aplicação na API</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="client_secret" class="form-label">Client Secret *</label>
                            <input type="password" class="form-control" id="client_secret" name="client_secret" required 
                                placeholder="Digite o Client Secret da API"
                                value="<?php echo $config_exists ? htmlspecialchars($config['client_secret']) : ''; ?>">
                            <small class="form-text text-muted">A chave secreta será salva e criptografada (se houver lógica de criptografia)</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="url_webhook" class="form-label">URL Webhook *</label>
                            <input type="url" class="form-control" id="url_webhook" name="url_webhook" required 
                                placeholder="https://seusite.com/webhook.php"
                                value="<?php echo $config_exists ? htmlspecialchars($config['url_webhook']) : ''; ?>">
                            <small class="form-text text-muted">URL que receberá as notificações da API</small>
                        </div>
                        
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                        <button type="submit" name="save_config" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Configuração
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Sidebar
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarBackdrop').classList.toggle('active');
        });

        document.getElementById('sidebarBackdrop').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
            this.classList.remove('active');
        });

        // Sidebar links
        document.querySelectorAll('.sidebar a').forEach(link => {
            link.addEventListener('click', function() {
                document.querySelectorAll('.sidebar a').forEach(item => {
                    item.classList.remove('active');
                });
                if (!this.classList.contains('text-danger')) {
                    this.classList.add('active');
                }
                
                if (window.innerWidth <= 767) {
                    document.getElementById('sidebar').classList.remove('active');
                    document.getElementById('sidebarBackdrop').classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>