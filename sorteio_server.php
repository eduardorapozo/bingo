<?php
session_start();
require_once('../conexao.php');

if (!isset($_SESSION['usuario_logado'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Não autorizado.']));
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Recebe os dados via JSON
$input = json_decode(file_get_contents('php://input'), true);
$sala_id = $input['sala_id'] ?? null;

if (!$sala_id) {
    die(json_encode(['error' => 'ID da sala não fornecido.']));
}

// Busca a sala específica
$stmt = $pdo->prepare("SELECT * FROM salas_bingo WHERE id = ? AND status = 'em_andamento' LIMIT 1");
$stmt->execute([$sala_id]);
$sala = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sala) {
    die(json_encode(['error' => 'Sala não encontrada ou não está em andamento.']));
}

$numeros_sorteados = json_decode($sala['numeros_sorteados'], true) ?: [];

// Verifica se já foi marcado como bingo fechado
$stmt_bingo_check = $pdo->prepare("SELECT COUNT(*) FROM transacoes WHERE tipo = 'premio_bingo' AND sala_id = ? AND descricao LIKE '%Bingo fechado%'");
$stmt_bingo_check->execute([$sala_id]);
$bingo_ja_fechado = $stmt_bingo_check->fetchColumn() > 0;

if ($bingo_ja_fechado || $sala['status'] === 'finalizado') {
    die(json_encode(['status' => 'bingo_fechado', 'numero' => end($numeros_sorteados)]));
}

// Define meta de bolas para esta sala (entre 25-45 bolas)
$meta_key = 'meta_bolas_' . $sala_id;
if (!isset($_SESSION[$meta_key])) {
    $_SESSION[$meta_key] = mt_rand(25, 45);
}
$meta_bolas = $_SESSION[$meta_key];

// Verifica se atingiu a meta de bolas
if (count($numeros_sorteados) >= $meta_bolas) {
    // Finaliza o jogo
    try {
        $pdo->beginTransaction();
        
        // Marca sala como finalizada
        $stmt_finalizar = $pdo->prepare("UPDATE salas_bingo SET status = 'finalizado', fim_jogo = NOW() WHERE id = ?");
        $stmt_finalizar->execute([$sala_id]);
        
        // Marca como bingo fechado no sistema
        $stmt_bingo_transacao = $pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, saldo_anterior, saldo_atual, descricao, sala_id, created_at) VALUES (1, 'premio_bingo', 0, 0, 0, 'Jogo encerrado - Bingo fechado', ?, NOW())");
        $stmt_bingo_transacao->execute([$sala_id]);
        
        $pdo->commit();
        
        // Limpa a meta da sessão
        unset($_SESSION[$meta_key]);
        
        die(json_encode(['status' => 'bingo_fechado', 'numero' => end($numeros_sorteados)]));
        
    } catch (Exception $e) {
        $pdo->rollBack();
        die(json_encode(['error' => 'Erro ao finalizar jogo: ' . $e->getMessage()]));
    }
}

// Sorteia próximo número
$numeros_disponiveis = array_diff(range(1, 75), $numeros_sorteados);

if (empty($numeros_disponiveis)) {
    // Se não há mais números, finaliza
    try {
        $pdo->beginTransaction();
        
        $stmt_finalizar = $pdo->prepare("UPDATE salas_bingo SET status = 'finalizado', fim_jogo = NOW() WHERE id = ?");
        $stmt_finalizar->execute([$sala_id]);
        
        $stmt_bingo_transacao = $pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, saldo_anterior, saldo_atual, descricao, sala_id, created_at) VALUES (1, 'premio_bingo', 0, 0, 0, 'Jogo encerrado - Números esgotados', ?, NOW())");
        $stmt_bingo_transacao->execute([$sala_id]);
        
        $pdo->commit();
        unset($_SESSION[$meta_key]);
        
        die(json_encode(['status' => 'bingo_fechado', 'numero' => end($numeros_sorteados)]));
        
    } catch (Exception $e) {
        $pdo->rollBack();
        die(json_encode(['error' => 'Erro ao finalizar jogo: ' . $e->getMessage()]));
    }
}

// Sorteia novo número
$newNumber = $numeros_disponiveis[array_rand($numeros_disponiveis)];
$numeros_sorteados[] = $newNumber;

// Atualiza no banco
try {
    $stmt_update = $pdo->prepare("UPDATE salas_bingo SET numeros_sorteados = ?, updated_at = NOW() WHERE id = ?");
    $stmt_update->execute([json_encode($numeros_sorteados), $sala_id]);
    
    // Verifica se AGORA atingiu a meta
    if (count($numeros_sorteados) >= $meta_bolas) {
        // Finaliza IMEDIATAMENTE após este número
        $pdo->beginTransaction();
        
        $stmt_finalizar = $pdo->prepare("UPDATE salas_bingo SET status = 'finalizado', fim_jogo = NOW() WHERE id = ?");
        $stmt_finalizar->execute([$sala_id]);
        
        $stmt_bingo_transacao = $pdo->prepare("INSERT INTO transacoes (usuario_id, tipo, valor, saldo_anterior, saldo_atual, descricao, sala_id, created_at) VALUES (1, 'premio_bingo', 0, 0, 0, 'Jogo encerrado - Bingo fechado', ?, NOW())");
        $stmt_bingo_transacao->execute([$sala_id]);
        
        $pdo->commit();
        unset($_SESSION[$meta_key]);
        
        echo json_encode([
            'numero' => $newNumber, 
            'status' => 'bingo_fechado',
            'total_bolas' => count($numeros_sorteados),
            'meta_atingida' => true
        ]);
    } else {
        // Jogo continua normalmente
        echo json_encode([
            'numero' => $newNumber,
            'total_bolas' => count($numeros_sorteados),
            'meta_bolas' => $meta_bolas
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar números: ' . $e->getMessage()]);
}
?>