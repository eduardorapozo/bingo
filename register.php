<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cadastro - Bingo</title>
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

    input[type="text"],
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

    .checkbox-group {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      margin-bottom: 20px;
      padding: 12px 0;
      cursor: pointer;
    }

    .custom-checkbox {
      position: relative;
      flex-shrink: 0;
      width: 16px;
      height: 16px;
      margin-top: 2px;
      cursor: pointer;
    }

    .custom-checkbox input[type="checkbox"] {
      position: absolute;
      opacity: 0;
      width: 100%;
      height: 100%;
      margin: 0;
      cursor: pointer;
    }

    .checkmark {
      position: absolute;
      top: 0;
      left: 0;
      height: 16px;
      width: 16px;
      background: white;
      border: 1px solid #d1d5db;
      border-radius: 3px;
      transition: all 0.2s ease;
      cursor: pointer;
    }

    .custom-checkbox input:checked ~ .checkmark {
      background: #3b82f6;
      border-color: #3b82f6;
    }

    .checkmark:after {
      content: "";
      position: absolute;
      display: none;
      left: 5px;
      top: 2px;
      width: 3px;
      height: 7px;
      border: solid white;
      border-width: 0 1.5px 1.5px 0;
      transform: rotate(45deg);
    }

    .custom-checkbox input:checked ~ .checkmark:after {
      display: block;
    }

    .checkbox-text {
      color: #6b7280;
      font-size: 13px;
      line-height: 1.4;
      cursor: pointer;
    }

    .checkbox-text a {
      color: #3b82f6;
      text-decoration: none;
      font-weight: 500;
    }

    .checkbox-text a:hover {
      text-decoration: underline;
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

    .login-link {
      text-align: center;
      margin-top: 16px;
    }

    .login-link a {
      color: #6b7280;
      text-decoration: none;
      font-size: 13px;
      transition: color 0.2s ease;
    }

    .login-link a:hover {
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

    /* Modal */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
      background: white;
      margin: 5% auto;
      padding: 24px;
      border-radius: 12px;
      width: 90%;
      max-width: 500px;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    }

    .close {
      color: #9ca3af;
      float: right;
      font-size: 24px;
      font-weight: bold;
      cursor: pointer;
      line-height: 1;
    }

    .close:hover {
      color: #374151;
    }

    .modal h2 {
      color: #1f2937;
      margin-bottom: 16px;
      font-size: 20px;
      font-weight: 600;
    }

    .modal p {
      color: #6b7280;
      line-height: 1.5;
      margin-bottom: 12px;
      font-size: 14px;
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
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="logo">
        <img src="assets/images/logo.png" alt="Logo">
      </div>
      <h1>Criar Conta</h1>
      <p class="subtitle">Entre no jogo agora</p>
    </div>

    <form id="register-form" action="libs/processa_register.php" method="POST">
      <div class="form-group">
        <label for="nome_completo">Nome Completo</label>
        <input type="text" id="nome_completo" name="nome_completo" placeholder="Seu nome completo" required>
        <div class="error-message" id="nome_completo-error"></div>
      </div>

      <div class="form-group">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" placeholder="seu@email.com" required>
        <div class="error-message" id="email-error"></div>
      </div>

      <div class="form-group">
        <label for="cpf">CPF</label>
        <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00" required maxlength="14">
        <div class="error-message" id="cpf-error"></div>
      </div>

      <div class="form-group">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" placeholder="Mínimo 8 caracteres" required minlength="8">
        <div class="error-message" id="senha-error"></div>
      </div>

      <div class="checkbox-group" onclick="toggleCheckbox()">
        <div class="custom-checkbox">
          <input type="checkbox" id="termos" name="termos" required>
          <span class="checkmark"></span>
        </div>
        <div class="checkbox-text">
          Aceito os <a href="#" id="open-terms" onclick="event.stopPropagation()">Termos de Uso</a> e <a href="#" id="open-privacy" onclick="event.stopPropagation()">Política de Privacidade</a>
        </div>
      </div>

      <button type="submit" class="btn-primary" id="register-btn">
        <span class="btn-text">Criar Conta</span>
        <span class="spinner"></span>
      </button>
    </form>

    <div class="divider">
      <span>ou</span>
    </div>

    <div class="login-link">
      <a href="login">Já tem conta? Faça login</a>
    </div>
  </div>

    <div id="terms-modal" class="modal">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h2>Termos de Uso</h2>
      <p>Ao criar uma conta em nossa plataforma de bingo, você concorda com os seguintes termos:</p>
      <p><strong>1. Elegibilidade:</strong> Você deve ter pelo menos 18 anos de idade para usar nossos serviços.</p>
      <p><strong>2. Responsabilidade:</strong> Você é responsável por manter suas informações de login seguras.</p>
      <p><strong>3. Jogo Responsável:</strong> Incentivamos o jogo responsável e oferecemos ferramentas de controle.</p>
      <p><strong>4. Privacidade:</strong> Seus dados pessoais são protegidos conforme nossa política de privacidade.</p>
      <p>Ao prosseguir, você confirma que leu e aceita estes termos.</p>
    </div>
  </div>

  <?php if (isset($_SESSION['register_error'])): ?>
    <div id="toast" class="toast show error"><?php echo $_SESSION['register_error']; unset($_SESSION['register_error']); ?></div>
  <?php endif; ?>

  <script>
    // Elements
    const form = document.getElementById('register-form');
    const btn = document.getElementById('register-btn');
    const cpfInput = document.getElementById('cpf');
    const modal = document.getElementById('terms-modal');
    const openTerms = document.getElementById('open-terms');
    const openPrivacy = document.getElementById('open-privacy');
    const closeModal = document.querySelector('.close');
    const toast = document.getElementById('toast');

    // CPF formatting
    function formatCPF(value) {
      return value
        .replace(/\D/g, '')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d)/, '$1.$2')
        .replace(/(\d{3})(\d{1,2})/, '$1-$2')
        .replace(/(-\d{2})\d+?$/, '$1');
    }

    // CPF validation
    function isValidCPF(cpf) {
      cpf = cpf.replace(/[^\d]+/g, '');
      if (cpf.length !== 11 || /^(\d)\1+$/.test(cpf)) return false;
     
      let soma = 0;
      for (let i = 0; i < 9; i++) {
        soma += parseInt(cpf.charAt(i)) * (10 - i);
      }
      let resto = 11 - (soma % 11);
      if (resto === 10 || resto === 11) resto = 0;
      if (resto !== parseInt(cpf.charAt(9))) return false;
     
      soma = 0;
      for (let i = 0; i < 10; i++) {
        soma += parseInt(cpf.charAt(i)) * (11 - i);
      }
      resto = 11 - (soma % 11);
      if (resto === 10 || resto === 11) resto = 0;
      return resto === parseInt(cpf.charAt(10));
    }

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

    // CPF input
    cpfInput.addEventListener('input', (e) => {
      e.target.value = formatCPF(e.target.value);
      if (e.target.value.length >= 14) {
        if (isValidCPF(e.target.value)) {
          clearFieldError('cpf');
        } else {
          showFieldError('cpf', 'CPF inválido');
        }
      }
    });

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

    // Modal
    [openTerms, openPrivacy].forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        modal.style.display = 'block';
      });
    });

    closeModal.addEventListener('click', () => {
      modal.style.display = 'none';
    });

    window.addEventListener('click', (e) => {
      if (e.target === modal) modal.style.display = 'none';
    });

    // Fix checkbox functionality
    function toggleCheckbox() {
      const checkbox = document.getElementById('termos');
      checkbox.checked = !checkbox.checked;
    }

    document.getElementById('termos').addEventListener('change', function(e) {
      console.log('Checkbox changed:', e.target.checked);
    });

    // Form submission
    form.addEventListener('submit', async (e) => {
      // Clear previous errors
      ['nome_completo', 'email', 'cpf', 'senha'].forEach(clearFieldError);
     
      // Get values
      const nome = document.getElementById('nome_completo').value.trim();
      const email = document.getElementById('email').value.trim();
      const cpf = document.getElementById('cpf').value.trim();
      const senha = document.getElementById('senha').value;
      const termos = document.getElementById('termos').checked;
     
      let hasError = false;

      // Validations
      if (nome.length < 3) {
        showFieldError('nome_completo', 'Nome muito curto');
        hasError = true;
      }

      if (!email.includes('@')) {
        showFieldError('email', 'E-mail inválido');
        hasError = true;
      }

      if (!isValidCPF(cpf)) {
        showFieldError('cpf', 'CPF inválido');
        hasError = true;
      }

      if (senha.length < 8) {
        showFieldError('senha', 'Mínimo 8 caracteres');
        hasError = true;
      }

      if (!termos) {
        showToast('Aceite os termos para continuar', 'error');
        hasError = true;
      }

      if (hasError) {
        e.preventDefault();
        return;
      }

      // Show loading state
      btn.classList.add('btn-loading');
      btn.disabled = true;
     
      // Let form submit naturally to PHP
    });
  </script>
</body>
</html>