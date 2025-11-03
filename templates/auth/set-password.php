<?php
/**
 * Set Password Template
 * 
 * Umožňuje novému uživateli nastavit si heslo poprvé po registraci.
 * URL: /set-password/?token=xyz
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ================================================
// ZPRACOVÁNÍ FORMULÁŘE (POST)
// ================================================

$error = '';
$success = false;
$user = null;

// Získej token z URL
$token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

if (empty($token)) {
    $error = 'Chybí token pro nastavení hesla.';
} else {
    // Načti SAW_Password handler
    if (!class_exists('SAW_Password')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-password.php';
    }
    
    $password_handler = new SAW_Password();
    
    // Validuj token a získej uživatele
    $user = $password_handler->validate_setup_token($token);
    
    if (!$user) {
        $error = 'Odkaz je neplatný nebo již expiroval. Platnost odkazu je 7 dní.';
    }
}

// Pokud je formulář odeslán (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $user) {
    
    // Ověř nonce
    if (!isset($_POST['saw_nonce']) || !wp_verify_nonce($_POST['saw_nonce'], 'saw_set_password')) {
        $error = 'Bezpečnostní kontrola selhala. Zkuste to znovu.';
    } else {
        
        // Získej hesla z formuláře
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
        
        // Validace
        if (empty($password)) {
            $error = 'Prosím vyplňte heslo.';
        } elseif (strlen($password) < 8) {
            $error = 'Heslo musí mít alespoň 8 znaků.';
        } elseif (!preg_match('/[a-zA-Z]/', $password)) {
            $error = 'Heslo musí obsahovat alespoň jedno písmeno.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $error = 'Heslo musí obsahovat alespoň jedno číslo.';
        } elseif ($password !== $confirm_password) {
            $error = 'Hesla se neshodují.';
        } else {
            // Vše je v pořádku - nastav heslo
            $result = $password_handler->set_password($token, $password);
            
            if (is_wp_error($result)) {
                $error = $result->get_error_message();
            } else {
                // Úspěch!
                $success = true;
                
                // Audit log
                if (class_exists('SAW_Audit')) {
                    SAW_Audit::log([
                        'action' => 'password_set_success',
                        'user_id' => $user['id'],
                        'customer_id' => $user['customer_id'] ?? null, // ✅ FIXED
                        'details' => 'Uživatel ' . $user['user_email'] . ' si úspěšně nastavil heslo',
                    ]);
                }
            }
        }
    }
}

// ================================================
// HTML VÝSTUP
// ================================================

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nastavte si heslo - SAW Visitors</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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
            font-size: 64px;
            margin-bottom: 16px;
        }

        h1 {
            color: #1f2937;
            font-size: 28px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #6b7280;
            font-size: 14px;
        }

        .alert {
            padding: 16px;
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

        .btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn:active:not(:disabled) {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .footer {
            text-align: center;
            margin-top: 24px;
            font-size: 14px;
        }

        .footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        .success-icon {
            font-size: 72px;
            text-align: center;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <?php if ($success): ?>
            <!-- Úspěšné nastavení hesla -->
            <div class="success-icon">✅</div>
            
            <div class="header">
                <h1>Heslo bylo nastaveno!</h1>
                <div class="subtitle">
                    Nyní se můžete přihlásit
                </div>
            </div>

            <div class="alert alert-success">
                <strong>Úspěch!</strong><br>
                Vaše heslo bylo úspěšně nastaveno.<br>
                Můžete se přihlásit pomocí svého emailu a hesla.
            </div>

            <a href="<?php echo home_url('/login/'); ?>" class="btn">
                Přejít na přihlášení
            </a>

        <?php elseif ($error): ?>
            <!-- Chyba (neplatný token, apod.) -->
            <div class="header">
                <div class="icon">⚠️</div>
                <h1>Nelze nastavit heslo</h1>
            </div>

            <div class="alert alert-error">
                <?php echo esc_html($error); ?>
            </div>

            <div class="footer">
                <a href="<?php echo home_url('/login/'); ?>">
                    Zpět na přihlášení
                </a>
            </div>

        <?php else: ?>
            <!-- Formulář pro nastavení hesla -->
            <div class="header">
                <div class="icon">🔐</div>
                <h1>Nastavte si heslo</h1>
                <div class="subtitle">
                    Vítejte, <?php echo esc_html($user['user_email'] ?? ''); ?>
                </div>
            </div>

            <form method="post" id="set-password-form">
                <?php wp_nonce_field('saw_set_password', 'saw_nonce'); ?>
                
                <!-- Hidden input pro zachování tokenu při POST -->
                <input type="hidden" name="token" value="<?php echo esc_attr($token); ?>">

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
                        <div class="requirement invalid" id="req-length">Alespoň 8 znaků</div>
                        <div class="requirement invalid" id="req-letter">Obsahuje písmeno</div>
                        <div class="requirement invalid" id="req-number">Obsahuje číslo</div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Potvrďte heslo</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required 
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>

                <button type="submit" class="btn" id="submit-btn" disabled>
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

    <?php if (!$success && !$error): ?>
    <!-- JavaScript pro real-time validaci -->
    <script>
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submit-btn');

        // Real-time password validation
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                
                // Length check
                const reqLength = document.getElementById('req-length');
                if (password.length >= 8) {
                    reqLength.classList.remove('invalid');
                    reqLength.classList.add('valid');
                } else {
                    reqLength.classList.remove('valid');
                    reqLength.classList.add('invalid');
                }
                
                // Letter check
                const reqLetter = document.getElementById('req-letter');
                if (/[a-zA-Z]/.test(password)) {
                    reqLetter.classList.remove('invalid');
                    reqLetter.classList.add('valid');
                } else {
                    reqLetter.classList.remove('valid');
                    reqLetter.classList.add('invalid');
                }
                
                // Number check
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
        
        // Před odesláním formuláře zobrazit loading stav
        document.getElementById('set-password-form')?.addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Nastavuji heslo...';
        });
    </script>
    <?php endif; ?>
</body>
</html>
<?php
// KRITICKÉ: Zastav další vykonávání kódu!
exit;
?>