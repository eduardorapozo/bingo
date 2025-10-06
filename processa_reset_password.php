<?php
session_start();
require_once('../conexao.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';
    
    // Log para debug
    error_log("Tentativa de redefinição de senha com token: " . substr($token, 0, 10) . "...");
    
    // Validações básicas
    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Token não fornecido.']);
        exit;
    }
    
    if (empty($senha) || empty($confirma_senha)) {
        echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
        exit;
    }
    
    if (strlen($senha) < 8) {
        echo json_encode(['success' => false, 'message' => 'A senha deve ter no mínimo 8 caracteres.']);
        exit;
    }
    
    if ($senha !== $confirma_senha) {
        echo json_encode(['success' => false, 'message' => 'As senhas não coincidem.']);
        exit;
    }
    
    try {
        // Verifica se o token é válido e não expirado
        $stmt = $pdo->prepare("
            SELECT pr.*, u.id as user_id, u.nome_completo, u.email 
            FROM password_resets pr 
            JOIN usuarios u ON pr.user_id = u.id 
            WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used_at IS NULL
        ");
        $stmt->execute([$token]);
        $reset_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reset_data) {
            error_log("Token inválido ou expirado: $token");
            echo json_encode(['success' => false, 'message' => 'Token inválido ou expirado.']);
            exit;
        }
        
        // Verifica se a nova senha é diferente da atual (opcional)
        $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmt->execute([$reset_data['user_id']]);
        $current_password_hash = $stmt->fetchColumn();
        
        if (password_verify($senha, $current_password_hash)) {
            echo json_encode(['success' => false, 'message' => 'A nova senha deve ser diferente da senha atual.']);
            exit;
        }
        
        // Inicia transação
        $pdo->beginTransaction();
        
        try {
            // Atualiza a senha do usuário
            $nova_senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmt->execute([$nova_senha_hash, $reset_data['user_id']]);
            
            // Marca o token como usado
            $stmt = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
            $stmt->execute([$reset_data['id']]);
            
            // Remove todos os outros tokens não utilizados deste usuário (por segurança)
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ? AND id != ?");
            $stmt->execute([$reset_data['user_id'], $reset_data['id']]);
            
            // Confirma transação
            $pdo->commit();
            
            error_log("Senha redefinida com sucesso para usuário ID: " . $reset_data['user_id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Senha redefinida com sucesso! Você será redirecionado para o login.'
            ]);
            
        } catch (PDOException $e) {
            // Rollback em caso de erro
            $pdo->rollback();
            throw $e;
        }
        
    } catch (PDOException $e) {
        error_log("Erro de banco na redefinição de senha: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Erro interno do sistema. Tente novamente.'
        ]);
    } catch (Exception $e) {
        error_log("Erro geral na redefinição de senha: " . $e->getMessage());
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