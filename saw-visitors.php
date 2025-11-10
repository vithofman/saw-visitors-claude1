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
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'SAW_VISITORS_VERSION', '4.8.2' );
define( 'SAW_VISITORS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SAW_DB_PREFIX', 'saw_' );

// ========================================
// 🚀 UNIVERSAL AJAX HANDLERS
// Funguje pro VŠECHNY moduly (customers, branches, departments, users, atd.)
// ========================================

/**
 * Universal AJAX handler for sidebar loading
 * Handles: saw_load_sidebar_{module}
 * Example: saw_load_sidebar_customers, saw_load_sidebar_branches, etc.
 */
function saw_universal_ajax_load_sidebar() {
    $action = $_POST['action'] ?? '';
    
    // Extract module name from action (e.g., 'saw_load_sidebar_customers' -> 'customers')
    if (!preg_match('/^saw_load_sidebar_(.+)$/', $action, $matches)) {
        if (SAW_DEBUG) {
            error_log('[SAW] Invalid AJAX action format: ' . $action);
        }
        wp_send_json_error(['message' => 'Invalid action format']);
        return;
    }
    
    $module = sanitize_key($matches[1]); // customers, branches, departments, etc.
    
    if (SAW_DEBUG) {
        error_log("[SAW] AJAX sidebar request for module: {$module}");
    }
    
    try {
        // Load base classes
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-model.php';
        
        // Build controller class name (e.g., SAW_Module_Customers_Controller)
        $controller_file = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$module}/controller.php";
        
        if (!file_exists($controller_file)) {
            throw new Exception("Controller not found for module: {$module}");
        }
        
        require_once $controller_file;
        
        // Convert module name to class name (customers -> Customers)
        $class_name = 'SAW_Module_' . str_replace(' ', '_', ucwords(str_replace('-', ' ', $module))) . '_Controller';
        
        if (!class_exists($class_name)) {
            throw new Exception("Controller class not found: {$class_name}");
        }
        
        $controller = new $class_name();
        
        if (!method_exists($controller, 'ajax_load_sidebar')) {
            throw new Exception("Method ajax_load_sidebar not found in {$class_name}");
        }
        
        $controller->ajax_load_sidebar();
        
    } catch (Throwable $e) {
        if (SAW_DEBUG) {
            error_log('[SAW] AJAX Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }
        wp_send_json_error(['message' => 'Server error: ' . $e->getMessage()]);
    }
}

// Register universal AJAX handlers for all modules
// This dynamically handles any module without hardcoding
add_action('wp_ajax_saw_load_sidebar_customers', 'saw_universal_ajax_load_sidebar');
add_action('wp_ajax_saw_load_sidebar_branches', 'saw_universal_ajax_load_sidebar');
add_action('wp_ajax_saw_load_sidebar_departments', 'saw_universal_ajax_load_sidebar');
add_action('wp_ajax_saw_load_sidebar_users', 'saw_universal_ajax_load_sidebar');
add_action('wp_ajax_saw_load_sidebar_contacts', 'saw_universal_ajax_load_sidebar');
add_action('wp_ajax_saw_load_sidebar_invitations', 'saw_universal_ajax_load_sidebar');
add_action('wp_ajax_saw_load_sidebar_visitors', 'saw_universal_ajax_load_sidebar');

// ========================================
// BRANCH SWITCHER AJAX
// ========================================

add_action('wp_ajax_saw_get_branches_for_switcher', function() {
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (!wp_verify_nonce($nonce, 'saw_branch_switcher')) {
        if (SAW_DEBUG) {
            error_log('[SAW] Branch switcher: Nonce verification failed');
        }
        wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
        return;
    }
    
    if (!class_exists('SAW_Context')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-context.php';
    }
    
    $customer_id = null;
    
    if (current_user_can('manage_options')) {
        if (class_exists('SAW_Context')) {
            $customer_id = SAW_Context::get_customer_id();
        }
        if (!$customer_id && isset($_POST['customer_id'])) {
            $customer_id = intval($_POST['customer_id']);
        }
    } else {
        if (class_exists('SAW_Context')) {
            $customer_id = SAW_Context::get_customer_id();
        }
        
        if (!$customer_id) {
            global $wpdb;
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT customer_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                get_current_user_id()
            ), ARRAY_A);
            
            if ($saw_user && $saw_user['customer_id']) {
                $customer_id = intval($saw_user['customer_id']);
            }
        }
    }
    
    if (!$customer_id) {
        if (SAW_DEBUG) {
            error_log('[SAW] Branch switcher: No customer_id found');
        }
        wp_send_json_error(['message' => 'Chybí ID zákazníka']);
        return;
    }
    
    global $wpdb;
    $branches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, city, street, code, is_headquarters
         FROM {$wpdb->prefix}saw_branches 
         WHERE customer_id = %d AND is_active = 1 
         ORDER BY is_headquarters DESC, name ASC",
        $customer_id
    ), ARRAY_A);
    
    $formatted = [];
    foreach ($branches as $b) {
        $address = array_filter([$b['street'] ?? '', $b['city'] ?? '']);
        $formatted[] = [
            'id' => (int)$b['id'],
            'name' => $b['name'],
            'code' => $b['code'] ?? '',
            'city' => $b['city'] ?? '',
            'address' => implode(', ', $address),
            'is_headquarters' => (bool)($b['is_headquarters'] ?? 0)
        ];
    }
    
    $current_branch_id = null;
    if (class_exists('SAW_Context')) {
        $current_branch_id = SAW_Context::get_branch_id();
    }
    
    wp_send_json_success([
        'branches' => $formatted,
        'current_branch_id' => $current_branch_id,
        'customer_id' => $customer_id
    ]);
});

add_action('wp_ajax_saw_switch_branch', function() {
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (!wp_verify_nonce($nonce, 'saw_branch_switcher')) {
        wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
        return;
    }
    
    $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
    
    if (!$branch_id) {
        wp_send_json_error(['message' => 'Chybí ID pobočky']);
        return;
    }
    
    if (!class_exists('SAW_Context')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-context.php';
    }
    
    global $wpdb;
    $branch = $wpdb->get_row($wpdb->prepare(
        "SELECT id, customer_id, name FROM {$wpdb->prefix}saw_branches WHERE id = %d AND is_active = 1",
        $branch_id
    ), ARRAY_A);
    
    if (!$branch) {
        wp_send_json_error(['message' => 'Pobočka nenalezena']);
        return;
    }
    
    if (!current_user_can('manage_options')) {
        $current_customer_id = null;
        if (class_exists('SAW_Context')) {
            $current_customer_id = SAW_Context::get_customer_id();
        }
        
        if ($branch['customer_id'] != $current_customer_id) {
            if (SAW_DEBUG) {
                error_log(sprintf(
                    '[SAW] SECURITY: Isolation violation - Branch customer: %d, User customer: %d',
                    $branch['customer_id'],
                    $current_customer_id
                ));
            }
            wp_send_json_error(['message' => 'Nemáte oprávnění k této pobočce']);
            return;
        }
    }
    
    if (class_exists('SAW_Context')) {
        SAW_Context::set_branch_id($branch_id);
    }
    
    if (SAW_DEBUG) {
        error_log('[SAW] Branch switched to: ' . $branch_id);
    }
    
    wp_send_json_success([
        'branch_id' => $branch_id,
        'branch_name' => $branch['name']
    ]);
});

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
			if ( $ctx ) { $line .= ' | ' . wp_json_encode( $ctx, JSON_UNESCAPED_UNICODE ); }
			error_log( $line );
		}
	}
}

add_action( 'plugins_loaded', function () {
	try {
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-module-style-manager.php';
		SAW_Module_Style_Manager::get_instance();
		
		if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/modules/training-languages/class-auto-setup.php')) {
			require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/training-languages/class-auto-setup.php';
		}
		
		$main = SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-visitors.php';
		if ( ! file_exists( $main ) ) {
			saw_log_error( 'Missing main class file', [ 'path' => $main ] );
			return;
		}
		require_once $main;
		if ( ! class_exists( 'SAW_Visitors' ) ) {
			saw_log_error( 'Class SAW_Visitors not found after require' );
			return;
		}
		
		$instance = SAW_Visitors::get_instance();
		if ( method_exists( $instance, 'run' ) ) {
			$instance->run();
		}
	} catch ( Throwable $t ) {
		saw_log_error( 'Bootstrap exception', [
			'message' => $t->getMessage(),
			'file'    => $t->getFile(),
			'line'    => $t->getLine(),
		] );
	}
}, 20 );