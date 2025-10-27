<?php
/**
 * Třída pro deaktivaci pluginu
 * 
 * Spouští se pouze při deaktivaci pluginu přes WordPress admin.
 * Provádí cleanup, ale NEMAZNE data (to dělá až uninstall.php).
 *
 * @package SAW_Visitors
 */

// Zabránit přímému přístupu
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SAW_Deactivator {

	/**
	 * Deaktivace pluginu
	 * 
	 * Provede:
	 * - Flush rewrite rules (smazání custom URL)
	 * - Cleanup dočasných dat
	 * - Ukončení aktivních session
	 * 
	 * NEMAZNE:
	 * - Databázové tabulky (to dělá až uninstall.php)
	 * - Upload soubory
	 * - Nastavení pluginu
	 */
	public static function deactivate() {
		global $wpdb;
		
		// Získat prefix pro naše tabulky
		$prefix = $wpdb->prefix . SAW_DB_PREFIX;
		
		// =================================================================
		// 1. Flush rewrite rules
		// =================================================================
		flush_rewrite_rules();
		
		// =================================================================
		// 2. Cleanup sessions (pokud existuje tabulka)
		// =================================================================
		$sessions_table = $prefix . 'sessions';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$sessions_table}'" ) === $sessions_table ) {
			$wpdb->query( "TRUNCATE TABLE {$sessions_table}" );
		}
		
		// =================================================================
		// 3. Vyčistit transients
		// =================================================================
		delete_transient( 'saw_active_visitors_count' );
		delete_transient( 'saw_today_visits_count' );
		
		// =================================================================
		// 4. Vyčistit scheduled cronjobs (pokud existují)
		// =================================================================
		$timestamp = wp_next_scheduled( 'saw_daily_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'saw_daily_cleanup' );
		}
		
		$timestamp = wp_next_scheduled( 'saw_email_queue_process' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'saw_email_queue_process' );
		}
		
		// =================================================================
		// 5. Log deaktivace
		// =================================================================
		error_log( '[SAW Visitors] Plugin deaktivován' );
		
		// =================================================================
		// POZNÁMKA:
		// Tabulky a data NEMAZEME - uživatel může chtít plugin znovu aktivovat
		// Kompletní smazání dat se provede až při odinstalaci přes uninstall.php
		// =================================================================
	}
}