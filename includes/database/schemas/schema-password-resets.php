<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_password_resets( $table_name, $prefix, $charset_collate ) {
	$users_table = $prefix . 'users';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		user_id bigint(20) UNSIGNED NOT NULL,
		token varchar(255) NOT NULL,
		expires_at datetime NOT NULL,
		used tinyint(1) DEFAULT 0,
		ip_address varchar(45) DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		used_at datetime DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY idx_token (token),
		KEY idx_user (user_id),
		KEY idx_expires (expires_at),
		KEY idx_used (used),
		KEY fk_reset_user (user_id)
	) {$charset_collate};";
}
