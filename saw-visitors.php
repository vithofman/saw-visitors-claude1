<?php
/**
 * Plugin Name: SAW Visitors
 * Plugin URI: https://visitors.sawuh.cz
 * Description: Komplexní systém pro správu návštěv s BOZP/PO (multi-tenant).
 * Version: 4.8.0
 * Author: SAW
 * Text Domain: saw-visitors
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */
define( 'SAW_DEBUG', true );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
if ( ! defined( 'ABSPATH' ) ) { exit; }
/** Konstanta verze a cesty */
define( 'SAW_VISITORS_VERSION', '4.8.0' );
define( 'SAW_VISITORS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SAW_DB_PREFIX', 'saw_' );



define( 'SAW_DB_PREFIX', 'saw_' );

// ========================================
// 🚀 NUCLEAR OPTION: Direct AJAX Registration
// Register BEFORE anything else loads
// ========================================
add_action('wp_ajax_saw_get_branches_for_switcher', function() {
    error_log('[NUCLEAR] Branch switcher AJAX handler called DIRECTLY');
    
    // Verify nonce
    $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
    if (!wp_verify_nonce($nonce, 'saw_branch_switcher')) {
        error_log('[NUCLEAR] Nonce verification FAILED');
        wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
        return;
    }
    
    error_log('[NUCLEAR] Nonce verification PASSED');
    
    // Load SAW_Context if needed
    if (!class_exists('SAW_Context')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-context.php';
    }
    
    // Determine customer_id
    $customer_id = null;
    
    if (current_user_can('manage_options')) {
        // Super admin
        if (class_exists('SAW_Context')) {
            $customer_id = SAW_Context::get_customer_id();
        }
        if (!$customer_id && isset($_POST['customer_id'])) {
            $customer_id = intval($_POST['customer_id']);
        }
    } else {
        // Admin/other roles
        if (class_exists('SAW_Context')) {
            $customer_id = SAW_Context::get_customer_id();
        }
        
        if (!$customer_id) {
            // Fallback: Load from database
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
    
    // Validate customer_id
    if (!$customer_id) {
        error_log('[NUCLEAR] ERROR: No customer_id');
        wp_send_json_error(['message' => 'Chybí ID zákazníka']);
        return;
    }
    
    // Load branches
    global $wpdb;
    $branches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, city, street, code, is_headquarters
         FROM {$wpdb->prefix}saw_branches 
         WHERE customer_id = %d AND is_active = 1 
         ORDER BY is_headquarters DESC, name ASC",
        $customer_id
    ), ARRAY_A);
    
    error_log('[NUCLEAR] Found ' . count($branches) . ' branches');
    
    // Format branches
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
    
    // Get current branch_id
    $current_branch_id = null;
    if (class_exists('SAW_Context')) {
        $current_branch_id = SAW_Context::get_branch_id();
    }
    
    error_log('[NUCLEAR] Sending SUCCESS response');
    
    // Send response
    wp_send_json_success([
        'branches' => $formatted,
        'current_branch_id' => $current_branch_id,
        'customer_id' => $customer_id,
        'debug' => 'NUCLEAR_OPTION_WORKS'
    ]);
});

add_action('wp_ajax_saw_switch_branch', function() {
    error_log('[NUCLEAR] Switch branch AJAX handler called DIRECTLY');
    
    // Verify nonce
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
    
    // Load SAW_Context if needed
    if (!class_exists('SAW_Context')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-context.php';
    }
    
    // Validate branch exists
    global $wpdb;
    $branch = $wpdb->get_row($wpdb->prepare(
        "SELECT id, customer_id, name FROM {$wpdb->prefix}saw_branches WHERE id = %d AND is_active = 1",
        $branch_id
    ), ARRAY_A);
    
    if (!$branch) {
        wp_send_json_error(['message' => 'Pobočka nenalezena']);
        return;
    }
    
    // Validate customer isolation
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
    
    // Set branch ID
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

error_log('[NUCLEAR] Branch Switcher handlers registered DIRECTLY in main plugin');
// ========================================

/** V produkci měj vypnuto */
if ( ! defined( 'SAW_DEBUG' ) ) {
    define( 'SAW_DEBUG', false );
}



/** Aktivátor/Deaktivátor – jen registrovat hooky, nic nevypisovat */
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-activator.php';
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-deactivator.php';

/** Tichý wrapper aktivace – zahodí případný výstup z tříd */
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
/** Jednoduchý logger do debug.log */
if ( ! function_exists( 'saw_log_error' ) ) {
	function saw_log_error( $msg, $ctx = [] ) {
		if ( defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
			$line = '[SAW Visitors] ' . $msg;
			if ( $ctx ) { $line .= ' | ' . wp_json_encode( $ctx, JSON_UNESCAPED_UNICODE ); }
			error_log( $line );
		}
	}
}
/** Bezpečný bootstrap až po načtení všech pluginů */
add_action( 'plugins_loaded', function () {
	try {
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-module-style-manager.php';
		SAW_Module_Style_Manager::get_instance();
		
		// ✅ NAČÍST AUTO-SETUP TADY - uvnitř plugins_loaded
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
		// ✅ ZMĚNA: Použij singleton místo new
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