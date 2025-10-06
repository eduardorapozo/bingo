<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_logado'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

require_once('conexao.php');

$usuario = $_SESSION['usuario_logado'];
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['external_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'External ID não fornecido']);
    exit;
}

$external_id = $input['external_id'];

try {
    // Verificar status do depósito no banco
    $stmt = $pdo->prepare("SELECT d.id, d.status, d.valor, u.saldo 
                          FROM depositos d 
                          JOIN usuarios u ON d.usuario_id = u.id 
                          WHERE d.external_id LIKE ? AND d.usuario_id = ?");
    $stmt->execute(["%{$external_id}%", $usuario['id']]);
    $deposito = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$deposito) {
        echo json_encode(['status' => 'NOT_FOUND']);
        exit;
    }

    switch ($deposito['status']) {
        case 'PAID':
            echo json_encode([
                'status' => 'PAID',
                'valor' => floatval($deposito['valor']),
                'novo_saldo' => floatval($deposito['saldo'])
            ]);
            break;
            
        case 'PENDING':
            // Verificar se foi pago recentemente (simulação de API checking)
            echo json_encode(['status' => 'PENDING']);
            break;
            
        case 'CANCELLED':
            echo json_encode(['status' => 'CANCELLED']);
            break;
            
        default:
            echo json_encode(['status' => 'UNKNOWN']);
            break;
    }

} catch (Exception $e) {
    error_log("Erro ao verificar pagamento: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}
?>