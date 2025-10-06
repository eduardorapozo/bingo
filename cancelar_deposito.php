<?php
header('Content-Type: application/json');

// Função de log
function logCancelDeposit($message, $context = []) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = [
        'timestamp' => $timestamp,
        'level' => 'INFO',
        'action' => 'cancel_deposit',
        'message' => $message,
        'context' => $context
    ];
    
    // Log estruturado em JSON
    $log_line = json_encode($log_entry, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    
    // Escreve no arquivo de log específico
    file_put_contents('cancel_deposit.log', $log_line, FILE_APPEND | LOCK_EX);
    
    // Também escreve no PHP error log para debugging local
    error_log("CANCEL_DEPOSIT: $message " . json_encode($context));
}

logCancelDeposit("Cancelar depósito iniciado", ["request_method" => $_SERVER['REQUEST_METHOD']]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
    logCancelDeposit("Sessão iniciada");
}

if (!isset($_SESSION['usuario_logado'])) {
    logCancelDeposit("Usuário não autorizado", ["session_status" => "no_user_logged"]);
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

require_once('conexao.php');

$usuario = $_SESSION['usuario_logado'];
$input_raw = file_get_contents('php://input');
$input = json_decode($input_raw, true);

logCancelDeposit("Dados recebidos", [
    "user_id" => $usuario['id'] ?? 'N/A',
    "user_name" => $usuario['nome_completo'] ?? 'N/A',
    "input_raw_length" => strlen($input_raw),
    "input_decoded" => is_array($input),
    "raw_input_preview" => substr($input_raw, 0, 200)
]);

if (!isset($input['external_id'])) {
    logCancelDeposit("External ID não fornecido", ["input_keys" => is_array($input) ? array_keys($input) : 'not_array']);
    http_response_code(400);
    echo json_encode(['error' => 'External ID não fornecido']);
    exit;
}

$external_id = $input['external_id'];

logCancelDeposit("Processando cancelamento", [
    "external_id" => $external_id,
    "user_id" => $usuario['id']
]);

try {
    logCancelDeposit("Iniciando transação de banco de dados");
    
    // Verificar estrutura da tabela depositos
    $table_info = $pdo->query("DESCRIBE depositos")->fetchAll(PDO::FETCH_ASSOC);
    $table_columns = array_column($table_info, 'Field');
    logCancelDeposit("Estrutura da tabela depositos", ["columns" => $table_columns]);
    
    $pdo->beginTransaction();
    
    // Verificar se o depósito existe e está pendente
    $search_pattern = "%{$external_id}%";
    logCancelDeposit("Buscando depósito", [
        "search_pattern" => $search_pattern,
        "user_id" => $usuario['id'],
        "sql_query" => "SELECT id FROM depositos WHERE external_id LIKE ? AND usuario_id = ? AND status = 'PENDING'"
    ]);
    
    $stmt = $pdo->prepare("SELECT id FROM depositos WHERE external_id LIKE ? AND usuario_id = ? AND status = 'PENDING'");
    $stmt->execute([$search_pattern, $usuario['id']]);
    $deposito = $stmt->fetch(PDO::FETCH_ASSOC);

    logCancelDeposit("Resultado da busca", [
        "deposito_encontrado" => !empty($deposito),
        "deposito_id" => $deposito['id'] ?? 'N/A',
        "external_id" => $external_id
    ]);

    if (!$deposito) {
        logCancelDeposit("Depósito não encontrado ou já processado", [
            "external_id" => $external_id,
            "status" => "not_found"
        ]);
        $pdo->rollBack();
        echo json_encode(['error' => 'Depósito não encontrado ou já processado']);
        exit;
    }

    // Cancelar o depósito
    logCancelDeposit("Atualizando status para CANCELLED", [
        "deposito_id" => $deposito['id'],
        "novo_status" => "CANCELLED",
        "sql_query" => "UPDATE depositos SET status = 'CANCELLED' WHERE id = ?"
    ]);
    
    $stmt_cancel = $pdo->prepare("UPDATE depositos SET status = 'CANCELLED' WHERE id = ?");
    $stmt_cancel->execute([$deposito['id']]);
    
    $rows_affected = $stmt_cancel->rowCount();
    logCancelDeposit("Status atualizado", [
        "deposito_id" => $deposito['id'],
        "rows_affected" => $rows_affected
    ]);

    $pdo->commit();
    logCancelDeposit("Transação commitada com sucesso", [
        "deposito_id" => $deposito['id'],
        "external_id" => $external_id
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Depósito cancelado com sucesso']);

} catch (Exception $e) {
    logCancelDeposit("Erro durante cancelamento", [
        "error_message" => $e->getMessage(),
        "error_file" => $e->getFile(),
        "error_line" => $e->getLine(),
        "external_id" => $external_id ?? 'N/A'
    ]);
    
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
        logCancelDeposit("Rollback realizado devido ao erro");
    }
    
    error_log("Erro ao cancelar depósito: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor']);
}
?>
