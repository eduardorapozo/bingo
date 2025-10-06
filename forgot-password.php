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

    .info-box {
      background: #f0f9ff;
      border: 1px solid #bae6fd;
      border-radius: 6px;
      padding: 12px;
      margin-bottom: 20px;
      color: #0c4a6e;
      font-size: 13px;
      line-height: 1.4;
    }

    .info-box strong {
      color: #0369a1;
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

    .back-links {
      text-align: center;
      margin-top: 16px;
    }

    .back-links a {
      color: #6b7280;
      text-decoration: none;
      font-size: 13px;
      transition: color 0.2s ease;
      margin: 0 8px;
    }

    .back-links a:hover {
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

    /* Success state */
    .success-state {
      text-align: center;
      padding: 20px 0;
    }

    .success-icon {
      width: 60px;
      height: 60px;
      background: linear-gradient(135deg, #10b981, #059669);
      border-radius: 50%;
      margin: 0 auto 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: white;
    }

    .success-title {
      font-size: 18px;
      font-weight: 600;
      color: #1e293b;
      margin-bottom: 8px;
    }

    .success-message {
      color: #64748b;
      font-size: 14px;
      line-height: 1.5;
      margin-bottom: 20px;
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

      .back-links a {
        display: block;
        margin: 8px 0;
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
      <h1>Recuperar Senha</h1>
      <p class="subtitle">Digite seu e-mail para receber as instruções de recuperação</p>
    </div>

    <div id="form-container">
      <div class="info-box">
        <strong>Como funciona:</strong> Enviaremos um link seguro para seu e-mail com instruções para criar uma nova senha.
      </div>

      <form id="forgot-form" action="libs/processa_forgot_password.php" method="POST">
        <div class="form-group">
          <label for="email">E-mail cadastrado</label>
          <input type="email" id="email" name="email" placeholder="seu@email.com" required>
          <div class="error-message" id="email-error"></div>
        </div>

        <button type="submit" class="btn-primary" id="forgot-btn">
          <span class="btn-text">Enviar Link de Recuperação</span>
          <span class="spinner"></span>
        </button>
      </form>
    </div>

    <div class="divider">
      <span>ou</span>
    </div>

    <div class="back-links">
      <a href="login">Voltar ao Login</a>
      <a href="register">Criar Nova Conta</a>
    </div>
  </div>

  <?php if (isset($_SESSION['forgot_error'])): ?>
    <div id="toast" class="toast show error"><?php echo $_SESSION['forgot_error']; unset($_SESSION['forgot_error']); ?></div>
  <?php endif; ?>

  <script>
    // Elements
    const form = document.getElementById('forgot-form');
    const btn = document.getElementById('forgot-btn');
    const formContainer = document.getElementById('form-container');
    const phpToast = document.getElementById('toast');

    // Toast function - simplified
    function showToast(message, type = 'success') {
      // Remove existing toast
      const existingToast = document.getElementById('dynamic-toast');
      if (existingToast) {
        existingToast.remove();
      }
     
      // Create new toast
      const toast = document.createElement('div');
      toast.id = 'dynamic-toast';
      toast.className = `toast show ${type}`;
      toast.textContent = message;
      document.body.appendChild(toast);
     
      // Auto hide after 3 seconds
      setTimeout(() => {
        if (toast && toast.parentNode) {
          toast.classList.remove('show');
          setTimeout(() => {
            if (toast && toast.parentNode) {
              toast.remove();
            }
          }, 300);
        }
      }, 3000);
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

    // Success state
    function showSuccessState(email) {
      formContainer.innerHTML = `
        <div class="success-state">
          <div class="success-icon">✓</div>
          <div class="success-title">E-mail Enviado!</div>
          <div class="success-message">
            Enviamos as instruções de recuperação para<br>
            <strong>${email}</strong><br><br>
            Verifique sua caixa de entrada e spam.<br>
            O link expira em 1 hora.
          </div>
          <button class="btn-primary" onclick="window.location.reload()">
            Enviar Novamente
          </button>
        </div>
      `;
    }

    // Auto-hide PHP toast
    if (phpToast && phpToast.classList.contains('show')) {
      setTimeout(() => {
        phpToast.classList.remove('show');
      }, 4000);
    }

    // Clear errors on input
    document.getElementById('email').addEventListener('input', () => {
      clearFieldError('email');
    });

    // Form submission
    form.addEventListener('submit', function(e) {
      e.preventDefault(); // SEMPRE previne redirecionamento
     
      clearFieldError('email');
     
      const email = document.getElementById('email').value.trim();
     
      // Validation
      if (!email.includes('@') || email.length < 6) {
        showFieldError('email', 'E-mail inválido');
        return;
      }
     
      // Show loading
      btn.classList.add('btn-loading');
      btn.disabled = true;
      showToast('Enviando e-mail de recuperação...', 'info');
     
      // Submit via AJAX with better error handling
      const formData = new FormData(form);
     
      fetch('libs/processa_forgot_password.php', {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => response.text())
      .then(text => {
        try {
          const data = JSON.parse(text);
          if (data.success) {
            showToast('E-mail de recuperação enviado!', 'success');
            setTimeout(() => {
              showSuccessState(email);
            }, 1500);
          } else {
            showToast(data.message || 'Erro desconhecido', 'error');
            btn.classList.remove('btn-loading');
            btn.disabled = false;
          }
        } catch (parseError) {
          console.error('JSON parse error:', parseError);
          showToast('Erro na resposta do servidor', 'error');
          btn.classList.remove('btn-loading');
          btn.disabled = false;
        }
      })
      .catch(error => {
        console.error('Fetch error:', error);
        showToast('Erro de conexão: ' + error.message, 'error');
        btn.classList.remove('btn-loading');
        btn.disabled = false;
      });
    });
  </script>
</body>
</html>