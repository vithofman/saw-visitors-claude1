<?php
/**
 * Invitation AJAX Handlers
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

/**
 * AJAX Handler: Invitation Autosave
 * 
 * @since 1.0.0
 */
function saw_ajax_invitation_autosave() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_invitation_autosave')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $token = $_POST['token'] ?? '';
    
    if (empty($token)) {
        wp_send_json_error('Invalid token');
        return;
    }
    
    // Validate token and get visit
    global $wpdb;
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visits 
         WHERE invitation_token = %s 
         AND invitation_token_expires_at > NOW()
         AND status IN ('pending', 'draft', 'confirmed')",
        $token
    ), ARRAY_A);
    
    if (!$visit) {
        wp_send_json_error('Invalid or expired token');
        return;
    }
    
    // Handle risks text autosave
    if (isset($_POST['data'])) {
        $data = json_decode(stripslashes($_POST['data']), true);
        
        if (isset($data['risks_text'])) {
            $risks_text = wp_kses_post($data['risks_text']);
            
            if (!empty(trim($risks_text))) {
                // Delete old text
                $wpdb->delete(
                    $wpdb->prefix . 'saw_visit_invitation_materials',
                    [
                        'visit_id' => $visit['id'],
                        'material_type' => 'text'
                    ]
                );
                
                // Insert new
                $wpdb->insert(
                    $wpdb->prefix . 'saw_visit_invitation_materials',
                    [
                        'visit_id' => $visit['id'],
                        'customer_id' => $visit['customer_id'],
                        'branch_id' => $visit['branch_id'],
                        'company_id' => $visit['company_id'] ?? null,
                        'material_type' => 'text',
                        'text_content' => $risks_text,
                    ]
                );
            }
        }
    }
    
    // Keep as draft
    $wpdb->update(
        $wpdb->prefix . 'saw_visits',
        ['status' => 'draft'],
        ['id' => $visit['id']]
    );
    
    // Invalidate cache
    if (class_exists('SAW_Cache')) {
        SAW_Cache::delete('invitation_visit_' . $visit['id'], 'invitations');
    }
    
    wp_send_json_success([
        'message' => 'Saved',
        'time' => current_time('H:i:s')
    ]);
}
add_action('wp_ajax_saw_invitation_autosave', 'saw_ajax_invitation_autosave');
add_action('wp_ajax_nopriv_saw_invitation_autosave', 'saw_ajax_invitation_autosave');

/**
 * AJAX Handler: Clear Invitation Session
 * 
 * @since 1.0.0
 */
function saw_ajax_clear_invitation_session() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_clear_invitation_session')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $token = $_POST['token'] ?? '';
    
    if (empty($token)) {
        wp_send_json_error('Invalid token');
        return;
    }
    
    // Clear session
    if (!class_exists('SAW_Session_Manager')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php';
    }
    
    $session = SAW_Session_Manager::instance();
    $flow = $session->get('invitation_flow');
    
    // Only clear if token matches
    if (isset($flow['token']) && $flow['token'] === $token) {
        $session->unset('invitation_flow');
        wp_send_json_success(['message' => 'Session cleared']);
    } else {
        wp_send_json_error('Token mismatch');
    }
}
add_action('wp_ajax_saw_clear_invitation_session', 'saw_ajax_clear_invitation_session');
add_action('wp_ajax_nopriv_saw_clear_invitation_session', 'saw_ajax_clear_invitation_session');

