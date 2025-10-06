<?php
session_start();

// Verifica se a sessão do usuário está ativa
if (!isset($_SESSION['usuario_logado'])) {
    header('Location: ../login.php');
    exit;
}

require_once('../conexao.php');

$usuario = $_SESSION['usuario_logado'];

// Busca os dados do usuário, incluindo a flag de admin
$stmt = $pdo->prepare("SELECT is_admin FROM usuarios WHERE id = ?");
$stmt->execute([$usuario['id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);

// Se o usuário não for um admin, redireciona de volta para a página principal
if (!$user_data || $user_data['is_admin'] != 1) {
    header('Location: ../main.php');
    exit;
}

// Se o usuário é um admin, redireciona para a página de listagem de usuários
header('Location: user_list.php');
exit;