<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Bingo</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      -webkit-tap-highlight-color: transparent;
      -webkit-touch-callout: none;
      -webkit-user-select: none;
      -moz-user-select: none;
      -ms-user-select: none;
      user-select: none;
    }

    input, textarea {
      -webkit-user-select: text;
      -moz-user-select: text;
      -ms-user-select: text;
      user-select: text;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #1e293b;
      padding: 20px;
    }

    .container {
      background: white;
      border-radius: 12px;
      padding: 32px;
      width: 100%;
      max-width: 380px;
      box-shadow:
        0 4px 6px -1px rgba(0, 0, 0, 0.1),
        0 2px 4px -1px rgba(0, 0, 0, 0.06);
      border: 1px solid #f1f5f9;
    }

    .header {
      text-align: center;
      margin-bottom: 24px;
    }

    .logo {
      /* Aumento do tamanho do contêiner da logo */
      width: 150px;
      height: auto; /* Altura automática para manter a proporção */
      margin: 0 auto 16px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .logo img {
      /* A imagem agora preenche todo o contêiner */
      width: 100%;
      height: auto;
      object-fit: contain;
      border-radius: 8px;
    }

    h1 {
      color: #1e293b;
      font-size: 22px;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .subtitle {
      color: #64748b;
      font-size: 14px;
      font-weight: 400;
    }

    .form-group {
      margin-bottom: 16px;
    }

    label {
      display: block;
      color: #374151;
      font-size: 13px;
      font-weight: 500;
      margin-bottom: 6px;
    }

    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 12px 14px;
      background: white;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      color: #1f2937;
      font-size: 14px;
      font-weight: 400;
      transition: all 0.2s ease;
    }

    input::placeholder {
      color: #9ca3af;
    }

    input:focus {
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .btn-primary {
      width: 100%;
      padding: 12px 16px;
      background: linear-gradient(135deg, #3b82f6, #1d4ed8);
      border: none;
      border-radius: 6px;
      color: white;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      transition: all 0.2s ease;
      margin-bottom: 16px;
    }

    .btn-primary:hover {
      background: linear-gradient(135deg, #2563eb, #1e40af);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    .btn-primary:active {
      transform: translateY(0);
    }

    .btn-primary:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .btn-loading {
      pointer-events: none;
    }

    .btn-text {
      transition: opacity 0.3s ease;
    }

    .btn-loading .btn-text {
      opacity: 0;
    }

    .spinner {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 16px;
      height: 16px;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-top: 2px solid white;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .btn-loading .spinner {
      opacity: 1;
    }

    @keyframes spin {
      0% { transform: translate(-50%, -50%) rotate(0deg); }
      100% { transform: translate(-50%, -50%) rotate(360deg); }
    }

    .forgot-password {
      text-align: center;
      margin-bottom: 20px;
    }

    .forgot-password a {
      color: #3b82f6;
      text-decoration: none;
      font-size: 13px;
      font-weight: 500;
      transition: color 0.2s ease;
    }

    .forgot-password a:hover {
      color: #2563eb;
      text-decoration: underline;
    }

    .divider {
      margin: 20px 0;
      text-align: center;
      position: relative;
    }

    .divider::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 0;
      right: 0;
      height: 1px;
      background: #e5e7eb;
    }

    .divider span {
      background: white;
      color: #9ca3af;
      padding: 0 12px;
      font-size: 12px;
      position: relative;
    }

    .register-link {
      text-align: center;
      margin-top: 16px;
    }

    .register-link a {
      color: #6b7280;
      text-decoration: none;
      font-size: 13px;
      transition: color 0.2s ease;
    }

    .register-link a:hover {
      color: #3b82f6;
    }

    /* Toast */
    .toast {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 12px 16px;
      border-radius: 8px;
      color: white;
      font-weight: 500;
      font-size: 13px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
      transform: translateX(400px);
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      z-index: 1000;
      max-width: 300px;
    }

    .toast.show {
      transform: translateX(0);
    }

    .toast.success {
      background: #10b981;
    }

    .toast.error {
      background: #ef4444;
    }

    /* Error messages */
    .error-message {
      color: #ef4444;
      font-size: 12px;
      margin-top: 4px;
      opacity: 0;
      transform: translateY(-5px);
      transition: all 0.3s ease;
    }

    .error-message.show {
      opacity: 1;
      transform: translateY(0);
    }

    .form-group.error input {
      border-color: #ef4444;
      background: #fef2f2;
    }

    /* Responsive */
    @media (max-width: 480px) {
      .container {
        padding: 24px;
        margin: 10px;
      }

      h1 {
        font-size: 20px;
      }

      .toast {
        top: 10px;
        right: 10px;
        left: 10px;
        transform: translateY(-100px);
        max-width: none;
      }

      .toast.show {
        transform: translateY(0);
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">
        <img src="assets/images/logo.png" alt="Logo">
      </div>
      <h1>Entrar</h1>
      <p class="subtitle">Acesse sua conta</p>
    </div>

    <form action="libs/processa_login.php" method="POST" id="login-form">
      <div class="form-group">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" placeholder="seu@email.com" required>
        <div class="error-message" id="email-error"></div>
      </div>

      <div class="form-group">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" placeholder="Sua senha" required>
        <div class="error-message" id="senha-error"></div>
      </div>

      <button type="submit" class="btn-primary" id="login-btn">
        <span class="btn-text">Entrar</span>
        <span class="spinner"></span>
      </button>
    </form>

    <div class="forgot-password">
      <a href="forgot-password">Esqueceu a senha?</a>
    </div>

    <div class="divider">
      <span>ou</span>
    </div>

    <div class="register-link">
      <a href="register">Ainda não tem conta? Criar conta</a>
    </div>
  </div>

  <?php if (isset($_SESSION['login_error'])): ?>
    <div id="toast" class="toast show error"><?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?></div>
  <?php endif; ?>

  <script>
    // Elementos
    const form = document.getElementById('login-form');
    const btn = document.getElementById('login-btn');
    const toast = document.getElementById('toast');

    // Toast
    function showToast(message, type = 'success') {
      if (!toast) return;
      toast.textContent = message;
      toast.className = `toast show ${type}`;
      setTimeout(() => toast.classList.remove('show'), 3000);
    }

    // Field error
    function showFieldError(fieldId, message) {
      const field = document.getElementById(fieldId);
      const errorDiv = document.getElementById(fieldId + '-error');
      field.parentElement.classList.add('error');
      errorDiv.textContent = message;
      errorDiv.classList.add('show');
    }

    function clearFieldError(fieldId) {
      const field = document.getElementById(fieldId);
      const errorDiv = document.getElementById(fieldId + '-error');
      field.parentElement.classList.remove('error');
      errorDiv.classList.remove('show');
    }

    // Clear errors on input
    document.querySelectorAll('input').forEach(input => {
      input.addEventListener('input', () => clearFieldError(input.id));
    });

    // Auto-hide toast from PHP
    if (toast) {
      setTimeout(() => {
        toast.classList.remove('show');
      }, 4000);
    }

    // Form submission with loading state
    form.addEventListener('submit', function(e) {
      // Clear previous errors
      ['email', 'senha'].forEach(clearFieldError);
     
      // Get values
      const email = document.getElementById('email').value.trim();
      const senha = document.getElementById('senha').value;
     
      let hasError = false;

      // Basic validations
      if (!email.includes('@')) {
        showFieldError('email', 'E-mail inválido');
        hasError = true;
        e.preventDefault();
        return;
      }

      if (senha.length < 1) {
        showFieldError('senha', 'Senha obrigatória');
        hasError = true;
        e.preventDefault();
        return;
      }

      if (!hasError) {
        // Show loading state
        btn.classList.add('btn-loading');
        btn.disabled = true;
      }
    });
  </script>
</body>
</html>