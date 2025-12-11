<?php
/**
 * SAW Notifications - Integration Examples
 *
 * This file shows HOW TO INTEGRATE notification triggers into your existing code.
 * These are NOT actual implementations - they show WHERE to add the hooks.
 *
 * @package    SAW_Visitors
 * @subpackage Notifications
 * @version    1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ============================================================
 * EXAMPLE 1: Trigger notification when hosts are assigned
 * ============================================================
 * 
 * Add this to your visits controller where hosts are saved.
 * File: includes/modules/visits/controller.php
 * Method: save_visit() or similar
 */

// IN YOUR VISITS CONTROLLER - after saving hosts:
/*
// Get old hosts before save
$old_hosts = $wpdb->get_col($wpdb->prepare(
    "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
    $visit_id
));

// ... your existing code to save hosts ...

// Get new hosts after save
$new_hosts = isset($_POST['hosts']) ? array_map('intval', $_POST['hosts']) : [];

// Trigger notification for newly assigned hosts
if (!empty($new_hosts)) {
    do_action('saw_visit_hosts_changed', $visit_id, $new_hosts, $old_hosts);
}
*/


/**
 * ============================================================
 * EXAMPLE 2: Trigger notification on check-in
 * ============================================================
 * 
 * Add this to your terminal controller or check-in handler.
 * File: includes/frontend/terminal/class-saw-terminal-checkin.php
 * Or wherever check-in logic is handled.
 */

// IN YOUR CHECK-IN HANDLER - after successful check-in:
/*
// After inserting into saw_visit_daily_logs:
$wpdb->insert($wpdb->prefix . 'saw_visit_daily_logs', [
    'visit_id' => $visit_id,
    'visitor_id' => $visitor_id,
    'log_date' => current_time('Y-m-d'),
    'checked_in_at' => current_time('mysql'),
    // ...
]);

// Trigger notification
do_action('saw_visitor_checked_in', $visitor_id, $visit_id);
*/


/**
 * ============================================================
 * EXAMPLE 3: Trigger notification on check-out
 * ============================================================
 */

// IN YOUR CHECK-OUT HANDLER - after successful check-out:
/*
// After updating saw_visit_daily_logs:
$wpdb->update(
    $wpdb->prefix . 'saw_visit_daily_logs',
    ['checked_out_at' => current_time('mysql')],
    ['id' => $log_id]
);

// Trigger notification
do_action('saw_visitor_checked_out', $visitor_id, $visit_id);
*/


/**
 * ============================================================
 * EXAMPLE 4: Trigger notification when visit dates change
 * ============================================================
 * 
 * Add this to your visits controller where dates are updated.
 */

// IN YOUR VISIT UPDATE HANDLER:
/*
// Get old date before update
$old_date = $wpdb->get_var($wpdb->prepare(
    "SELECT planned_date_from FROM {$wpdb->prefix}saw_visits WHERE id = %d",
    $visit_id
));

// ... your update code ...

$new_date = sanitize_text_field($_POST['planned_date_from']);

// If date changed, trigger notification
if ($old_date !== $new_date) {
    do_action('saw_visit_rescheduled', $visit_id, $old_date, $new_date);
}
*/


/**
 * ============================================================
 * EXAMPLE 5: Trigger notification when visit is cancelled
 * ============================================================
 */

// IN YOUR VISIT STATUS UPDATE HANDLER:
/*
$new_status = sanitize_text_field($_POST['status']);

if ($new_status === 'cancelled') {
    // Get visit data before updating
    $visit = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d",
        $visit_id
    ), ARRAY_A);
    
    // Update status
    $wpdb->update(
        $wpdb->prefix . 'saw_visits',
        ['status' => 'cancelled'],
        ['id' => $visit_id]
    );
    
    // Trigger notification
    do_action('saw_visit_cancelled', $visit_id, $visit);
}
*/


/**
 * ============================================================
 * EXAMPLE 6: Trigger notification when invitation is completed
 * ============================================================
 * 
 * Add this to your invitation flow handler.
 * File: includes/frontend/invitation/class-saw-invitation-flow.php
 */

// IN YOUR INVITATION SUCCESS HANDLER:
/*
// After visitor confirms and visit status changes to 'confirmed':
$wpdb->update(
    $wpdb->prefix . 'saw_visits',
    [
        'status' => 'confirmed',
        'invitation_confirmed_at' => current_time('mysql'),
    ],
    ['id' => $visit_id]
);

// Trigger notification to creator
do_action('saw_visit_confirmed', $visit_id);
*/


/**
 * ============================================================
 * EXAMPLE 7: Trigger notification when training is completed
 * ============================================================
 */

// IN YOUR TRAINING COMPLETION HANDLER:
/*
// After marking training as completed:
$wpdb->update(
    $wpdb->prefix . 'saw_visitors',
    [
        'training_status' => 'completed',
        'training_completed_at' => current_time('mysql'),
    ],
    ['id' => $visitor_id]
);

// Trigger notification
do_action('saw_training_completed', $visitor_id);
*/


/**
 * ============================================================
 * HELPER FUNCTION: Manual notification creation
 * ============================================================
 * 
 * You can also create notifications directly without using triggers.
 */

/**
 * Create a custom notification
 *
 * @param int    $user_id     SAW user ID
 * @param string $type        Notification type
 * @param string $title       Notification title
 * @param string $message     Notification message
 * @param int    $visit_id    Optional related visit
 * @param int    $visitor_id  Optional related visitor
 * @return int|false Notification ID or false
 */
function saw_create_notification($user_id, $type, $title, $message, $visit_id = null, $visitor_id = null) {
    if (!class_exists('SAW_Notifications')) {
        $file = SAW_VISITORS_PLUGIN_DIR . 'includes/notifications/class-saw-notifications.php';
        if (file_exists($file)) {
            require_once $file;
        } else {
            return false;
        }
    }
    
    // Get user's customer_id
    global $wpdb;
    $user = $wpdb->get_row($wpdb->prepare(
        "SELECT customer_id FROM {$wpdb->prefix}saw_users WHERE id = %d",
        $user_id
    ), ARRAY_A);
    
    if (!$user) {
        return false;
    }
    
    // Get branch_id from visit if provided
    $branch_id = null;
    if ($visit_id) {
        $branch_id = $wpdb->get_var($wpdb->prepare(
            "SELECT branch_id FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ));
    }
    
    return SAW_Notifications::create([
        'user_id' => $user_id,
        'customer_id' => $user['customer_id'],
        'branch_id' => $branch_id,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'visit_id' => $visit_id,
        'visitor_id' => $visitor_id,
        'action_url' => $visit_id 
            ? SAW_Notifications::build_action_url('visit_detail', $visit_id) 
            : null,
    ]);
}


/**
 * ============================================================
 * USAGE EXAMPLES FOR MANUAL NOTIFICATION CREATION
 * ============================================================
 */

// Example: Notify a user about a system message
/*
saw_create_notification(
    $user_id,
    'system',
    'Aktualizace systému',
    'Systém byl aktualizován na verzi 3.5.0. Podívejte se na novinky.'
);
*/

// Example: Notify hosts about a new visit
/*
$hosts = [1, 2, 3]; // User IDs
foreach ($hosts as $host_id) {
    saw_create_notification(
        $host_id,
        'visit_assigned',
        'Nová návštěva',
        'Byli jste přiřazeni k návštěvě firmy ACME Corp.',
        $visit_id
    );
}
*/


/**
 * ============================================================
 * QUICK REFERENCE: Available notification types
 * ============================================================
 * 
 * - visit_assigned     : User assigned as host
 * - visit_today        : Visit scheduled for today (morning reminder)
 * - visit_tomorrow     : Visit scheduled for tomorrow
 * - visitor_checkin    : Visitor checked in (HIGH PRIORITY)
 * - visitor_checkout   : Visitor checked out
 * - visit_rescheduled  : Visit dates changed
 * - visit_cancelled    : Visit was cancelled
 * - visit_confirmed    : Invitation completed
 * - training_completed : Visitor finished training
 * - system             : System notification
 */
