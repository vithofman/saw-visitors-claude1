<?php
/**
 * SAW Login Controller
 * 
 * Controller pro všechny login/logout operace.
 * Používá blank-template.php wrapper pro správné zobrazení.
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/includes/controllers
 * @since      4.6.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAW_Login_Controller {

    /**
     * Auth handler
     */
    private $auth;

    /**
     * Password handler
     */
    private $password;

    /**
     * Constructor
     */
    public function __construct() {
        $this->auth = new SAW_Auth();
        $this->password = new SAW_Password();
    }

    /**
     * Admin login handler
     * 
     * URL: /admin/login/
     */
    public function admin_login() {
        // Pokud je již přihlášen, redirect na dashboard
        if ( $this->auth->check_auth() && $this->auth->is_admin() ) {
            wp_safe_redirect( home_url( '/admin/dashboard/' ) );
            exit;
        }

        $error = '';
        $success = '';
        $email = '';

        // Forgot password akce
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'forgot-password' ) {
            return $this->forgot_password( 'admin' );
        }

        // Reset password akce
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'reset_password' && isset( $_GET['token'] ) ) {
            return $this->reset_password( 'admin', $_GET['token'] );
        }

        // Zpracování přihlášení
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            // Validace nonce
            if ( ! wp_verify_nonce( $_POST['saw_nonce'] ?? '', 'saw_login_admin' ) ) {
                $error = __( 'Bezpečnostní kontrola selhala. Zkuste to znovu.', 'saw-visitors' );
            } else {
                // Sanitizace vstupů
                $email = sanitize_email( $_POST['email'] ?? '' );
                $password = $_POST['password'] ?? '';

                // Přihlášení
                $result = $this->auth->login( $email, $password, 'admin' );

                if ( is_wp_error( $result ) ) {
                    $error = $result->get_error_message();
                } else {
                    // Úspěch - redirect
                    $redirect_url = $result['redirect_url'];

                    // Použít redirect_to pokud je zadáno
                    if ( ! empty( $_GET['redirect_to'] ) ) {
                        $redirect_url = esc_url_raw( $_GET['redirect_to'] );
                    }

                    wp_safe_redirect( $redirect_url );
                    exit;
                }
            }
        }

        // Zobrazení šablony
        $this->render_with_wrapper( 'auth/login', array(
            'page_title'           => __( 'Přihlášení administrátora', 'saw-visitors' ),
            'role'                 => 'admin',
            'form_action'          => home_url( '/admin/login/' ),
            'forgot_password_url'  => home_url( '/admin/login/?action=forgot-password' ),
            'redirect_to'          => $_GET['redirect_to'] ?? home_url( '/admin/dashboard/' ),
            'nonce_action'         => 'saw_login_admin',
            'error'                => $error,
            'success'              => $success,
            'email'                => $email,
        ) );
    }

    /**
     * Manager login handler
     * 
     * URL: /manager/login/
     */
    public function manager_login() {
        // Pokud je již přihlášen, redirect na dashboard
        if ( $this->auth->check_auth() && $this->auth->is_manager() ) {
            wp_safe_redirect( home_url( '/manager/dashboard/' ) );
            exit;
        }

        $error = '';
        $success = '';
        $email = '';

        // Forgot password akce
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'forgot-password' ) {
            return $this->forgot_password( 'manager' );
        }

        // Reset password akce
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'reset_password' && isset( $_GET['token'] ) ) {
            return $this->reset_password( 'manager', $_GET['token'] );
        }

        // Zpracování přihlášení
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            // Validace nonce
            if ( ! wp_verify_nonce( $_POST['saw_nonce'] ?? '', 'saw_login_manager' ) ) {
                $error = __( 'Bezpečnostní kontrola selhala. Zkuste to znovu.', 'saw-visitors' );
            } else {
                // Sanitizace vstupů
                $email = sanitize_email( $_POST['email'] ?? '' );
                $password = $_POST['password'] ?? '';

                // Přihlášení
                $result = $this->auth->login( $email, $password, 'manager' );

                if ( is_wp_error( $result ) ) {
                    $error = $result->get_error_message();
                } else {
                    // Úspěch - redirect
                    $redirect_url = $result['redirect_url'];

                    if ( ! empty( $_GET['redirect_to'] ) ) {
                        $redirect_url = esc_url_raw( $_GET['redirect_to'] );
                    }

                    wp_safe_redirect( $redirect_url );
                    exit;
                }
            }
        }

        // Zobrazení šablony
        $this->render_with_wrapper( 'auth/login', array(
            'page_title'           => __( 'Přihlášení manažera', 'saw-visitors' ),
            'role'                 => 'manager',
            'form_action'          => home_url( '/manager/login/' ),
            'forgot_password_url'  => home_url( '/manager/login/?action=forgot-password' ),
            'redirect_to'          => $_GET['redirect_to'] ?? home_url( '/manager/dashboard/' ),
            'nonce_action'         => 'saw_login_manager',
            'error'                => $error,
            'success'              => $success,
            'email'                => $email,
        ) );
    }

    /**
     * Terminal login handler
     * 
     * URL: /terminal/login/
     */
    public function terminal_login() {
        // Pokud je již přihlášen, redirect na check-in
        if ( $this->auth->check_auth() && $this->auth->is_terminal() ) {
            wp_safe_redirect( home_url( '/terminal/checkin/' ) );
            exit;
        }

        $error = '';
        $success = '';
        $email = '';

        // Zpracování přihlášení
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            // Validace nonce
            if ( ! wp_verify_nonce( $_POST['saw_nonce'] ?? '', 'saw_login_terminal' ) ) {
                $error = __( 'Bezpečnostní kontrola selhala. Zkuste to znovu.', 'saw-visitors' );
            } else {
                // Sanitizace vstupů
                $email = sanitize_email( $_POST['email'] ?? '' );
                $password = $_POST['password'] ?? '';

                // Přihlášení
                $result = $this->auth->login( $email, $password, 'terminal' );

                if ( is_wp_error( $result ) ) {
                    $error = $result->get_error_message();
                } else {
                    // Úspěch - redirect
                    $redirect_url = $result['redirect_url'];
                    wp_safe_redirect( $redirect_url );
                    exit;
                }
            }
        }

        // Zobrazení šablony
        $this->render_with_wrapper( 'auth/login', array(
            'page_title'           => __( 'Přihlášení terminál', 'saw-visitors' ),
            'role'                 => 'terminal',
            'form_action'          => home_url( '/terminal/login/' ),
            'forgot_password_url'  => null, // Terminal nemá forgot password
            'redirect_to'          => home_url( '/terminal/checkin/' ),
            'nonce_action'         => 'saw_login_terminal',
            'error'                => $error,
            'success'              => $success,
            'email'                => $email,
        ) );
    }

    /**
     * Forgot password handler
     * 
     * @param string $role User role (admin/manager/terminal)
     */
    public function forgot_password( $role ) {
        $error = '';
        $success = '';
        $email = '';

        // Zpracování formuláře
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            // Validace nonce
            if ( ! wp_verify_nonce( $_POST['saw_nonce'] ?? '', 'saw_forgot_password_' . $role ) ) {
                $error = __( 'Bezpečnostní kontrola selhala. Zkuste to znovu.', 'saw-visitors' );
            } else {
                // Sanitizace
                $email = sanitize_email( $_POST['email'] ?? '' );

                // Request password reset
                $result = $this->password->request_password_reset( $email, $role );

                if ( is_wp_error( $result ) ) {
                    $error = $result->get_error_message();
                } else {
                    $success = __( 'Odkaz pro reset hesla byl odeslán na váš email.', 'saw-visitors' );
                }
            }
        }

        // Zobrazení šablony
        $this->render_with_wrapper( 'auth/forgot-password', array(
            'page_title'   => __( 'Zapomenuté heslo', 'saw-visitors' ),
            'role'         => $role,
            'form_action'  => home_url( '/' . $role . '/login/?action=forgot-password' ),
            'back_url'     => home_url( '/' . $role . '/login/' ),
            'nonce_action' => 'saw_forgot_password_' . $role,
            'error'        => $error,
            'success'      => $success,
            'email'        => $email,
        ) );
    }

    /**
     * Reset password handler
     * 
     * @param string $role  User role
     * @param string $token Reset token
     */
    public function reset_password( $role, $token ) {
        $error = '';
        $success = '';

        // Verify token
        $token_data = $this->password->verify_reset_token( $token );

        if ( is_wp_error( $token_data ) ) {
            $error = $token_data->get_error_message();
        }

        // Zpracování formuláře
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && empty( $error ) ) {
            // Validace nonce
            if ( ! wp_verify_nonce( $_POST['saw_nonce'] ?? '', 'saw_reset_password_' . $role ) ) {
                $error = __( 'Bezpečnostní kontrola selhala. Zkuste to znovu.', 'saw-visitors' );
            } else {
                // Sanitizace
                $new_password = $_POST['password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';

                // Validace
                if ( empty( $new_password ) ) {
                    $error = __( 'Vyplňte nové heslo.', 'saw-visitors' );
                } elseif ( $new_password !== $confirm_password ) {
                    $error = __( 'Hesla se neshodují.', 'saw-visitors' );
                } elseif ( strlen( $new_password ) < 8 ) {
                    $error = __( 'Heslo musí mít alespoň 8 znaků.', 'saw-visitors' );
                } else {
                    // Reset password
                    $result = $this->password->reset_password( $token, $new_password );

                    if ( is_wp_error( $result ) ) {
                        $error = $result->get_error_message();
                    } else {
                        $success = __( 'Heslo bylo úspěšně změněno. Nyní se můžete přihlásit.', 'saw-visitors' );
                    }
                }
            }
        }

        // Zobrazení šablony
        $this->render_with_wrapper( 'auth/reset-password', array(
            'page_title'   => __( 'Reset hesla', 'saw-visitors' ),
            'role'         => $role,
            'token'        => $token,
            'form_action'  => home_url( '/' . $role . '/login/?action=reset_password&token=' . $token ),
            'login_url'    => home_url( '/' . $role . '/login/' ),
            'nonce_action' => 'saw_reset_password_' . $role,
            'error'        => $error,
            'success'      => $success,
        ) );
    }

    /**
     * Logout handler (universal)
     */
    public function logout() {
        // Logout
        $this->auth->logout();

        // Redirect na hlavní stránku
        wp_safe_redirect( home_url() );
        exit;
    }

    /**
     * Render template with blank wrapper
     * 
     * @param string $template Template path (relative to templates/)
     * @param array  $data     Data to pass to template
     */
    private function render_with_wrapper( $template, $data = array() ) {
    $template_file = SAW_VISITORS_PLUGIN_DIR . 'templates/' . $template . '.php';

    if ( ! file_exists( $template_file ) ) {
        wp_die( 'Template not found: ' . esc_html( $template_file ) ); // Přidej _file
    }

    // Extract data pro template
    if ( is_array( $data ) ) {
        extract( $data );
    }

    // Load blank template wrapper
    include SAW_VISITORS_PLUGIN_DIR . 'templates/blank-template.php';
}
}