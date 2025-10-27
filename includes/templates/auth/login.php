<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $page_title ?? 'Přihlášení' ); ?> - SAW Visitors</title>
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

        .login-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 420px;
            padding: 40px;
        }

        .login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .login-logo {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: #666;
            font-size: 14px;
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

        .role-terminal {
            background: #e8f5e9;
            color: #388e3c;
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

        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="text"]:focus {
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

        .login-footer {
            margin-top: 24px;
            text-align: center;
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }

        .login-footer a:hover {
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
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e0e0e0;
        }

        .divider span {
            background: white;
            padding: 0 10px;
            position: relative;
            color: #999;
            font-size: 12px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .remember-me input {
            margin-right: 8px;
        }

        .remember-me label {
            margin: 0;
            font-weight: 400;
            cursor: pointer;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            .login-logo {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-logo">SAW Visitors</div>
            <div class="login-subtitle">Správa návštěv</div>
            
            <?php if ( isset( $role ) ) : ?>
                <span class="role-badge role-<?php echo esc_attr( $role ); ?>">
                    <?php
                    $role_names = array(
                        'admin'    => 'Administrátor',
                        'manager'  => 'Manažer',
                        'terminal' => 'Terminál',
                    );
                    echo esc_html( $role_names[ $role ] ?? $role );
                    ?>
                </span>
            <?php endif; ?>
        </div>

        <?php
        // Zobrazení chybových/úspěšných zpráv
        if ( isset( $error ) && $error ) :
        ?>
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

        <form method="post" action="<?php echo esc_url( $form_action ?? '' ); ?>">
            <?php wp_nonce_field( 'saw_login_' . ( $role ?? 'user' ), 'saw_nonce' ); ?>

            <div class="form-group">
                <label for="email">Email</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    required 
                    autocomplete="username"
                    value="<?php echo esc_attr( $email ?? '' ); ?>"
                    placeholder="vas@email.cz"
                >
            </div>

            <div class="form-group">
                <label for="password">
                    <?php echo $role === 'terminal' ? 'PIN' : 'Heslo'; ?>
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    autocomplete="current-password"
                    placeholder="<?php echo $role === 'terminal' ? 'Zadejte PIN' : 'Zadejte heslo'; ?>"
                >
            </div>

            <?php if ( $role !== 'terminal' ) : ?>
                <div class="remember-me">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <label for="remember">Zapamatovat přihlášení</label>
                </div>
            <?php endif; ?>

            <input type="hidden" name="role" value="<?php echo esc_attr( $role ?? '' ); ?>">
            <input type="hidden" name="redirect_to" value="<?php echo esc_url( $redirect_to ?? '' ); ?>">

            <button type="submit" class="btn btn-primary">
                Přihlásit se
            </button>
        </form>

        <?php if ( $role !== 'terminal' ) : ?>
            <div class="login-footer">
                <a href="<?php echo esc_url( $forgot_password_url ?? '#' ); ?>">
                    Zapomněli jste heslo?
                </a>
            </div>
        <?php endif; ?>

        <?php if ( $role === 'admin' && isset( $show_other_roles ) && $show_other_roles ) : ?>
            <div class="divider">
                <span>nebo</span>
            </div>
            <div class="login-footer">
                <a href="<?php echo esc_url( home_url( '/manager/login/' ) ); ?>">
                    Přihlásit se jako manažer
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
/**
 * POUŽITÍ V CONTROLLERU:
 * 
 * // Admin login
 * $data = array(
 *     'page_title' => 'Přihlášení administrátora',
 *     'role' => 'admin',
 *     'form_action' => home_url( '/admin/login/' ),
 *     'forgot_password_url' => home_url( '/admin/login/?action=forgot-password' ),
 *     'redirect_to' => home_url( '/admin/dashboard/' ),
 *     'error' => '', // Chybová zpráva
 *     'success' => '', // Úspěšná zpráva
 *     'info' => '', // Informační zpráva
 *     'email' => '', // Předvyplněný email
 *     'show_other_roles' => true, // Zobrazit odkazy na jiné role
 * );
 * include( plugin_dir_path( __FILE__ ) . '../templates/auth/login.php' );
 * 
 * // Manager login
 * $data = array(
 *     'page_title' => 'Přihlášení manažera',
 *     'role' => 'manager',
 *     'form_action' => home_url( '/manager/login/' ),
 *     'forgot_password_url' => wp_lostpassword_url(), // WP systém!
 *     'redirect_to' => home_url( '/manager/dashboard/' ),
 * );
 * 
 * // Terminal login
 * $data = array(
 *     'page_title' => 'Přihlášení terminálu',
 *     'role' => 'terminal',
 *     'form_action' => home_url( '/terminal/login/' ),
 *     'redirect_to' => home_url( '/terminal/checkin/' ),
 * );
 * 
 * // Extrakce proměnných do šablony
 * extract( $data );
 * include( $template_path );
 */
?>
