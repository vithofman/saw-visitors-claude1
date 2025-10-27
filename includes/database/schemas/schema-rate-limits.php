<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_rate_limits( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		identifier varchar(255) NOT NULL,
		identifier_type enum('ip','user','email') NOT NULL DEFAULT 'ip',
		customer_id bigint(20) UNSIGNED DEFAULT NULL,
		action_type varchar(50) NOT NULL,
		attempt_count int(11) UNSIGNED DEFAULT 1,
		window_start_at datetime NOT NULL,
		is_blocked tinyint(1) DEFAULT 0,
		blocked_until datetime DEFAULT NULL,
		ip_address varchar(45) DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_identifier_action (identifier, action_type, customer_id),
		KEY idx_customer (customer_id),
		KEY idx_action (action_type),
		KEY idx_window (window_start_at),
		KEY idx_blocked (is_blocked, blocked_until),
		KEY fk_ratelimit_customer (customer_id)
	) {$charset_collate};";
}
