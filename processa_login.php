<?php

require_once('../conexao.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $senha = $_POST['senha'];

    if (empty($email) || empty($senha)) {
        $_SESSION['login_error'] = "Por favor, preencha todos os campos.";
        header("Location: ../login.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_logado'] = [
                'id' => $usuario['id'],
                'nome_completo' => $usuario['nome_completo']
            ];
            header("Location: ../main.php");
            exit;
        } else {
            $_SESSION['login_error'] = "E-mail ou senha inválidos.";
            header("Location: ../login.php");
            exit;
        }
    } catch (PDOException $e) {
        error_log("Erro de login: " . $e->getMessage());
        $_SESSION['login_error'] = "Ocorreu um erro no servidor. Tente novamente.";
        header("Location: ../login.php");
        exit;
    }
} else {
    header("Location: ../login.php");
    exit;
}

?>