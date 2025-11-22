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

// 🚨 SUPER EMERGENCY DEBUG: Direct AJAX registration in root file
// Placed at the end to ensure constants are defined
add_action('wp_ajax_saw_generate_pin', function() {
    // Log that we hit the handler
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('🚨 ROOT FILE AJAX HANDLER FIRED: saw_generate_pin');
    }
    
    // Ensure dependencies are loaded
    if (!class_exists('SAW_Base_Controller')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
    }
    if (!trait_exists('SAW_AJAX_Handlers')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
    }
    if (!class_exists('SAW_Module_Visits_Controller')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/controller.php';
    }
    
    // Instantiate and call
    try {
        $controller = new SAW_Module_Visits_Controller();
        $controller->ajax_generate_pin();
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Critical Error: ' . $e->getMessage()]);
    }
    
    wp_die();
});
