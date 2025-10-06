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

// Função para fazer upload de imagem
function uploadImage($file) {
    $upload_dir = '../uploads/banners/';
    
    // Criar diretório se não existir
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Tipo de arquivo não permitido. Use JPEG, PNG, GIF ou WebP.');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('Arquivo muito grande. Tamanho máximo: 5MB.');
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $new_filename = 'banner_' . uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return $new_filename;
    } else {
        throw new Exception('Erro ao fazer upload da imagem.');
    }
}

// Lógica para adicionar novo banner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_banner'])) {
    $titulo = trim($_POST['titulo']);
    $subtitulo = trim($_POST['subtitulo']);
    $link = trim($_POST['link']);
    $ordem = intval($_POST['ordem']);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $data_inicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
    $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
    
    try {
        $imagem = null;
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            $imagem = uploadImage($_FILES['imagem']);
        }
        
        $stmt = $pdo->prepare("INSERT INTO banners (titulo, subtitulo, imagem, link, ordem, ativo, data_inicio, data_fim, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([$titulo, $subtitulo, $imagem, $link, $ordem, $ativo, $data_inicio, $data_fim]);
        
        $success_message = "Banner adicionado com sucesso!";
    } catch (Exception $e) {
        $error_message = "Erro ao adicionar banner: " . $e->getMessage();
    }
}

// Lógica para editar banner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_banner'])) {
    $banner_id = intval($_POST['banner_id']);
    $titulo = trim($_POST['titulo']);
    $subtitulo = trim($_POST['subtitulo']);
    $link = trim($_POST['link']);
    $ordem = intval($_POST['ordem']);
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    $data_inicio = !empty($_POST['data_inicio']) ? $_POST['data_inicio'] : null;
    $data_fim = !empty($_POST['data_fim']) ? $_POST['data_fim'] : null;
    
    try {
        // Buscar banner atual para pegar a imagem existente
        $stmt_current = $pdo->prepare("SELECT imagem FROM banners WHERE id = ?");
        $stmt_current->execute([$banner_id]);
        $current_banner = $stmt_current->fetch(PDO::FETCH_ASSOC);
        
        $imagem = $current_banner['imagem'];
        
        // Se uma nova imagem foi enviada
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] === UPLOAD_ERR_OK) {
            // Remover imagem antiga se existir
            if ($current_banner['imagem'] && file_exists('../uploads/banners/' . $current_banner['imagem'])) {
                unlink('../uploads/banners/' . $current_banner['imagem']);
            }
            $imagem = uploadImage($_FILES['imagem']);
        }
        
        $stmt = $pdo->prepare("UPDATE banners SET titulo = ?, subtitulo = ?, imagem = ?, link = ?, ordem = ?, ativo = ?, data_inicio = ?, data_fim = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$titulo, $subtitulo, $imagem, $link, $ordem, $ativo, $data_inicio, $data_fim, $banner_id]);
        
        $success_message = "Banner atualizado com sucesso!";
    } catch (Exception $e) {
        $error_message = "Erro ao atualizar banner: " . $e->getMessage();
    }
}

// Lógica para excluir banner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_banner'])) {
    $banner_id = intval($_POST['banner_id']);
    
    try {
        // Buscar banner para remover a imagem
        $stmt_banner = $pdo->prepare("SELECT imagem FROM banners WHERE id = ?");
        $stmt_banner->execute([$banner_id]);
        $banner = $stmt_banner->fetch(PDO::FETCH_ASSOC);
        
        // Remover imagem do servidor se existir
        if ($banner && $banner['imagem'] && file_exists('../uploads/banners/' . $banner['imagem'])) {
            unlink('../uploads/banners/' . $banner['imagem']);
        }
        
        // Excluir banner
        $stmt_del = $pdo->prepare("DELETE FROM banners WHERE id = ?");
        $stmt_del->execute([$banner_id]);
        
        $success_message = "Banner excluído com sucesso!";
    } catch (PDOException $e) {
        $error_message = "Erro ao excluir banner: " . $e->getMessage();
    }
}

// Buscar todos os banners
$stmt_banners = $pdo->prepare("SELECT * FROM banners ORDER BY ordem ASC, id ASC");
$stmt_banners->execute();
$banners = $stmt_banners->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banners - Painel Admin</title>
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

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: -100%;
            width: 280px;
            height: 111vh;
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

        @media (min-width: 768px) {
            .content {
                margin-left: 280px;
                padding: 1.5rem;
                max-width: calc(100vw - 280px);
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
            background: var(--card-bg);
            border-radius: 10px;
            overflow-x: auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .table {
            --bs-table-bg: var(--card-bg);
            --bs-table-color: var(--text-color);
            --bs-table-hover-bg: #e9ecef;
            font-size: 0.8rem;
        }

        .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: #f1f1f1;
        }

        .table th {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
        }

        .table td {
            vertical-align: middle;
        }

        /* Banner Card - Mobile */
        .banner-card {
            background: var(--card-bg);
            border-left: 4px solid var(--primary-color);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .banner-card .card-label {
            font-weight: 600;
            color: var(--primary-color);
        }

        .banner-card .card-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px dashed var(--secondary-color);
        }

        .banner-card .card-row:last-child {
            border-bottom: none;
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

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
            border-color: var(--primary-color);
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            min-height: 38px;
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
            box-shadow: var(--shadow-md);
        }

        .btn-secondary:hover {
            background: linear-gradient(135deg, #475569, #334155);
            border-color: #475569;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--text-primary);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            border-color: var(--danger-color);
            color: var(--text-primary);
            box-shadow: var(--shadow-md);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--text-primary);
        }

        /* Banner Image Preview */
        .banner-image-preview {
            max-width: 100px;
            max-height: 60px;
            object-fit: cover;
            border-radius: 5px;
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
            color: var(--text-primary) !important;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        /* Garantir que todos os textos sejam brancos */
        .modal-content * {
            color: var(--text-primary) !important;
        }

        .modal-content input::placeholder {
            color: var(--text-secondary) !important;
            opacity: 0.7;
        }

        .modal-content input[type="text"], 
        .modal-content input[type="url"], 
        .modal-content input[type="number"], 
        .modal-content input[type="date"],
        .modal-content input[type="file"] {
            color: var(--text-primary) !important;
        }

        .modal-content input[type="text"]:focus, 
        .modal-content input[type="url"]:focus, 
        .modal-content input[type="number"]:focus,
        .modal-content input[type="date"]:focus {
            color: var(--text-primary) !important;
        }

        .modal-content .text-muted {
            color: var(--text-primary) !important;
            opacity: 0.8;
        }

        .modal-content small {
            color: var(--text-primary) !important;
        }

        /* Input de arquivo específico */
        .modal-content .form-control[type="file"] {
            color: var(--text-primary) !important;
        }

        .modal-content .form-control[type="file"]::-webkit-file-upload-button {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 0.5rem 1rem;
            margin-right: 1rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .modal-content .form-control[type="file"]::-webkit-file-upload-button:hover {
            background: var(--border-light);
        }

        /* Texto interno dos inputs */
        .modal-content input[value] {
            color: var(--text-primary) !important;
        }

        /* Data inputs */
        .modal-content input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
            cursor: pointer;
        }

        /* Pequenos textos e labels */
        .modal-content label,
        .modal-content .form-check-label {
            color: var(--text-primary) !important;
        }

        /* Preview de imagem */
        #current_image_preview {
            color: var(--text-primary) !important;
        }

        .modal-content .text-center {
            color: var(--text-primary) !important;
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
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-body {
            padding: 2rem;
            color: var(--text-primary);
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding:  1.5rem;
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
            }
            .banner-card {
                display: none;
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
            .banner-card {
                display: block;
            }
            .table-responsive {
                display: none;
            }
        }

        /* Responsive Modal Styles */
        @media (max-width: 767px) {
            .modal-body {
                padding: 1.5rem;
            }

            .modal-footer {
                padding: 1rem;
            }

            .modal-header {
                padding: 1rem;
            }
        }

        @media (max-width: 475px) {
            .modal-dialog {
                margin: 0.5rem;
            }

            .modal-content {
                border-radius: 12px;
            }

            .modal-header {
                padding: 0.75rem;
                border-radius: 12px 12px 0 0;
            }

            .modal-body {
                padding: 1rem;
            }

            .modal-footer {
                padding: 0.75rem;
                border-radius: 0 0 12px 12px;
            }
        }
        
        @media (max-width: 767px) {
            .banner-card {
                display: block;
            }
            .table-responsive {
                display: none;
            }
            
            /* Mobile buttons */
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
            <a href="banners.php" class="active"><i class="fas fa-images"></i> Banners</a>
            <a href="gateway.php"><i class="fas fa-credit-card"></i> Gateway</a>
            <a href="configuracoes.php"><i class="fas fa-cogs"></i> Configurações</a>
            <a href="depositos.php"><i class="fas fa-money-check-alt"></i> Depósitos</a>
            <a href="saques.php"><i class="fas fa-hand-holding-usd"></i> Saques</a>
            <a href="../" class="exit"><i class="fas fa-sign-out-alt"></i> Voltar</a>
        </div>
    </div>

    <div class="content" id="content">
        <div class="header">
            <h1>Gerenciar Banners</h1>
            <p>Controle os banners exibidos no site.</p>
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

        <div class="mb-3">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBannerModal">
                <i class="fas fa-plus"></i> Adicionar Banner
            </button>
        </div>

        <!-- Tabela para Desktop -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Imagem</th>
                        <th>Título</th>
                        <th>Subtítulo</th>
                        <th>Ordem</th>
                        <th>Status</th>
                        <th>Período</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($banners): ?>
                        <?php foreach ($banners as $banner): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($banner['id']); ?></td>
                                <td>
                                    <?php if ($banner['imagem']): ?>
                                        <img src="../uploads/banners/<?php echo htmlspecialchars($banner['imagem']); ?>" 
                                             alt="Banner" class="banner-image-preview">
                                    <?php else: ?>
                                        <span class="text-muted">Sem imagem</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($banner['titulo']); ?></td>
                                <td><?php echo htmlspecialchars($banner['subtitulo']); ?></td>
                                <td><?php echo $banner['ordem']; ?></td>
                                <td>
                                    <?php if ($banner['ativo']): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        <?php if ($banner['data_inicio']): ?>
                                            De: <?php echo date('d/m/Y', strtotime($banner['data_inicio'])); ?><br>
                                        <?php endif; ?>
                                        <?php if ($banner['data_fim']): ?>
                                            Até: <?php echo date('d/m/Y', strtotime($banner['data_fim'])); ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <button type="button" class="btn-action btn-edit" 
                                            data-bs-toggle="modal" data-bs-target="#editBannerModal"
                                            data-banner-id="<?php echo $banner['id']; ?>"
                                            data-banner-titulo="<?php echo htmlspecialchars($banner['titulo']); ?>"
                                            data-banner-subtitulo="<?php echo htmlspecialchars($banner['subtitulo']); ?>"
                                            data-banner-link="<?php echo htmlspecialchars($banner['link']); ?>"
                                            data-banner-ordem="<?php echo $banner['ordem']; ?>"
                                            data-banner-ativo="<?php echo $banner['ativo']; ?>"
                                            data-banner-data-inicio="<?php echo $banner['data_inicio']; ?>"
                                            data-banner-data-fim="<?php echo $banner['data_fim']; ?>"
                                            data-banner-imagem="<?php echo htmlspecialchars($banner['imagem']); ?>">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button type="button" class="btn-action btn-delete" 
                                            data-bs-toggle="modal" data-bs-target="#deleteBannerModal"
                                            data-banner-id="<?php echo $banner['id']; ?>"
                                            data-banner-titulo="<?php echo htmlspecialchars($banner['titulo']); ?>">
                                        <i class="fas fa-trash"></i> Excluir
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">Nenhum banner encontrado.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Cards para Mobile -->
        <div class="d-block d-md-none">
            <?php if (empty($banners)): ?>
                <div class="banner-card text-center">
                    Nenhum banner encontrado.
                </div>
            <?php else: ?>
                <?php foreach ($banners as $banner): ?>
                    <div class="banner-card mb-3">
                        <div class="card-row">
                            <span class="card-label">ID:</span>
                            <span class="card-value"><?php echo htmlspecialchars($banner['id']); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Título:</span>
                            <span class="card-value"><?php echo htmlspecialchars($banner['titulo']); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Subtítulo:</span>
                            <span class="card-value"><?php echo htmlspecialchars($banner['subtitulo']); ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Ordem:</span>
                            <span class="card-value"><?php echo $banner['ordem']; ?></span>
                        </div>
                        <div class="card-row">
                            <span class="card-label">Status:</span>
                            <span class="card-value"><?php echo $banner['ativo'] ? 'Ativo' : 'Inativo'; ?></span>
                        </div>
                        <?php if ($banner['imagem']): ?>
                            <div class="card-row">
                                <span class="card-label">Imagem:</span>
                                <span class="card-value">
                                    <img src="../uploads/banners/<?php echo htmlspecialchars($banner['imagem']); ?>" 
                                         alt="Banner" class="banner-image-preview">
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="d-flex mt-3">
                            <button type="button" class="btn-action btn-edit me-2" 
                                    data-bs-toggle="modal" data-bs-target="#editBannerModal"
                                    data-banner-id="<?php echo $banner['id']; ?>"
                                    data-banner-titulo="<?php echo htmlspecialchars($banner['titulo']); ?>"
                                    data-banner-subtitulo="<?php echo htmlspecialchars($banner['subtitulo']); ?>"
                                    data-banner-link="<?php echo htmlspecialchars($banner['link']); ?>"
                                    data-banner-ordem="<?php echo $banner['ordem']; ?>"
                                    data-banner-ativo="<?php echo $banner['ativo']; ?>"
                                    data-banner-data-inicio="<?php echo $banner['data_inicio']; ?>"
                                    data-banner-data-fim="<?php echo $banner['data_fim']; ?>"
                                    data-banner-imagem="<?php echo htmlspecialchars($banner['imagem']); ?>">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                            <button type="button" class="btn-action btn-delete" 
                                    data-bs-toggle="modal" data-bs-target="#deleteBannerModal"
                                    data-banner-id="<?php echo $banner['id']; ?>"
                                    data-banner-titulo="<?php echo htmlspecialchars($banner['titulo']); ?>">
                                <i class="fas fa-trash"></i> Excluir
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Adicionar Banner -->
    <div class="modal fade" id="addBannerModal" tabindex="-1" aria-labelledby="addBannerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBannerModalLabel">
                        <i class="fas fa-plus"></i> Adicionar Novo Banner
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="banners.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_titulo" class="form-label">Título *</label>
                                <input type="text" class="form-control" id="add_titulo" name="titulo" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_subtitulo" class="form-label">Subtítulo</label>
                                <input type="text" class="form-control" id="add_subtitulo" name="subtitulo">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_imagem" class="form-label">Imagem</label>
                                <input type="file" class="form-control" id="add_imagem" name="imagem" accept="image/*">
                                <small class="form-text text-muted">Formatos aceitos: JPEG, PNG, GIF, WebP. Tamanho máximo: 5MB</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_link" class="form-label">Link (URL)</label>
                                <input type="url" class="form-control" id="add_link" name="link" placeholder="https://example.com">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_ordem" class="form-label">Ordem *</label>
                                <input type="number" class="form-control" id="add_ordem" name="ordem" value="1" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="add_ativo" name="ativo" checked>
                                    <label class="form-check-label" for="add_ativo">
                                        Banner Ativo
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="add_data_inicio" class="form-label">Data de Início</label>
                                <input type="date" class="form-control" id="add_data_inicio" name="data_inicio">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="add_data_fim" class="form-label">Data de Fim</label>
                                <input type="date" class="form-control" id="add_data_fim" name="data_fim">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="add_banner" class="btn btn-primary">
                            <i class="fas fa-save"></i> Salvar Banner
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Banner -->
    <div class="modal fade" id="editBannerModal" tabindex="-1" aria-labelledby="editBannerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editBannerModalLabel">
                        <i class="fas fa-edit"></i> Editar Banner
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="banners.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="banner_id" id="edit_banner_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_titulo" class="form-label">Título *</label>
                                <input type="text" class="form-control" id="edit_titulo" name="titulo" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_subtitulo" class="form-label">Subtítulo</label>
                                <input type="text" class="form-control" id="edit_subtitulo" name="subtitulo">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_imagem" class="form-label">Nova Imagem</label>
                                <input type="file" class="form-control" id="edit_imagem" name="imagem" accept="image/*">
                                <small class="form-text text-muted">Deixe em branco para manter a imagem atual</small>
                                <div id="current_image_preview" class="mt-2"></div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_link" class="form-label">Link (URL)</label>
                                <input type="url" class="form-control" id="edit_link" name="link" placeholder="https://example.com">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_ordem" class="form-label">Ordem *</label>
                                <input type="number" class="form-control" id="edit_ordem" name="ordem" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="edit_ativo" name="ativo">
                                    <label class="form-check-label" for="edit_ativo">
                                        Banner Ativo
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_data_inicio" class="form-label">Data de Início</label>
                                <input type="date" class="form-control" id="edit_data_inicio" name="data_inicio">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_data_fim" class="form-label">Data de Fim</label>
                                <input type="date" class="form-control" id="edit_data_fim" name="data_fim">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="edit_banner" class="btn btn-primary">
                            <i class="fas fa-save"></i> Atualizar Banner
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Excluir Banner -->
    <div class="modal fade" id="deleteBannerModal" tabindex="-1" aria-labelledby="deleteBannerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteBannerModalLabel">
                        <i class="fas fa-trash"></i> Confirmar Exclusão
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="banners.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="banner_id" id="delete_banner_id">
                        <p>Você tem certeza que deseja excluir o banner <strong id="delete_banner_titulo"></strong>?</p>
                        <p class="text-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Esta ação é irreversível e removerá também a imagem associada.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="delete_banner" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Sim, Excluir
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

        // Modal Editar Banner
        const editBannerModal = document.getElementById('editBannerModal');
        editBannerModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            
            const bannerId = button.getAttribute('data-banner-id');
            const titulo = button.getAttribute('data-banner-titulo');
            const subtitulo = button.getAttribute('data-banner-subtitulo');
            const link = button.getAttribute('data-banner-link');
            const ordem = button.getAttribute('data-banner-ordem');
            const ativo = button.getAttribute('data-banner-ativo');
            const dataInicio = button.getAttribute('data-banner-data-inicio');
            const dataFim = button.getAttribute('data-banner-data-fim');
            const imagem = button.getAttribute('data-banner-imagem');
            
            document.getElementById('edit_banner_id').value = bannerId;
            document.getElementById('edit_titulo').value = titulo;
            document.getElementById('edit_subtitulo').value = subtitulo;
            document.getElementById('edit_link').value = link || '';
            document.getElementById('edit_ordem').value = ordem;
            document.getElementById('edit_ativo').checked = ativo == '1';
            document.getElementById('edit_data_inicio').value = dataInicio || '';
            document.getElementById('edit_data_fim').value = dataFim || '';
            
            // Preview da imagem atual
            const currentImagePreview = document.getElementById('current_image_preview');
            if (imagem) {
                currentImagePreview.innerHTML = `
                    <small class="text-muted">Imagem atual:</small><br>
                    <img src="../uploads/banners/${imagem}" alt="Imagem atual" class="banner-image-preview">
                `;
            } else {
                currentImagePreview.innerHTML = '<small class="text-muted">Nenhuma imagem atual</small>';
            }
        });

        // Modal Excluir Banner
        const deleteBannerModal = document.getElementById('deleteBannerModal');
        deleteBannerModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const bannerId = button.getAttribute('data-banner-id');
            const titulo = button.getAttribute('data-banner-titulo');
            
            document.getElementById('delete_banner_id').value = bannerId;
            document.getElementById('delete_banner_titulo').textContent = titulo;
        });

        // Preview de imagem no upload
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `
                        <small class="text-muted">Preview:</small><br>
                        <img src="${e.target.result}" alt="Preview" class="banner-image-preview">
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        }

        // Adicionar preview para o modal de adicionar
        document.getElementById('add_imagem').addEventListener('change', function() {
            const preview = document.createElement('div');
            preview.id = 'add_image_preview';
            preview.className = 'mt-2';
            
            // Remove preview anterior se existir
            const existingPreview = document.getElementById('add_image_preview');
            if (existingPreview) {
                existingPreview.remove();
            }
            
            this.parentNode.appendChild(preview);
            previewImage(this, 'add_image_preview');
        });

        // Adicionar preview para o modal de editar
        document.getElementById('edit_imagem').addEventListener('change', function() {
            const preview = document.createElement('div');
            preview.id = 'edit_image_preview';
            preview.className = 'mt-2';
            
            // Remove preview anterior se existir
            const existingPreview = document.getElementById('edit_image_preview');
            if (existingPreview) {
                existingPreview.remove();
            }
            
            this.parentNode.appendChild(preview);
            previewImage(this, 'edit_image_preview');
        });

        // Validação de formulário
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const fileInput = this.querySelector('input[type="file"]');
                if (fileInput && fileInput.files[0]) {
                    const file = fileInput.files[0];
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    
                    if (file.size > maxSize) {
                        e.preventDefault();
                        alert('Arquivo muito grande. Tamanho máximo permitido: 5MB');
                        return false;
                    }
                    
                    if (!allowedTypes.includes(file.type)) {
                        e.preventDefault();
                        alert('Tipo de arquivo não permitido. Use JPEG, PNG, GIF ou WebP.');
                        return false;
                    }
                }
            });
        });
    </script>
</body>
</html>