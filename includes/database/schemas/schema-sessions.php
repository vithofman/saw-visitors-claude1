<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_sessions($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	$users_table = $prefix . 'users';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		user_id BIGINT(20) UNSIGNED NOT NULL,
		session_token VARCHAR(64) NOT NULL UNIQUE,
		ip_address VARCHAR(45) DEFAULT NULL,
		user_agent VARCHAR(500) DEFAULT NULL,
		expires_at DATETIME NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_token (session_token),
		KEY idx_customer (customer_id),
		KEY idx_user (user_id),
		KEY idx_expires (expires_at)
	) {$charset_collate} COMMENT='Sessions';";
}
