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

// Lógica para excluir o usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);

    try {
        // Excluir registros relacionados para evitar erros de chave estrangeira
        $pdo->beginTransaction();
        // Nota: A ordem de exclusão é importante (dependências primeiro)
        $stmt_del_cartelas = $pdo->prepare("DELETE FROM cartelas_compradas WHERE usuario_id = ?");
        $stmt_del_cartelas->execute([$user_id]);
        $stmt_del_historico = $pdo->prepare("DELETE FROM historico_jogos WHERE usuario_id = ?");
        $stmt_del_historico->execute([$user_id]);
        $stmt_del_transacoes = $pdo->prepare("DELETE FROM transacoes WHERE usuario_id = ?");
        $stmt_del_transacoes->execute([$user_id]);
        $stmt_del_depositos = $pdo->prepare("DELETE FROM depositos WHERE usuario_id = ?");
        $stmt_del_depositos->execute([$user_id]);
        $stmt_del_saques = $pdo->prepare("DELETE FROM saques WHERE usuario_id = ?");
        $stmt_del_saques->execute([$user_id]);
        
        // Excluir o usuário
        $stmt_del_user = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND is_admin = 0");
        $stmt_del_user->execute([$user_id]);
        $pdo->commit();
        $success_message = "Usuário ID {$user_id} excluído com sucesso!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Erro ao excluir o usuário: " . $e->getMessage();
    }
}


// Busca a lista de todos os usuários
$stmt_users = $pdo->prepare("SELECT id, nome_completo, email, saldo, is_admin FROM usuarios ORDER BY is_admin DESC, id ASC");
$stmt_users->execute();
$users = $stmt_users->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Painel Admin</title>
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--bg-secondary) 100%);
            color: var(--text-primary);
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-tertiary);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb {
            background: var(--secondary-color);
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }

        /* Mobile First Sidebar */
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

        /* Main Content */
        .content {
            padding: 1rem;
            transition: var(--transition);
            width: 100%;
            min-height: 100vh;
            max-width: calc(100vw - 280px);
            margin-left: 280px;
        }

        /* Header */
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

        /* Alerts */
        .alert {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            backdrop-filter: blur(20px);
            border-left: 4px solid;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-left-color: var(--success-color);
            color: var(--success-color);
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-left-color: var(--danger-color);
            color: var(--danger-color);
        }

        /* Table Container */
        .table-container {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(20px);
        }

        .table-header {
            padding: 1.5rem;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .table-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .table-count {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            border: 1px solid var(--border-color);
        }

        /* Table Styles */
        .table {
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-primary);
            --bs-table-border-color: var(--border-color);
            --bs-table-hover-bg: var(--bg-hover);
            margin: 0;
            font-size: 0.875rem;
        }

        .table th {
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-weight: 600;
            padding: 1rem;
            border-color: var(--border-color);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .table td {
            padding: 1rem;
            border-color: var(--border-color);
            vertical-align: middle;
            color: var(--text-primary) !important;
            font-weight: 500;
        }

        .table tbody tr {
            transition: var(--transition);
        }

        .table tbody tr:hover {
            background: var(--bg-hover);
            transform: scale(1.01);
        }

        .table td.actions {
            min-width: 200px;
            white-space: nowrap;
        }

        .table td.actions .d-flex {
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Badge Styles */
        .badge {
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.025em;
        }

        .badge.bg-success {
            background: linear-gradient(135deg, var(--success-color), #059669) !important;
            color: white;
        }

        .badge.bg-secondary {
            background: linear-gradient(135deg, var(--secondary-color), #475569) !important;
            color: white;
        }

        /* Action Buttons */
        .btn-action {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            margin: 0.25rem;
            transition: var(--transition);
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            letter-spacing: 0.025em;
            min-width: 100px;
            min-height: 44px;
        }

        .btn-edit {
            background: linear-gradient(135deg, var(--warning-color), #d97706);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        /* User Cards for Mobile */
        .user-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.25rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(20px);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .user-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, var(--primary-color), var(--primary-hover));
            border-radius: 4px 0 0 4px;
        }

        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--border-light);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .card-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-color);
        }

        .card-row:last-child {
            border-bottom: none;
            margin-bottom: 1rem;
        }

        .card-label {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.025em;
            opacity: 0.8;
        }

        .card-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .card-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        /* Modal Styles */
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            backdrop-filter: blur(20px);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.5rem;
        }

        .modal-title {
            color: var(--text-primary);
            font-weight: 600;
        }

        .modal-body {
            padding: 1.5rem;
            color: var(--text-primary);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1.5rem;
        }

        .btn-light {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
        }

        .btn-light:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border-color: var(--border-light);
        }

        /* Responsive Design */
        @media (min-width: 768px) {
            .sidebar {
                left: 0;
                width: 280px;
            }

            .toggle-btn {
                display: none;
            }

            .content {
                margin-left: 280px;
                padding: 1.5rem;
                max-width: calc(100vw - 280px);
            }

            .user-cards {
                display: none;
            }

            .table-responsive {
                display: block;
            }
        }

        @media (max-width: 767px) {
            .sidebar {
                width: 85%;
            }

            .content {
                padding: 1rem;
                margin-left: 0;
                max-width: 100vw;
            }

            .user-cards {
                display: block;
            }

            .table-responsive {
                display: none;
            }

            .table-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .card-actions {
                flex-direction: row;
                gap: 0.75rem;
            }

            .btn-action {
                flex: 1;
                justify-content: center;
                min-height: 48px;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            .content {
                padding: 0.75rem;
            }

            .header {
                padding: 1rem;
            }

            .header h1 {
                font-size: 1.5rem;
            }

            .user-card {
                padding: 1rem;
            }

            .sidebar-nav a {
                padding: 0.75rem 1rem;
                margin: 0.25rem 0.75rem;
            }
        }

        /* Focus States */
        .btn-action:focus,
        .toggle-btn:focus {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }

        /* High Contrast Mode */
        @media (prefers-contrast: high) {
            :root {
                --border-color: #ffffff;
                --text-secondary: #ffffff;
                --bg-card: #000000;
            }
        }

        /* Reduced Motion */
        @media (prefers-reduced-motion: reduce) {
            * {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>
</head>
<body>
    <button class="btn btn-primary toggle-btn" id="toggleSidebar">
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
            <h1>Gerenciar Usuários</h1>
            <p>Lista de todos os usuários cadastrados.</p>
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

        <div class="table-container d-none d-md-block">
            <div class="table-header">
                <h3 class="table-title">Lista de Usuários</h3>
                <div class="table-count"><?php echo count($users); ?> usuários</div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome Completo</th>
                        <th>Email</th>
                        <th>Saldo</th>
                        <th>Admin</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users): ?>
                        <?php foreach ($users as $user_item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user_item['id']); ?></td>
                                <td><?php echo htmlspecialchars($user_item['nome_completo']); ?></td>
                                <td><?php echo htmlspecialchars($user_item['email']); ?></td>
                                <td>R$ <?php echo number_format($user_item['saldo'], 2, ',', '.'); ?></td>
                                <td>
                                    <?php if ($user_item['is_admin']): ?>
                                        <span class="badge bg-success">Sim</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Não</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <div class="d-flex gap-2 flex-wrap">
                                        <a href="edit_user.php?id=<?php echo $user_item['id']; ?>" class="btn-action btn-edit">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <?php if ($user_item['id'] != $usuario_logado['id']): ?>
                                            <button type="button" class="btn-action btn-delete" data-bs-toggle="modal" data-bs-target="#deleteUserModal" data-user-id="<?php echo $user_item['id']; ?>" data-user-name="<?php echo htmlspecialchars($user_item['nome_completo']); ?>">
                                                <i class="fas fa-trash"></i> Excluir
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">Nenhum usuário encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
                </table>
            </div>
        </div>
        
        <div class="user-cards d-block d-md-none">
            <?php if (empty($users)): ?>
                <div class="user-card text-center">
                    <div class="card-title">Nenhum usuário encontrado</div>
                </div>
            <?php else: ?>
                <?php foreach ($users as $user_item): ?>
                    <div class="user-card">
                        <div class="card-header">
                            <div class="card-title"><?php echo htmlspecialchars($user_item['nome_completo']); ?></div>
                            <?php if ($user_item['is_admin']): ?>
                                <span class="card-badge bg-success">Admin</span>
                            <?php else: ?>
                                <span class="card-badge bg-secondary">Usuário</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-row">
                            <span class="card-label">ID:</span>
                            <span class="card-value">#<?php echo htmlspecialchars($user_item['id']); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Email:</span>
                            <span class="card-value"><?php echo htmlspecialchars($user_item['email']); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Saldo:</span>
                            <span class="card-value">R$ <?php echo number_format($user_item['saldo'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="card-actions">
                            <a href="edit_user.php?id=<?php echo $user_item['id']; ?>" class="btn-action btn-edit">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <?php if ($user_item['id'] != $usuario_logado['id']): ?>
                                <button type="button" class="btn-action btn-delete" data-bs-toggle="modal" data-bs-target="#deleteUserModal" data-user-id="<?php echo $user_item['id']; ?>" data-user-name="<?php echo htmlspecialchars($user_item['nome_completo']); ?>">
                                    <i class="fas fa-trash"></i> Excluir
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="user_list.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="delete-user-id">
                        <p>Você tem certeza que deseja excluir o usuário <strong id="delete-user-name"></strong>?</p>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Esta ação é irreversível e apagará todos os dados associados a este usuário.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="delete_user" class="btn btn-danger">Sim, Excluir</button>
                    </div>
                </form>
            </div>
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
        
        // Modal de exclusão de usuário
        const deleteUserModal = document.getElementById('deleteUserModal');
        deleteUserModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const userName = button.getAttribute('data-user-name');
            
            const modalInput = deleteUserModal.querySelector('#delete-user-id');
            const modalText = deleteUserModal.querySelector('#delete-user-name');
            
            modalInput.value = userId;
            modalText.textContent = userName;
        });
    </script>
</body>
</html>