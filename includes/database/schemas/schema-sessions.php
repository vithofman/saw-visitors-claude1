<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_sessions( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$users_table = $prefix . 'users';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		session_token varchar(255) NOT NULL,
		customer_id bigint(20) UNSIGNED NOT NULL,
		user_id bigint(20) UNSIGNED NOT NULL,
		ip_address varchar(45) NOT NULL,
		user_agent varchar(500) DEFAULT NULL,
		last_activity datetime NOT NULL,
		expires_at datetime NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_token (session_token),
		KEY idx_user (user_id),
		KEY idx_customer (customer_id),
		KEY idx_expires (expires_at),
		KEY idx_activity (last_activity),
		KEY fk_session_customer (customer_id),
		KEY fk_session_user (user_id)
	) {$charset_collate};";
}
