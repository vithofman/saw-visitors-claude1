<?php
/**
 * Visitor Info Portal Router
 * 
 * Handles URL routing for the visitor info portal.
 * 
 * URL patterns:
 * - /visitor-info/{token}/           → main entry point
 * - /visitor-info/{token}/language/  → language selection
 * - /visitor-info/{token}/training/  → training flow
 * - /visitor-info/{token}/summary/   → summary view
 * 
 * @package     SAW_Visitors
 * @subpackage  Frontend/VisitorInfo
 * @since       3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Visitor_Info_Router {
    
    /**
     * Constructor - register hooks
     * 
     * @since 3.3.0
     */
    public function __construct() {
        add_action('init', array($this, 'register_routes'), 5);
        add_filter('query_vars', array($this, 'register_query_vars'));
        add_action('template_redirect', array($this, 'handle_request'), 1);
    }
    
    /**
     * Register rewrite rules
     * 
     * @since 3.3.0
     */
    public function register_routes() {
        // Base route with token only
        add_rewrite_rule(
            '^visitor-info/([a-zA-Z0-9]{64})/?$',
            'index.php?saw_visitor_info_token=$matches[1]',
            'top'
        );
        
        // Route with token and step
        add_rewrite_rule(
            '^visitor-info/([a-zA-Z0-9]{64})/([a-z-]+)/?$',
            'index.php?saw_visitor_info_token=$matches[1]&saw_visitor_info_step=$matches[2]',
            'top'
        );
    }
    
    /**
     * Register query variables
     * 
     * @since 3.3.0
     * @param array $vars Existing query vars
     * @return array Modified query vars
     */
    public function register_query_vars($vars) {
        $vars[] = 'saw_visitor_info_token';
        $vars[] = 'saw_visitor_info_step';
        return $vars;
    }
    
    /**
     * Handle incoming requests
     * 
     * @since 3.3.0
     */
    public function handle_request() {
        $token = get_query_var('saw_visitor_info_token');
        
        if (empty($token)) {
            return;
        }
        
        // Sanitize token - only alphanumeric, exactly 64 chars
        $token = preg_replace('/[^a-zA-Z0-9]/', '', $token);
        if (strlen($token) !== 64) {
            $this->render_error('invalid_token');
            exit;
        }
        
        // Get step parameter
        $step = get_query_var('saw_visitor_info_step', '');
        $step = sanitize_key($step);
        
        // Load and execute controller
        $this->load_controller($token, $step);
        exit;
    }
    
    /**
     * Load controller and handle request
     * 
     * @since 3.3.0
     * @param string $token Visitor token
     * @param string $step Current step
     */
    private function load_controller($token, $step) {
        $controller_file = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/visitor-info/visitor-info-controller.php';
        
        if (!file_exists($controller_file)) {
            $this->render_error('system_error');
            return;
        }
        
        require_once $controller_file;
        
        $controller = new SAW_Visitor_Info_Controller($token, $step);
        $controller->init();
    }
    
    /**
     * Render error page
     * 
     * @since 3.3.0
     * @param string $type Error type (invalid_token, system_error)
     */
    private function render_error($type) {
        $status_code = ($type === 'invalid_token') ? 404 : 500;
        status_header($status_code);
        
        ?>
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="robots" content="noindex, nofollow">
            <title>Chyba</title>
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    padding: 1rem;
                }
                .error-card {
                    background: white;
                    border-radius: 24px;
                    padding: 3rem 2rem;
                    text-align: center;
                    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                    max-width: 400px;
                    width: 100%;
                }
                .error-icon {
                    font-size: 4rem;
                    margin-bottom: 1rem;
                }
                h1 {
                    font-size: 1.5rem;
                    color: #1e293b;
                    margin: 0 0 0.5rem;
                }
                p {
                    color: #64748b;
                    margin: 0;
                    line-height: 1.5;
                }
            </style>
        </head>
        <body>
            <div class="error-card">
                <div class="error-icon">❌</div>
                <h1>Stránka nenalezena</h1>
                <p>Odkaz je neplatný nebo byl odstraněn.</p>
            </div>
        </body>
        </html>
        <?php
    }
}