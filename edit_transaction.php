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

// Verifica se o ID da transação a ser editada foi fornecido na URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: transacoes.php');
    exit;
}

$transaction_id_to_edit = $_GET['id'];

$success_message = null;
$error_message = null;

// Processa o formulário de edição quando for enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valor = floatval(str_replace(',', '.', str_replace('.', '', trim($_POST['valor']))));
    $tipo = trim($_POST['tipo']);
    $descricao = trim($_POST['descricao']);

    // TODO: Adicionar lógica para atualizar o saldo do usuário se o valor mudar.
    // Isso é complexo e precisa ser feito com cuidado para não causar inconsistências.

    try {
        $stmt = $pdo->prepare("UPDATE transacoes SET valor = ?, tipo = ?, descricao = ? WHERE id = ?");
        $stmt->execute([$valor, $tipo, $descricao, $transaction_id_to_edit]);
        $success_message = "Transação atualizada com sucesso!";
    } catch (PDOException $e) {
        $error_message = "Erro ao atualizar transação: " . $e->getMessage();
    }
}

// Busca os dados da transação a ser editada para preencher o formulário
$stmt = $pdo->prepare("SELECT t.id, t.usuario_id, u.nome_completo, t.tipo, t.valor, t.descricao, t.data_transacao FROM transacoes t JOIN usuarios u ON t.usuario_id = u.id WHERE t.id = ?");
$stmt->execute([$transaction_id_to_edit]);
$transaction_to_edit = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaction_to_edit) {
    header('Location: transacoes.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Transação - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --bg-color: #f8f9fa;
            --card-bg: #ffffff;
            --text-color: #343a40;
            --link-hover-color: #0b5ed7;
            --active-bg: #e9ecef;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: -100%;
            width: 80%;
            height: 100%;
            background-color: var(--text-color);
            transition: var(--transition);
            z-index: 1000;
            padding-top: 20px;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.5);
            overflow-y: auto;
        }

        .sidebar.active {
            left: 0;
        }

        .sidebar h4 {
            color: #fff;
            font-weight: 600;
            margin-bottom: 30px;
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 1.2rem;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            color: #fff;
            padding: 12px 15px;
            margin: 5px 10px;
            border-radius: 8px;
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .sidebar a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            font-size: 1rem;
        }

        .sidebar a:hover {
            background-color: var(--secondary-color);
            color: #fff;
        }
        
        .sidebar a.active {
            background-color: var(--active-bg);
            color: var(--text-color);
            transform: translateX(5px);
            text-shadow: none;
        }

        .sidebar .badge {
            margin-left: auto;
            background-color: var(--danger-color);
            font-size: 0.7rem;
            color: white;
        }

        /* Content Styles */
        .content {
            padding: 15px;
            transition: var(--transition);
            width: 100%;
        }

        .content.active {
            transform: translateX(80%);
            width: 100%;
        }

        /* Toggle Button */
        .toggle-btn {
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background-color: var(--secondary-color);
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: var(--transition);
        }

        .toggle-btn:hover {
            transform: scale(1.1);
            background-color: var(--secondary-color);
        }

        /* Backdrop for sidebar */
        .sidebar-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            display: none;
        }

        .sidebar-backdrop.active {
            display: block;
        }

        /* Header Styles */
        .header {
            background: var(--active-bg);
            color: var(--text-color);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-color);
        }

        .header h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-shadow: none;
        }

        .header p {
            margin: 5px 0 0;
            font-size: 0.8rem;
            opacity: 0.9;
        }

        .form-container {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 20px auto;
        }

        .form-label {
            color: var(--text-color);
            font-weight: 500;
        }

        .form-control, .form-check-input {
            background-color: #f8f9fa;
            color: var(--text-color);
            border: 1px solid #ced4da;
            box-shadow: none;
        }
        .form-control:focus, .form-check-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
            background-color: #f8f9fa;
            color: var(--text-color);
        }
        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .form-check-label {
            color: var(--text-color);
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: bold;
            box-shadow: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-primary:hover {
            background-color: var(--link-hover-color);
            border-color: var(--link-hover-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(13, 110, 253, 0.3);
        }
        .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: white;
        }
        .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: white;
            font-weight: bold;
        }
        .btn-warning:hover {
            background-color: #e0a800;
            border-color: #e0a800;
            color: white;
        }
        .input-group-text {
            background-color: var(--active-bg);
            color: var(--text-color);
            border: 1px solid #ced4da;
        }
        @media (min-width: 768px) {
            .sidebar { width: 250px; left: 0; }
            .toggle-btn { display: none; }
            .content { margin-left: 250px; }
        }
    </style>
</head>
<body>
    <button class="btn btn-primary toggle-btn" id="toggleSidebar">
        <i class="fas fa-bars"></i>
    </button>
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
    <div class="sidebar" id="sidebar">
        <h4 class="text-center mb-4">Painel Administrativo</h4>
        <a href="user_list.php"><i class="fas fa-users"></i> Usuários</a>
        <a href="configuracoes.php"><i class="fas fa-cogs"></i> Configurações</a>
        <a href="transacoes.php" class="active"><i class="fas fa-exchange-alt"></i> Transações</a>
        <a href="../" class="text-danger"><i class="fas fa-sign-out-alt"></i> Voltar</a>
    </div>
    <div class="content" id="content">
        <div class="header">
            <h1>Editar Transação</h1>
            <p>Gerencie os detalhes da transação #<?php echo htmlspecialchars($transaction_to_edit['id']); ?></p>
        </div>
        
        <div class="form-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <form action="edit_transaction.php?id=<?php echo htmlspecialchars($transaction_to_edit['id']); ?>" method="POST">
                <input type="hidden" name="update_transaction" value="1">
                <div class="mb-3">
                    <label for="nome_completo" class="form-label">Usuário</label>
                    <input type="text" class="form-control" id="nome_completo" value="<?php echo htmlspecialchars($transaction_to_edit['nome_completo']); ?>" disabled>
                </div>
                <div class="mb-3">
                    <label for="tipo" class="form-label">Tipo</label>
                    <input type="text" class="form-control" id="tipo" name="tipo" value="<?php echo htmlspecialchars($transaction_to_edit['tipo']); ?>" required>
                </div>
                <div class="mb-3">
                    <label for="valor" class="form-label">Valor</label>
                    <div class="input-group">
                        <span class="input-group-text">R$</span>
                        <input type="text" class="form-control" id="valor" name="valor" value="<?php echo number_format($transaction_to_edit['valor'], 2, ',', '.'); ?>" required>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="3"><?php echo htmlspecialchars($transaction_to_edit['descricao']); ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary w-100 mb-2">Salvar Alterações</button>
                <a href="transacoes.php" class="btn btn-secondary w-100">Voltar para Transações</a>
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
        
        // Formata o campo de valor para usar vírgula como separador decimal
        const valorInput = document.getElementById('valor');
        valorInput.addEventListener('blur', (event) => {
            let value = event.target.value.replace('.', '').replace(',', '.');
            value = parseFloat(value);
            if (!isNaN(value)) {
                event.target.value = value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
        });
    </script>
</body>
</html>