<?php
/**
 * Třída pro aktivaci pluginu
 * 
 * Spouští se pouze při aktivaci pluginu přes WordPress admin.
 * Vytváří databázové tabulky pomocí schema souborů, upload složky a základní nastavení.
 *
 * @package SAW_Visitors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Activator {

	/**
	 * Aktivace pluginu
	 */
	public static function activate() {
		global $wpdb;
		
		$prefix = $wpdb->prefix . SAW_DB_PREFIX;
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		$charset_collate = $wpdb->get_charset_collate();
		
		self::create_tables( $prefix, $charset_collate );
		
		self::create_upload_directories();
		
		self::set_default_options();
		
		flush_rewrite_rules();
		
		error_log( '[SAW Visitors] Plugin aktivován - verze ' . SAW_VISITORS_VERSION );
	}
	
	/**
	 * Vytvoření všech tabulek pomocí schema souborů
	 */
	private static function create_tables( $prefix, $charset_collate ) {
		global $wpdb;
		
		$schema_dir = SAW_VISITORS_PLUGIN_DIR . 'includes/database/schemas/';
		
		$tables_order = array(
			'customers',
			'users',
			'training-config',
			'departments',
			'user-departments',
			'contact-persons',
			'companies',
			'invitations',
			'invitation-departments',
			'materials',
			'documents',
			'department-materials',
			'department-documents',
			'uploaded-docs',
			'visitors',
			'visits',
			'audit-log',
			'error-log',
			'rate-limits',
			'sessions',
			'password-resets',
			'email-queue',
		);
		
		foreach ( $tables_order as $table_name ) {
			$schema_file = $schema_dir . 'schema-' . $table_name . '.php';
			
			if ( ! file_exists( $schema_file ) ) {
				error_log( "[SAW Visitors] Schema file not found: {$schema_file}" );
				continue;
			}
			
			require_once $schema_file;
			
			$function_name = 'saw_get_schema_' . str_replace( '-', '_', $table_name );
			
			if ( ! function_exists( $function_name ) ) {
				error_log( "[SAW Visitors] Schema function not found: {$function_name}" );
				continue;
			}
			
			$full_table_name = $prefix . str_replace( '-', '_', $table_name );
			
			if ( $table_name === 'users' ) {
				$sql = $function_name( $full_table_name, $prefix, $wpdb->users, $charset_collate );
			} else {
				$sql = $function_name( $full_table_name, $prefix, $charset_collate );
			}
			
			dbDelta( $sql );
			
			error_log( "[SAW Visitors] Table created/updated: {$full_table_name}" );
		}
	}
	
	/**
	 * Vytvoření upload složek s .htaccess ochranou
	 */
	private static function create_upload_directories() {
		$base_dir = SAW_VISITORS_UPLOAD_DIR;
		
		$directories = array(
			$base_dir,
			$base_dir . 'materials/',
			$base_dir . 'visitor-uploads/',
			$base_dir . 'risk-docs/',
			$base_dir . 'company-logos/',
		);
		
		foreach ( $directories as $dir ) {
			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}
		}
		
		$htaccess_content = "# SAW Visitors - Ochrana souborů\n";
		$htaccess_content .= "Order Deny,Allow\n";
		$htaccess_content .= "Deny from all\n";
		$htaccess_content .= "<FilesMatch \"\\.(pdf|jpg|jpeg|png|mp4|webm)$\">\n";
		$htaccess_content .= "    Allow from all\n";
		$htaccess_content .= "</FilesMatch>\n";
		
		$htaccess_file = $base_dir . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			file_put_contents( $htaccess_file, $htaccess_content );
		}
		
		$index_content = "<?php\n// Silence is golden.\n";
		foreach ( $directories as $dir ) {
			$index_file = $dir . 'index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, $index_content );
			}
		}
	}
	
	/**
	 * Nastavení výchozích options
	 */
	private static function set_default_options() {
		update_option( 'saw_visitors_version', SAW_VISITORS_VERSION );
		
		if ( ! get_option( 'saw_visitors_installed_date' ) ) {
			update_option( 'saw_visitors_installed_date', current_time( 'mysql' ) );
		}
		
		$default_config = array(
			'training_enabled' => true,
			'training_version' => 1,
			'email_notifications' => true,
			'risk_document_required' => true,
			'retention_period_days' => 1825,
		);
		
		if ( ! get_option( 'saw_visitors_config' ) ) {
			update_option( 'saw_visitors_config', $default_config );
		}
	}
}
