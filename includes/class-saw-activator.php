<?php
/**
 * Třída pro aktivaci pluginu
 * 
 * Spouští se pouze jednou při aktivaci pluginu přes WordPress admin.
 * Provádí:
 * - Kontrolu minimálních požadavků (PHP 8.1+, WordPress 6.0+)
 * - Vytvoření databázových tabulek
 * - Vytvoření výchozích dat
 * - Vytvoření upload složek
 * - Flush rewrite rules (Phase 4)
 *
 * @package SAW_Visitors
 */

// Zabránit přímému přístupu
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Activator {

	/**
	 * Aktivace pluginu
	 */
	public static function activate() {
		global $wpdb;
		
		// =================================================================
		// 1. Kontrola minimálních požadavků
		// =================================================================
		self::check_requirements();
		
		// =================================================================
		// 2. Vytvoření databázových tabulek (všech 22)
		// =================================================================
		self::create_database_tables();
		
		// =================================================================
		// 3. Vytvoření výchozích dat
		// =================================================================
		self::insert_default_data();
		
		// =================================================================
		// 4. Vytvoření upload složek
		// =================================================================
		self::create_upload_directories();
		
		// =================================================================
		// 5. Nastavení výchozích options
		// =================================================================
		self::set_default_options();
		
		// =================================================================
		// 6. Flush rewrite rules (Phase 4)
		// =================================================================
		self::flush_rewrite_rules();
		
		// =================================================================
		// 7. Log aktivace
		// =================================================================
		error_log( '[SAW Visitors] Plugin aktivován v' . SAW_VISITORS_VERSION );
	}

	/**
	 * Kontrola minimálních požadavků
	 */
	private static function check_requirements() {
		// PHP verze
		if ( version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
			wp_die(
				'SAW Visitors vyžaduje PHP 8.1 nebo vyšší. Vaše verze: ' . PHP_VERSION,
				'Nekompatibilní PHP verze',
				array( 'back_link' => true )
			);
		}
		
		// WordPress verze
		global $wp_version;
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			wp_die(
				'SAW Visitors vyžaduje WordPress 6.0 nebo vyšší. Vaše verze: ' . $wp_version,
				'Nekompatibilní WordPress verze',
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Vytvoření databázových tabulek
	 */
	private static function create_database_tables() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		// Načíst všechny schema soubory
		$schema_files = array(
			'schema-customers.php',
			'schema-users.php',
			'schema-departments.php',
			'schema-user-departments.php',
			'schema-contact-persons.php',
			'schema-companies.php',
			'schema-invitations.php',
			'schema-invitation-departments.php',
			'schema-visitors.php',
			'schema-visits.php',
			'schema-materials.php',
			'schema-documents.php',
			'schema-department-materials.php',
			'schema-department-documents.php',
			'schema-uploaded-docs.php',
			'schema-training-config.php',
			'schema-audit-log.php',
			'schema-error-log.php',
			'schema-sessions.php',
			'schema-password-resets.php',
			'schema-rate-limits.php',
			'schema-email-queue.php',
		);
		
		foreach ( $schema_files as $file ) {
			$path = SAW_VISITORS_PLUGIN_DIR . 'includes/database/schemas/' . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
				
				// Každý schema soubor má funkci saw_create_[table_name]_table()
				$function_name = 'saw_create_' . str_replace( 
					array( 'schema-', '.php', '-' ), 
					array( '', '', '_' ), 
					$file 
				) . '_table';
				
				if ( function_exists( $function_name ) ) {
					call_user_func( $function_name );
				}
			}
		}
	}

	/**
	 * Vložení výchozích dat
	 */
	private static function insert_default_data() {
		global $wpdb;
		$prefix = $wpdb->prefix . SAW_DB_PREFIX;
		
		// Zkontrolovat jestli už existují nějaká data
		$customers_count = $wpdb->get_var( 
			"SELECT COUNT(*) FROM {$prefix}customers" 
		);
		
		if ( $customers_count > 0 ) {
			return; // Již existují data
		}
		
		// Vytvořit testovacího zákazníka (volitelné)
		// V produkci můžete toto odstranit
		$wpdb->insert(
			$prefix . 'customers',
			array(
				'name'          => 'Demo Zákazník',
				'ico'           => '12345678',
				'address'       => 'Demo ulice 123, Praha',
				'primary_color' => '#0073aa',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Vytvoření upload složek
	 */
	private static function create_upload_directories() {
		$upload_dir = wp_upload_dir();
		$base_dir = $upload_dir['basedir'] . '/saw-visitor-docs';
		
		// Hlavní složka
		if ( ! file_exists( $base_dir ) ) {
			wp_mkdir_p( $base_dir );
		}
		
		// Podsložky
		$subdirs = array(
			'logos',           // Loga zákazníků
			'materials',       // Školící materiály
			'documents',       // Dokumenty
			'risk-docs',       // Dokumenty rizik od návštěvníků
			'visitor-photos',  // Fotky návštěvníků
		);
		
		foreach ( $subdirs as $subdir ) {
			$path = $base_dir . '/' . $subdir;
			if ( ! file_exists( $path ) ) {
				wp_mkdir_p( $path );
			}
		}
		
		// .htaccess pro zabezpečení
		$htaccess = $base_dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			file_put_contents(
				$htaccess,
				"Options -Indexes\n<FilesMatch '\.(php|php5|php7|phtml)$'>\nOrder Allow,Deny\nDeny from all\n</FilesMatch>"
			);
		}
		
		// index.php pro zabezpečení
		$index = $base_dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '<?php // Silence is golden' );
		}
	}

	/**
	 * Nastavení výchozích options
	 */
	private static function set_default_options() {
		add_option( 'saw_visitors_version', SAW_VISITORS_VERSION );
		add_option( 'saw_visitors_db_version', '1.0' );
		add_option( 'saw_visitors_activated_at', current_time( 'mysql' ) );
		
		// Výchozí nastavení
		add_option( 'saw_email_from_name', get_bloginfo( 'name' ) );
		add_option( 'saw_email_from_address', get_bloginfo( 'admin_email' ) );
		add_option( 'saw_session_lifetime', 7 * DAY_IN_SECONDS );
		add_option( 'saw_rate_limit_attempts', 5 );
		add_option( 'saw_rate_limit_window', 15 * MINUTE_IN_SECONDS );
	}

	/**
	 * Flush rewrite rules (Phase 4)
	 */
	private static function flush_rewrite_rules() {
		// Načíst hlavní třídu pro registraci rewrite rules
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-loader.php';
		require_once SAW_VISITORS_PLUGIN_DIR . 'includes/class-saw-visitors.php';
		
		// Vytvořit instanci a zaregistrovat rules
		$plugin = new SAW_Visitors();
		$plugin->register_rewrite_rules();
		
		// Flush
		flush_rewrite_rules();
		
		error_log( '[SAW Visitors] Rewrite rules flushed' );
	}
}
