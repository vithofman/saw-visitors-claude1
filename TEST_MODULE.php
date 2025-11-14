<?php
/**
 * TEST SCRIPT - položit do /wp-content/plugins/saw-visitors/
 * Otevřít: https://visitors.sawuh.cz/wp-content/plugins/saw-visitors/TEST_MODULE.php
 */

define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

header('Content-Type: text/plain; charset=utf-8');

echo "MODULE LOADER TEST\n";
echo str_repeat("=", 80) . "\n\n";

// 1. Test module discovery
echo "1. DISCOVERING MODULES...\n";
$modules = SAW_Module_Loader::get_all();
echo "   Found " . count($modules) . " modules\n\n";

foreach ($modules as $slug => $config) {
    echo "   - {$slug}: {$config['route']}\n";
}

echo "\n";

// 2. Test settings module specifically
echo "2. LOADING SETTINGS MODULE...\n";
$settings = SAW_Module_Loader::load('settings');

if (!$settings) {
    echo "   ❌ SETTINGS MODULE NOT FOUND!\n";
    echo "\n3. CHECKING FILES...\n";
    
    $files = [
        'includes/modules/settings/config.php',
        'includes/modules/settings/controller.php',
        'includes/modules/settings/model.php',
    ];
    
    foreach ($files as $file) {
        $path = SAW_VISITORS_PLUGIN_DIR . $file;
        $exists = file_exists($path);
        echo "   " . ($exists ? '✅' : '❌') . " {$file}\n";
        if ($exists) {
            echo "      Size: " . filesize($path) . " bytes\n";
        }
    }
} else {
    echo "   ✅ Settings config loaded\n";
    echo "   Route: {$settings['route']}\n";
    echo "   Entity: {$settings['entity']}\n";
    echo "   Controller: {$settings['controller']}\n";
    
    echo "\n3. TESTING CONTROLLER CLASS...\n";
    
    $controller_file = $settings['path'] . 'controller.php';
    echo "   Controller file: {$controller_file}\n";
    echo "   Exists: " . (file_exists($controller_file) ? 'YES' : 'NO') . "\n";
    
    if (file_exists($controller_file)) {
        echo "   Loading controller...\n";
        
        try {
            require_once $controller_file;
            echo "   ✅ Controller file loaded\n";
            
            if (class_exists('SAW_Module_Settings_Controller')) {
                echo "   ✅ Class exists\n";
                
                echo "   Creating instance...\n";
                $controller = new SAW_Module_Settings_Controller();
                echo "   ✅ Instance created\n";
                
                if (method_exists($controller, 'index')) {
                    echo "   ✅ index() method exists\n";
                } else {
                    echo "   ❌ index() method MISSING\n";
                }
            } else {
                echo "   ❌ Class SAW_Module_Settings_Controller NOT FOUND\n";
            }
        } catch (Throwable $e) {
            echo "   ❌ ERROR: " . $e->getMessage() . "\n";
            echo "   File: " . $e->getFile() . "\n";
            echo "   Line: " . $e->getLine() . "\n";
        }
    }
}

echo "\n" . str_repeat("=", 80) . "\n";
