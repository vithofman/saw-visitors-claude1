<?php
/**
 * Login Template
 * 
 * Přihlašovací formulář pro SAW Visitors
 * URL: /login/
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ================================================
// ZPRACOVÁNÍ PŘIHLÁŠENÍ (POST)
// ================================================

$error = '';
$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw($_GET['redirect_to']) : home_url('/admin/');

// Pokud je už přihlášen, přesměruj
if (is_user_logged_in()) {
    wp_redirect($redirect_to);
    exit;
}

// Pokud je formulář odeslán (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Ověř nonce
    if (!isset($_POST['saw_login_nonce']) || !wp_verify_nonce($_POST['saw_login_nonce'], 'saw_login')) {
        $error = 'Bezpečnostní kontrola selhala. Zkuste to znovu.';
    } else {
        
        // Získej údaje z formuláře
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
        
        // Validace
        if (empty($email)) {
            $error = 'Prosím vyplňte email.';
        } elseif (empty($password)) {
            $error = 'Prosím vyplňte heslo.';
        } else {
            
            // WordPress přihlášení
            $credentials = [
                'user_login'    => $email,
                'user_password' => $password,
                'remember'      => $remember,
            ];
            
            $user = wp_signon($credentials, is_ssl());
            
            if (is_wp_error($user)) {
                $error = 'Nesprávný email nebo heslo.';
                
                // Audit log - failed login
                if (class_exists('SAW_Audit')) {
                    global $wpdb;
                    $wpdb->insert(
                        $wpdb->prefix . 'saw_audit_log',
                        [
                            'customer_id' => null,
                            'user_id' => null,
                            'action' => 'login_failed',
                            'entity_type' => 'auth',
                            'entity_id' => null,
                            'old_values' => null,
                            'new_values' => wp_json_encode(['email' => $email]),
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                            'created_at' => current_time('mysql'),
                        ],
                        ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
                    );
                }
            } else {
                // Úspěch! Přihlášen
                
                // Načti SAW user data
                global $wpdb;
                $saw_user = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                    $user->ID
                ), ARRAY_A);
                
                if ($saw_user) {
                    // Start session
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    
                    // Ulož SAW data do session
                    $_SESSION['saw_user_id'] = $saw_user['id'];
                    $_SESSION['saw_role'] = $saw_user['role'];
                    $_SESSION['saw_customer_id'] = $saw_user['customer_id'];
                    $_SESSION['saw_branch_id'] = $saw_user['branch_id'];
                    
                    // Update last_login
                    $wpdb->update(
                        $wpdb->prefix . 'saw_users',
                        ['last_login' => current_time('mysql')],
                        ['id' => $saw_user['id']],
                        ['%s'],
                        ['%d']
                    );
                    
                    // Audit log - success
                    $wpdb->insert(
                        $wpdb->prefix . 'saw_audit_log',
                        [
                            'customer_id' => $saw_user['customer_id'],
                            'user_id' => $saw_user['id'],
                            'action' => 'login_success',
                            'entity_type' => 'auth',
                            'entity_id' => $saw_user['id'],
                            'old_values' => null,
                            'new_values' => wp_json_encode(['user_id' => $saw_user['id'], 'role' => $saw_user['role']]),
                            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                            'created_at' => current_time('mysql'),
                        ],
                        ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
                    );
                }
                
                // Přesměruj podle role
                if ($saw_user) {
                    switch ($saw_user['role']) {
                        case 'super_admin':
                        case 'admin':
                            $redirect_to = home_url('/admin/');
                            break;
                        case 'super_manager':
                        case 'manager':
                            $redirect_to = home_url('/manager/');
                            break;
                        case 'terminal':
                            $redirect_to = home_url('/terminal/');
                            break;
                        default:
                            $redirect_to = home_url('/admin/');
                    }
                }
                
                wp_redirect($redirect_to);
                exit;
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
    <title>Přihlášení - SAW Visitors</title>
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
            max-width: 420px;
            width: 100%;
            padding: 40px;
        }

        .header {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo {
            font-size: 48px;
            margin-bottom: 16px;
        }

        h1 {
            color: #1f2937;
            font-size: 24px;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #6b7280;
            font-size: 14px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 14px;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
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

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin-right: 8px;
            cursor: pointer;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: 400;
            cursor: pointer;
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

        .btn:active {
            transform: translateY(0);
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
    </style>
    <?php include SAW_VISITORS_PLUGIN_DIR . 'includes/pwa/pwa-head-tags.php'; ?>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🔐</div>
            <h1>SAW Visitors</h1>
            <div class="subtitle">Správa návštěv</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo esc_html($error); ?>
            </div>
        <?php endif; ?>

        <form method="post" id="login-form">
            <?php wp_nonce_field('saw_login', 'saw_login_nonce'); ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    autocomplete="email"
                    value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>"
                    placeholder="vas@email.cz"
                >
            </div>

            <div class="form-group">
                <label for="password">Heslo</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    autocomplete="current-password"
                    placeholder="••••••••"
                >
            </div>

            <div class="checkbox-group">
                <input 
                    type="checkbox" 
                    id="remember" 
                    name="remember" 
                    value="1"
                >
                <label for="remember">Zapamatovat přihlášení</label>
            </div>

            <button type="submit" class="btn">
                Přihlásit se
            </button>
        </form>

        <div class="footer">
            <a href="<?php echo home_url('/reset-password/'); ?>">
                Zapomněli jste heslo?
            </a>
        </div>
    </div>

    <script>
        // Před odesláním formuláře zobrazit loading stav
        document.getElementById('login-form')?.addEventListener('submit', function(e) {
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Přihlašuji...';
        });
    </script>
</body>
</html>
<?php
// KRITICKÉ: Zastav další vykonávání kódu!
exit;
?>