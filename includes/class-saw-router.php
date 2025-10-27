<?php
/**
 * SAW Router - URL Routing System
 * 
 * Centralizovaný router pro všechny custom URL v pluginu.
 * Zpracovává: /admin/*, /manager/*, /terminal/*, /visitor/*
 * 
 * @package    SAW_Visitors
 * @subpackage SAW_Visitors/includes
 * @since      4.6.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SAW_Router {

    /**
     * Current route
     */
    private $route;

    /**
     * Route action
     */
    private $action;

    /**
     * Route params
     */
    private $params = array();

    /**
     * Auth handler
     */
    private $auth;

    /**
     * Constructor
     */
    public function __construct() {
        $this->auth = new SAW_Auth();
        $this->parse_request();
    }

    /**
     * Parse current request
     */
    private function parse_request() {
        $this->route = get_query_var( 'saw_route', '' );
        $this->action = get_query_var( 'saw_action', '' );
        $this->params = array(
            'token' => get_query_var( 'saw_token', '' ),
            'id'    => get_query_var( 'saw_id', '' ),
        );
    }

    /**
     * Main dispatch method
     */
    public function dispatch() {
        // Pokud není naše route, nic nedělat
        if ( empty( $this->route ) ) {
            return;
        }

        // Log request pro debugging
        error_log( sprintf( 
            '[SAW Router] Route: %s, Action: %s', 
            $this->route, 
            $this->action 
        ) );

        // Dispatch podle route
        switch ( $this->route ) {
            case 'admin':
                $this->handle_admin_routes();
                break;

            case 'manager':
                $this->handle_manager_routes();
                break;

            case 'terminal':
                $this->handle_terminal_routes();
                break;

            case 'visitor':
                $this->handle_visitor_routes();
                break;

            case 'logout':
                $this->handle_logout();
                break;

            default:
                $this->render_404();
                break;
        }

        // Ukončit WordPress processing
        exit;
    }

    /**
     * Handle admin routes
     */
    private function handle_admin_routes() {
        // Načíst controller
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/class-saw-login-controller.php';
        $controller = new SAW_Login_Controller();

        switch ( $this->action ) {
            case 'login':
                $controller->admin_login();
                break;

            case 'dashboard':
                // Kontrola autentizace
                saw_require_admin();
                $this->render_template( 'admin/dashboard' );
                break;

            case 'invitations':
                saw_require_admin();
                $this->render_template( 'admin/invitations' );
                break;

            case 'companies':
                saw_require_admin();
                $this->render_template( 'admin/companies' );
                break;

            case 'visitors':
                saw_require_admin();
                $this->render_template( 'admin/visitors' );
                break;

            case 'departments':
                saw_require_admin();
                $this->render_template( 'admin/departments' );
                break;

            case 'content':
                saw_require_admin();
                $this->render_template( 'admin/content' );
                break;

            case 'statistics':
                saw_require_admin();
                $this->render_template( 'admin/statistics' );
                break;

            case 'settings':
                saw_require_admin();
                $this->render_template( 'admin/settings' );
                break;

            default:
                $this->render_404();
                break;
        }
    }

    /**
     * Handle manager routes
     */
    private function handle_manager_routes() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/class-saw-login-controller.php';
        $controller = new SAW_Login_Controller();

        switch ( $this->action ) {
            case 'login':
                $controller->manager_login();
                break;

            case 'dashboard':
                saw_require_manager();
                $this->render_template( 'manager/dashboard' );
                break;

            case 'invitations':
                saw_require_manager();
                $this->render_template( 'manager/invitations' );
                break;

            case 'visitors':
                saw_require_manager();
                $this->render_template( 'manager/visitors' );
                break;

            case 'statistics':
                saw_require_manager();
                $this->render_template( 'manager/statistics' );
                break;

            default:
                $this->render_404();
                break;
        }
    }

    /**
     * Handle terminal routes
     */
    private function handle_terminal_routes() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/class-saw-login-controller.php';
        $controller = new SAW_Login_Controller();

        switch ( $this->action ) {
            case 'login':
                $controller->terminal_login();
                break;

            case 'checkin':
                saw_require_terminal();
                $this->render_template( 'terminal/checkin' );
                break;

            case 'checkout':
                saw_require_terminal();
                $this->render_template( 'terminal/checkout' );
                break;

            default:
                $this->render_404();
                break;
        }
    }

    /**
     * Handle visitor routes
     */
    private function handle_visitor_routes() {
        switch ( $this->action ) {
            case 'invitation':
                // Public route - návštěvník s tokenem
                $token = $this->params['token'];
                if ( empty( $token ) ) {
                    $this->render_404();
                }
                $this->render_template( 'visitor/invitation', array( 'token' => $token ) );
                break;

            case 'draft':
                // Public route - firma vyplňuje draft
                $token = $this->params['token'];
                if ( empty( $token ) ) {
                    $this->render_404();
                }
                $this->render_template( 'visitor/draft', array( 'token' => $token ) );
                break;

            case 'walkin':
                // Public route - walk-in návštěvník
                $this->render_template( 'visitor/walkin' );
                break;

            case 'training':
                // Public route - školení
                $token = $this->params['token'];
                if ( empty( $token ) ) {
                    $this->render_404();
                }
                $this->render_template( 'visitor/training', array( 'token' => $token ) );
                break;

            default:
                $this->render_404();
                break;
        }
    }

    /**
     * Handle logout
     */
    private function handle_logout() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/class-saw-login-controller.php';
        $controller = new SAW_Login_Controller();
        $controller->logout();
    }

    /**
     * Render template
     * 
     * @param string $template Template path (relative to templates/)
     * @param array  $data     Data to pass to template
     */
    private function render_template( $template, $data = array() ) {
    $template_file = SAW_VISITORS_PLUGIN_DIR . 'templates/' . $template . '.php';

    if ( ! file_exists( $template_file ) ) {
        error_log( sprintf( '[SAW Router] Template not found: %s', $template_file ) );
        $this->render_404();
        return;
    }

    // Extract data
    if ( is_array( $data ) ) {
        extract( $data );
    }

    // Load blank template
    include SAW_VISITORS_PLUGIN_DIR . 'templates/blank-template.php';
}

    /**
     * Render 404 page
     */
    private function render_404() {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();

        // Pokud existuje 404 template, použít ho
        $template_404 = SAW_VISITORS_PLUGIN_DIR . 'templates/404.php';
        if ( file_exists( $template_404 ) ) {
            include $template_404;
        } else {
            // Fallback - jednoduchá 404 stránka
            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta charset="<?php bloginfo( 'charset' ); ?>">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>404 - Stránka nenalezena</title>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        min-height: 100vh;
                        margin: 0;
                        background: #f5f5f5;
                    }
                    .error-container {
                        text-align: center;
                        padding: 2rem;
                    }
                    .error-code {
                        font-size: 6rem;
                        font-weight: bold;
                        color: #333;
                        margin: 0;
                    }
                    .error-message {
                        font-size: 1.5rem;
                        color: #666;
                        margin: 1rem 0;
                    }
                    .error-link {
                        display: inline-block;
                        margin-top: 2rem;
                        padding: 0.75rem 1.5rem;
                        background: #0073aa;
                        color: white;
                        text-decoration: none;
                        border-radius: 4px;
                    }
                    .error-link:hover {
                        background: #005177;
                    }
                </style>
            </head>
            <body>
                <div class="error-container">
                    <h1 class="error-code">404</h1>
                    <p class="error-message">Stránka nenalezena</p>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="error-link">
                        Zpět na hlavní stránku
                    </a>
                </div>
            </body>
            </html>
            <?php
        }
    }

    /**
     * Get current route
     * 
     * @return string
     */
    public function get_route() {
        return $this->route;
    }

    /**
     * Get current action
     * 
     * @return string
     */
    public function get_action() {
        return $this->action;
    }

    /**
     * Get route param
     * 
     * @param string $key Param key
     * @return string
     */
    public function get_param( $key ) {
        return isset( $this->params[ $key ] ) ? $this->params[ $key ] : '';
    }
}
