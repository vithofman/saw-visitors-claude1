<?php
/**
 * Třída pro lokalizaci pluginu (překlady)
 * 
 * Zajišťuje načítání překladů z /languages/ složky.
 *
 * @package SAW_Visitors
 */

// Zabránit přímému přístupu
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_i18n {

	/**
	 * Načte překlady pluginu
	 * 
	 * Text domain: 'saw-visitors'
	 * Složka s překlady: /languages/
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'saw-visitors',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}