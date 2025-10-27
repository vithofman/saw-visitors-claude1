<?php
/**
 * Třída pro správu databáze
 * 
 * Utility funkce pro práci s databází
 *
 * @package SAW_Visitors
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Database {

	/**
	 * Seznam všech tabulek (bez prefixu 'saw_')
	 * Seřazeno podle závislostí (foreign keys)
	 */
	private static $tables_order = array(
		'customers',
		'users',
		'training_config',
		'departments',
		'user_departments',
		'contact_persons',
		'companies',
		'invitations',
		'invitation_departments',
		'materials',
		'documents',
		'department_materials',
		'department_documents',
		'uploaded_docs',
		'visitors',
		'visits',
		'audit_log',
		'error_log',
		'rate_limits',
		'sessions',
		'password_resets',
		'email_queue',
	);

	/**
	 * Vytvoření všech tabulek ze schema souborů
	 * 
	 * @return bool True při úspěchu, false při chybě
	 */
	public static function create_tables() {
		global $wpdb;
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		$prefix = $wpdb->prefix . SAW_DB_PREFIX;
		$charset_collate = $wpdb->get_charset_collate();
		
		$schema_dir = SAW_VISITORS_PLUGIN_DIR . 'includes/database/schemas/';
		
		foreach ( self::$tables_order as $table_name ) {
			$schema_file = $schema_dir . 'schema-' . str_replace( '_', '-', $table_name ) . '.php';
			
			if ( ! file_exists( $schema_file ) ) {
				saw_debug( "Schema soubor neexistuje: {$schema_file}", 'SAW_Database' );
				continue;
			}
			
			require_once $schema_file;
			
			$function_name = 'saw_get_schema_' . $table_name;
			
			if ( ! function_exists( $function_name ) ) {
				saw_debug( "Schema funkce neexistuje: {$function_name}", 'SAW_Database' );
				continue;
			}
			
			$full_table_name = $prefix . $table_name;
			
			if ( $table_name === 'users' ) {
				$sql = $function_name( $full_table_name, $prefix, $wpdb->users, $charset_collate );
			} else {
				$sql = $function_name( $full_table_name, $prefix, $charset_collate );
			}
			
			dbDelta( $sql );
			
			saw_debug( "Tabulka vytvořena/aktualizována: {$full_table_name}", 'SAW_Database' );
		}
		
		return true;
	}

	/**
	 * Kontrola zda tabulka existuje
	 * 
	 * @param string $table_name Název tabulky (bez prefixu, např. 'customers')
	 * @return bool True pokud existuje, false pokud ne
	 */
	public static function table_exists( $table_name ) {
		global $wpdb;
		
		$full_table_name = $wpdb->prefix . SAW_DB_PREFIX . $table_name;
		
		$result = $wpdb->get_var( 
			$wpdb->prepare( 
				"SHOW TABLES LIKE %s", 
				$full_table_name 
			) 
		);
		
		return $result === $full_table_name;
	}

	/**
	 * Získání verze tabulky
	 * 
	 * @param string $table_name Název tabulky
	 * @return int Verze tabulky (default 1)
	 */
	public static function get_table_version( $table_name ) {
		$option_name = 'saw_table_version_' . $table_name;
		return (int) get_option( $option_name, 1 );
	}

	/**
	 * Aktualizace verze tabulky
	 * 
	 * @param string $table_name Název tabulky
	 * @param int $version Nová verze
	 * @return bool True při úspěchu
	 */
	public static function update_table_version( $table_name, $version ) {
		$option_name = 'saw_table_version_' . $table_name;
		return update_option( $option_name, $version );
	}

	/**
	 * Kontrola všech tabulek - zda existují
	 * 
	 * @return array Pole s chybějícími tabulkami
	 */
	public static function check_all_tables() {
		$missing_tables = array();
		
		foreach ( self::$tables_order as $table_name ) {
			if ( ! self::table_exists( $table_name ) ) {
				$missing_tables[] = $table_name;
			}
		}
		
		return $missing_tables;
	}

	/**
	 * Smazání všech tabulek (pro uninstall)
	 * POZOR: Destruktivní operace!
	 * 
	 * @return bool True při úspěchu
	 */
	public static function drop_all_tables() {
		global $wpdb;
		
		$prefix = $wpdb->prefix . SAW_DB_PREFIX;
		
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );
		
		$tables_reverse = array_reverse( self::$tables_order );
		
		foreach ( $tables_reverse as $table_name ) {
			$full_table_name = $prefix . $table_name;
			$wpdb->query( "DROP TABLE IF EXISTS {$full_table_name}" );
		}
		
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
		
		foreach ( self::$tables_order as $table_name ) {
			delete_option( 'saw_table_version_' . $table_name );
		}
		
		return true;
	}

	/**
	 * Získání počtu řádků v tabulce
	 * 
	 * @param string $table_name Název tabulky (bez prefixu)
	 * @param int $customer_id Volitelně filtrovat podle customer_id
	 * @return int Počet řádků
	 */
	public static function get_table_count( $table_name, $customer_id = null ) {
		global $wpdb;
		
		$full_table_name = $wpdb->prefix . SAW_DB_PREFIX . $table_name;
		
		if ( $customer_id && self::has_customer_id_column( $table_name ) ) {
			$count = $wpdb->get_var( 
				$wpdb->prepare( 
					"SELECT COUNT(*) FROM {$full_table_name} WHERE customer_id = %d", 
					$customer_id 
				) 
			);
		} else {
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$full_table_name}" );
		}
		
		return (int) $count;
	}

	/**
	 * Kontrola zda tabulka má sloupec customer_id
	 * 
	 * @param string $table_name Název tabulky (bez prefixu)
	 * @return bool True pokud má, false pokud ne
	 */
	public static function has_customer_id_column( $table_name ) {
		global $wpdb;
		
		$full_table_name = $wpdb->prefix . SAW_DB_PREFIX . $table_name;
		
		$columns = $wpdb->get_col( 
			$wpdb->prepare( 
				"SHOW COLUMNS FROM {$full_table_name} LIKE %s", 
				'customer_id' 
			) 
		);
		
		return ! empty( $columns );
	}

	/**
	 * Získání seznamu všech tabulek
	 * 
	 * @return array Seznam názvů tabulek (bez prefixu)
	 */
	public static function get_all_tables() {
		return self::$tables_order;
	}

	/**
	 * Truncate tabulky (vymazat všechny záznamy)
	 * POZOR: Destruktivní operace!
	 * 
	 * @param string $table_name Název tabulky (bez prefixu)
	 * @return bool True při úspěchu
	 */
	public static function truncate_table( $table_name ) {
		global $wpdb;
		
		$full_table_name = $wpdb->prefix . SAW_DB_PREFIX . $table_name;
		
		if ( ! self::table_exists( $table_name ) ) {
			return false;
		}
		
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );
		
		$result = $wpdb->query( "TRUNCATE TABLE {$full_table_name}" );
		
		$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );
		
		return $result !== false;
	}

	/**
	 * Získání informací o tabulce
	 * 
	 * @param string $table_name Název tabulky (bez prefixu)
	 * @return array|null Informace o tabulce nebo null
	 */
	public static function get_table_info( $table_name ) {
		global $wpdb;
		
		if ( ! self::table_exists( $table_name ) ) {
			return null;
		}
		
		$full_table_name = $wpdb->prefix . SAW_DB_PREFIX . $table_name;
		
		$count = self::get_table_count( $table_name );
		
		$size_result = $wpdb->get_row( 
			$wpdb->prepare( 
				"SELECT 
					data_length + index_length as size_bytes,
					data_length,
					index_length
				FROM information_schema.TABLES 
				WHERE table_schema = %s 
				AND table_name = %s",
				DB_NAME,
				$full_table_name
			),
			ARRAY_A
		);
		
		return array(
			'name' => $table_name,
			'full_name' => $full_table_name,
			'count' => $count,
			'size_bytes' => $size_result ? (int) $size_result['size_bytes'] : 0,
			'data_size' => $size_result ? (int) $size_result['data_length'] : 0,
			'index_size' => $size_result ? (int) $size_result['index_length'] : 0,
			'version' => self::get_table_version( $table_name ),
		);
	}
}
