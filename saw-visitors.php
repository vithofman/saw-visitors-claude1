<?php
/**
 * Plugin Name: SAW Visitors
 * Plugin URI: https://visitors.sawuh.cz
 * Description: Komplexní systém pro správu návštěv s BOZP/PO compliance a multi-tenant architekturou. Frontend admin systém pro zákaznÃ­ky.
 * Version: 4.6.1
 * Author: SAW
 * Author URI: https://sawuh.cz
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: saw-visitors
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin verze
 */
define( 'SAW_VISITORS_VERSION', '4.6.1' );

/**
 * Plugin cesty
 */
define( 'SAW_VISITORS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SAW_VISITORS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Upload složky pro dokumenty
 */
define( 'SAW_VISITORS_UPLOAD_DIR', WP_CONTENT_DIR . '/uploads/saw-visitor-docs/' );
define( 'SAW_VISITORS_UPLOAD_URL', WP_CONTENT_URL . '/uploads/saw-visitor-docs/' );

/**
 * Prefix pro databázové tabulky
 */
define( 'SAW_DB_PREFIX', 'saw_' );

/**
 * Debug mód (vypnout v produkci)
 */
define( 'SAW_DEBUG', false );

/**
 * Aktivace pluginu
 * 
 * OPRAVA: Cesta k activatoru je přímo v includes/, ne v includes/database/
 */
function saw_activate_plugin() {
	require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-activator.php';
	SAW_Activator::activate();
}
register_activation_hook( __FILE__, 'saw_activate_plugin' );

/**
 * Deaktivace pluginu
 * 
 * OPRAVA: Cesta k deactivatoru je přímo v includes/, ne v includes/database/
 */
function saw_deactivate_plugin() {
	require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-deactivator.php';
	SAW_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'saw_deactivate_plugin' );

/**
 * Načtení hlavní třídy pluginu
 */
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-visitors.php';

/**
 * Spuštění pluginu
 */
function saw_run_plugin() {
	$plugin = new SAW_Visitors();
	$plugin->run();
}
saw_run_plugin();

/**
 * Helper funkce pro debug
 */
function saw_debug( $data, $label = '' ) {
	if ( ! SAW_DEBUG ) {
		return;
	}
	
	$output = '';
	if ( ! empty( $label ) ) {
		$output .= '[SAW DEBUG - ' . $label . '] ';
	}
	$output .= print_r( $data, true );
	
	error_log( $output );
}

/**
 * Helper funkce pro bezpečné získání hodnoty z pole
 */
function saw_array_get( $array, $key, $default = null ) {
	if ( ! is_array( $array ) ) {
		return $default;
	}
	
	return isset( $array[ $key ] ) ? $array[ $key ] : $default;
}