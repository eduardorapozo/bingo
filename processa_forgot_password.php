<?php
session_start();
require_once('../conexao.php');

// Define que sempre retornará JSON para AJAX
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // Log para debug
    error_log("Tentativa de recuperação de senha para: $email");
    
    // Validações básicas
    if (empty($email)) {
        echo json_encode(['success' => false, 'message' => 'E-mail é obrigatório.']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'E-mail inválido.']);
        exit;
    }
    
    try {
        // Verifica se o e-mail existe no banco
        $stmt = $pdo->prepare("SELECT id, nome_completo FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario) {
            // Por segurança, não revelar se o e-mail existe ou não
            echo json_encode([
                'success' => true, 
                'message' => 'Se o e-mail estiver cadastrado, você receberá as instruções de recuperação.'
            ]);
            exit;
        }
        
        // Gera token único para recuperação
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour')); // Expira em 1 hora
        
        // Salva o token no banco
        $stmt = $pdo->prepare("
            INSERT INTO password_resets (user_id, email, token, expires_at, created_at) 
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            token = VALUES(token), 
            expires_at = VALUES(expires_at), 
            created_at = NOW()
        ");
        $stmt->execute([$usuario['id'], $email, $token, $expiry]);
        
        // Monta o link de recuperação
        $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;
        
        // Conteúdo do e-mail em HTML
        $subject = "Recuperação de Senha - Bingo";
        $message = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #3b82f6, #1d4ed8); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .button { display: inline-block; background: #3b82f6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 20px 0; }
                .button:hover { background: #2563eb; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 14px; color: #666; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 6px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔒 Recuperação de Senha</h1>
                </div>
                <div class='content'>
                    <h2>Olá, " . htmlspecialchars($usuario['nome_completo']) . "!</h2>
                    
                    <p>Recebemos uma solicitação para redefinir a senha da sua conta no <strong>Bingo</strong>.</p>
                    
                    <p>Se você solicitou esta alteração, clique no botão abaixo para criar uma nova senha:</p>
                    
                    <div style='text-align: center;'>
                        <a href='" . $reset_link . "' class='button'>Redefinir Minha Senha</a>
                    </div>
                    
                    <div class='warning'>
                        <strong>⚠️ Importante:</strong>
                        <ul>
                            <li>Este link expira em <strong>1 hora</strong></li>
                            <li>Use apenas se você solicitou a recuperação</li>
                            <li>Nunca compartilhe este link com outras pessoas</li>
                        </ul>
                    </div>
                    
                    <p>Se você não solicitou esta recuperação, pode ignorar este e-mail com segurança. Sua senha permanecerá inalterada.</p>
                    
                    <p><strong>Link alternativo:</strong><br>
                    <small>Se o botão não funcionar, copie e cole este link no seu navegador:</small><br>
                    <a href='" . $reset_link . "'>" . $reset_link . "</a></p>
                </div>
                <div class='footer'>
                    <p><strong>Bingo - Sistema de Jogos</strong></p>
                    <p>Este é um e-mail automático, não responda.</p>
                    <p><small>Solicitado em: " . date('d/m/Y H:i:s') . " | IP: " . $_SERVER['REMOTE_ADDR'] . "</small></p>
                </div>
            </div>
        </body>
        </html>";
        
        // Headers para e-mail HTML
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: Bingo Sistema <noreply@' . $_SERVER['HTTP_HOST'] . '>',
            'Reply-To: noreply@' . $_SERVER['HTTP_HOST'],
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // Envia o e-mail
        $emailSent = mail($email, $subject, $message, implode("\r\n", $headers));
        
        if ($emailSent) {
            error_log("E-mail de recuperação enviado com sucesso para: $email");
            echo json_encode([
                'success' => true,
                'message' => 'E-mail de recuperação enviado com sucesso!'
            ]);
        } else {
            error_log("Falha ao enviar e-mail de recuperação para: $email");
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao enviar e-mail. Tente novamente em alguns instantes.'
            ]);
        }
        
    } catch (PDOException $e) {
        error_log("Erro de banco na recuperação de senha: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erro interno do sistema. Tente novamente.'
        ]);
    } catch (Exception $e) {
        error_log("Erro geral na recuperação de senha: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erro inesperado. Tente novamente.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido.'
    ]);
}
?>