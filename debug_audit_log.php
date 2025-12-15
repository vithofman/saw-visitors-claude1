<?php
// Load WordPress
require_once('wp-load.php');

global $wpdb;

echo "Checking last 5 audit logs for visits with 'action_oopp' in details...\n\n";

$logs = $wpdb->get_results("
    SELECT * FROM {$wpdb->prefix}saw_audit_logs 
    WHERE entity_type = 'visits' 
    AND details LIKE '%action_oopp%'
    ORDER BY created_at DESC 
    LIMIT 5
", ARRAY_A);

if (empty($logs)) {
    echo "No matching logs found via LIKE query. Showing last 5 logs for visits regardless of content:\n";
    $logs = $wpdb->get_results("
        SELECT * FROM {$wpdb->prefix}saw_audit_logs 
        WHERE entity_type = 'visits' 
        ORDER BY created_at DESC 
        LIMIT 5
    ", ARRAY_A);
}

foreach ($logs as $log) {
    echo "ID: " . $log['id'] . "\n";
    echo "Action: " . $log['action'] . "\n";
    echo "Created: " . $log['created_at'] . "\n";
    echo "Details JSON:\n";
    $details = json_decode($log['details'], true);
    if (json_last_error() === JSON_ERROR_NONE) {
        print_r($details);
    } else {
        echo "INVALID JSON: " . $log['details'] . "\n";
    }
    echo "---------------------------------------------------\n";
}

// Also check config as loaded by the system
echo "\nChecking loaded config for 'action_oopp':\n";
if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php')) {
    $config = include SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
    if (isset($config['audit']['relations']['action_oopp'])) {
        print_r($config['audit']['relations']['action_oopp']);
    } else {
        echo "Config found but 'action_oopp' missing in audit->relations!\n";
    }
} else {
    echo "Config file not found!\n";
}
