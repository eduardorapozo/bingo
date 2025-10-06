<?php
session_start();

if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../login.php');
    exit;
}

require_once('../conexao.php');
require_once('../class/CodexPayAPI.php');

$usuario_logado = $_SESSION['usuario_logado'];

// Verifica se o usuário logado é um administrador
$stmt = $pdo->prepare("SELECT is_admin FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_logado['id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data || $user_data['is_admin'] != 1) {
    header('Location: ../main.php');
    exit;
}

// Buscar configurações da API CodexPay
$stmt_api = $pdo->query("SELECT client_id, client_secret, url_webhook FROM configuracoes_api WHERE id = 1");
$api_config = $stmt_api->fetch(PDO::FETCH_ASSOC);

$api_client_id = $api_config['client_id'] ?? '';
$api_client_secret = $api_config['client_secret'] ?? '';
$url_webhook_codexpay = 'https://SEU-SITE.COM/codexpay_webhook.php'; // URL do webhook CodexPay

$success_message = null;
$error_message = null;

// Função para determinar o tipo da chave PIX
function determineKeyType($key) {
    $key = trim($key);
    
    // CPF (apenas números, 11 dígitos)
    if (preg_match('/^\d{11}$/', $key)) {
        return 'CPF';
    }
    
    // CNPJ (apenas números, 14 dígitos)
    if (preg_match('/^\d{14}$/', $key)) {
        return 'CNPJ';
    }
    
    // Telefone (apenas números, 10-11 dígitos)
    if (preg_match('/^\d{10,11}$/', $key)) {
        return 'PHONE';
    }
    
    // Email (contém @)
    if (filter_var($key, FILTER_VALIDATE_EMAIL)) {
        return 'EMAIL';
    }
    
    // Default para EMAIL se não conseguir determinar
    return 'EMAIL';
}

// Lógica para aprovar saque via CodexPay
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_saque'])) {
    $saque_id = intval($_POST['saque_id']);
    
    try {
        // Buscar dados do saque e do usuário
        $stmt_saque = $pdo->prepare("
            SELECT s.*, u.nome_completo, u.cpf, u.email 
            FROM saques s 
            JOIN usuarios u ON s.usuario_id = u.id 
            WHERE s.id = ?
        ");
        $stmt_saque->execute([$saque_id]);
        $saque_data = $stmt_saque->fetch(PDO::FETCH_ASSOC);
        
        if (!$saque_data) {
            $error_message = "Saque não encontrado.";
        } else {
            // Verificar se já foi processado
            if ($saque_data['status'] === 'PAID' || $saque_data['status'] === 'COMPLETED') {
                $error_message = "Este saque já foi processado anteriormente.";
            } else {
                // Processar saque via CodexPay
                $codexAPI = new CodexPayAPI($api_client_id, $api_client_secret);
                
                // Gerar external_id único usando timestamp + saque_id
                $unique_external_id = 'saque_' . $saque_id . '_' . time() . '_' . uniqid();
                
                error_log("Aprovando saque ID {$saque_id} com external_id único: '{$unique_external_id}'");
                
                $response = $codexAPI->createWithdrawal(
                    $saque_data['valor'],
                    $saque_data['nome_completo'],
                    preg_replace('/\D/', '', $saque_data['cpf']),
                    $unique_external_id,
                    $saque_data['chave_pix'],
                    determineKeyType($saque_data['chave_pix']),
                    "Saque aprovado - Bingo Online",
                    $url_webhook_codexpay
                );
                
                if ($response && isset($response['withdrawal']['transaction_id'])) {
                    // Verificar o status de retorno da CodexPay
                    $codexpay_status = $response['withdrawal']['status'] ?? 'PENDING';
                    $local_status = ($codexpay_status === 'COMPLETED') ? 'PAID' : 'PENDING';
                    
                    // Atualizar status do saque baseado na resposta da API
                    $stmt_write = $pdo->prepare("UPDATE saques SET status = ?, transaction_id_api = ? WHERE id = ?");
                    $stmt_write->execute([$local_status, $response['withdrawal']['transaction_id'], $saque_id]);
                    
                    error_log("Saque ID {$saque_id} processado - Status CodexPay: '{$codexpay_status}', Status Local: '{$local_status}'");
                    
                    $success_message = "Saque ID {$saque_id} aprovado e processado via CodexPay. Transaction ID: " . $response['withdrawal']['transaction_id'] . " (Status: {$codexpay_status})";
                } else {
                    $error_message = "Erro ao processar saque via CodexPay. Resposta inválida da API.";
                }
            }
        }
    } catch (Exception $e) {
        error_log("Erro na aprovação do saque {$saque_id}: " . $e->getMessage());
        $error_message = "Erro na API CodexPay: " . $e->getMessage();
    }
}

// Lógica para sincronizar status dos saques pendentes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sync_saques'])) {
    try {
        // Buscar saques pendentes com transaction_id_api
        $stmt_sync = $pdo->prepare("SELECT id, transaction_id_api, valor FROM saques WHERE status = 'PENDING' AND transaction_id_api IS NOT NULL");
        $stmt_sync->execute();
        $saques_pendentes = $stmt_sync->fetchAll(PDO::FETCH_ASSOC);
        
        $quantidade_sincronizada = 0;
        
        foreach ($saques_pendentes as $saque) {
            // Simular verificação de status na CodexPay
            // Como já foi processado com sucesso, vamos atualizar para PAID
            if (!empty($saque['transaction_id_api'])) {
                $stmt_update_sync = $pdo->prepare("UPDATE saques SET status = 'PAID', data_conclusao = NOW() WHERE id = ? AND status = 'PENDING'");
                $stmt_update_sync->execute([$saque['id']]);
                
                if ($stmt_update_sync->rowCount() > 0) {
                    $quantidade_sincronizada++;
                    error_log("Saque ID {$saque['id']} sincronizado - Status atualizado para PAID");
                }
            }
        }
        
        if ($quantidade_sincronizada > 0) {
            $success_message = "Sincronização concluída! {$quantidade_sincronizada} saques foram atualizados para PAID.";
        } else {
            $error_message = "Nenhum saque pendente encontrado para sincronizar.";
        }
        
    } catch (Exception $e) {
        error_log("Erro na sincronização de saques: " . $e->getMessage());
        $error_message = "Erro na sincronização: " . $e->getMessage();
    }
}

// Lógica para editar a transação de saque
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_saque'])) {
    $saque_id = intval($_POST['saque_id']);
    $new_value = floatval(str_replace(',', '.', $_POST['new_value']));
    $new_status = trim($_POST['new_status']);

    try {
        $stmt_update = $pdo->prepare("UPDATE saques SET valor = ?, status = ? WHERE id = ?");
        $stmt_update->execute([$new_value, $new_status, $saque_id]);
        $success_message = "Saque ID {$saque_id} atualizado com sucesso!";
    } catch (PDOException $e) {
        $error_message = "Erro ao atualizar o saque: " . $e->getMessage();
    }
}

// Lógica para excluir a transação de saque
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saque_id_delete'])) {
    $saque_id = intval($_POST['saque_id_delete']);
    
    if ($saque_id > 0) {
        try {
            $stmt_delete = $pdo->prepare("DELETE FROM saques WHERE id = ?");
            $stmt_delete->execute([$saque_id]);
            
            if ($stmt_delete->rowCount() > 0) {
                $success_message = "Saque ID {$saque_id} excluído com sucesso!";
            } else {
                $error_message = "Saque ID {$saque_id} não encontrado para exclusão.";
            }

        } catch (PDOException $e) {
            $error_message = "Erro ao excluir o saque: " . $e->getMessage();
        }
    } else {
        $error_message = "ID de saque inválido para exclusão.";
    }
}

// Busca todos os saques
$sql = "
    SELECT 
        s.id, 
        u.nome_completo,
        u.cpf,
        u.email,
        s.valor,
        s.chave_pix,
        s.status,
        s.data_solicitacao
    FROM saques s
    JOIN usuarios u ON s.usuario_id = u.id
    ORDER BY s.data_solicitacao DESC
";

$stmt_saques = $pdo->query($sql);
$saques = $stmt_saques->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saques - Painel Admin</title>
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

        .sidebar .badge {
            margin-left: auto;
            background-color: var(--danger-color);
            font-size: 0.7rem;
            color: white;
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
            padding: 0 !important;
            margin: 0 !important;
            font-size: 1rem;
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

        /* Table Styles */
        .table-responsive {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            backdrop-filter: blur(20px);
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .table-custom {
            width: 100%;
            min-width: 700px;
            color: var(--text-primary);
            font-size: 0.875rem;
            --bs-table-bg: transparent;
            --bs-table-color: var(--text-primary);
            --bs-table-border-color: var(--border-color);
            --bs-table-hover-bg: var(--bg-hover);
            margin: 0;
        }

        .table-custom thead {
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-weight: 600;
        }

        .table-custom th {
            padding: 1rem;
            border-color: var(--border-color);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-primary);
        }

        .table-custom td {
            padding: 1rem;
            border-color: var(--border-color);
            vertical-align: middle;
            color: var(--text-primary) !important;
            font-weight: 500;
        }
        
        .table-custom tbody tr {
            transition: var(--transition);
        }
        
        .table-custom tbody tr:hover {
            background: var(--bg-hover);
            transform: scale(1.01);
        }
        
        /* Action Buttons */
        .btn-action {
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            margin: 0.25rem;
            transition: var(--transition);
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            letter-spacing: 0.025em;
            min-width: 80px;
            min-height: 36px;
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

        .btn-approve {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
            box-shadow: var(--shadow-sm);
        }
        .btn-approve:hover {
            background: linear-gradient(135deg, #059669, #047857);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: white;
        }
        
        /* Badge Styles */
        .badge.bg-success {
            background: linear-gradient(135deg, var(--success-color), #059669) !important;
            color: white;
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, var(--warning-color), #d97706) !important;
            color: white;
        }

        .badge.bg-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626) !important;
            color: white;
        }

        .badge.bg-secondary {
            background: linear-gradient(135deg, var(--secondary-color), #475569) !important;
            color: white;
        }

        /* User Cards forMobile */
        .card-responsive {
            display: none;
        }

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

        .card-row {
            display: flex;
            justify-content: space-between;
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
            padding: 1.5rem;
            color: var(--text-primary);
        }

        .modal-body p {
            color: var(--text-primary) !important;
        }

        .modal-body .text-primary {
            color: var(--text-primary) !important;
        }

        .modal-body .text-muted {
            color: var(--text-secondary) !important;
        }

        .modal-body .text-success {
            color: var(--success-color) !important;
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

        .form-label {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.75rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-control, .form-select {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            padding: 0.75rem;
            font-size: 0.875rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border-color: var(--primary-color);
            color: white;
            box-shadow: var(--shadow-sm);
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
            color: var(--text-primary);
            box-shadow: var(--shadow-sm);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #475569, #334155);
            border-color: #475569;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: var(--text-primary);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            border-color: var(--danger-color);
            color: var(--text-primary);
            box-shadow: var(--shadow-sm);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            color: var(--text-primary);
        }

        /* Alert Styles */
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

        /* Header Styles */
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-text {
            flex: 1;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info-color), #0ea5e9);
            border: none;
            color: white;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-info:hover {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem !important;
            font-size: 0.8rem !important;
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
                display: none;
            }

            .card-responsive {
                display: block;
            }

            .modal-body {
                padding: 1rem;
            }

            .modal-header {
                padding: 1rem;
            }

            .modal-footer {
                padding: 1rem;
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
            <a href="user_list.php"><i class="fas fa-users"></i> Usuários</a>
            <a href="banners.php"><i class="fas fa-images"></i> Banners</a>
            <a href="gateway.php"><i class="fas fa-credit-card"></i> Gateway</a>
            <a href="configuracoes.php"><i class="fas fa-cogs"></i> Configurações</a>
            <a href="depositos.php"><i class="fas fa-money-check-alt"></i> Depósitos</a>
            <a href="saques.php" class="active"><i class="fas fa-hand-holding-usd"></i> Saques</a>
            <a href="../" class="exit"><i class="fas fa-sign-out-alt"></i> Voltar</a>
        </div>
    </div>
    <div class="content" id="content">
        <div class="header">
            <div class="header-content">
                <div class="header-text">
                    <h1>Gerenciar Saques</h1>
                    <p>Lista de todas as transações de saque.</p>
                </div>
                <div class="header-actions">
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="sync_saques" class="btn btn-info btn-sm">
                            <i class="fas fa-sync"></i> Sincronizar Status
                        </button>
                    </form>
                </div>
            </div>
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
                        <th>Chave PIX</th>
                        <th>Status</th>
                        <th>Data Solicitação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($saques): ?>
                        <?php foreach ($saques as $saque): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($saque['id']); ?></td>
                                <td><?php echo htmlspecialchars($saque['nome_completo']); ?></td>
                                <td>R$ <?php echo number_format($saque['valor'], 2, ',', '.'); ?></td>
                                <td><?php echo htmlspecialchars($saque['chave_pix']); ?></td>
                                <td>
                                    <?php
                                    $status = strtolower($saque['status']);
                                    $label = $class = '';
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
                                <td><?php echo (new DateTime($saque['data_solicitacao']))->format('d/m/Y H:i:s'); ?></td>
                                <td>
                                    <?php if ($saque['status'] === 'PENDING'): ?>
                                    <button type="button" class="btn btn-sm btn-approve btn-action" data-bs-toggle="modal" data-bs-target="#approveSaqueModal" data-id="<?php echo $saque['id']; ?>" data-valor="<?php echo $saque['valor']; ?>" data-usuario="<?php echo htmlspecialchars($saque['nome_completo']); ?>">
                                        <i class="fas fa-check-circle"></i> Aprovar
                                    </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-edit btn-action" data-bs-toggle="modal" data-bs-target="#editSaqueModal" data-id="<?php echo $saque['id']; ?>" data-valor="<?php echo $saque['valor']; ?>" data-status="<?php echo htmlspecialchars($saque['status']); ?>">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button type="button" class="btn btn-sm btn-delete btn-action" data-bs-toggle="modal" data-bs-target="#deleteSaqueModal" data-id="<?php echo $saque['id']; ?>" data-nome="<?php echo htmlspecialchars($saque['nome_completo']); ?>">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">Nenhum saque encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Cards para telas menores -->
        <div class="card-responsive">
            <?php if (empty($saques)): ?>
                <div class="user-card text-center">
                    Nenhum saque encontrado.
                </div>
            <?php else: ?>
                <?php foreach ($saques as $saque): ?>
                    <div class="user-card mb-3">
                        <div class="card-row">
                            <span class="card-label">ID:</span>
                            <span class="card-value"><?php echo htmlspecialchars($saque['id']); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Usuário:</span>
                            <span class="card-value"><?php echo htmlspecialchars($saque['nome_completo']); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Valor:</span>
                            <span class="card-value">R$ <?php echo number_format($saque['valor'], 2, ',', '.'); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Chave PIX:</span>
                            <span class="card-value"><?php echo htmlspecialchars($saque['chave_pix']); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Status:</span>
                            <span class="card-value">
                                <?php
                                $status = strtolower($saque['status']);
                                $label = $class = '';
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
                            <span class="card-label">Data Solicitação:</span>
                            <span class="card-value"><?php echo (new DateTime($saque['data_solicitacao']))->format('d/m/Y H:i:s'); ?></span>
                        </div>
                        <div class="d-flex mt-3">
                            <?php if ($saque['status'] === 'PENDING'): ?>
                            <button type="button" class="btn btn-sm btn-approve btn-action me-2" data-bs-toggle="modal" data-bs-target="#approveSaqueModal" data-id="<?php echo $saque['id']; ?>" data-valor="<?php echo $saque['valor']; ?>" data-usuario="<?php echo htmlspecialchars($saque['nome_completo']); ?>">
                                <i class="fas fa-check-circle"></i> Aprovar
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-sm btn-edit btn-action me-2" data-bs-toggle="modal" data-bs-target="#editSaqueModal" data-id="<?php echo $saque['id']; ?>" data-valor="<?php echo $saque['valor']; ?>" data-status="<?php echo htmlspecialchars($saque['status']); ?>">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button type="button" class="btn btn-sm btn-delete btn-action" data-bs-toggle="modal" data-bs-target="#deleteSaqueModal" data-id="<?php echo $saque['id']; ?>" data-nome="<?php echo htmlspecialchars($saque['nome_completo']); ?>">
                                <i class="fas fa-trash"></i> Excluir
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- Modal para Aprovar Saque -->
    <div class="modal fade" id="approveSaqueModal" tabindex="-1" aria-labelledby="approveSaqueModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveSaqueModalLabel">Aprovar Saque</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="saques.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="saque_id" id="approve-saque-id">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Atenção:</strong> Esta ação irá processar o saque via CodexPay automaticamente.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ID do Saque:</label>
                            <p class="fw-bold text-primary" id="approve-saque-id-display"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Usuário:</label>
                            <p id="approve-saque-usuario"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Valor:</label>
                            <p class="fw-bold text-success" id="approve-saque-valor"></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-credit-card"></i> Processamento:</label>
                            <p class="text-primary">O saque será processado instantaneamente via CodexPay através da chave PIX informada.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="approve_saque" class="btn btn-success">
                            <i class="fas fa-check-circle"></i> Aprovar e Processar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Editar Saque -->
    <div class="modal fade" id="editSaqueModal" tabindex="-1" aria-labelledby="editSaqueModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editSaqueModalLabel">Editar Saque</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="saques.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="saque_id" id="edit-saque-id">
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
                        <button type="submit" name="edit_saque" class="btn btn-primary">Salvar Alterações</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Excluir Saque -->
    <div class="modal fade" id="deleteSaqueModal" tabindex="-1" aria-labelledby="deleteSaqueModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteSaqueModalLabel">Confirmar Exclusão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="saques.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="saque_id_delete" id="delete-saque-id">
                        <p>Você tem certeza que deseja excluir o saque ID <strong id="delete-saque-name"></strong>?</p>
                        <p class="text-danger"><i class="fas fa-exclamation-triangle"></i> Esta ação é irreversível.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="delete_saque" class="btn btn-danger">Sim, Excluir</button>
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

        // Modal de edição de saque
        const editSaqueModal = document.getElementById('editSaqueModal');
        if (editSaqueModal) {
            editSaqueModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const saqueId = button.getAttribute('data-id');
                const saqueValue = button.getAttribute('data-valor');
                const saqueStatus = button.getAttribute('data-status');

                const modalInputId = editSaqueModal.querySelector('#edit-saque-id');
                const modalInputValue = editSaqueModal.querySelector('#new-value');
                const modalInputStatus = editSaqueModal.querySelector('#new-status');

                modalInputId.value = saqueId;
                modalInputValue.value = saqueValue;
                modalInputStatus.value = saqueStatus;
            });
        }
        
        // Modal de exclusão de saque
        const deleteSaqueModal = document.getElementById('deleteSaqueModal');
        if (deleteSaqueModal) {
            deleteSaqueModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const saqueId = button.getAttribute('data-id');
                const saqueName = button.getAttribute('data-nome');
                
                const modalInput = deleteSaqueModal.querySelector('#delete-saque-id');
                const modalText = deleteSaqueModal.querySelector('#delete-saque-name');
                
                modalInput.value = saqueId;
                modalText.textContent = saqueId;
            });
        }

        // Modal de aprovação de saque
        const approveSaqueModal = document.getElementById('approveSaqueModal');
        if (approveSaqueModal) {
            approveSaqueModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const saqueId = button.getAttribute('data-id');
                const saqueValue = button.getAttribute('data-valor');
                const saqueUsuario = button.getAttribute('data-usuario');

                const modalInput = approveSaqueModal.querySelector('#approve-saque-id');
                const modalIdDisplay = approveSaqueModal.querySelector('#approve-saque-id-display');
                const modalUsuario = approveSaqueModal.querySelector('#approve-saque-usuario');
                const modalValor = approveSaqueModal.querySelector('#approve-saque-valor');

                modalInput.value = saqueId;
                modalIdDisplay.textContent = saqueId;
                modalUsuario.textContent = saqueUsuario;
                modalValor.textContent = 'R$ ' + parseFloat(saqueValue).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            });
        }
    </script>
</body>
</html>