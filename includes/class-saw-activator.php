<?php
/**
 * Třída pro aktivaci pluginu
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Activator {

	public static function activate() {
		self::check_requirements();
		self::create_database_tables();
		self::insert_default_data();
		self::create_upload_directories();
		self::set_default_options();
		self::flush_rewrite_rules();
		
		error_log( '[SAW Visitors] Plugin aktivován v ' . SAW_VISITORS_VERSION );
	}

	private static function check_requirements() {
		if ( version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
			wp_die( 'SAW Visitors vyžaduje PHP 8.1+. Vaše verze: ' . PHP_VERSION );
		}
		
		global $wp_version;
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			wp_die( 'SAW Visitors vyžaduje WordPress 6.0+. Vaše verze: ' . $wp_version );
		}
	}

	private static function create_database_tables() {
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-database.php';
		SAW_Database::create_tables();
	}

	private static function insert_default_data() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'saw_';
		
		$customers_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}customers" );
		
		if ( $customers_count > 0 ) {
			return;
		}
		
		$wpdb->insert(
			$prefix . 'customers',
			array(
				'name'          => 'Demo Zákazník',
				'ico'           => '12345678',
				'address'       => 'Demo ulice 123, Praha',
				'primary_color' => '#0073aa',
			),
			array( '%s', '%s', '%s', '%s' )
		);
	}

	private static function create_upload_directories() {
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'] . '/saw-visitor-docs';
		
		if ( ! file_exists( $base_dir ) ) {
			wp_mkdir_p( $base_dir );
		}
		
		// Podsložky
		$subdirs = array( 'materials', 'visitor-uploads', 'risk-docs' );
		foreach ( $subdirs as $subdir ) {
			$path = $base_dir . '/' . $subdir;
			if ( ! file_exists( $path ) ) {
				wp_mkdir_p( $path );
			}
		}
	}

	private static function set_default_options() {
		add_option( 'saw_db_version', SAW_VISITORS_VERSION );
	}

	private static function flush_rewrite_rules() {
		flush_rewrite_rules();
	}
}