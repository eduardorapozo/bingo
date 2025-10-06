<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'hahhapjdi'; // Substitua pelo nome do seu banco de dados
$user = 'hahhapjdi';          // Substitua pelo seu usuário do banco
$password = 'hahhapjdi';        // Substitua pela sua senha do banco

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}

?>