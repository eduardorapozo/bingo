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

// Verifica se o ID do usuário a ser editado foi fornecido na URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: user_list.php');
    exit;
}

$user_id_to_edit = $_GET['id'];

$success_message = null;
$error_message = null;

// Processa o formulário de edição quando for enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Lógica para editar dados do usuário (nome, email, saldo, is_admin)
    if (isset($_POST['update_user'])) {
        $nome_completo = trim($_POST['nome_completo']);
        $email = trim($_POST['email']);
        // Converte o formato do saldo de BRL para float
        $saldo = floatval(str_replace(',', '.', str_replace('.', '', trim($_POST['saldo']))));
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE usuarios SET nome_completo = ?, email = ?, saldo = ?, is_admin = ? WHERE id = ?");
            $stmt->execute([$nome_completo, $email, $saldo, $is_admin, $user_id_to_edit]);
            $_SESSION['success_message'] = "Usuário atualizado com sucesso!";
            header('Location: edit_user.php?id=' . $user_id_to_edit);
            exit;
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Erro ao atualizar usuário: " . $e->getMessage();
            header('Location: edit_user.php?id=' . $user_id_to_edit);
            exit;
        }
    }

    // Lógica para editar a senha do usuário
    if (isset($_POST['edit_password'])) {
        $new_password = trim($_POST['new_password']);
        
        if (empty($new_password) || strlen($new_password) < 6) {
            $_SESSION['error_message'] = "A nova senha deve ter no mínimo 6 caracteres.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            try {
                $stmt_update = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                $stmt_update->execute([$hashed_password, $user_id_to_edit]);
                $_SESSION['success_message'] = "Senha do usuário alterada com sucesso!";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Erro ao atualizar a senha: " . $e->getMessage();
            }
        }
        header('Location: edit_user.php?id=' . $user_id_to_edit);
        exit;
    }
}

// Busca os dados do usuário a ser editado para preencher o formulário
$stmt = $pdo->prepare("SELECT id, nome_completo, email, saldo, is_admin FROM usuarios WHERE id = ?");
$stmt->execute([$user_id_to_edit]);
$user_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

// Se o usuário não for encontrado, redireciona de volta
if (!$user_to_edit) {
    $_SESSION['error_message'] = "Usuário não encontrado.";
    header('Location: user_list.php');
    exit;
}

// Mensagens de sucesso/erro
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário - Painel Admin</title>
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
            --shadow-xl: 0 20px in25px -5px rgba(0, 0, 0, 0.6);
            
            /* Transitions */
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            color: var(--text-primary);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: -100%;
            width: 85%;
            height: 100%;
            background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-tertiary) 100%);
            backdrop-filter: blur(20px);
            transition: var(--transition);
            z-index: 1000;
            padding-top: 20px;
            box-shadow: var(--shadow-xl);
            overflow-y: auto;
        }

        .sidebar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.02)"/><circle cx="90" cy="90" r="1" fill="rgba(255,255,255,0.01)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
            pointer-events: none;
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
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: var(--transition-fast);
        }

        .sidebar-nav a:hover::before {
            left: 100%;
        }

        .sidebar-nav a:hover {
            background: var(--bg-hover);
            color： var(--text-primary);
            transform: translateX(4px);
            box-shadow: var(--shadow-md);
        }

        .sidebar-nav a.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: var(--text-primary);
            box-shadow: var(--shadow-lg);
        }

        .sidebar-nav a.active i {
            color: var(--text-primary);
        }

        .sidebar-nav a i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
            font-size: 1rem;
            transition: var(--transition);
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

        @media (min-width: 768px) {
            .sidebar {
                width: 280px;
                left: 0;
                position: fixed;
            }
        }

        /* Content Styles */
        .content {
            padding: 1rem;
            transition: var(--transition);
            width: 100%;
            margin-left: 0;
            max-width: 1200px;
            margin: 0 auto;
        }

        @media (min-width: 768px) {
            .content {
                margin-left: 280px;
                max-width: calc(100vw - 280px);
                padding: 1.5rem;
            }
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
            padding: 0 !important;
            margin: 0 !important;
            font-size: 1rem;
        }

        .toggle-btn:hover {
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-card));
            transform: scale(1.05);
            color: var(--primary-color);
        }

        /* Override Bootstrap btn classes */
        button.toggle-btn {
            background: linear-gradient(135deg, var(--bg-card), var(--bg-tertiary)) !important;
            border: 1px solid var(--border-color) !important;
            color: var(--text-primary) !important;
            border-radius: 12px !important;
            box-shadow: var(--shadow-md) !important;
        }

        button.toggle-btn:hover {
            background: linear-gradient(135deg, var(--bg-tertiary), var(--bg-card)) !important;
            color: var(--primary-color) !important;
            box-shadow: var(--shadow-lg) !important;
        }

        button.toggle-btn:focus {
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.3) !important;
            outline: none !important;
        }

        button.toggle-btn:active {
            transform: scale(0.95) !important;
            transition: all 0.1s ease !important;
        }

        /* Melhorar visibilidade do ícone */
        button.toggle-btn i {
            font-size: 1.1rem;
            transition: transform 0.2s ease;
        }

        button.toggle-btn:hover i {
            transform: scale(1.1);
        }

        @media (min-width: 768px) {
            .toggle-btn {
                display: none;
            }
        }

        /* Backdrop */
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

        /* Form Container */
        .form-container {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            padding: 1.5rem;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
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
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .input-group-text {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px 0 0 8px;
            color: var(--text-primary);
            font-weight: 600;
            padding: 0.75rem;
            border-right: none;
            font-size: 0.875rem;
        }

        .input-group .form-control {
            border-radius: 0 8px 8px 0;
            border-left: none;
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

        /* Button Styles */
        .btn {
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: none;
            transition: var(--transition);
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            min-height: 38px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, var(--primary-hover), #4f46e5);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
        }

        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, #d97706, #b45309);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-color), #475569);
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, #475569, #334155);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        /* Alert Styles */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            box-shadow: var(--shadow-md);
        }

        .alert-success {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: var(--text-primary);
        }

        .alert-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: var(--text-primary);
        }

        /* Modal Styles */
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: var(--shadow-xl);
            backdrop-filter: blur(20px);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
            background: var(--bg-tertiary);
            border-radius: 16px 16px 0 0;
        }

        .modal-title {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 1.25rem;
        }

        .modal-body {
            padding: 1.5rem;
            color: var(--text-primary);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1.5rem;
            background: var(--bg-tertiary);
            border-radius: 0 0 16px 16px;
        }

        .btn-close {
            background: transparent;
            border: none;
            opacity: 0.7;
            transition: var(--transition);
        }

        .btn-close:hover {
            opacity: 1;
            background: var(--danger-color);
        }

        /* Responsive Styles */
        @media (max-width: 767px) {
            .sidebar {
                width: 85%;
            }

            .content {
                padding: 1rem;
                margin-left: 0;
                max-width: 100vw;
            }

            .form-container {
                padding: 1rem;
                max-width: none;
            }

            .header {
                padding: 1.5rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .btn {
                width: 100%;
                margin: 0.25rem 0;
            }

            /* Remove max-width em mobile para campos ocuparem toda largura */
            .form-control[style*="max-width"] {
                max-width: none !important;
            }
            
            .mb-3[style*="max-width"] {
                max-width: none !important;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 0.75rem;
            }

            .form-container {
                padding: 1rem;
            }

            .header {
                padding: 1rem;
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
            <a href="user_list.php" class="active"><i class="fas fa-users"></i> Usuários</a>
            <a href="banners.php"><i class="fas fa-images"></i> Banners</a>
            <a href="gateway.php"><i class="fas fa-credit-card"></i> Gateway</a>
            <a href="configuracoes.php"><i class="fas fa-cogs"></i> Configurações</a>
            <a href="depositos.php"><i class="fas fa-money-check-alt"></i> Depósitos</a>
            <a href="saques.php"><i class="fas fa-hand-holding-usd"></i> Saques</a>
            <a href="../" class="exit"><i class="fas fa-sign-out-alt"></i> Voltar</a>
        </div>
    </div>

    <div class="content" id="content">
        <div class="header">
            <h1>
                <i class="fas fa-user-edit"></i>
                Editar Usuário
            </h1>
            <p>Gerencie as informações do usuário ID: <?php echo htmlspecialchars($user_to_edit['id']); ?></p>
        </div>
        
        <div class="form-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form action="edit_user.php?id=<?php echo htmlspecialchars($user_to_edit['id']); ?>" method="POST" class="mb-4">
                <input type="hidden" name="update_user" value="1">
                
                <div class="mb-3">
                    <label for="nome_completo" class="form-label">Nome Completo</label>
                    <input type="text" class="form-control" id="nome_completo" name="nome_completo" value="<?php echo htmlspecialchars($user_to_edit['nome_completo']); ?>" required style="max-width: 400px;">
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user_to_edit['email']); ?>" required style="max-width: 400px;">
                </div>
                
                <div class="mb-3" style="max-width: 300px;">
                    <label for="saldo" class="form-label">Saldo</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control" id="saldo" name="saldo" value="<?php echo number_format($user_to_edit['saldo'], 2, ',', '.'); ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="is_admin" name="is_admin" <?php echo $user_to_edit['is_admin'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_admin">
                            <i class="fas fa-crown"></i>
                            Conceder acesso de Administrador
                        </label>
                    </div>
                </div>
                
                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Salvar Alterações
                    </button>
                    <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#editPasswordModal">
                        <i class="fas fa-key"></i>
                        Alterar Senha
                    </button>
                    <a href="user_list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Voltar para Lista
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Alteração de Senha -->
    <div class="modal fade" id="editPasswordModal" tabindex="-1" aria-labelledby="editPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPasswordModalLabel">
                        <i class="fas fa-key"></i>
                        Alterar Senha
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form action="edit_user.php?id=<?php echo htmlspecialchars($user_to_edit['id']); ?>" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="edit_password" value="1">
                        <div class="mb-3">
                            <label for="new-password" class="form-label">Nova Senha</label>
                            <input type="password" class="form-control" id="new-password" name="new_password" required minlength="6" placeholder="Digite a nova senha...">
                            <div class="form-text" style="color: var(--text-secondary);">
                                A senha deve ter no mínimo 6 caracteres.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Salvar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar functionality
        document.getElementById('toggleSidebar').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('sidebarBackdrop').classList.toggle('active');
        });

        document.getElementById('sidebarBackdrop').addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('active');
            this.classList.remove('active');
        });

        // Fechar a sidebar ao clicar em um link (melhora a UX mobile)
        document.querySelectorAll('.sidebar-nav a').forEach(link => {
            link.addEventListener('click', function() {
                // Remove 'active' de todos os links, depois adiciona ao link clicado (exceto se for Voltar)
                document.querySelectorAll('.sidebar-nav a').forEach(item => {
                    item.classList.remove('active');
                });
                if (!this.classList.contains('exit')) {
                    this.classList.add('active');
                }
                
                // Fecha a sidebar em mobile
                if (window.innerWidth <= 767) {
                    document.getElementById('sidebar').classList.remove('active');
                    document.getElementById('sidebarBackdrop').classList.remove('active');
                }
            });
        });
        
        // Mantém 'user_list.php' ativo ao carregar a página
        window.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.sidebar-nav a').forEach(item => {
                if (item.getAttribute('href') === 'user_list.php') {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });
        });

        // Formata o campo de saldo para usar vírgula como separador decimal
        const saldoInput = document.getElementById('saldo');
        saldoInput.addEventListener('blur', (event) => {
            let value = event.target.value.replace(/\./g, '').replace(',', '.');
            value = parseFloat(value);
            if (!isNaN(value)) {
                event.target.value = value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        });

        // Melhora UX das validações
        document.querySelector('form').addEventListener('submit', function(e) {
            const nome = document.getElementById('nome_completo').value.trim();
            const email = document.getElementById('email').value.trim();
            const saldo = document.getElementById('saldo').value.trim();
            
            if (!nome || !email || !saldo) {
                e.preventDefault();
                alert('Por favor, preencha tipos os campos obrigatórios.');
                return false;
            }
            
            // Validação de email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Por favor, insira um email válido.');
                return false;
            }
        });

        // Animação de entrada dos elementos
        window.addEventListener('load', function() {
            document.querySelector('.form-container').style.opacity = '0';
            document.querySelector('.form-container').style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                document.querySelector('.form-container').style.transition = 'all 0.5s ease';
                document.querySelector('.form-container').style.opacity = '1';
                document.querySelector('.form-container').style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>