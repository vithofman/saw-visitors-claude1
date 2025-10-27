<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_visits( $table_name, $prefix, $charset_collate ) {
	$visitors_table = $prefix . 'visitors';
	$customers_table = $prefix . 'customers';
	$users_table = $prefix . 'users';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		visitor_id bigint(20) UNSIGNED NOT NULL,
		customer_id bigint(20) UNSIGNED NOT NULL,
		check_in_time datetime DEFAULT NULL,
		check_out_time datetime DEFAULT NULL,
		check_in_by bigint(20) UNSIGNED DEFAULT NULL,
		check_in_method enum('terminal','admin','auto') DEFAULT 'terminal',
		check_in_notes text DEFAULT NULL,
		check_out_by bigint(20) UNSIGNED DEFAULT NULL,
		check_out_method enum('terminal','admin','auto','bulk') DEFAULT 'terminal',
		check_out_notes text DEFAULT NULL,
		duration_minutes int(11) UNSIGNED DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_visitor (visitor_id),
		KEY idx_customer (customer_id),
		KEY idx_check_in (check_in_time),
		KEY idx_check_out (check_out_time),
		KEY idx_active (customer_id, check_in_time, check_out_time),
		KEY fk_visit_visitor (visitor_id),
		KEY fk_visit_customer (customer_id),
		KEY fk_visit_checkin_by (check_in_by),
		KEY fk_visit_checkout_by (check_out_by)
	) {$charset_collate};";
}
