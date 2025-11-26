<?php
/**
 * Invitation Router
 * 
 * Handles URL rewriting and token validation for visitor invitations.
 * 
 * URL Pattern: /visitor-invitation/{token}/
 * Query Params: ?step={step_name}
 * 
 * @package SAW_Visitors
 * @since 2.0.0
 */

if (!defined('ABSPATH')) exit;

class SAW_Invitation_Router {
    
    public function __construct() {
        // Priority 5 for init (before main router at 10)
        add_action('init', [$this, 'register_routes'], 5);
        add_filter('query_vars', [$this, 'register_query_vars']);
        // Priority 1 for template_redirect (before main router at 1, but we check token first)
        add_action('template_redirect', [$this, 'handle_request'], 1);
    }
    
    /**
     * Register rewrite rules
     */
    public function register_routes() {
        // Main route: /visitor-invitation/{token}/
        add_rewrite_rule(
            '^visitor-invitation/([a-f0-9]{64})/?$',
            'index.php?saw_invitation_token=$matches[1]',
            'top'
        );
    }
    
    /**
     * Register query vars
     */
    public function register_query_vars($vars) {
        $vars[] = 'saw_invitation_token';
        return $vars;
    }
    
    /**
     * Handle token validation and render
     */
    public function handle_request() {
        $token = get_query_var('saw_invitation_token');
        
        if (!$token) return;
        
        // Validate token and render
        $this->validate_and_render($token);
        exit;
    }
    
    /**
     * Validate token and render invitation controller
     * 
     * @param string $token Invitation token
     * @return void
     */
    private function validate_and_render($token) {
        global $wpdb;
        
        // Validate token
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visits 
             WHERE invitation_token = %s 
             AND invitation_token_expires_at > NOW()
             AND status IN ('pending', 'draft', 'confirmed')",
            $token
        ), ARRAY_A);
        
        if (!$visit) {
            wp_die('
                <h1>❌ Neplatný nebo expirovaný odkaz</h1>
                <p>Odkaz je buď neplatný, nebo již vypršel (platnost 30 dní).</p>
                <p>Kontaktujte prosím osobu, která vám pozvánku zaslala.</p>
            ', 'Neplatný odkaz', ['response' => 403]);
        }
        
        // Initialize session
        if (!class_exists('SAW_Session_Manager')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php';
        }
        
        $session = SAW_Session_Manager::instance();
        
        // Set invitation flow (POUZE pokud ještě neexistuje nebo token se změnil)
        $flow = $session->get('invitation_flow', []);
        
        if (!isset($flow['token']) || $flow['token'] !== $token) {
            $flow = [
                'token' => $token,
                'visit_id' => $visit['id'],
                'customer_id' => $visit['customer_id'],
                'branch_id' => $visit['branch_id'],
                'company_id' => $visit['company_id'] ?? null,
                'step' => 'language',
            ];
            
            $session->set('invitation_flow', $flow);
        }
        
        // Log access
        if (class_exists('SAW_Logger')) {
            SAW_Logger::info("Invitation accessed: visit #{$visit['id']}, token: {$token}");
        }
        
        // Load controller and render
        $controller_file = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/invitation-controller.php';
        
        if (!file_exists($controller_file)) {
            wp_die('Invitation controller not found', 'Error', ['response' => 500]);
        }
        
        require_once $controller_file;
        
        if (!class_exists('SAW_Invitation_Controller')) {
            wp_die('Invitation controller class not found', 'Error', ['response' => 500]);
        }
        
        $controller = new SAW_Invitation_Controller();
        $controller->render();
    }
}

