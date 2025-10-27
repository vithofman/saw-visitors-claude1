<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zapomenuté heslo - SAW Visitors</title>
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

        .forgot-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px;
        }

        .forgot-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .forgot-icon {
            font-size: 48px;
            margin-bottom: 16px;
        }

        .forgot-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .forgot-subtitle {
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

        input[type="email"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="email"]:focus {
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

        .forgot-footer {
            margin-top: 24px;
            text-align: center;
        }

        .forgot-footer a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .forgot-footer a:hover {
            text-decoration: underline;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .instructions {
            background: #f5f5f5;
            padding: 16px;
            border-radius: 8px;
            font-size: 13px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        @media (max-width: 480px) {
            .forgot-container {
                padding: 30px 20px;
            }

            .forgot-title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-header">
            <div class="forgot-icon">🔒</div>
            <div class="forgot-title">Zapomenuté heslo</div>
            <div class="forgot-subtitle">
                Zadejte svůj email a my vám pošleme odkaz pro reset hesla
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

        <?php if ( isset( $success ) && $success ) : ?>
            <div class="alert alert-success">
                <?php echo esc_html( $success ); ?>
            </div>
        <?php endif; ?>

        <?php if ( isset( $info ) && $info ) : ?>
            <div class="alert alert-info">
                <?php echo esc_html( $info ); ?>
            </div>
        <?php endif; ?>

        <?php if ( ! isset( $success ) || ! $success ) : ?>
            <div class="instructions">
                <strong>ℹ️ Jak to funguje:</strong><br>
                1. Zadejte svůj emailovou adresu<br>
                2. Obdržíte email s odkazem pro reset hesla<br>
                3. Odkaz je platný 1 hodinu<br>
                4. Po resetu se můžete přihlásit s novým heslem
            </div>

            <form method="post" action="<?php echo esc_url( $form_action ?? '' ); ?>">
                <?php wp_nonce_field( 'saw_forgot_password_' . ( $role ?? 'user' ), 'saw_nonce' ); ?>

                <div class="form-group">
                    <label for="email">Emailová adresa</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        required 
                        autocomplete="email"
                        value="<?php echo esc_attr( $email ?? '' ); ?>"
                        placeholder="vas@email.cz"
                    >
                </div>

                <input type="hidden" name="role" value="<?php echo esc_attr( $role ?? '' ); ?>">
                <input type="hidden" name="action" value="forgot_password">

                <button type="submit" class="btn btn-primary">
                    Odeslat reset odkaz
                </button>
            </form>
        <?php else : ?>
            <div class="alert alert-info">
                <strong>📧 Email byl odeslán!</strong><br>
                Zkontrolujte svou emailovou schránku a postupujte podle instrukcí v emailu.
                Pokud email neobdržíte do 5 minut, zkontrolujte spam folder.
            </div>
        <?php endif; ?>

        <div class="forgot-footer">
            <a href="<?php echo esc_url( $back_url ?? home_url() ); ?>" class="back-link">
                <span>←</span>
                <span>Zpět na přihlášení</span>
            </a>
        </div>
    </div>
</body>
</html>
<?php
/**
 * POUŽITÍ V CONTROLLERU:
 * 
 * // Admin forgot password
 * $data = array(
 *     'role' => 'admin',
 *     'form_action' => home_url( '/admin/login/?action=forgot-password' ),
 *     'back_url' => home_url( '/admin/login/' ),
 *     'error' => '', // Chybová zpráva
 *     'success' => false, // True pokud byl email úspěšně odeslán
 *     'email' => '', // Předvyplněný email
 * );
 * 
 * // Manager forgot password - použít WP systém
 * // Pro managery použít wp_lostpassword_url() a WP template
 * 
 * // Extrakce proměnných
 * extract( $data );
 * include( plugin_dir_path( __FILE__ ) . '../templates/auth/forgot-password.php' );
 * 
 * 
 * CONTROLLER LOGIKA:
 * 
 * if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
 *     // Validace nonce
 *     if ( ! wp_verify_nonce( $_POST['saw_nonce'], 'saw_forgot_password_admin' ) ) {
 *         $error = 'Bezpečnostní kontrola selhala';
 *     } else {
 *         $email = sanitize_email( $_POST['email'] );
 *         $role = sanitize_text_field( $_POST['role'] );
 *         
 *         $password_handler = new SAW_Password();
 *         $result = $password_handler->send_reset_email( $email, $role );
 *         
 *         if ( is_wp_error( $result ) ) {
 *             $error = $result->get_error_message();
 *         } else {
 *             $success = true;
 *         }
 *     }
 * }
 */
?>
