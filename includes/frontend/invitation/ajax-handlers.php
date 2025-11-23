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
    saw_verify_ajax_unified();
    
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
         AND status IN ('pending', 'draft')",
        $token
    ), ARRAY_A);
    
    if (!$visit) {
        wp_send_json_error('Invalid or expired token');
        return;
    }
    
    // Update/create visitors
    $visitors = $_POST['visitors'] ?? [];
    
    foreach ($visitors as $v) {
        if (!empty($v['id'])) {
            // Update existing
            $wpdb->update(
                $wpdb->prefix . 'saw_visitors',
                [
                    'first_name' => sanitize_text_field($v['first_name'] ?? ''),
                    'last_name' => sanitize_text_field($v['last_name'] ?? ''),
                    'training_skipped' => !empty($v['training_skip']) ? 1 : 0,
                ],
                ['id' => intval($v['id'])],
                ['%s', '%s', '%d'],
                ['%d']
            );
        } else {
            // Create new (keep as 'planned')
            $wpdb->insert(
                $wpdb->prefix . 'saw_visitors',
                [
                    'visit_id' => $visit['id'],
                    'customer_id' => $visit['customer_id'],
                    'branch_id' => $visit['branch_id'],
                    'first_name' => sanitize_text_field($v['first_name'] ?? ''),
                    'last_name' => sanitize_text_field($v['last_name'] ?? ''),
                    'participation_status' => 'planned',
                    'training_skipped' => !empty($v['training_skip']) ? 1 : 0,
                ],
                ['%d', '%d', '%d', '%s', '%s', '%s', '%d']
            );
        }
    }
    
    // Keep as draft
    $wpdb->update(
        $wpdb->prefix . 'saw_visits',
        ['status' => 'draft'],
        ['id' => $visit['id']],
        ['%s'],
        ['%d']
    );
    
    wp_send_json_success([
        'message' => 'Saved',
        'time' => current_time('H:i:s')
    ]);
}
add_action('wp_ajax_saw_invitation_autosave', 'saw_ajax_invitation_autosave');
add_action('wp_ajax_nopriv_saw_invitation_autosave', 'saw_ajax_invitation_autosave');

