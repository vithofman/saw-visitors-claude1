<?php
/**
 * Uninstall script
 * 
 * Spustí se POUZE při odinstalaci pluginu přes WordPress admin.
 * MAZNE všechna data včetně:
 * - Databázových tabulek
 * - Upload souborů
 * - WordPress options
 * 
 * POZOR: Toto je destruktivní operace a nelze ji vrátit zpět!
 *
 * @package SAW_Visitors
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$prefix = $wpdb->prefix . 'saw_';

/**
 * 1. SMAZÁNÍ DATABÁZOVÝCH TABULEK
 * Všech 25 tabulek v obráceném pořadí (kvůli FK)
 * Seznam musí odpovídat SAW_Installer::get_tables_order()
 */
$tables = array_reverse(array(
    // Core (4)
    'customers',
    'companies',
    'branches',
    'account_types',
    
    // Users & Auth (4)
    'users',
    'sessions',
    'password_resets',
    'contact_persons',
    
    // Departments & Relations (3)
    'departments',
    'user_branches',
    'user_departments',
    
    // Permissions (1)
    'permissions',
    
    // Training System (6)
    'training_languages',
    'training_language_branches',
    'training_document_types',
    'training_content',
    'training_department_content',
    'training_documents',
    
    // Visitor Training System (6)
    'visits',
    'visit_schedules',
    'visitors',
    'visit_hosts',
    'visit_daily_logs',
    'visitor_certificates',
    
    // System Logs (2)
    'audit_log',
    'error_log',
));

// Přidat prefix k názvům tabulek
$tables = array_map(function($table) use ($prefix) {
    return $prefix . $table;
}, $tables);

// Vypnout kontrolu foreign keys pro bezpečné mazání
$wpdb->query('SET FOREIGN_KEY_CHECKS=0');

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
}

$wpdb->query('SET FOREIGN_KEY_CHECKS=1');

/**
 * 2. SMAZÁNÍ UPLOAD SOUBORŮ
 */
$upload_dir = wp_upload_dir();
$base_dir = $upload_dir['basedir'] . '/saw-visitor-docs';

if (file_exists($base_dir)) {
    function saw_delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            if (!saw_delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        
        return rmdir($dir);
    }
    
    saw_delete_directory($base_dir);
}

/**
 * 3. SMAZÁNÍ WORDPRESS OPTIONS
 */
$options = array(
    'saw_db_version',
    'saw_plugin_activated',
    'saw_visitors_version',
    'saw_visitors_installed_date',
    'saw_visitors_config',
);

foreach ($options as $option) {
    delete_option($option);
}

// Smazat všechny table version options
foreach ($tables as $table) {
    $table_name = str_replace($prefix, '', $table);
    delete_option('saw_table_version_' . $table_name);
}

/**
 * 4. SMAZÁNÍ TRANSIENTS
 */
$wpdb->query("DELETE FROM `{$wpdb->options}` WHERE option_name LIKE '_transient_saw_%'");
$wpdb->query("DELETE FROM `{$wpdb->options}` WHERE option_name LIKE '_transient_timeout_saw_%'");

/**
 * 5. SMAZÁNÍ SCHEDULED CRONJOBS
 */
wp_clear_scheduled_hook('saw_daily_cleanup');
wp_clear_scheduled_hook('saw_email_queue_process');

/**
 * 6. SMAZÁNÍ USER META
 */
$wpdb->query("DELETE FROM `{$wpdb->usermeta}` WHERE meta_key LIKE 'saw_%'");

/**
 * 7. LOG ODINSTALACE
 */
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
    error_log('[SAW Visitors] Plugin odinstalován - všechna data smazána');
}