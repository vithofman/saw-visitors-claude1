<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
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

        .role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 12px;
        }

        .role-admin {
            background: #e3f2fd;
            color: #1976d2;
        }

        .role-manager {
            background: #f3e5f5;
            color: #7b1fa2;
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

        .alert-info {
            background: #e3f2fd;
            color: #1565c0;
            border: 1px solid #42a5f5;
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
            text-transform: uppercase;
            letter-spacing: 0.5px;
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

        .password-requirements ul {
            margin-left: 20px;
            margin-top: 8px;
        }

        .password-requirements li {
            margin-bottom: 4px;
        }

        .password-strength {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 8px;
            overflow: hidden;
        }

        .password-strength-bar {
            height: 100%;
            width: 0;
            transition: all 0.3s;
            border-radius: 2px;
        }

        .strength-weak {
            background: #f44336;
            width: 33%;
        }

        .strength-medium {
            background: #ff9800;
            width: 66%;
        }

        .strength-strong {
            background: #4caf50;
            width: 100%;
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
</head>
<body>
    <div class="reset-container">
        <?php if ( isset( $success ) && $success ) : ?>
            <!-- Úspěšný reset -->
            <div class="success-message">
                <div class="success-icon">✅</div>
                <div class="reset-title">Heslo bylo resetováno</div>
                <div class="reset-subtitle">
                    Vaše heslo bylo úspěšně změněno. Nyní se můžete přihlásit s novým heslem.
                </div>
                
                <div style="margin-top: 32px;">
                    <a href="<?php echo esc_url( $login_url ?? home_url() ); ?>" class="btn btn-primary">
                        Přejít na přihlášení
                    </a>
                </div>
            </div>
        <?php else : ?>
            <!-- Formulář pro reset -->
            <div class="reset-header">
                <div class="reset-icon">🔑</div>
                <div class="reset-title">Nastavení nového hesla</div>
                <div class="reset-subtitle">
                    Zadejte své nové heslo
                </div>
                
                <?php if ( isset( $role ) ) : ?>
                    <span class="role-badge role-<?php echo esc_attr( $role ); ?>">
                        <?php
                        $role_names = array(
                            'admin'   => 'Administrátor',
                            'manager' => 'Manažer',
                        );
                        echo esc_html( $role_names[ $role ] ?? $role );
                        ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ( isset( $error ) && $error ) : ?>
                <div class="alert alert-error">
                    <?php echo esc_html( $error ); ?>
                </div>
            <?php endif; ?>

            <?php if ( isset( $token_invalid ) && $token_invalid ) : ?>
                <div class="alert alert-error">
                    <strong>❌ Neplatný nebo expirovaný odkaz</strong><br>
                    Tento odkaz pro reset hesla je neplatný nebo již vypršela jeho platnost (1 hodina).
                    Prosím, požádejte o nový reset hesla.
                </div>
                
                <div class="reset-footer">
                    <a href="<?php echo esc_url( $forgot_password_url ?? '#' ); ?>">
                        Požádat o nový reset hesla
                    </a>
                </div>
            <?php else : ?>
                <div class="password-requirements">
                    <strong>🔒 Požadavky na heslo:</strong>
                    <ul>
                        <li>Minimálně 12 znaků</li>
                        <li>Alespoň 1 velké písmeno (A-Z)</li>
                        <li>Alespoň 1 malé písmeno (a-z)</li>
                        <li>Alespoň 1 číslice (0-9)</li>
                        <li>Alespoň 1 speciální znak (!@#$%^&*)</li>
                    </ul>
                </div>

                <form method="post" action="<?php echo esc_url( $form_action ?? '' ); ?>" id="resetForm">
                    <?php wp_nonce_field( 'saw_reset_password_' . ( $role ?? 'user' ), 'saw_nonce' ); ?>

                    <div class="form-group">
                        <label for="new_password">Nové heslo</label>
                        <input 
                            type="password" 
                            id="new_password" 
                            name="new_password" 
                            required 
                            autocomplete="new-password"
                            placeholder="Zadejte nové heslo"
                            minlength="12"
                        >
                        <div class="password-strength">
                            <div class="password-strength-bar" id="strengthBar"></div>
                        </div>
                        <small id="strengthText" style="color: #999; font-size: 12px;"></small>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Potvrzení hesla</label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            required 
                            autocomplete="new-password"
                            placeholder="Zadejte heslo znovu"
                            minlength="12"
                        >
                    </div>

                    <input type="hidden" name="token" value="<?php echo esc_attr( $token ?? '' ); ?>">
                    <input type="hidden" name="role" value="<?php echo esc_attr( $role ?? '' ); ?>">
                    <input type="hidden" name="action" value="reset_password">

                    <button type="submit" class="btn btn-primary">
                        Nastavit nové heslo
                    </button>
                </form>

                <div class="reset-footer">
                    <a href="<?php echo esc_url( $login_url ?? home_url() ); ?>">
                        Zpět na přihlášení
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        // Password strength checker
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const form = document.getElementById('resetForm');

        if (newPasswordInput && strengthBar) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                const strength = calculatePasswordStrength(password);
                
                strengthBar.className = 'password-strength-bar';
                
                if (strength.score === 0) {
                    strengthBar.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
                    strengthText.textContent = '';
                } else if (strength.score < 3) {
                    strengthBar.classList.add('strength-weak');
                    strengthText.textContent = 'Slabé heslo';
                    strengthText.style.color = '#f44336';
                } else if (strength.score < 5) {
                    strengthBar.classList.add('strength-medium');
                    strengthText.textContent = 'Střední heslo';
                    strengthText.style.color = '#ff9800';
                } else {
                    strengthBar.classList.add('strength-strong');
                    strengthText.textContent = 'Silné heslo';
                    strengthText.style.color = '#4caf50';
                }
            });
        }

        // Form validation
        if (form) {
            form.addEventListener('submit', function(e) {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;

                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Hesla se neshodují!');
                    return false;
                }

                if (newPassword.length < 12) {
                    e.preventDefault();
                    alert('Heslo musí mít alespoň 12 znaků!');
                    return false;
                }
            });
        }

        function calculatePasswordStrength(password) {
            let score = 0;
            
            if (password.length >= 12) score++;
            if (password.length >= 16) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;
            
            return { score: score, max: 6 };
        }
    </script>
</body>
</html>
<?php
/**
 * POUŽITÍ V CONTROLLERU:
 * 
 * // Reset password page
 * $token = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';
 * $role = isset( $_GET['role'] ) ? sanitize_text_field( $_GET['role'] ) : '';
 * 
 * $password_handler = new SAW_Password();
 * 
 * // Validace tokenu
 * $user_id = $password_handler->validate_reset_token( $token );
 * 
 * if ( ! $user_id ) {
 *     $data = array(
 *         'role' => $role,
 *         'token_invalid' => true,
 *         'forgot_password_url' => home_url( '/' . $role . '/login/?action=forgot-password' ),
 *         'login_url' => home_url( '/' . $role . '/login/' ),
 *     );
 * } else {
 *     // Token je platný
 *     if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
 *         // Zpracování formuláře
 *         if ( ! wp_verify_nonce( $_POST['saw_nonce'], 'saw_reset_password_' . $role ) ) {
 *             $error = 'Bezpečnostní kontrola selhala';
 *         } else {
 *             $new_password = $_POST['new_password'];
 *             $confirm_password = $_POST['confirm_password'];
 *             
 *             if ( $new_password !== $confirm_password ) {
 *                 $error = 'Hesla se neshodují';
 *             } else {
 *                 $result = $password_handler->reset_password( $token, $new_password );
 *                 
 *                 if ( is_wp_error( $result ) ) {
 *                     $error = $result->get_error_message();
 *                 } else {
 *                     $success = true;
 *                 }
 *             }
 *         }
 *     }
 *     
 *     $data = array(
 *         'role' => $role,
 *         'token' => $token,
 *         'form_action' => add_query_arg( array( 'action' => 'reset_password', 'token' => $token, 'role' => $role ), home_url() ),
 *         'login_url' => home_url( '/' . $role . '/login/' ),
 *         'error' => $error ?? '',
 *         'success' => $success ?? false,
 *     );
 * }
 * 
 * extract( $data );
 * include( plugin_dir_path( __FILE__ ) . '../templates/auth/reset-password.php' );
 */
?>
