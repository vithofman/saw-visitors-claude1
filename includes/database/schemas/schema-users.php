<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_users( $table_name, $prefix, $wp_users_table, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		wp_user_id bigint(20) UNSIGNED DEFAULT NULL,
		customer_id bigint(20) UNSIGNED NOT NULL,
		role enum('admin','manager','terminal') NOT NULL,
		email varchar(255) NOT NULL,
		first_name varchar(100) DEFAULT NULL,
		last_name varchar(100) DEFAULT NULL,
		phone varchar(50) DEFAULT NULL,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_email_customer (email, customer_id),
		KEY idx_wp_user (wp_user_id),
		KEY idx_customer (customer_id),
		KEY idx_role (role),
		KEY fk_user_customer (customer_id),
		KEY fk_user_wpuser (wp_user_id)
	) {$charset_collate};";
}
