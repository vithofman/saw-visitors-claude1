<?php
/**
 * Plugin Name: SAW Visitors
 * Plugin URI: https://visitors.sawuh.cz
 * Description: Komplexní systém pro správu návštěv s BOZP/PO (multi-tenant).
 * Version: 5.0.0
 * Author: SAW
 * Text Domain: saw-visitors
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constants
define('SAW_VISITORS_VERSION', '5.0.0');
define('SAW_VISITORS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SAW_VISITORS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SAW_VISITORS_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('SAW_DB_PREFIX', 'saw_');

// Activation/Deactivation hooks
register_activation_hook(__FILE__, function() {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-activator.php';
    SAW_Activator::activate();
});

register_deactivation_hook(__FILE__, function() {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-deactivator.php';
    SAW_Deactivator::deactivate();
});

// Bootstrap
add_action('plugins_loaded', function() {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-bootstrap.php';
    SAW_Bootstrap::init();
}, 10);
