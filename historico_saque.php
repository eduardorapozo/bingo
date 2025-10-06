<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_logado'])) {
    header('Location: login.php');
    exit;
}

require_once('conexao.php');
$usuario = $_SESSION['usuario_logado'];

// Buscar dados completos do usuário para o menu lateral
$stmt_user = $pdo->prepare("SELECT id, nome_completo, saldo, is_admin FROM usuarios WHERE id = ?");
$stmt_user->execute([$usuario['id']]);
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Buscar histórico de saques do usuário logado
$stmt_saques = $pdo->prepare("SELECT valor, chave_pix, status, data_solicitacao FROM saques WHERE usuario_id = ? ORDER BY data_solicitacao DESC");
$stmt_saques->execute([$user_data['id']]);
$historico_saques = $stmt_saques->fetchAll(PDO::FETCH_ASSOC);

// Variáveis de layout necessárias para o sidebar
$is_admin = $user_data['is_admin'] ?? 0;
$url_suporte = '#';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Saques - Bingo Online</title>
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

        /* MOBILE HEADER - Alinhado (sem saldo no topo) */
        .mobile-header {
            display: none;
            align-items: center;
            justify-content: flex-start; /* Alinha o toggle à esquerda */
            padding: 15px 20px;
            background-color: var(--neutral-bg-light); 
            border-bottom: 1px solid var(--neutral-border);
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .menu-toggle { background: none; border: none; font-size: 24px; color: var(--primary-color); cursor: pointer; }
        
        /* Oculta o saldo do topo mobile */
        .mobile-header .user-balance { display: none; }
        
        @media (max-width: 768px) {
            .mobile-header { display: flex; justify-content: flex-start; }
            .sidebar { transform: translateX(-100%); width: 100%; max-width: 250px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; max-width: 100vw; padding: 15px; }
        }
        
        /* --- HISTORICO SAQUES CONTAINER - TEMA CLARO --- */
        .historico-container { 
            max-width: 900px; 
            margin: 20px auto; 
            background: var(--neutral-bg-light); 
            border-radius: 12px; 
            padding: 30px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
            border: 1px solid var(--neutral-border); 
        }
        .historico-container h3 { 
            color: var(--primary-color); 
            font-size: 24px; 
            font-weight: bold; 
            margin-bottom: 25px; 
            text-align: center; 
            text-shadow: none; 
        }
        
        /* Tabela */
        .historico-tabela { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 20px; 
            font-size: 14px; 
        }
        .historico-tabela th, .historico-tabela td { 
            padding: 12px 15px; 
            text-align: left; 
            border-bottom: 1px solid var(--neutral-border); 
        }
        .historico-tabela th { 
            background: var(--neutral-bg-dark); 
            color: var(--primary-color); 
            font-weight: bold; 
            text-transform: uppercase; 
        }
        .historico-tabela tr:last-child td { border-bottom: none; }
        .historico-tabela td { color: var(--neutral-text); }
        .historico-tabela tr:hover { background: rgba(0,0,0,0.03); }

        .status-badge { 
            padding: 5px 10px; 
            border-radius: 4px; /* Menos arredondado */
            font-weight: bold; 
            font-size: 12px; 
            display: inline-block; 
            text-transform: uppercase;
        }
        .status-badge.pending { background: var(--secondary-dark); color: white; }
        .status-badge.paid { background: var(--success-color); color: white; }
        .status-badge.failed { background: var(--error-color); color: white; }
        
        /* Valor Negativo (Saque) */
        .historico-tabela .valor { 
            color: var(--error-color); /* Saque em vermelho */
            font-weight: bold; 
        }
        
        .empty-state { 
            text-align: center; 
            padding: 50px; 
            color: var(--secondary-text-light); 
        }
        .empty-state i { 
            font-size: 3rem; 
            margin-bottom: 15px; 
            color: var(--neutral-border);
        }

        /* Responsive Table */
        @media (max-width: 768px) {
            .historico-container { padding: 20px; }
            .historico-tabela, .historico-tabela thead, .historico-tabela tbody, .historico-tabela th, .historico-tabela td, .historico-tabela tr { display: block; }
            .historico-tabela thead tr { position: absolute; top: -9999px; left: -9999px; }
            .historico-tabela tr { border: 1px solid var(--neutral-border); margin-bottom: 15px; border-radius: 8px; padding: 10px; background: var(--neutral-bg-light); box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
            .historico-tabela td { border-bottom: 1px solid var(--neutral-border); position: relative; padding-left: 50%; text-align: right; }
            .historico-tabela td:last-child { border-bottom: none; }
            .historico-tabela td::before { 
                content: attr(data-label); 
                position: absolute; 
                left: 15px; 
                width: 45%; 
                padding-right: 10px; 
                white-space: nowrap; 
                font-weight: bold; 
                color: var(--primary-color); 
            }
        }

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
            <a href="sacar.php" class="menu-item">
                <img src="assets/icons/sacar.png" alt="Sacar" onerror="this.style.display='none'">
                Sacar
            </a>
            <a href="historico.php" class="menu-item active">
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
            </div>

        <div class="historico-container">
            <h3><i class="fas fa-history"></i> Histórico de Saques</h3>
            
            <?php if (empty($historico_saques)): ?>
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Você ainda não solicitou nenhum saque.</p>
                </div>
            <?php else: ?>
                <table class="historico-tabela">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Valor</th>
                            <th>Chave PIX</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historico_saques as $saque): ?>
                            <tr>
                                <td data-label="Data">
                                    <?php echo (new DateTime($saque['data_solicitacao']))->format('d/m/Y H:i'); ?>
                                </td>
                                <td data-label="Valor" class="valor">
                                    - R$ <?php echo number_format($saque['valor'], 2, ',', '.'); ?>
                                </td>
                                <td data-label="Chave PIX">
                                    <?php echo htmlspecialchars($saque['chave_pix']); ?>
                                </td>
                                <td data-label="Status">
                                    <?php
                                    $status = strtolower($saque['status']);
                                    $label = '';
                                    $class = '';
                                    if ($status == 'pending') {
                                        $label = 'Pendente';
                                        $class = 'pending';
                                    } elseif ($status == 'paid') {
                                        $label = 'Pago';
                                        $class = 'paid';
                                    } elseif ($status == 'failed') {
                                        $label = 'Falhou';
                                        $class = 'failed';
                                    }
                                    echo "<span class='status-badge $class'>$label</span>";
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="toast" id="toast"></div>

    <script>
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = `toast show ${type}`;
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

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
        
        window.onload = function() {
            // Remove o saldo que estava no mobile-header por padrão (se existir)
            const mobileHeader = document.querySelector('.mobile-header');
            const mobileBalance = mobileHeader ? mobileHeader.querySelector('.user-balance') : null;
            if (mobileBalance) {
                mobileBalance.remove();
            }
            
            const initialToast = document.querySelector('.toast.show');
            if (initialToast) {
                setTimeout(() => {
                    initialToast.classList.remove('show');
                }, 3000);
            }
        };

        function logout() {
            if (confirm('Tem certeza que deseja sair?')) {
                window.location.href = 'libs/logout.php';
            }
        }
    </script>
</body>
</html>