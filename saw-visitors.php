<?php
/**
 * Plugin Name: SAW Visitors
 * Plugin URI: https://visitors.sawuh.cz
 * Description: Komplexní systém pro správu návštěv s BOZP/PO compliance a multi-tenant architekturou. Jeden WordPress může obsluhovat více zákazníků s úplnou izolací dat.
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

// Zabránit přímému přístupu k souboru
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
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
 * Spustí se pouze při aktivaci pluginu přes WP admin
 */
function saw_activate_plugin() {
	require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-activator.php';
	SAW_Activator::activate();
}
register_activation_hook( __FILE__, 'saw_activate_plugin' );

/**
 * Deaktivace pluginu
 * Spustí se pouze při deaktivaci pluginu přes WP admin
 */
function saw_deactivate_plugin() {
	require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-deactivator.php';
	SAW_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'saw_deactivate_plugin' );

/**
 * Načtení hlavní třídy pluginu
 */
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-visitors.php';

/**
 * Spuštění pluginu
 * Vytvoří instanci hlavní třídy a spustí loader
 */
function saw_run_plugin() {
	$plugin = new SAW_Visitors();
	$plugin->run();
}

// Spustit plugin po načtení WordPress
saw_run_plugin();

/**
 * Helper funkce pro výpis debug informací (pouze pokud SAW_DEBUG = true)
 * 
 * @param mixed $data Data k výpisu
 * @param string $label Štítek pro identifikaci
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
 * 
 * @param array $array Pole
 * @param string $key Klíč
 * @param mixed $default Defaultní hodnota
 * @return mixed
 */
function saw_array_get( $array, $key, $default = null ) {
	if ( ! is_array( $array ) ) {
		return $default;
	}
	
	return isset( $array[ $key ] ) ? $array[ $key ] : $default;
}

/**
 * Helper funkce pro bezpečné získání POST hodnoty
 * 
 * @param string $key Klíč
 * @param mixed $default Defaultní hodnota
 * @return mixed
 */
function saw_post( $key, $default = null ) {
	return saw_array_get( $_POST, $key, $default );
}

/**
 * Helper funkce pro bezpečné získání GET hodnoty
 * 
 * @param string $key Klíč
 * @param mixed $default Defaultní hodnota
 * @return mixed
 */
function saw_get( $key, $default = null ) {
	return saw_array_get( $_GET, $key, $default );
}

/**
 * Helper funkce pro získání aktuálního času v MySQL formátu
 * 
 * @return string
 */
function saw_current_time() {
	return current_time( 'mysql' );
}