<?php
require_once('conexao.php');

// External ID que veio no webhook
$external_id_webhook = "68d708707443f";

echo "<h3>Debug: Verificando External ID</h3>";
echo "<p><strong>External ID do webhook:</strong> {$external_id_webhook}</p>";

// 1. Buscar por LIKE
echo "<h4>1. Busca por LIKE (contém):</h4>";
$stmt = $pdo->prepare("SELECT id, usuario_id, external_id, status, data_criacao FROM depositos WHERE external_id LIKE ?");
$stmt->execute(["%{$external_id_webhook}%"]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($results) {
    foreach ($results as $row) {
        echo "<p>✅ Encontrado: ID {$row['id']}, External ID: {$row['external_id']}, Status: {$row['status']}</p>";
    }
} else {
    echo "<p>❌ Nenhum registro encontrado com LIKE</p>";
}

// 2. Buscar por igualdade exata
echo "<h4>2. Busca exata:</h4>";
$stmt = $pdo->prepare("SELECT id, usuario_id, external_id, status, data_criacao FROM depositos WHERE external_id = ?");
$stmt->execute([$external_id_webhook]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    echo "<p>✅ Encontrado: ID {$result['id']}, External ID: {$result['external_id']}, Status: {$result['status']}</p>";
} else {
    echo "<p>❌ Nenhum registro encontrado com busca exata</p>";
}

// 3. Mostrar todos os depósitos pendentes
echo "<h4>3. Todos os depósitos pendentes:</h4>";
$stmt = $pdo->prepare("SELECT id, usuario_id, external_id, valor, status, data_criacao FROM depositos WHERE status = 'PENDING' ORDER BY data_criacao DESC");
$stmt->execute();
$pendentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($pendentes) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>ID</th><th>Usuário</th><th>External ID</th><th>Valor</th><th>Data Criação</th></tr>";
    foreach ($pendentes as $row) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['usuario_id']}</td>";
        echo "<td>{$row['external_id']}</td>";
        echo "<td>R$ " . number_format($row['valor'], 2, ',', '.') . "</td>";
        echo "<td>{$row['data_criacao']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>❌ Nenhum depósito pendente encontrado</p>";
}

// 4. Verificar padrão dos external_ids
echo "<h4>4. Análise do padrão:</h4>";
echo "<p>External ID do webhook: <code>{$external_id_webhook}</code> (tamanho: " . strlen($external_id_webhook) . ")</p>";

if ($pendentes) {
    foreach ($pendentes as $row) {
        $stored_id = $row['external_id'];
        echo "<p>ID armazenado: <code>{$stored_id}</code> (tamanho: " . strlen($stored_id) . ")";
        
        // Verificar se contém o ID do webhook
        if (strpos($stored_id, $external_id_webhook) !== false) {
            echo " - ✅ <strong>CONTÉM o ID do webhook!</strong>";
        } else {
            echo " - ❌ Não contém o ID do webhook";
        }
        echo "</p>";
    }
}
?>