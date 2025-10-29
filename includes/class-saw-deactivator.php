<?php
/**
 * SAW Deactivator
 * 
 * Runs only when plugin is deactivated via WordPress admin.
 * Performs cleanup but does NOT delete data (that's done by uninstall.php).
 *
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Deactivator {

    /**
     * Deactivate plugin
     * 
     * Performs:
     * - Flush rewrite rules (remove custom URLs)
     * - Cleanup temporary data
     * - End active sessions
     * 
     * Does NOT delete:
     * - Database tables (done by uninstall.php)
     * - Uploaded files
     * - Plugin settings
     */
    public static function deactivate() {
        global $wpdb;
        
        $prefix = $wpdb->prefix . 'saw_';
        
        // 1. Flush rewrite rules
        flush_rewrite_rules();
        
        // 2. Cleanup sessions (if table exists)
        $sessions_table = $prefix . 'sessions';
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $sessions_table
        ));
        
        if ($table_exists === $sessions_table) {
            $wpdb->query("TRUNCATE TABLE `{$sessions_table}`");
        }
        
        // 3. Clear transients
        delete_transient('saw_active_visitors_count');
        delete_transient('saw_today_visits_count');
        
        // 4. Clear scheduled cron jobs (if exist)
        $timestamp = wp_next_scheduled('saw_daily_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'saw_daily_cleanup');
        }
        
        $timestamp = wp_next_scheduled('saw_email_queue_process');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'saw_email_queue_process');
        }
        
        // 5. Log deactivation
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[SAW Visitors] Plugin deactivated');
        }
        
        // NOTE:
        // Tables and data are NOT deleted - user may want to reactivate
        // Complete data deletion happens during uninstall via uninstall.php
    }
}