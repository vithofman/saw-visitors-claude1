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

// Zabránít přímému přístupu k souboru
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
 * 🆕 NOVÉ: Cesty pro frontend systém
 */
define( 'SAW_FRONTEND_DIR', SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/' );
define( 'SAW_CONTROLLERS_DIR', SAW_VISITORS_PLUGIN_DIR . 'includes/controllers/' );
define( 'SAW_TEMPLATES_DIR', SAW_VISITORS_PLUGIN_DIR . 'templates/' );
define( 'SAW_LAYOUTS_DIR', SAW_TEMPLATES_DIR . 'layouts/' );
define( 'SAW_PAGES_DIR', SAW_TEMPLATES_DIR . 'pages/' );
define( 'SAW_COMPONENTS_DIR', SAW_TEMPLATES_DIR . 'pages/components/' );

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
 * 🆕 NOVÉ: Autoloader pro frontend třídy
 * 
 * Automaticky načte třídy z includes/frontend/ a includes/controllers/
 * podle konvence: class-saw-xxx-yyy.php → SAW_Xxx_Yyy
 */
spl_autoload_register( function( $class_name ) {
	// Kontrola, zda třída začíná SAW_
	if ( strpos( $class_name, 'SAW_' ) !== 0 ) {
		return;
	}
	
	// Převod názvu třídy na název souboru
	// SAW_App_Layout → class-saw-app-layout.php
	$class_name = str_replace( 'SAW_', '', $class_name );
	$class_name = str_replace( '_', '-', strtolower( $class_name ) );
	$filename = 'class-saw-' . $class_name . '.php';
	
	// Zkusit načíst z různých složek
	$paths = array(
		SAW_FRONTEND_DIR . $filename,
		SAW_CONTROLLERS_DIR . $filename,
		SAW_VISITORS_PLUGIN_DIR . 'includes/' . $filename,
	);
	
	foreach ( $paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
} );

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
 * 🆕 NOVÉ: Helper funkce pro načtení template souboru
 * 
 * @param string $template_path Relativní cesta k template (bez .php)
 * @param array $data Data pro template (extractují se do proměnných)
 * @return void
 */
function saw_load_template( $template_path, $data = array() ) {
	$full_path = SAW_TEMPLATES_DIR . $template_path . '.php';
	
	if ( ! file_exists( $full_path ) ) {
		if ( SAW_DEBUG ) {
			error_log( '[SAW] Template not found: ' . $full_path );
		}
		return;
	}
	
	// Extrahovat data do lokálních proměnných
	if ( ! empty( $data ) && is_array( $data ) ) {
		extract( $data );
	}
	
	// Načíst template
	include $full_path;
}

/**
 * 🆕 NOVÉ: Helper funkce pro ověření role uživatele
 * 
 * @param string $required_role Role, kterou musí mít uživatel (superadmin, admin, manager)
 * @return bool
 */
function saw_user_can( $required_role ) {
	if ( ! class_exists( 'SAW_Auth' ) ) {
		return false;
	}
	
	$auth = SAW_Auth::get_instance();
	$user = $auth->get_current_user();
	
	if ( ! $user ) {
		return false;
	}
	
	// SuperAdmin může vše
	if ( $user->role === 'superadmin' ) {
		return true;
	}
	
	// Admin může vše kromě superadmin akcí
	if ( $user->role === 'admin' && $required_role !== 'superadmin' ) {
		return true;
	}
	
	// Manager může jen manager akce
	if ( $user->role === 'manager' && $required_role === 'manager' ) {
		return true;
	}
	
	return false;
}
