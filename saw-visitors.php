<?php
/**
 * Plugin Name: SAW Visitors
 * Plugin URI: https://visitors.sawuh.cz
 * Description: Komplexní systém pro správu návštěv s BOZP/PO (multi-tenant).
 * Version: 4.6.1
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
define( 'SAW_VISITORS_VERSION', '4.6.1' );
define( 'SAW_VISITORS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SAW_DB_PREFIX', 'saw_' );

/** V produkci měj vypnuto */
if ( ! defined( 'SAW_DEBUG' ) ) {
	define( 'SAW_DEBUG', false );
}

/** Aktivátor/Deaktivátor – jen registrovat hooky, nic nevypisovat */
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-activator.php';
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-deactivator.php';

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

		$instance = new SAW_Visitors();
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
