<?php
// Inclui o arquivo de conexão e inicia a sessão (mesma lógica do main.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_logado'])) {
    header('Location: login.php');
    exit;
}

require_once('conexao.php');
$usuario = $_SESSION['usuario_logado'];
$bonus_valor = 5.00; // Valor do bônus

// 1. Buscar dados completos do usuário
$stmt = $pdo->prepare("SELECT id, nome_completo, email, saldo, is_admin, telefone, created_at, bonus_perfil_concedido FROM usuarios WHERE id = ?");
$stmt->execute([$usuario['id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Verifica se o campo telefone já foi preenchido E se o bônus foi concedido para desabilitar o campo
// Se o usuário tem um telefone mas ainda não recebeu o bônus, ele pode atualizar.
$telefone_preenchido_e_bonus_concedido = !empty($user_data['telefone']) && ($user_data['bonus_perfil_concedido'] == 1);

// 2. Lógica de atualização de perfil e concessão de bônus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_perfil'])) {
    $telefone_input = $_POST['telefone'] ?? '';
    $email_novo = $_POST['email_novo'] ?? $user_data['email'];

    // Filtra e sanitiza
    $telefone_sanitizado = preg_replace('/\D/', '', $telefone_input); // Remove caracteres não numéricos
    $email_novo = filter_var($email_novo, FILTER_SANITIZE_EMAIL);
    
    // Se o campo de telefone está bloqueado, usamos o valor do banco para evitar sobrescrita
    $telefone_final = $telefone_preenchido_e_bonus_concedido ? $user_data['telefone'] : $telefone_sanitizado;

    $perfil_completo_para_bonus = false;
    
    if ($user_data['bonus_perfil_concedido'] == 0) {
        // Verifica o telefone sanitizado do input (se for a primeira vez)
        if (!empty($telefone_sanitizado) && strlen($telefone_sanitizado) >= 10) {
            $perfil_completo_para_bonus = true;
            $telefone_final = $telefone_sanitizado; // Usa o novo telefone para salvar e conceder bônus
        }
    }

    try {
        $pdo->beginTransaction();
        
        // 2.1. Atualiza os dados do usuário
        $stmt_update = $pdo->prepare("UPDATE usuarios SET telefone = ?, email = ? WHERE id = ?");
        $stmt_update->execute([$telefone_final, $email_novo, $user_data['id']]);

        // 2.2. Concede o bônus se aplicável
        if ($perfil_completo_para_bonus) {
            $novo_saldo = $user_data['saldo'] + $bonus_valor;
            
            // Atualiza saldo e marca o bônus como concedido
            $stmt_bonus = $pdo->prepare("UPDATE usuarios SET saldo = ?, bonus_perfil_concedido = 1 WHERE id = ?");
            $stmt_bonus->execute([$novo_saldo, $user_data['id']]);
            
            // Registra a transação de bônus
            $descricao = "Bônus por completar perfil (telefone/email)";
            $stmt_transacao = $pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, saldo_anterior, saldo_atual, descricao) VALUES (?, 'bonus', ?, ?, ?, ?)");
            $stmt_transacao->execute([$user_data['id'], $bonus_valor, $user_data['saldo'], $novo_saldo, $descricao]);

            $_SESSION['success'] = "Perfil atualizado e você ganhou R$ " . number_format($bonus_valor, 2, ',', '.') . " de bônus!";
        } else {
            $_SESSION['success'] = "Perfil atualizado com sucesso!";
        }
        
        $pdo->commit();
        
        // Recarrega os dados do usuário para refletir as mudanças
        $stmt_reload = $pdo->prepare("SELECT id, nome_completo, email, saldo, is_admin, telefone, created_at, bonus_perfil_concedido FROM usuarios WHERE id = ?");
        $stmt_reload->execute([$usuario['id']]);
        $user_data = $stmt_reload->fetch(PDO::FETCH_ASSOC);

        header('Location: perfil.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Erro ao atualizar perfil. Tente novamente."; 
    }
}

// Atualiza a variável de desabilitação após o POST
$telefone_preenchido_e_bonus_concedido = !empty($user_data['telefone']) && ($user_data['bonus_perfil_concedido'] == 1);

// Variáveis de layout
$is_admin = $user_data['is_admin'] ?? 0;
$url_suporte = 'suporte.php'; // URL de exemplo
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil do Usuário - Bingo Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&family=Orbitron:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Reset e Configurações Base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }

        /* Reabilita a seleção de texto para campos de formulário */
        input, textarea {
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
            user-select: text;
        }

        body {
            font-family: Arial, sans-serif;
            /* Tema Claro: Fundo principal */
            background-color: #f0f2f5; /* Um cinza muito claro, quase branco */
            min-height: 100vh;
            color: #333; /* Cor do texto principal escura */
            display: flex;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        /* Cores de Destaque */
        :root {
            --primary-color: #3b82f6; /* Azul */
            --primary-hover: #2563eb;
            --secondary-color: #FFD700; /* Amarelo/Dourado do Bingo */
            --secondary-dark: #FFA500;
            --success-color: #10b981; /* Verde */
            --error-color: #ef4444; /* Vermelho */
            --neutral-bg-light: #ffffff; /* Fundo dos painéis claro */
            --neutral-bg-dark: #e9ecef; /* Um cinza um pouco mais escuro para elementos de fundo */
            --neutral-border: #dee2e6; /* Cor de borda para tema claro */
            --neutral-text: #495057; /* Texto neutro escuro */
            --secondary-text-light: #6c757d; /* Texto secundário claro (para labels, etc.) */
        }

        /* SIDEBAR */
        .sidebar {
            width: 250px;
            background-color: var(--neutral-bg-light); /* Fundo branco/claro para sidebar */
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
            background-color: var(--neutral-bg-dark); /* Fundo um pouco mais escuro para o cabeçalho */
            border-bottom: 1px solid var(--neutral-border);
            text-align: center;
        }

        .logo {
            width: 100px;
            height: auto;
            margin-bottom: 10px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .logo img {
            width: 100%;
            height: 100%;
            border-radius: 8px;
        }

        .sidebar-header h2 {
            color: var(--neutral-text); /* Título escuro */
            font-size: 20px;
            margin-bottom: 5px;
        }

        .user-info {
            color: var(--neutral-text); /* Info do usuário escuro */
            font-size: 12px;
            margin-bottom: 10px;
        }

        .user-balance {
            background-color: var(--primary-color); /* Manter destaque primário para o saldo */
            color: white;
            padding: 10px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .sidebar-menu {
            padding: 10px 0;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: var(--neutral-text); /* Texto do menu escuro */
            text-decoration: none;
            transition: background-color 0.3s ease, color 0.3s ease;
            border: none;
            background: none;
            width: 100%;
            cursor: pointer;
            font-size: 14px;
        }

        .menu-item:hover {
            background-color: var(--neutral-bg-dark); /* Fundo claro no hover */
            color: var(--primary-color);
        }

        .menu-item.active {
            background-color: var(--primary-color); /* Manter primário para ativo */
            color: white;
            border-left: 5px solid var(--secondary-color);
        }

        .menu-item img {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            filter: none;
        }

        .deposit-btn {
            background-color: var(--success-color) !important;
            color: white !important;
            border: none !important;
            padding: 10px 15px !important;
            border-radius: 20px !important;
            font-weight: bold !important;
            cursor: pointer !important;
            transition: background-color 0.3s ease !important;
            text-decoration: none !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            margin: 15px 20px !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1) !important;
        }

        .deposit-btn:hover {
            background-color: #059669 !important; /* Um verde um pouco mais escuro no hover */
        }

        /* MAIN CONTENT AREA */
        .main-content {
            margin-left: 250px;
            flex: 1;
            padding: 20px;
            min-height: 100vh;
            background-color: #f0f2f5; /* Fundo do conteúdo principal claro */
            max-width: calc(100vw - 250px);
            overflow-x: hidden;
        }

        .mobile-header {
            display: none;
            align-items: center;
            justify-content: space-between;
            padding: 15px 20px;
            background-color: var(--neutral-bg-light); /* Fundo branco no mobile */
            border-bottom: 1px solid var(--neutral-border);
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        /* SOLICITADO: Remover saldo do topo mobile - display none */
        .mobile-header .user-balance {
            display: none; 
        }

        .menu-toggle {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--primary-color);
            cursor: pointer;
        }

        /* --- PERFIL CONTENT --- */

        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: var(--neutral-bg-light);
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border: 1px solid var(--neutral-border);
        }

        .profile-container h3 {
            color: var(--primary-color);
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .info-card {
            background-color: var(--neutral-bg-dark);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--neutral-border);
        }

        .info-card h4 {
            color: var(--neutral-text);
            font-size: 18px;
            margin-bottom: 15px;
            border-bottom: 1px dashed var(--neutral-border);
            padding-bottom: 10px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 15px;
        }

        .info-label {
            color: var(--secondary-text-light); 
            font-weight: 500;
        }

        .info-value {
            color: var(--primary-color); 
            font-weight: bold;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--neutral-text);
            font-weight: bold;
            font-size: 14px;
        }

        .form-group input[type="text"],
        .form-group input[type="email"] {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid var(--neutral-border);
            background-color: var(--neutral-bg-light); 
            color: var(--neutral-text); 
            font-size: 16px;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 5px rgba(59, 130, 246, 0.5);
        }
        
        /* Estilo para campo desativado/travado */
        .form-group input:disabled {
            background-color: var(--neutral-bg-dark);
            color: var(--secondary-text-light);
            cursor: not-allowed;
            border-style: dashed;
        }

        .btn-submit {
            width: 100%;
            padding: 15px;
            background-color: var(--primary-color);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-transform: uppercase;
        }

        .btn-submit:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }

        .bonus-status {
            text-align: center;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: bold;
            border: 1px solid;
            font-size: 14px;
        }
        
        .bonus-status.pending {
            background-color: rgba(255, 165, 0, 0.1); 
            border-color: var(--secondary-dark);
            color: var(--secondary-dark);
        }

        .bonus-status.granted {
            background-color: rgba(46, 213, 115, 0.1);
            border-color: var(--success-color);
            color: var(--success-color);
        }
        
        /* TOAST - Mantendo o estilo da referência */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 18px;
            border-radius: 8px;
            color: white;
            font-weight: bold;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transform: translateX(300px);
            transition: all 0.3s ease-in-out;
            z-index: 1000;
            max-width: 300px;
            border: 1px solid;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.success {
            background-color: var(--success-color);
            border-color: #059669;
        }

        .toast.error {
            background-color: var(--error-color);
            border-color: #b91c1c;
        }

        .toast.info {
            background-color: var(--primary-color);
            border-color: #2563eb;
        }

        /* OVERLAY */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4); 
            z-index: 99;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
            pointer-events: none;
        }

        .overlay.show {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        /* RESPONSIVE MOBILE */
        @media (max-width: 768px) {
            body { flex-direction: column; }
            .sidebar { transform: translateX(-100%); width: 100%; max-width: 250px; }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; max-width: 100vw; padding: 15px; }
            .mobile-header { display: flex; }
            .profile-container { padding: 20px; }
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
            <div class="user-info">Olá, <?php echo htmlspecialchars($user_data['nome_completo'] ?? ""); ?></div>
            <div class="user-balance">Saldo: R$ <?php echo number_format($user_data['saldo'], 2, ',', '.'); ?></div>
            <a href="depositar.php" class="deposit-btn">
                <i class="fas fa-plus"></i>
                DEPOSITAR
            </a>
            <?php if ($is_admin): ?>
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
            <a href="perfil.php" class="menu-item active">
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
            <a href="historico.php" class="menu-item">
                <img src="assets/icons/historico.png" alt="Histórico" onerror="this.style.display='none'">
                Histórico
            </a>
            <a href="<?php echo htmlspecialchars($url_suporte ?? "#"); ?>" class="menu-item">
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

        <div class="profile-container">
            <h3><i class="fas fa-user-circle"></i> Meu Perfil</h3>
            
            <?php
            $bonus_concedido = $user_data['bonus_perfil_concedido'] ?? 0;
            $valor_bonus_formatado = number_format($bonus_valor, 2, ',', '.');

            if ($bonus_concedido) {
                echo '<div class="bonus-status granted">';
                echo '<i class="fas fa-check-circle"></i> Bônus de perfil de R$ ' . $valor_bonus_formatado . ' JÁ CONCEDIDO! Não é mais possível editar o telefone.';
                echo '</div>';
            } else {
                echo '<div class="bonus-status pending">';
                echo '<i class="fas fa-exclamation-triangle"></i> Complete seu **Telefone/WhatsApp** para ganhar R$ ' . $valor_bonus_formatado . ' de bônus!';
                echo '</div>';
            }
            ?>
            
            <form method="POST" action="perfil.php" id="profileForm">
                <div class="info-card">
                    <h4>Informações de Contato</h4>
                    
                    <div class="form-group">
                        <label for="nome">Nome Completo</label>
                        <input type="text" id="nome" value="<?php echo htmlspecialchars($user_data['nome_completo'] ?? ""); ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label for="email_novo">Email</label>
                        <input type="email" id="email_novo" name="email_novo" value="<?php echo htmlspecialchars($user_data['email'] ?? ""); ?>">
                    </div>

                    <div class="form-group">
                        <label for="telefone">Telefone (WhatsApp - Somente números)</label>
                        <input 
                            type="text" 
                            id="telefone" 
                            name="telefone" 
                            placeholder="Ex: 5541988887777" 
                            value="<?php echo htmlspecialchars($user_data['telefone'] ?? ''); ?>"
                            <?php echo $telefone_preenchido_e_bonus_concedido ? 'disabled' : ''; ?>
                            data-locked="<?php echo $telefone_preenchido_e_bonus_concedido ? 'true' : 'false'; ?>"
                        >
                        <?php if ($telefone_preenchido_e_bonus_concedido): ?>
                            <small style="color: var(--primary-color); display: block; margin-top: 5px;">
                                <i class="fas fa-lock"></i> Este campo está bloqueado após a concessão do bônus.
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-card">
                    <h4>Dados Financeiros</h4>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-wallet"></i> Saldo Atual</span>
                        <span class="info-value">R$ <?php echo number_format($user_data['saldo'], 2, ',', '.'); ?></span>
                    </div>
                    <?php if (isset($user_data['created_at'])): ?>
                    <div class="info-item">
                        <span class="info-label"><i class="fas fa-clock"></i> Membro Desde</span>
                        <span class="info-value"><?php echo (new DateTime($user_data['created_at']))->format('d/m/Y'); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <button type="submit" name="atualizar_perfil" class="btn-submit">
                    <i class="fas fa-save"></i> SALVAR ALTERAÇÕES
                </button>
            </form>
        </div>

    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="toast show success" id="toast"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="toast show error" id="toast"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <script>
        // Lógica de Toast e Menu Mobile
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const initialToast = document.getElementById('toast');

        // Função para mostrar Toast manualmente
        function showToast(message, type = 'info') {
            const existingToast = document.getElementById('toast');
            if (existingToast) existingToast.remove();
            
            const toast = document.createElement('div');
            toast.id = 'toast';
            toast.className = `toast show ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.classList.remove('show'), 4000);
        }

        // Toggle Menu
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
        
        // Auto-hide initial toast (do PHP)
        if (initialToast) {
            setTimeout(() => {
                initialToast.classList.remove('show');
            }, 4000);
        }

        function logout() {
            if (confirm('Tem certeza que deseja sair?')) {
                window.location.href = 'libs/logout.php';
            }
        }
        
        // Validação de telefone (UX)
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const telefoneField = document.getElementById('telefone');
            const isLocked = telefoneField.getAttribute('data-locked') === 'true';
            
            if (!isLocked) {
                const telefone = telefoneField.value.trim().replace(/\D/g, '');
                
                if (telefone.length > 0 && telefone.length < 10) {
                    showToast("Por favor, insira um número de telefone válido (no mínimo 10 dígitos, somente números).", 'error');
                    e.preventDefault();
                    return;
                }
            } else {
                 // Se o campo está bloqueado, previne o envio se houver tentativa de alteração no front-end
                 // A validação real de que o valor não será alterado ocorre no PHP
            }
        });
    </script>
</body>
</html>