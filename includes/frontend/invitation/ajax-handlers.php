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
    
    // Handle autosave data
    if (isset($_POST['data'])) {
        $data = json_decode(stripslashes($_POST['data']), true);
        
        // Handle risks text autosave
        if (isset($data['risks_text'])) {
            $risks_text = wp_kses_post($data['risks_text']);
            
            if (!empty(trim($risks_text))) {
                // Check if text already exists
                $existing_text_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}saw_visit_invitation_materials 
                     WHERE visit_id = %d AND material_type = 'text'
                     ORDER BY uploaded_at DESC LIMIT 1",
                    $visit['id']
                ));
                
                if ($existing_text_id) {
                    // Update existing
                    $wpdb->update(
                        $wpdb->prefix . 'saw_visit_invitation_materials',
                        [
                            'text_content' => $risks_text,
                            'uploaded_at' => current_time('mysql')
                        ],
                        ['id' => $existing_text_id],
                        ['%s', '%s'],
                        ['%d']
                    );
                } else {
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
                            'uploaded_at' => current_time('mysql')
                        ]
                    );
                }
            }
        }
        
        // Handle visitors autosave
        if (isset($data['visitors']) && is_array($data['visitors'])) {
            foreach ($data['visitors'] as $visitor_data) {
                if (isset($visitor_data['id']) && $visitor_data['id'] > 0) {
                    // Update existing visitor
                    $wpdb->update(
                        $wpdb->prefix . 'saw_visitors',
                        [
                            'training_skipped' => isset($visitor_data['training_skip']) && $visitor_data['training_skip'] == 1 ? 1 : 0,
                            'training_status' => isset($visitor_data['training_skip']) && $visitor_data['training_skip'] == 1 ? 'skipped' : 'pending',
                        ],
                        [
                            'id' => intval($visitor_data['id']),
                            'visit_id' => $visit['id']
                        ],
                        ['%d', '%s'],
                        ['%d', '%d']
                    );
                } elseif (isset($visitor_data['first_name']) && isset($visitor_data['last_name'])) {
                    // Insert new visitor (draft)
                    $wpdb->insert(
                        $wpdb->prefix . 'saw_visitors',
                        [
                            'visit_id' => $visit['id'],
                            'customer_id' => $visit['customer_id'],
                            'branch_id' => $visit['branch_id'],
                            'first_name' => sanitize_text_field($visitor_data['first_name']),
                            'last_name' => sanitize_text_field($visitor_data['last_name']),
                            'position' => sanitize_text_field($visitor_data['position'] ?? ''),
                            'email' => sanitize_email($visitor_data['email'] ?? ''),
                            'phone' => sanitize_text_field($visitor_data['phone'] ?? ''),
                            'participation_status' => 'draft', // Draft until form is submitted
                            'current_status' => 'draft',
                            'training_skipped' => isset($visitor_data['training_skip']) && $visitor_data['training_skip'] == 1 ? 1 : 0,
                            'training_status' => isset($visitor_data['training_skip']) && $visitor_data['training_skip'] == 1 ? 'skipped' : 'pending',
                        ]
                    );
                }
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

