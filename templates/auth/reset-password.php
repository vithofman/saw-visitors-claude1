<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset hesla - SAW Visitors</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .reset-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px;
        }

        .reset-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .reset-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .reset-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .reset-subtitle {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef5350;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #66bb6a;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }

        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .reset-footer {
            margin-top: 24px;
            text-align: center;
        }

        .reset-footer a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .reset-footer a:hover {
            text-decoration: underline;
        }

        .password-requirements {
            background: #f5f5f5;
            padding: 16px;
            border-radius: 8px;
            font-size: 13px;
            color: #666;
            margin-bottom: 24px;
        }

        .password-requirements strong {
            display: block;
            margin-bottom: 8px;
            color: #333;
        }

        .success-message {
            text-align: center;
            padding: 40px 20px;
        }

        .success-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        @media (max-width: 480px) {
            .reset-container {
                padding: 30px 20px;
            }

            .reset-title {
                font-size: 20px;
            }
        }
    </style>
    <?php include SAW_VISITORS_PLUGIN_DIR . 'includes/pwa/pwa-head-tags.php'; ?>
</head>
<body>
    <div class="reset-container">
        <?php if (isset($success) && $success): ?>
            <!-- Success State -->
            <div class="success-message">
                <div class="success-icon">✅</div>
                <div class="reset-title">Heslo bylo resetováno</div>
                <div class="reset-subtitle">
                    Vaše heslo bylo úspěšně změněno. Nyní se můžete přihlásit s novým heslem.
                </div>
                
                <div style="margin-top: 32px;">
                    <a href="<?php echo esc_url(home_url('/login/')); ?>" class="btn btn-primary">
                        Přejít na přihlášení
                    </a>
                </div>
            </div>

        <?php elseif (isset($token_invalid) && $token_invalid): ?>
            <!-- Invalid Token State -->
            <div class="reset-header">
                <div class="reset-icon">❌</div>
                <div class="reset-title">Neplatný odkaz</div>
                <div class="reset-subtitle">
                    Odkaz pro reset hesla vypršel nebo je neplatný
                </div>
            </div>

            <div class="alert alert-error">
                <strong>Neplatný nebo expirovaný odkaz</strong><br>
                Tento odkaz pro reset hesla je neplatný nebo již vypršela jeho platnost (1 hodina).
                Prosím, požádejte o nový reset hesla.
            </div>
            
            <div class="reset-footer">
                <a href="<?php echo esc_url(home_url('/forgot-password/')); ?>">
                    Požádat o nový reset hesla
                </a>
            </div>

        <?php else: ?>
            <!-- Reset Password Form -->
            <div class="reset-header">
                <div class="reset-icon">🔐</div>
                <div class="reset-title">Nastavení nového hesla</div>
                <div class="reset-subtitle">
                    Zadejte své nové heslo
                </div>
            </div>

            <?php if (isset($error) && $error): ?>
                <div class="alert alert-error">
                    <?php echo esc_html($error); ?>
                </div>
            <?php endif; ?>

            <div class="password-requirements">
                <strong>Požadavky na heslo:</strong>
                • Minimálně 8 znaků<br>
                • Alespoň 1 písmeno<br>
                • Alespoň 1 číslo
            </div>

            <form method="post" id="resetForm">
                <?php wp_nonce_field('saw_reset_password', 'saw_nonce'); ?>
                <input type="hidden" name="token" value="<?php echo esc_attr($token ?? ''); ?>">

                <div class="form-group">
                    <label for="password">Nové heslo</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="Zadejte nové heslo"
                    >
                </div>

                <div class="form-group">
                    <label for="confirm_password">Potvrzení hesla</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required 
                        minlength="8"
                        autocomplete="new-password"
                        placeholder="Zadejte heslo znovu"
                    >
                </div>

                <button type="submit" class="btn btn-primary">
                    Nastavit nové heslo
                </button>
            </form>

            <div class="reset-footer">
                <a href="<?php echo esc_url(home_url('/login/')); ?>">
                    Zpět na přihlášení
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const form = document.getElementById('resetForm');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Hesla se neshodují!');
                    return false;
                }

                if (password.length < 8) {
                    e.preventDefault();
                    alert('Heslo musí mít alespoň 8 znaků!');
                    return false;
                }
            });
        }
    </script>
</body>
</html>