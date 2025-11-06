<?php
/**
 * SAW Deactivator - Plugin Deactivation Handler
 *
 * Runs when plugin is deactivated via WordPress admin.
 * Performs cleanup but does NOT delete data (handled by uninstall.php).
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Plugin deactivator class
 *
 * @since 1.0.0
 */
class SAW_Deactivator {

    /**
     * Deactivate plugin
     *
     * Performs cleanup tasks:
     * - Flush rewrite rules (remove custom URLs)
     * - Cleanup temporary data (sessions, transients)
     * - Clear scheduled cron jobs
     *
     * Does NOT delete:
     * - Database tables (done by uninstall.php)
     * - Uploaded files
     * - Plugin settings
     *
     * @since 1.0.0
     */
    public static function deactivate() {
        self::flush_rewrite_rules();
        self::cleanup_sessions();
        self::clear_transients();
        self::clear_scheduled_cron_jobs();
    }

    /**
     * Flush WordPress rewrite rules
     *
     * @since 1.0.0
     */
    private static function flush_rewrite_rules() {
        flush_rewrite_rules();
    }

    /**
     * Cleanup active sessions
     *
     * @since 1.0.0
     */
    private static function cleanup_sessions() {
        global $wpdb;
        
        $sessions_table = $wpdb->prefix . 'saw_sessions';
        
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->esc_like($sessions_table)
        ));
        
        if ($table_exists === $sessions_table) {
            $wpdb->query($wpdb->prepare(
                "TRUNCATE TABLE %i",
                $sessions_table
            ));
        }
    }

    /**
     * Clear plugin transients
     *
     * @since 1.0.0
     */
    private static function clear_transients() {
        $transients = [
            'saw_active_visitors_count',
            'saw_today_visits_count',
            'saw_module_manifest_v2'
        ];
        
        foreach ($transients as $transient) {
            delete_transient($transient);
        }
    }

    /**
     * Clear scheduled cron jobs
     *
     * @since 1.0.0
     */
    private static function clear_scheduled_cron_jobs() {
        $cron_hooks = [
            'saw_daily_cleanup',
            'saw_email_queue_process'
        ];
        
        foreach ($cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }
}