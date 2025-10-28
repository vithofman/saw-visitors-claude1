<?php
/**
 * Uninstall script
 * 
 * Spustí se POUZE při odinstalaci pluginu přes WordPress admin.
 * MAZNE všechna data včetně:
 * - Databázových tabulek
 * - Upload souborů
 * - WordPress options
 * 
 * POZOR: Toto je destruktivní operace a nelze ji vrátit zpět!
 *
 * @package SAW_Visitors
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$prefix = $wpdb->prefix . 'saw_';

/**
 * 1. SMAZÁNÍ DATABÁZOVÝCH TABULEK
 */
$tables = array(
	// Phase 0 - Multi-tenant
	$prefix . 'customers',
	$prefix . 'users',
	
	// Phase 1 - Departments
	$prefix . 'departments',
	$prefix . 'user_departments',
	$prefix . 'contact_persons',
	
	// Phase 2 - Companies & Invitations
	$prefix . 'companies',
	$prefix . 'invitations',
	$prefix . 'invitation_departments',
	
	// Budoucí tabulky
	$prefix . 'visitors',
	$prefix . 'visits',
	$prefix . 'materials',
	$prefix . 'documents',
	$prefix . 'department_materials',
	$prefix . 'department_documents',
	$prefix . 'uploaded_docs',
	$prefix . 'training_config',
	$prefix . 'audit_log',
	$prefix . 'error_log',
	$prefix . 'sessions',
	$prefix . 'password_resets',
	$prefix . 'rate_limits',
	$prefix . 'email_queue',
);

$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0' );

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1' );

/**
 * 2. SMAZÁNÍ UPLOAD SOUBORŮ
 */
$upload_dir = WP_CONTENT_DIR . '/uploads/saw-visitor-docs/';

if ( file_exists( $upload_dir ) ) {
	function saw_delete_directory( $dir ) {
		if ( ! file_exists( $dir ) ) {
			return true;
		}
		
		if ( ! is_dir( $dir ) ) {
			return unlink( $dir );
		}
		
		foreach ( scandir( $dir ) as $item ) {
			if ( $item == '.' || $item == '..' ) {
				continue;
			}
			
			if ( ! saw_delete_directory( $dir . DIRECTORY_SEPARATOR . $item ) ) {
				return false;
			}
		}
		
		return rmdir( $dir );
	}
	
	saw_delete_directory( $upload_dir );
}

/**
 * 3. SMAZÁNÍ WORDPRESS OPTIONS
 */
$options = array(
	'saw_visitors_version',
	'saw_visitors_installed_date',
	'saw_visitors_config',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

/**
 * 4. SMAZÁNÍ TRANSIENTS
 */
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_saw_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_saw_%'" );

/**
 * 5. SMAZÁNÍ SCHEDULED CRONJOBS
 */
wp_clear_scheduled_hook( 'saw_daily_cleanup' );
wp_clear_scheduled_hook( 'saw_email_queue_process' );

/**
 * 6. SMAZÁNÍ USER META
 */
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'saw_%'" );

/**
 * 7. LOG ODINSTALACE
 */
error_log( '[SAW Visitors] Plugin odinstalován - všechna data smazána' );