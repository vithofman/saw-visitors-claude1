<?php
/**
 * SAW Table Autoloader
 *
 * Automatically loads all SAW Table component classes.
 * Include this file to load the entire SAW Table system.
 *
 * @package     SAW_Visitors
 * @subpackage  Components/SAWTable
 * @version     1.0.0
 * @since       3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Load SAW Table component classes
 *
 * @since 1.0.0
 */
function saw_table_autoload() {
    $base_path = dirname(__FILE__) . '/';
    
    // Core classes - load order matters
    $core_classes = [
        'class-saw-table-config.php',
        'class-saw-table-permissions.php',
        'class-saw-table-translations.php',
        'class-saw-table-context.php',
        'class-saw-table.php',
    ];
    
    foreach ($core_classes as $file) {
        $path = $base_path . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }
    
    // Renderer classes
    $renderers = [
        'class-badge-renderer.php',
        'class-row-renderer.php',
        'class-section-renderer.php',
        'class-detail-renderer.php',
    ];
    
    foreach ($renderers as $file) {
        $path = $base_path . 'renderers/' . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }
}

// Auto-load on include
saw_table_autoload();

/**
 * Initialize SAW Table system
 *
 * Call this function after autoload to initialize permissions
 * and set up the environment.
 *
 * @since 1.0.0
 */
function saw_table_init() {
    // Initialize permissions
    if (class_exists('SAW_Table_Permissions')) {
        SAW_Table_Permissions::init();
    }
}

/**
 * Create SAW Table instance
 *
 * Helper function to create a new SAW_Table instance.
 *
 * @since 1.0.0
 * @param string $entity Module entity name
 * @param array  $config Module configuration
 * @return SAW_Table
 */
function saw_table($entity, $config = []) {
    return new SAW_Table($entity, $config);
}

/**
 * Check if SAW Table new system is enabled
 *
 * @since 1.0.0
 * @return bool
 */
function saw_table_is_enabled() {
    return defined('SAW_USE_NEW_TABLE') && SAW_USE_NEW_TABLE === true;
}

/**
 * Get SAW Table version
 *
 * @since 1.0.0
 * @return string
 */
function saw_table_version() {
    return '1.0.0';
}
