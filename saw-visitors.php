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
	exit;
}

/**
 * !!! ZAPNUTÝ DEBUG MÓD !!!
 * Uvidíš přesnou chybovou hlášku
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
 * Debug mód
 */
define( 'SAW_DEBUG', true );

/**
 * Aktivace pluginu
 * Spustí se pouze při aktivaci pluginu přes WP admin
 */
function saw_activate_plugin() {
	try {
		error_log('=== SAW ACTIVATION START ===');
		
		if (!file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-activator.php')) {
			die('CHYBA: class-saw-activator.php neexistuje!');
		}
		
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-activator.php';
		
		if (!class_exists('SAW_Activator')) {
			die('CHYBA: SAW_Activator třída se nenačetla!');
		}
		
		SAW_Activator::activate();
		
		error_log('=== SAW ACTIVATION SUCCESS ===');
	} catch (Exception $e) {
		die('CHYBA PŘI AKTIVACI: ' . $e->getMessage() . '<br>File: ' . $e->getFile() . '<br>Line: ' . $e->getLine());
	}
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
error_log('Loading main class...');
if (!file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-visitors.php')) {
	die('CHYBA: class-saw-visitors.php neexistuje na: ' . SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-visitors.php');
}
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-visitors.php';

if (!class_exists('SAW_Visitors')) {
	die('CHYBA: SAW_Visitors třída se nenačetla!');
}

/**
 * Spuštění pluginu
 * Vytvoří instanci hlavní třídy a spustí loader
 */
function saw_run_plugin() {
	try {
		error_log('Creating SAW_Visitors instance...');
		$plugin = new SAW_Visitors();
		
		error_log('Running plugin loader...');
		$plugin->run();
		
		error_log('Plugin loaded successfully!');
	} catch (Exception $e) {
		die('CHYBA PŘI SPUŠTĚNÍ: ' . $e->getMessage() . '<br>File: ' . $e->getFile() . '<br>Line: ' . $e->getLine());
	}
}

// Spustit plugin po načtení WordPress
saw_run_plugin();

/**
 * Helper funkce pro výpis debug informací
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