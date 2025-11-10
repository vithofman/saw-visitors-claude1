<?php
/**
 * TEST - Existence souborů
 * Vlož do: /wp-content/plugins/saw-visitors/test-files.php
 * Spusť: https://visitors.sawuh.cz/wp-content/plugins/saw-visitors/test-files.php
 */

define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

header('Content-Type: text/plain; charset=utf-8');

echo "FILE EXISTENCE TEST\n";
echo str_repeat("=", 60) . "\n\n";

$files = [
    'includes/base/class-base-controller.php',
    'includes/base/class-saw-base-controller.php',
    'includes/base/trait-ajax-handlers.php',
    'includes/base/class-base-model.php',
    'includes/base/class-saw-base-model.php',
    'includes/modules/customers/controller.php',
    'includes/modules/customers/relations.php',
    'includes/components/admin-table/detail-sidebar.php',
];

foreach ($files as $file) {
    $full_path = SAW_VISITORS_PLUGIN_DIR . $file;
    $exists = file_exists($full_path);
    
    echo ($exists ? '✅' : '❌') . " {$file}\n";
    
    if ($exists) {
        $size = filesize($full_path);
        echo "   Size: {$size} bytes\n";
    } else {
        echo "   Path: {$full_path}\n";
    }
}

echo "\n";
echo "Plugin dir: " . SAW_VISITORS_PLUGIN_DIR . "\n";
echo str_repeat("=", 60) . "\n";