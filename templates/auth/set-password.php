<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nastavení hesla - SAW Visitors</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 480px;
            width: 100%;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 32px;
        }

        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
        }

        h1 {
            font-size: 24px;
            color: #111827;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #6b7280;
            font-size: 14px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #93c5fd;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .password-requirements {
            margin-top: 8px;
            font-size: 12px;
            color: #6b7280;
        }

        .requirement {
            padding: 4px 0;
        }

        .requirement.valid {
            color: #059669;
        }

        .requirement.valid::before {
            content: "✓ ";
        }

        .requirement.invalid::before {
            content: "○ ";
            color: #9ca3af;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .footer a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .success-content {
            text-align: center;
        }

        .success-content .icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }

            h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($token_invalid): ?>
            <!-- Invalid Token -->
            <div class="header">
                <div class="icon">❌</div>
                <h1>Neplatný odkaz</h1>
                <div class="subtitle">Odkaz pro nastavení hesla vypršel nebo je neplatný</div>
            </div>

            <div class="alert alert-error">
                Odkaz je již neplatný. Platnost odkazu je 7 dní od vytvoření účtu.
            </div>

            <div class="footer">
                <p style="color: #6b7280; margin-bottom: 12px;">
                    Potřebujete pomoc?
                </p>
                <a href="mailto:support@sawvisitors.com">Kontaktujte podporu</a>
            </div>

        <?php elseif ($success): ?>
            <!-- Success -->
            <div class="success-content">
                <div class="icon">✓</div>
                <h1>Heslo nastaveno!</h1>
                <div class="subtitle" style="margin-bottom: 24px;">
                    Nyní se můžete přihlásit
                </div>

                <div class="alert alert-success">
                    Vaše heslo bylo úspěšně nastaveno. Můžete se přihlásit pomocí svého emailu a hesla.
                </div>

                <a href="<?php echo home_url('/login/'); ?>" class="btn">
                    Přejít na přihlášení
                </a>
            </div>

        <?php else: ?>
            <!-- Set Password Form -->
            <div class="header">
                <div class="icon">🔐</div>
                <h1>Nastavte si heslo</h1>
                <div class="subtitle">
                    Vítejte, <?php echo esc_html($user['user_email'] ?? ''); ?>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo esc_html($error); ?>
                </div>
            <?php endif; ?>

            <form method="post" id="set-password-form">
                <?php wp_nonce_field('saw_set_password', 'saw_nonce'); ?>

                <div class="form-group">
                    <label for="password">Nové heslo</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        minlength="8"
                        autocomplete="new-password"
                    >
                    <div class="password-requirements">
                        <div class="requirement" id="req-length">Alespoň 8 znaků</div>
                        <div class="requirement" id="req-letter">Obsahuje písmeno</div>
                        <div class="requirement" id="req-number">Obsahuje číslo</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Potvrďte heslo</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required 
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="btn" id="submit-btn">
                    Nastavit heslo
                </button>
            </form>

            <div class="footer">
                <a href="<?php echo home_url('/login/'); ?>">
                    Zpět na přihlášení
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submit-btn');

        // Real-time password validation
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                
                // Length
                const reqLength = document.getElementById('req-length');
                if (password.length >= 8) {
                    reqLength.classList.remove('invalid');
                    reqLength.classList.add('valid');
                } else {
                    reqLength.classList.remove('valid');
                    reqLength.classList.add('invalid');
                }
                
                // Letter
                const reqLetter = document.getElementById('req-letter');
                if (/[a-zA-Z]/.test(password)) {
                    reqLetter.classList.remove('invalid');
                    reqLetter.classList.add('valid');
                } else {
                    reqLetter.classList.remove('valid');
                    reqLetter.classList.add('invalid');
                }
                
                // Number
                const reqNumber = document.getElementById('req-number');
                if (/[0-9]/.test(password)) {
                    reqNumber.classList.remove('invalid');
                    reqNumber.classList.add('valid');
                } else {
                    reqNumber.classList.remove('valid');
                    reqNumber.classList.add('invalid');
                }
                
                validateForm();
            });
        }

        if (confirmInput) {
            confirmInput.addEventListener('input', validateForm);
        }

        function validateForm() {
            const password = passwordInput?.value || '';
            const confirm = confirmInput?.value || '';
            
            const isValid = password.length >= 8 && 
                          /[a-zA-Z]/.test(password) && 
                          /[0-9]/.test(password) &&
                          password === confirm;
            
            if (submitBtn) {
                submitBtn.disabled = !isValid;
            }
        }

        // Initial validation
        validateForm();
    </script>
</body>
</html>