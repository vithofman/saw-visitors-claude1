<?php
/**
 * SAW Login Controller - Ukázková implementace
 * 
 * Tento soubor ukazuje, jak implementovat login controller pro všechny role.
 * Použijte tento pattern ve Vašem routing systému (Phase 4).
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
        $this->render_login_template( array(
            'page_title'           => __( 'Přihlášení administrátora', 'saw-visitors' ),
            'role'                 => 'admin',
            'form_action'          => home_url( '/admin/login/' ),
            'forgot_password_url'  => home_url( '/admin/login/?action=forgot-password' ),
            'redirect_to'          => $_GET['redirect_to'] ?? home_url( '/admin/dashboard/' ),
            'error'                => $error,
            'success'              => $success,
            'email'                => $email,
            'show_other_roles'     => true,
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

        // Pro managery používáme WordPress systém pro password reset
        // Takže forgot-password redirectuje na wp_lostpassword_url()

        $error = '';
        $success = '';
        $email = '';

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
        $this->render_login_template( array(
            'page_title'           => __( 'Přihlášení manažera', 'saw-visitors' ),
            'role'                 => 'manager',
            'form_action'          => home_url( '/manager/login/' ),
            'forgot_password_url'  => wp_lostpassword_url( home_url( '/manager/login/' ) ), // WP systém!
            'redirect_to'          => $_GET['redirect_to'] ?? home_url( '/manager/dashboard/' ),
            'error'                => $error,
            'success'              => $success,
            'email'                => $email,
            'show_other_roles'     => false,
        ) );
    }

    /**
     * Terminal login handler
     * 
     * URL: /terminal/login/
     */
    public function terminal_login() {
        // Pokud je již přihlášen, redirect na checkin
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
                $password = $_POST['password'] ?? ''; // PIN

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

        // Zobrazení šablony (terminály nemají forgot password)
        $this->render_login_template( array(
            'page_title'  => __( 'Přihlášení terminálu', 'saw-visitors' ),
            'role'        => 'terminal',
            'form_action' => home_url( '/terminal/login/' ),
            'redirect_to' => home_url( '/terminal/checkin/' ),
            'error'       => $error,
            'success'     => $success,
            'email'       => $email,
        ) );
    }

    /**
     * Forgot password handler
     * 
     * URL: /admin/login/?action=forgot-password
     *      /manager/login/?action=forgot-password (redirect na WP)
     * 
     * @param string $role Role
     */
    private function forgot_password( $role ) {
        // Pro managery použít WP systém
        if ( $role === 'manager' ) {
            wp_safe_redirect( wp_lostpassword_url( home_url( '/manager/login/' ) ) );
            exit;
        }

        $error = '';
        $success = false;
        $email = '';

        // Zpracování formuláře
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            // Validace nonce
            if ( ! wp_verify_nonce( $_POST['saw_nonce'] ?? '', 'saw_forgot_password_' . $role ) ) {
                $error = __( 'Bezpečnostní kontrola selhala. Zkuste to znovu.', 'saw-visitors' );
            } else {
                // Sanitizace
                $email = sanitize_email( $_POST['email'] ?? '' );

                if ( empty( $email ) ) {
                    $error = __( 'Zadejte emailovou adresu.', 'saw-visitors' );
                } else {
                    // Odeslání reset emailu
                    $result = $this->password->send_reset_email( $email, $role );

                    if ( is_wp_error( $result ) ) {
                        $error = $result->get_error_message();
                    } else {
                        $success = true;
                    }
                }
            }
        }

        // Zobrazení šablony
        $this->render_forgot_password_template( array(
            'role'        => $role,
            'form_action' => home_url( '/' . $role . '/login/?action=forgot-password' ),
            'back_url'    => home_url( '/' . $role . '/login/' ),
            'error'       => $error,
            'success'     => $success,
            'email'       => $email,
        ) );
    }

    /**
     * Reset password handler
     * 
     * URL: /admin/login/?action=reset_password&token=xxx
     * 
     * @param string $role  Role
     * @param string $token Reset token
     */
    private function reset_password( $role, $token ) {
        $error = '';
        $success = false;
        $token_invalid = false;

        // Validace tokenu
        $user_id = $this->password->validate_reset_token( $token );

        if ( ! $user_id ) {
            $token_invalid = true;
        } else {
            // Token je platný - zpracování formuláře
            if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
                // Validace nonce
                if ( ! wp_verify_nonce( $_POST['saw_nonce'] ?? '', 'saw_reset_password_' . $role ) ) {
                    $error = __( 'Bezpečnostní kontrola selhala. Zkuste to znovu.', 'saw-visitors' );
                } else {
                    $new_password = $_POST['new_password'] ?? '';
                    $confirm_password = $_POST['confirm_password'] ?? '';

                    // Kontrola shody hesel
                    if ( $new_password !== $confirm_password ) {
                        $error = __( 'Hesla se neshodují.', 'saw-visitors' );
                    } elseif ( empty( $new_password ) ) {
                        $error = __( 'Zadejte nové heslo.', 'saw-visitors' );
                    } else {
                        // Reset hesla
                        $result = $this->password->reset_password( $token, $new_password );

                        if ( is_wp_error( $result ) ) {
                            $error = $result->get_error_message();
                        } else {
                            $success = true;
                        }
                    }
                }
            }
        }

        // Zobrazení šablony
        $this->render_reset_password_template( array(
            'role'                => $role,
            'token'               => $token,
            'form_action'         => add_query_arg(
                array(
                    'action' => 'reset_password',
                    'token'  => $token,
                ),
                home_url( '/' . $role . '/login/' )
            ),
            'login_url'           => home_url( '/' . $role . '/login/' ),
            'forgot_password_url' => home_url( '/' . $role . '/login/?action=forgot-password' ),
            'error'               => $error,
            'success'             => $success,
            'token_invalid'       => $token_invalid,
        ) );
    }

    /**
     * Logout handler
     * 
     * URL: /logout/ nebo /{role}/logout/
     */
    public function logout() {
        // Logout
        $this->auth->logout();

        // Redirect na hlavní stránku
        wp_safe_redirect( home_url() );
        exit;
    }

    /**
     * Render login template
     * 
     * @param array $data Template data
     */
    private function render_login_template( $data ) {
        extract( $data );
        include plugin_dir_path( dirname( __FILE__ ) ) . '../templates/auth/login.php';
    }

    /**
     * Render forgot password template
     * 
     * @param array $data Template data
     */
    private function render_forgot_password_template( $data ) {
        extract( $data );
        include plugin_dir_path( dirname( __FILE__ ) ) . '../templates/auth/forgot-password.php';
    }

    /**
     * Render reset password template
     * 
     * @param array $data Template data
     */
    private function render_reset_password_template( $data ) {
        extract( $data );
        include plugin_dir_path( dirname( __FILE__ ) ) . '../templates/auth/reset-password.php';
    }
}

/**
 * POUŽITÍ V ROUTING SYSTÉMU (Phase 4):
 * 
 * add_action( 'template_redirect', 'saw_handle_routes' );
 * 
 * function saw_handle_routes() {
 *     $request_uri = $_SERVER['REQUEST_URI'];
 *     
 *     $controller = new SAW_Login_Controller();
 *     
 *     // Admin routes
 *     if ( preg_match( '#^/admin/login/?$#', $request_uri ) ) {
 *         $controller->admin_login();
 *         exit;
 *     }
 *     
 *     // Manager routes
 *     if ( preg_match( '#^/manager/login/?$#', $request_uri ) ) {
 *         $controller->manager_login();
 *         exit;
 *     }
 *     
 *     // Terminal routes
 *     if ( preg_match( '#^/terminal/login/?$#', $request_uri ) ) {
 *         $controller->terminal_login();
 *         exit;
 *     }
 *     
 *     // Logout (universal)
 *     if ( preg_match( '#^/(admin|manager|terminal)?/?logout/?$#', $request_uri ) ) {
 *         $controller->logout();
 *         exit;
 *     }
 * }
 * 
 * 
 * NEBO S REWRITE RULES (lepší):
 * 
 * function saw_add_rewrite_rules() {
 *     add_rewrite_rule(
 *         '^admin/login/?$',
 *         'index.php?saw_route=admin&saw_action=login',
 *         'top'
 *     );
 *     
 *     add_rewrite_rule(
 *         '^manager/login/?$',
 *         'index.php?saw_route=manager&saw_action=login',
 *         'top'
 *     );
 *     
 *     add_rewrite_rule(
 *         '^terminal/login/?$',
 *         'index.php?saw_route=terminal&saw_action=login',
 *         'top'
 *     );
 *     
 *     add_rewrite_rule(
 *         '^logout/?$',
 *         'index.php?saw_route=logout',
 *         'top'
 *     );
 * }
 * add_action( 'init', 'saw_add_rewrite_rules' );
 * 
 * function saw_add_query_vars( $vars ) {
 *     $vars[] = 'saw_route';
 *     $vars[] = 'saw_action';
 *     return $vars;
 * }
 * add_filter( 'query_vars', 'saw_add_query_vars' );
 * 
 * function saw_template_redirect() {
 *     $route = get_query_var( 'saw_route' );
 *     $action = get_query_var( 'saw_action' );
 *     
 *     if ( ! $route ) {
 *         return;
 *     }
 *     
 *     $controller = new SAW_Login_Controller();
 *     
 *     // Routes
 *     if ( $route === 'admin' && $action === 'login' ) {
 *         $controller->admin_login();
 *         exit;
 *     }
 *     
 *     if ( $route === 'manager' && $action === 'login' ) {
 *         $controller->manager_login();
 *         exit;
 *     }
 *     
 *     if ( $route === 'terminal' && $action === 'login' ) {
 *         $controller->terminal_login();
 *         exit;
 *     }
 *     
 *     if ( $route === 'logout' ) {
 *         $controller->logout();
 *         exit;
 *     }
 * }
 * add_action( 'template_redirect', 'saw_template_redirect' );
 */
