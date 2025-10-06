<?php
session_start();

// Debug: Log da tentativa de acesso
error_log("processa_register.php acessado - Método: " . $_SERVER['REQUEST_METHOD']);

require_once('../conexao.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log dos dados recebidos
    error_log("Dados POST recebidos: " . print_r($_POST, true));
    
    $nome_completo = trim($_POST['nome_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf = preg_replace('/[^0-9]/', '', trim($_POST['cpf'] ?? '')); // Remove pontos e hífen
    $senha = $_POST['senha'] ?? '';

    // Debug: Log dos dados processados
    error_log("Dados processados - Nome: $nome_completo, Email: $email, CPF: $cpf, Senha: " . (empty($senha) ? 'vazia' : 'preenchida'));

    // Validações básicas
    if (empty($nome_completo) || empty($email) || empty($cpf) || empty($senha)) {
        $error_msg = 'Por favor, preencha todos os campos.';
        error_log("Erro de validação: $error_msg");
        $_SESSION['register_error'] = $error_msg;
        header("Location: ../register.php");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'E-mail inválido.';
        error_log("Erro de validação: $error_msg - Email: $email");
        $_SESSION['register_error'] = $error_msg;
        header("Location: ../register.php");
        exit;
    }

    if (strlen($cpf) !== 11) {
        $error_msg = 'O CPF deve conter exatamente 11 dígitos.';
        error_log("Erro de validação: $error_msg - CPF: $cpf (tamanho: " . strlen($cpf) . ")");
        $_SESSION['register_error'] = $error_msg;
        header("Location: ../register.php");
        exit;
    }

    // Validação de CPF mais robusta
    function validarCPF($cpf) {
        // Verifica se o CPF tem 11 dígitos
        if (strlen($cpf) != 11) return false;
        
        // Verifica se não é uma sequência de números iguais
        if (preg_match('/(\d)\1{10}/', $cpf)) return false;
        
        // Calcula primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += intval($cpf[$i]) * (10 - $i);
        }
        $resto = $soma % 11;
        $dv1 = ($resto < 2) ? 0 : 11 - $resto;
        
        // Calcula segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += intval($cpf[$i]) * (11 - $i);
        }
        $resto = $soma % 11;
        $dv2 = ($resto < 2) ? 0 : 11 - $resto;
        
        // Verifica se os dígitos calculados conferem
        return ($dv1 == intval($cpf[9]) && $dv2 == intval($cpf[10]));
    }

    if (!validarCPF($cpf)) {
        $error_msg = 'CPF inválido.';
        error_log("Erro de validação: $error_msg - CPF: $cpf");
        $_SESSION['register_error'] = $error_msg;
        header("Location: ../register.php");
        exit;
    }

    if (strlen($senha) < 8) {
        $error_msg = 'A senha deve ter no mínimo 8 caracteres.';
        error_log("Erro de validação: $error_msg - Tamanho da senha: " . strlen($senha));
        $_SESSION['register_error'] = $error_msg;
        header("Location: ../register.php");
        exit;
    }

    // Debug: Tentativa de conexão com BD
    error_log("Tentando conectar com o banco de dados...");

    // Criptografa a senha
    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    try {
        // Debug: Verificando conexão PDO
        if (!$pdo) {
            throw new Exception("Conexão PDO não estabelecida");
        }
        
        error_log("Conexão PDO estabelecida. Verificando duplicatas...");
        
        // Verifica se e-mail ou CPF já existem
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE email = ? OR cpf = ?");
        $stmt->execute([$email, $cpf]);
        $count = $stmt->fetchColumn();
        
        error_log("Verificação de duplicatas - Count: $count");
        
        if ($count > 0) {
            $error_msg = 'E-mail ou CPF já cadastrados.';
            error_log("Erro: $error_msg");
            $_SESSION['register_error'] = $error_msg;
            header("Location: ../register.php");
            exit;
        }

        error_log("Inserindo novo usuário...");

        // Insere o novo usuário
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome_completo, email, cpf, senha, data_cadastro) VALUES (?, ?, ?, ?, NOW())");
        $result = $stmt->execute([$nome_completo, $email, $cpf, $senha_hash]);
        
        error_log("Resultado da inserção: " . ($result ? 'sucesso' : 'falha'));

        // Pega o ID do usuário recém-criado
        $novo_usuario_id = $pdo->lastInsertId();
        error_log("Novo usuário ID: $novo_usuario_id");

        // Busca os dados do novo usuário para criar a sessão
        $stmt = $pdo->prepare("SELECT id, nome_completo, email FROM usuarios WHERE id = ?");
        $stmt->execute([$novo_usuario_id]);
        $novo_usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Dados do novo usuário: " . print_r($novo_usuario, true));

        if ($novo_usuario) {
            // Cria a sessão de login automático
            $_SESSION['usuario_logado'] = [
                'id' => $novo_usuario['id'],
                'nome_completo' => $novo_usuario['nome_completo'],
                'email' => $novo_usuario['email']
            ];

            error_log("Sessão criada. Redirecionando para main.php");
            
            // Define mensagem de sucesso para o main.php
            $_SESSION['welcome_message'] = 'Conta criada com sucesso! Bem-vindo(a), ' . $novo_usuario['nome_completo'] . '!';
            
            // Redireciona diretamente para o dashboard principal
            header("Location: ../main.php");
            exit;
        } else {
            $error_msg = 'Erro ao criar a sessão. Tente fazer login manualmente.';
            error_log("Erro: $error_msg");
            $_SESSION['register_error'] = $error_msg;
            header("Location: ../login.php");
            exit;
        }

    } catch (PDOException $e) {
        // Log do erro para debug
        error_log("Erro PDO: " . $e->getMessage());
        error_log("Código do erro: " . $e->getCode());
        
        // Verifica se é erro de duplicata
        if ($e->getCode() == 23000) {
            $_SESSION['register_error'] = 'E-mail ou CPF já cadastrados.';
        } else {
            $_SESSION['register_error'] = 'Erro interno do servidor: ' . $e->getMessage();
        }
        
        header("Location: ../register.php");
        exit;
    } catch (Exception $e) {
        error_log("Erro geral: " . $e->getMessage());
        $_SESSION['register_error'] = 'Erro interno: ' . $e->getMessage();
        header("Location: ../register.php");
        exit;
    }
} else {
    // Acesso direto ao arquivo não é permitido
    error_log("Acesso direto negado - Método: " . $_SERVER['REQUEST_METHOD']);
    header("Location: ../register.php");
    exit;
}
?>