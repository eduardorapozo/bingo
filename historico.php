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

// Buscar estatísticas do usuário na view `usuario_estatisticas`
// Nota: A view `usuario_estatisticas` deve existir no banco de dados.
$stmt_estatisticas = $pdo->prepare("SELECT total_jogos, total_cartelas_compradas, total_gasto, total_ganho, total_quadras, total_quinas, total_cartelas_cheias FROM usuario_estatisticas WHERE id = ?");
$stmt_estatisticas->execute([$user_data['id']]);
$estatisticas = $stmt_estatisticas->fetch(PDO::FETCH_ASSOC);

// Variáveis de layout necessárias para o sidebar
$is_admin = $user_data['is_admin'] ?? 0;
$url_suporte = '#';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico de Jogo - Bingo Online</title>
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
            -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; user-select: none; 
        }

        /* OVERLAY (NOVO) */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 99; /* Fica abaixo do sidebar (100) */
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }

        .overlay.show {
            opacity: 1;
            visibility: visible;
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
        
        /* --- HISTORICO CONTAINER - TEMA CLARO --- */
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
        
        /* Estatísticas Grid */
        .estatisticas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .card-estatistica {
            background: var(--neutral-bg-dark);
            border-radius: 10px;
            padding: 15px;
            text-align: center;
            border: 1px solid var(--neutral-border);
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .card-estatistica i {
            font-size: 2rem;
            color: var(--secondary-dark);
            margin-bottom: 8px;
        }

        .card-estatistica h4 {
            font-size: 14px;
            color: var(--neutral-text);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .card-estatistica .valor-estatistica {
            font-family: 'Inter', sans-serif;
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--success-color); /* Ganho em verde */
        }

        .card-estatistica.gasto .valor-estatistica {
            color: var(--error-color); /* Gasto em vermelho */
        }
        
        /* Botões de Histórico */
        .historico-botoes {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 25px;
        }

        .btn-historico-tipo {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .btn-historico-tipo.deposito {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            border: 1px solid #059669;
        }
        
        .btn-historico-tipo.deposito:hover {
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
        }

        .btn-historico-tipo.saque {
            background: linear-gradient(135deg, var(--error-color), #c22b2b);
            color: white;
            border: 1px solid #c22b2b;
        }

        .btn-historico-tipo.saque:hover {
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
            transform: translateY(-2px);
        }
        
        /* Empty State */
        .empty-state { text-align: center; padding: 50px; color: var(--secondary-text-light); }
        .empty-state i { font-size: 3rem; margin-bottom: 15px; color: var(--neutral-border); }


        /* TOASTS - Cores do tema claro anterior */
        .toast { position: fixed; top: 20px; right: 20px; padding: 12px 18px; border-radius: 8px; color: white; font-weight: bold; box-shadow: 0 5px 15px rgba(0,0,0,0.1); transform: translateX(300px); transition: all 0.3s ease-in-out; z-index: 1000; max-width: 300px; border: 1px solid; }
        .toast.show { transform: translateX(0); }
        .toast.success { background-color: var(--success-color); border-color: #059669; }
        .toast.error { background-color: var(--error-color); border-color: #b91c1c; }
        .toast.info { background-color: var(--primary-color); border-color: #2563eb; }

        @media (max-width: 768px) {
            .estatisticas-grid { grid-template-columns: 1fr; }
            .historico-botoes { flex-direction: column; }
        }
    </style>
</head>
<body>
    
    <div class="overlay" id="overlay"></div>

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
                <i class="fas fa-sign-out-alt"></i> Sair
            </button>
        </nav>
    </div>
    
    
    <div class="main-content">
        <div class="mobile-header">
            <button class="menu-toggle" id="menuToggle">☰</button>
            </div>

        <div class="historico-container">
            <h3><i class="fas fa-chart-line"></i> Resumo de Jogos</h3>
            
            <?php if (empty($estatisticas) || ($estatisticas['total_jogos'] == 0)): ?>
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Você ainda não participou de nenhum jogo.</p>
                </div>
            <?php else: ?>
                <div class="estatisticas-grid">
                    <div class="card-estatistica">
                        <i class="fas fa-th"></i>
                        <h4>Cartelas Compradas</h4>
                        <div class="valor-estatistica"><?php echo $estatisticas['total_cartelas_compradas']; ?></div>
                    </div>
                    <div class="card-estatistica gasto">
                        <i class="fas fa-shopping-cart"></i>
                        <h4>Total Gasto</h4>
                        <div class="valor-estatistica">R$ <?php echo number_format($estatisticas['total_gasto'], 2, ',', '.'); ?></div>
                    </div>
                    <div class="card-estatistica">
                        <i class="fas fa-trophy"></i>
                        <h4>Total Ganho</h4>
                        <div class="valor-estatistica">R$ <?php echo number_format($estatisticas['total_ganho'], 2, ',', '.'); ?></div>
                    </div>
                    <div class="card-estatistica">
                        <i class="fas fa-dice"></i>
                        <h4>Total de Jogos</h4>
                        <div class="valor-estatistica"><?php echo $estatisticas['total_jogos']; ?></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="historico-botoes">
                <a href="historico_deposito.php" class="btn-historico-tipo deposito">
                    <i class="fas fa-arrow-down"></i> Histórico de Depósitos
                </a>
                <a href="historico_saque.php" class="btn-historico-tipo saque">
                    <i class="fas fa-arrow-up"></i> Histórico de Saques
                </a>
            </div>
            
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
        
        // Função unificada para fechar o menu
        function closeMenu() {
            // Apenas fecha se estiver aberto, para evitar re-execução desnecessária
            if (sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                overlay.classList.remove('show');
            }
        }

        // 1. Listener para abrir/fechar com o botão
        if (menuToggle) {
            menuToggle.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                // Alterna as classes para abrir/fechar
                sidebar.classList.toggle('open');
                overlay.classList.toggle('show');
            });
        }
        
        // 2. Listener PRINCIPAL para fechar ao clicar no Overlay (fundo escuro)
        if (overlay) {
            overlay.addEventListener('click', closeMenu);
        }

        // 3. Fechar menu ao clicar em qualquer item (melhora a UX mobile)
        document.querySelectorAll('.sidebar-menu .menu-item').forEach(item => {
            item.addEventListener('click', () => {
                // Checa se está em tela pequena
                if (window.innerWidth <= 768) {
                    // Pequeno atraso para o link funcionar antes de fechar
                    setTimeout(closeMenu, 150); 
                }
            });
        });
        
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