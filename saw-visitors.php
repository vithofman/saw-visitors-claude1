<?php
/**
 * Plugin Name: SAW Visitors
 * Plugin URI: https://visitors.sawuh.cz
 * Description: Komplexní systém pro správu návštěv s BOZP/PO (multi-tenant).
 * Version: 4.8.2
 * Author: SAW
 * Text Domain: saw-visitors
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

define( 'SAW_DEBUG', true );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SAW_VISITORS_VERSION', '4.8.2' );
define( 'SAW_VISITORS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SAW_DB_PREFIX', 'saw_' );

// ========================================
// EARLY COMPONENT INITIALIZATION FOR AJAX
// ========================================
if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-component-manager.php')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-component-manager.php';
    SAW_Component_Manager::instance();
}

// ========================================
// 🚀 UNIVERSAL AJAX HANDLERS
// Funguje pro VŠECHNY moduly (customers, branches, departments, users, atd.)
// ========================================

/**
 * NOVÝ Univerzální AJAX handler
 * Zpracuje VŠECHNY akce ve formátu:
 * wp_ajax_saw_{metoda}_{modul}
 *
 * Příklady:
 * - saw_load_sidebar_customers -> $controller->ajax_load_sidebar()
 * - saw_delete_branches -> $controller->ajax_delete()
 * - saw_get_gps_coordinates_branches -> $controller->ajax_get_gps_coordinates()
 */

// ========================================
// Registrace AJAX handlerů
// ========================================

// ✅ KRITICKÉ: Univerzální nested sidebar handler pro Select-Create komponentu
add_action('wp_ajax_saw_load_nested_sidebar', function() {
    check_ajax_referer('saw_ajax_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Nemáte oprávnění'));
        return;
    }
    
    $target_module = sanitize_key($_POST['target_module'] ?? '');
    
    if (empty($target_module)) {
        wp_send_json_error(array('message' => 'Chybí cílový modul'));
        return;
    }
    
    // Načti Base Controller
    if (!class_exists('SAW_Base_Controller')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
    }
    
    // Načti controller cílového modulu
    $controller_class = 'SAW_Module_' . ucfirst($target_module) . '_Controller';
    $controller_file = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$target_module}/controller.php";
    
    if (!file_exists($controller_file)) {
        wp_send_json_error(array('message' => 'Modul nenalezen: ' . $target_module));
        return;
    }
    
    require_once $controller_file;
    
    if (!class_exists($controller_class)) {
        wp_send_json_error(array('message' => 'Controller nenalezen: ' . $controller_class));
        return;
    }
    
    $controller = new $controller_class();
    
    if (!method_exists($controller, 'ajax_load_nested_sidebar')) {
        wp_send_json_error(array('message' => 'Metoda ajax_load_nested_sidebar nenalezena'));
        return;
    }
    
    $controller->ajax_load_nested_sidebar();
});

// ✅ Register AJAX handler for inline create - companies
add_action('wp_ajax_saw_inline_create_companies', function() {
    if (!class_exists('SAW_Module_Companies_Controller')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/companies/controller.php';
    }
    
    $controller = new SAW_Module_Companies_Controller();
    $controller->ajax_inline_create();
});

// Modulové AJAX akce se registrují automaticky přes SAW_Visitors::register_module_ajax_handlers()
// např.: saw_load_sidebar_customers, saw_delete_branches, atd.


// ========================================
// WIDGETS
// ========================================

// Widget
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/widgets/visitors/current-visitors/widget-current-visitors.php';
SAW_Widget_Current_Visitors::init();

// Dashboard  
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/dashboard/dashboard.php';


// ========================================
// BOOTSTRAP & AKTIVACE
// ========================================

if ( ! defined( 'SAW_DEBUG' ) ) {
    define( 'SAW_DEBUG', false );
}

require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-activator.php';
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-deactivator.php';

function saw_activate_plugin_silent() {
    if ( function_exists( 'ob_start' ) ) { ob_start(); }
    SAW_Activator::activate();
    if ( function_exists( 'ob_get_level' ) && ob_get_level() ) { ob_end_clean(); }
}
register_activation_hook( __FILE__, 'saw_activate_plugin_silent' );

function saw_deactivate_plugin_silent() {
    if ( function_exists( 'ob_start' ) ) { ob_start(); }
    SAW_Deactivator::deactivate();
    if ( function_exists( 'ob_get_level' ) && ob_get_level() ) { ob_end_clean(); }
}
register_deactivation_hook( __FILE__, 'saw_deactivate_plugin_silent' );

if ( ! function_exists( 'saw_log_error' ) ) {
    function saw_log_error( $msg, $ctx = [] ) {
        if ( defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
            $line = '[SAW Visitors] ' . $msg;
            if ( $ctx ) {
                $line .= ' | ' . wp_json_encode( $ctx, JSON_UNESCAPED_UNICODE );
            }
            error_log( $line );
        }
    }
}

add_action( 'plugins_loaded', function () {
    // ✅ DEBUG
    $log = WP_CONTENT_DIR . '/saw-bootstrap-debug.log';
    file_put_contents($log, "\n" . date('H:i:s') . " ==================\n", FILE_APPEND);
    file_put_contents($log, "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "\n", FILE_APPEND);
    file_put_contents($log, "Bootstrap started\n", FILE_APPEND);

    try {
        file_put_contents($log, "Loading Module Style Manager\n", FILE_APPEND);
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-module-style-manager.php';
        SAW_Module_Style_Manager::get_instance();

        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/modules/training-languages/class-auto-setup.php')) {
            file_put_contents($log, "Loading training languages\n", FILE_APPEND);
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/training-languages/class-auto-setup.php';
        }

        $main = SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-visitors.php';
        file_put_contents($log, "Checking main class: $main\n", FILE_APPEND);

        if ( ! file_exists( $main ) ) {
            file_put_contents($log, "ERROR: Main class file not found\n", FILE_APPEND);
            saw_log_error( 'Missing main class file', [ 'path' => $main ] );
            return;
        }

        file_put_contents($log, "Requiring main class\n", FILE_APPEND);
        require_once $main;

        if ( ! class_exists( 'SAW_Visitors' ) ) {
            file_put_contents($log, "ERROR: SAW_Visitors class not found\n", FILE_APPEND);
            saw_log_error( 'Class SAW_Visitors not found after require' );
            return;
        }

        file_put_contents($log, "Getting SAW_Visitors instance\n", FILE_APPEND);
        $instance = SAW_Visitors::get_instance();

        if ( method_exists( $instance, 'run' ) ) {
            file_put_contents($log, "Calling run()\n", FILE_APPEND);
            $instance->run();
            file_put_contents($log, "Bootstrap completed successfully\n", FILE_APPEND);
        } else {
            file_put_contents($log, "ERROR: run() method not found\n", FILE_APPEND);
        }
    } catch ( Throwable $t ) {
        file_put_contents($log, "EXCEPTION: " . $t->getMessage() . "\n", FILE_APPEND);
        file_put_contents($log, "File: " . $t->getFile() . "\n", FILE_APPEND);
        file_put_contents($log, "Line: " . $t->getLine() . "\n", FILE_APPEND);

        saw_log_error( 'Bootstrap exception', [
            'message' => $t->getMessage(),
            'file'    => $t->getFile(),
            'line'    => $t->getLine(),
        ] );
    }
}, 20 );