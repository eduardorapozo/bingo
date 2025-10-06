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

// Lógica para editar a transação de depósito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_deposito'])) {
    $deposito_id = intval($_POST['deposito_id']);
    $new_value = floatval(str_replace(',', '.', $_POST['new_value']));
    $new_status = trim($_POST['new_status']);

    try {
        $stmt_update = $pdo->prepare("UPDATE depositos SET valor = ?, status = ? WHERE id = ?");
        $stmt_update->execute([$new_value, $new_status, $deposito_id]);
        $success_message = "Depósito ID {$deposito_id} atualizado com sucesso!";
    } catch (PDOException $e) {
        $error_message = "Erro ao atualizar o depósito: " . $e->getMessage();
    }
}

// Lógica para excluir a transação de depósito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposito_id_delete'])) {
    $deposito_id = intval($_POST['deposito_id_delete']);
    
    if ($deposito_id > 0) {
        try {
            $stmt_delete = $pdo->prepare("DELETE FROM depositos WHERE id = ?");
            $stmt_delete->execute([$deposito_id]);
            
            if ($stmt_delete->rowCount() > 0) {
                $success_message = "Depósito ID {$deposito_id} excluído com sucesso!";
            } else {
                $error_message = "Depósito ID {$deposito_id} não encontrado para exclusão.";
            }

        } catch (PDOException $e) {
            $error_message = "Erro ao excluir o depósito: " . $e->getMessage();
        }
    } else {
        $error_message = "ID de depósito inválido para exclusão.";
    }
}

// Busca todos os depósitos
$sql = "
    SELECT 
        d.id, 
        u.nome_completo,
        d.valor,
        d.status,
        d.external_id,
        d.data_criacao
    FROM depositos d
    JOIN usuarios u ON d.usuario_id = u.id
    ORDER BY d.data_criacao DESC
";

$stmt_depositos = $pdo->query($sql);
$depositos = $stmt_depositos->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Depósitos - Painel Admin</title>
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

        /* Table Styles */
        .table-responsive {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            backdrop-filter: blur(20px);
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .table-custom {
            width: 100%;
            min-width: 700px;
            color: var(--text-color);
        }

        .table-custom thead {
            background-color: #f1f1f1;
            color: var(--primary-color);
        }

        .table-custom th, .table-custom td {
            vertical-align: middle;
            font-size: 0.9rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table-custom tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .btn-action {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .btn-edit {
            background-color: #ffc107;
            border-color: #ffc107;
            color: white;
        }
        .btn-edit:hover {
            background-color: #e0a800;
            border-color: #e0a800;
        }

        .btn-delete {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }
        .btn-delete:hover {
            background-color: #c82333;
            border-color: #c82333;
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 12px;
            border: none;
            backdrop-filter: blur(20px);
            font-weight: 500;
            margin-bottom: 1.5rem;
            color: white !important;
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(34, 197, 94, 0.05));
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success-color) !important;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--danger-color) !important;
        }

        /* Modal Styles */
        .modal-content {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            backdrop-filter: blur(20px);
            color: var(--text-primary);
        }

        .modal-header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            border-radius: 16px 16px 0 0;
        }

        .modal-title {
            color: var(--text-primary);
            font-weight: 600;
        }

        .modal-body {
            color: var(--text-primary);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
        }

        .btn-close {
            filter: invert(1);
        }

        /* Form Controls in Modals */
        .form-label {
            color: var(--text-primary);
            font-weight: 600;
        }

        .form-control {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .form-control:focus {
            background: var(--bg-tertiary);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            color: var(--text-primary);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border-color: var(--primary-color);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-color), #475569);
            border-color: var(--secondary-color);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            border-color: var(--danger-color);
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
            .table-responsive {
                max-width: 1400px;
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
                max-width: 100%;
            }

            .header h1 {
                font-size: 1.5rem;
                margin-bottom: 0.25rem;
            }

            .btn-action {
                width: auto;
                padding: 0.5rem 0.75rem;
                margin: 0.25rem 0.25rem 0.25rem 0;
                font-size: 0.75rem;
                min-width: 70px;
            }

            .d-flex .btn-action {
                flex: 1;
                justify-content: center;
            }

            .table-responsive {
                max-width: 100%;
                margin: 0;
            }
        }

        /* Card Styles for Mobile */
        .card-responsive {
            display: none;
        }

        @media (max-width: 767px) {
            .table-responsive {
                display: none;
            }
            .card-responsive {
                display: block;
            }
            .user-card {
                background: var(--bg-card);
                border: 1px solid var(--border-color);
                border-left: 4px solid var(--primary-color);
                border-radius: 16px;
                padding: 1.25rem;
                margin-bottom: 1rem;
                box-shadow: var(--shadow-md);
                backdrop-filter: blur(20px);
            }
            .user-card .card-label {
                font-weight: 700;
                color: var(--primary-color);
                font-size: 0.875rem;
            }
            .user-card .card-value {
                color: var(--text-primary);
                font-weight: 500;
                font-size: 0.875rem;
            }
            .user-card .card-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0;
                border-bottom: 1px solid var(--border-color);
            }
            .user-card .card-row:last-child {
                border-bottom: none;
            }
            
            .user-card .d-flex {
                margin-top: 1rem;
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
            <a href="configuracoes.php"><i class="fas fa-cogs"></i> Configurações</a>
            <a href="depositos.php" class="active"><i class="fas fa-money-check-alt"></i> Depósitos</a>
            <a href="saques.php"><i class="fas fa-hand-holding-usd"></i> Saques</a>
            <a href="../" class="exit"><i class="fas fa-sign-out-alt"></i> Voltar</a>
        </div>
    </div>
    <div class="content" id="content">
        <div class="header">
            <h1>Gerenciar Depósitos</h1>
            <p>Lista de todas as transações de depósito.</p>
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

        <!-- Tabela para telas maiores -->
        <div class="table-responsive">
            <table class="table table-custom table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuário</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>External ID</th>
                        <th>Data Criação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($depositos): ?>
                        <?php foreach ($depositos as $deposito): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($deposito['id']); ?></td>
                                <td><?php echo htmlspecialchars($deposito['nome_completo']); ?></td>
                                <td>R$ <?php echo number_format($deposito['valor'], 2, ',', '.'); ?></td>
                                <td>
                                    <?php
                                    $status = strtolower($deposito['status']);
                                    $label = '';
                                    $class = '';
                                    if ($status == 'pending') {
                                        $label = 'Pendente';
                                        $class = 'bg-warning';
                                    } elseif ($status == 'paid') {
                                        $label = 'Pago';
                                        $class = 'bg-success';
                                    } elseif ($status == 'cancelled' || $status == 'failed') {
                                        $label = 'Falhou';
                                        $class = 'bg-danger';
                                    }
                                    echo "<span class='badge {$class}'>{$label}</span>";
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($deposito['external_id']); ?></td>
                                <td><?php echo (new DateTime($deposito['data_criacao']))->format('d/m/Y H:i:s'); ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-edit btn-action" data-bs-toggle="modal" data-bs-target="#editDepositoModal" data-id="<?php echo $deposito['id']; ?>" data-valor="<?php echo $deposito['valor']; ?>" data-status="<?php echo htmlspecialchars($deposito['status']); ?>">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-delete btn-action" data-bs-toggle="modal" data-bs-target="#deleteDepositoModal" data-id="<?php echo $deposito['id']; ?>">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Nenhum depósito encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Cards para telas menores -->
        <div class="card-responsive">
            <?php if (empty($depositos)): ?>
                <div class="user-card text-center">
                    Nenhum depósito encontrado.
                </div>
            <?php else: ?>
                <?php foreach ($depositos as $deposito): ?>
                    <div class="user-card mb-3">
                        <div class="card-row">
                            <span class="card-label">ID:</span>
                            <span class="card-value"><?php echo htmlspecialchars($deposito['id']); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Usuário:</span>
                            <span class="card-value"><?php echo htmlspecialchars($deposito['nome_completo']); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Valor:</span>
                            <span class="card-value">R$ <?php echo number_format($deposito['valor'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Status:</span>
                            <span class="card-value">
                                <?php
                                $status = strtolower($deposito['status']);
                                $label = '';
                                $class = '';
                                if ($status == 'pending') {
                                    $label = 'Pendente';
                                    $class = 'bg-warning';
                                } elseif ($status == 'paid') {
                                    $label = 'Pago';
                                    $class = 'bg-success';
                                } elseif ($status == 'cancelled' || $status == 'failed') {
                                    $label = 'Falhou';
                                    $class = 'bg-danger';
                                }
                                echo "<span class='badge {$class}'>{$label}</span>";
                                ?>
                            </span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">External ID:</span>
                            <span class="card-value"><?php echo htmlspecialchars($deposito['external_id']); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Data Criação:</span>
                            <span class="card-value"><?php echo (new DateTime($deposito['data_criacao']))->format('d/m/Y H:i:s'); ?></span>
                        </div>
                        <div class="d-flex mt-3">
                            <button type="button" class="btn btn-sm btn-edit btn-action me-2" data-bs-toggle="modal" data-bs-target="#editDepositoModal" data-id="<?php echo $deposito['id']; ?>" data-valor="<?php echo $deposito['valor']; ?>" data-status="<?php echo htmlspecialchars($deposito['status']); ?>">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button type="button" class="btn btn-sm btn-delete btn-action" data-bs-toggle="modal" data-bs-target="#deleteDepositoModal" data-id="<?php echo $deposito['id']; ?>">
                                <i class="fas fa-trash"></i> Excluir
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- Modal para Editar Depósito -->
    <div class="modal fade" id="editDepositoModal" tabindex="-1" aria-labelledby="editDepositoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editDepositoModalLabel">Editar Depósito</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="depositos.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="deposito_id" id="edit-deposito-id">
                        <div class="mb-3">
                            <label for="new-value" class="form-label">Novo Valor</label>
                            <input type="number" step="0.01" class="form-control" id="new-value" name="new_value" required>
                        </div>
                        <div class="mb-3">
                            <label for="new-status" class="form-label">Novo Status</label>
                            <select class="form-control" id="new-status" name="new_status" required>
                                <option value="PENDING">Pendente</option>
                                <option value="PAID">Pago</option>
                                <option value="CANCELLED">Cancelado</option>
                                <option value="FAILED">Falhou</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="edit_deposito" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Excluir Depósito -->
    <div class="modal fade" id="deleteDepositoModal" tabindex="-1" aria-labelledby="deleteDepositoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteDepositoModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="depositos.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="deposito_id_delete" id="delete-deposito-id">
                        <p>Você tem certeza que deseja excluir o depósito ID <strong id="delete-deposito-name"></strong>?</p>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Esta ação é irreversível.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="delete_deposito" class="btn btn-danger">Sim, Excluir</button>
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

        document.querySelectorAll('.sidebar a').forEach(link => {
            link.addEventListener('click', function() {
                document.querySelectorAll('.sidebar a').forEach(item => {
                    item.classList.remove('active');
                });
                this.classList.add('active');
                
                document.getElementById('sidebar').classList.remove('active');
                document.getElementById('sidebarBackdrop').classList.remove('active');
            });
        });

        // Modal de edição de depósito
        const editDepositoModal = document.getElementById('editDepositoModal');
        if (editDepositoModal) {
            editDepositoModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const depositoId = button.getAttribute('data-id');
                const depositoValue = button.getAttribute('data-valor');
                const depositoStatus = button.getAttribute('data-status');

                const modalInputId = editDepositoModal.querySelector('#edit-deposito-id');
                const modalInputValue = editDepositoModal.querySelector('#new-value');
                const modalInputStatus = editDepositoModal.querySelector('#new-status');

                modalInputId.value = depositoId;
                modalInputValue.value = depositoValue;
                modalInputStatus.value = depositoStatus;
            });
        }
        
        // Modal de exclusão de depósito
        const deleteDepositoModal = document.getElementById('deleteDepositoModal');
        if (deleteDepositoModal) {
            deleteDepositoModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const depositoId = button.getAttribute('data-id');
                
                const modalInput = deleteDepositoModal.querySelector('#delete-deposito-id');
                const modalText = deleteDepositoModal.querySelector('#delete-deposito-name');
                
                modalInput.value = depositoId;
                modalText.textContent = depositoId;
            });
        }
    </script>
</body>
</html>