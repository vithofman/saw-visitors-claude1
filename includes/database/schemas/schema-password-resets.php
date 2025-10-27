<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function saw_get_schema_password_resets( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$users_table = $prefix . 'users';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		user_id BIGINT(20) UNSIGNED NOT NULL,
		token VARCHAR(64) NOT NULL UNIQUE,
		expires_at DATETIME NOT NULL,
		used TINYINT(1) DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_token (token),
		KEY idx_customer (customer_id),
		KEY idx_user (user_id),
		KEY idx_expires (expires_at),
		CONSTRAINT fk_pwreset_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_pwreset_user FOREIGN KEY (user_id) REFERENCES {$users_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='Password reset tokens';";
}
