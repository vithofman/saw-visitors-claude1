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
 * Všech 33 tabulek v obráceném pořadí (kvůli FK)
 */
$tables = array(
    // System (5)
    $prefix . 'email_queue',
    $prefix . 'password_resets',
    $prefix . 'sessions',
    $prefix . 'rate_limits',
    $prefix . 'error_log',
    $prefix . 'audit_log',
    
    // Visitor Management (8)
    $prefix . 'visits',
    $prefix . 'visitors',
    $prefix . 'uploaded_docs',
    $prefix . 'documents',
    $prefix . 'materials',
    $prefix . 'invitation_departments',
    $prefix . 'invitations',
    $prefix . 'companies',
    
    // Multi-tenant Core (5)
    $prefix . 'contact_persons',
    $prefix . 'department_documents',
    $prefix . 'department_materials',
    $prefix . 'user_departments',
    $prefix . 'departments',
    
    // POI System (9) - opravené pořadí
    $prefix . 'poi_additional_info',
    $prefix . 'poi_risks',
    $prefix . 'poi_pdfs',
    $prefix . 'poi_media',
    $prefix . 'poi_content',
    $prefix . 'route_pois',
    $prefix . 'routes',
    $prefix . 'pois',
    $prefix . 'beacons',
    
    // Core (5)
    $prefix . 'account_types',
    $prefix . 'training_config',
    $prefix . 'users',
    $prefix . 'customer_api_keys',
    $prefix . 'customers',
);

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