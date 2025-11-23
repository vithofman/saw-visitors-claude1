<?php
/**
 * Invitation Router - Samostatný systém pro emailové pozvánky
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

class SAW_Invitation_Router {
    
    public function __construct() {
        add_action('init', [$this, 'register_routes'], 5);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('template_redirect', [$this, 'handle_redirect'], 5);
    }
    
    /**
     * Register rewrite rules
     */
    public function register_routes() {
        add_rewrite_rule(
            '^visitor-training/([a-f0-9]{64})/?$',
            'index.php?saw_invitation_token=$matches[1]',
            'top'
        );
        
        // Flush rewrite rules if needed (only once per version)
        // Check if version constant exists, otherwise use plugin version from DB
        $plugin_version = defined('SAW_VISITORS_VERSION') ? SAW_VISITORS_VERSION : get_option('saw_db_version', '1.0.0');
        $flushed_version = get_option('saw_invitation_rewrite_flushed');
        
        // Force flush on first run or version change
        if ($flushed_version !== $plugin_version || empty($flushed_version)) {
            // Use add_option to only set if not exists, preventing multiple flushes
            if (empty($flushed_version)) {
                add_option('saw_invitation_rewrite_flushed', $plugin_version, '', 'no');
            } else {
                update_option('saw_invitation_rewrite_flushed', $plugin_version);
            }
            
            // Schedule flush for next request (safer than immediate flush)
            add_option('saw_invitation_rewrite_needs_flush', '1', '', 'no');
        }
        
        // Check if flush is needed and perform it
        if (get_option('saw_invitation_rewrite_needs_flush') === '1') {
            flush_rewrite_rules(false); // false = don't hard flush, just update
            delete_option('saw_invitation_rewrite_needs_flush');
        }
    }
    
    /**
     * Register query vars
     */
    public function register_query_vars($vars) {
        $vars[] = 'saw_invitation_token';
        return $vars;
    }
    
    /**
     * Handle token validation and redirect
     */
    public function handle_redirect() {
        $token = get_query_var('saw_invitation_token');
        
        if (!$token) return;
        
        // Validace tokenu
        global $wpdb;
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, c.name as company_name 
             FROM {$wpdb->prefix}saw_visits v
             LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
             WHERE v.invitation_token = %s 
             AND v.invitation_token_expires_at > NOW()
             AND v.status IN ('pending', 'draft')",
            $token
        ), ARRAY_A);
        
        if (!$visit) {
            wp_die('
                <h1>❌ Neplatný odkaz na pozvánku</h1>
                <p>Odkaz je buď neplatný, nebo již vypršel (platnost 30 dní).</p>
                <p>Kontaktujte prosím osobu, která vám pozvánku zaslala.</p>
            ');
        }
        
        // Nastav session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['invitation_flow'] = [
            'mode' => 'invitation',
            'token' => $token,
            'visit_id' => $visit['id'],
            'customer_id' => $visit['customer_id'],
            'branch_id' => $visit['branch_id'],
            'company_id' => $visit['company_id'],
        ];
        
        // Log
        if (class_exists('SAW_Logger')) {
            SAW_Logger::info("Invitation accessed: visit #{$visit['id']}, token: {$token}");
        }
        
        // Redirect na terminal
        wp_redirect(home_url('/terminal/?invitation=1'));
        exit;
    }
}

