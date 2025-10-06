<?php
// Este arquivo é um endpoint e não deve ter saída HTML ou visual.
require_once('conexao.php');

// Recebe o payload do webhook em formato JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Loga todo o payload recebido imediatamente para CodexPay
file_put_contents('log_codexpay_webhook.log', date('Y-m-d H:i:s') . " - Payload Recebido: " . $input . PHP_EOL, FILE_APPEND);

// Verifica se os dados são válidos para CodexPay
if (!$data || !isset($data['type'])) {
    // Registra a falha no log de erro
    error_log(date('Y-m-d H:i:s') . " - Erro: Dados inválidos do CodexPay. Input: " . $input . PHP_EOL, 3, 'error_codexpay_webhook.log');
    http_response_code(400); // Bad Request
    die('Dados inválidos');
}

try {
    $pdo->beginTransaction();

    // Processa o webhook baseado no tipo de transação CodexPay
    switch ($data['type']) {
        case 'Deposit':
            if (isset($data['status']) && $data['status'] === 'COMPLETED') {
                $transaction_id = $data['transaction_id'] ?? null;
                $amount = $data['amount'] ?? null;

                if ($transaction_id && $amount) {
                    error_log(date('Y-m-d H:i:s') . " - Debug: transaction_id recebido: '{$transaction_id}', amount: {$amount}" . PHP_EOL, 3, 'error_codexpay_webhook.log');
                    
                    // Buscar depósito pelo transaction_id_api
                    $stmt = $pdo->prepare("SELECT id, usuario_id, status, external_id, valor FROM depositos WHERE transaction_id_api = ? AND status = 'PENDING'");
                    $stmt->execute([$transaction_id]);
                    $deposito_db = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($deposito_db) {
                        $usuario_id = $deposito_db['usuario_id'];
                        
                        $stmt_user_saldo = $pdo->prepare("SELECT saldo FROM usuarios WHERE id = ?");
                        $stmt_user_saldo->execute([$usuario_id]);
                        $saldo_anterior = $stmt_user_saldo->fetchColumn();
                        $novo_saldo = $saldo_anterior + $amount;

                        // Atualiza o saldo do usuário na tabela 'usuarios'
                        $stmt_user = $pdo->prepare("UPDATE usuarios SET saldo = ? WHERE id = ?");
                        $stmt_user->execute([$novo_saldo, $usuario_id]);
                        
                        // Atualiza o status na tabela 'depositos'
                        $stmt_deposito = $pdo->prepare("UPDATE depositos SET status = 'PAID' WHERE id = ?");
                        $stmt_deposito->execute([$deposito_db['id']]);

                        // Insere um registro na tabela 'transacoes' para histórico financeiro
                        $descricao_transacao = "Depósito via PIX (transaction_id: {$transaction_id})";
                        $stmt_transacao = $pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, saldo_anterior, saldo_atual, descricao) VALUES (?, 'deposito', ?, ?, ?, ?)");
                        $stmt_transacao->execute([$usuario_id, $amount, $saldo_anterior, $novo_saldo, $descricao_transacao]);
                        
                        error_log(date('Y-m-d H:i:s') . " - Sucesso: Depósito {$deposito_db['id']} confirmado. Saldo atualizado para R$ " . number_format($novo_saldo, 2, ',', '.') . PHP_EOL, 3, 'error_codexpay_webhook.log');

                    } else {
                        error_log(date('Y-m-d H:i:s') . " - Aviso: Depósito não encontrado com transaction_id: {$transaction_id}." . PHP_EOL, 3, 'error_codexpay_webhook.log');
                    }
                }
            }
            break;

        case 'Withdrawal':
            $transaction_id = $data['transaction_id'] ?? null;
            $status = $data['status'] ?? null;

            if ($transaction_id && $status) {
                // Buscar saque pelo transaction_id_api
                $stmt = $pdo->prepare("SELECT id, usuario_id, valor, status FROM saques WHERE transaction_id_api = ? AND status = 'PENDING'");
                $stmt->execute([$transaction_id]);
                $saque_db = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($saque_db) {
                    $usuario_id = $saque_db['usuario_id'];
                    $valor_saque = $saque_db['valor'];
                    $status_saque = ($status === 'COMPLETED') ? 'PAID' : 'FAILED';

                    // Atualiza o status do saque na tabela 'saques'
                    $stmt_update_saque = $pdo->prepare("UPDATE saques SET status = ?, data_conclusao = NOW() WHERE id = ?");
                    $stmt_update_saque->execute([$status_saque, $saque_db['id']]);
                    
                    if ($status_saque === 'FAILED') {
                        // Se falhou, estorna o valor (reembolsa) para o saldo do usuário
                        $stmt_user_saldo = $pdo->prepare("SELECT saldo FROM usuarios WHERE id = ?");
                        $stmt_user_saldo->execute([$usuario_id]);
                        $saldo_anterior = $stmt_user_saldo->fetchColumn();
                        $novo_saldo = $saldo_anterior + $valor_saque; // Estorno

                        $stmt_user = $pdo->prepare("UPDATE usuarios SET saldo = ? WHERE id = ?");
                        $stmt_user->execute([$novo_saldo, $usuario_id]);

                        $descricao_transacao = "Estorno de saque falhado (ID saque: {$saque_db['id']})";
                        $stmt_transacao = $pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, saldo_anterior, saldo_atual, descricao) VALUES (?, 'bonus', ?, ?, ?, ?)");
                        $stmt_transacao->execute([$usuario_id, $valor_saque, $saldo_anterior, $novo_saldo, $descricao_transacao]);
                        
                        error_log(date('Y-m-d H:i:s') . " - Falha: Saque {$saque_db['id']} falhou. Estorno de R$ " . number_format($valor_saque, 2, ',', '.') . " realizado." . PHP_EOL, 3, 'error_codexpay_webhook.log');

                    } else {
                        // Se foi pago, a dedução já ocorreu na solicitação inicial. Apenas logamos a conclusão.
                        error_log(date('Y-m-d H:i:s') . " - Sucesso: Saque {$saque_db['id']} concluído e pago." . PHP_EOL, 3, 'error_codexpay_webhook.log');
                    }
                } else {
                    error_log(date('Y-m-d H:i:s') . " - Aviso: Saque com transaction_id {$transaction_id} não encontrado ou já foi processado." . PHP_EOL, 3, 'error_codexpay_webhook.log');
                }
            }
            break;
    }

    $pdo->commit();
    http_response_code(200);
    echo "OK";

} catch (Exception $e) {
    $pdo->rollBack();
    error_log(date('Y-m-d H:i:s') . " - Erro Fatal: " . $e->getMessage() . PHP_EOL, 3, 'error_codexpay_webhook.log');
    http_response_code(500);
    die("Erro interno do servidor");
}
?>