<?php
/**
 * Plugin Name: SAW Visitors
 * Plugin URI: https://visitors.sawuh.cz
 * Description: Komplexní systém pro správu návštěv s BOZP/PO (multi-tenant).
 * Version: 4.8.1
 * Author: SAW
 * Text Domain: saw-visitors
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */
define( 'SAW_DEBUG', true );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
if ( ! defined( 'ABSPATH' ) ) { exit; }
define( 'SAW_VISITORS_VERSION', '4.8.1' );
define( 'SAW_VISITORS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SAW_DB_PREFIX', 'saw_' );

// ========================================
// 🚀 NUCLEAR AJAX - Register BEFORE init
// ========================================

// CUSTOMERS AJAX
add_action('wp_ajax_saw_load_sidebar_customers', function() {
    error_log('🔥 Step 1: AJAX CALLED');
    
    try {
        error_log('🔥 Step 2: Loading base controller');
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-base-controller.php';
        
        error_log('🔥 Step 3: Loading AJAX trait');
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/traits/trait-saw-ajax-handlers.php';
        
        error_log('🔥 Step 4: Loading base model');
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-base-model.php';
        
        error_log('🔥 Step 5: Loading customers controller');
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/customers/controller.php';
        
        error_log('🔥 Step 6: Creating controller instance');
        $controller = new SAW_Module_Customers_Controller();
        
        error_log('🔥 Step 7: Calling ajax_load_sidebar');
        $controller->ajax_load_sidebar();
        
        error_log('🔥 Step 8: DONE');
    } catch (Throwable $e) {
        error_log('💥 FATAL: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        wp_send_json_error(['message' => 'Server error: ' . $e->getMessage()]);
    }
});

// BRANCH SWITCHER AJAX
add_action('wp_ajax_saw_get_branches_for_switcher', function() {
    error_log('[NUCLEAR] Branch switcher AJAX handler called DIRECTLY');
    
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (!wp_verify_nonce($nonce, 'saw_branch_switcher')) {
        error_log('[NUCLEAR] Nonce verification FAILED');
        wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
        return;
    }
    
    error_log('[NUCLEAR] Nonce verification PASSED');
    
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
    
    error_log('[NUCLEAR] Customer ID: ' . ($customer_id ?? 'NULL'));
    
    if (!$customer_id) {
        error_log('[NUCLEAR] ERROR: No customer_id');
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
    
    error_log('[NUCLEAR] Found ' . count($branches) . ' branches');
    
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
    
    error_log('[NUCLEAR] Sending SUCCESS response');
    
    wp_send_json_success([
        'branches' => $formatted,
        'current_branch_id' => $current_branch_id,
        'customer_id' => $customer_id,
        'debug' => 'NUCLEAR_OPTION_WORKS'
    ]);
});

add_action('wp_ajax_saw_switch_branch', function() {
    error_log('[NUCLEAR] Switch branch AJAX handler called DIRECTLY');
    
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
            error_log(sprintf(
                '[NUCLEAR] SECURITY: Isolation violation - Branch customer: %d, User customer: %d',
                $branch['customer_id'],
                $current_customer_id
            ));
            wp_send_json_error(['message' => 'Nemáte oprávnění k této pobočce']);
            return;
        }
    }
    
    if (class_exists('SAW_Context')) {
        SAW_Context::set_branch_id($branch_id);
    }
    
    error_log('[NUCLEAR] Branch switched to: ' . $branch_id);
    
    wp_send_json_success([
        'branch_id' => $branch_id,
        'branch_name' => $branch['name'],
        'debug' => 'NUCLEAR_SWITCH_WORKS'
    ]);
});

error_log('[NUCLEAR] All AJAX handlers registered DIRECTLY in main plugin');
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