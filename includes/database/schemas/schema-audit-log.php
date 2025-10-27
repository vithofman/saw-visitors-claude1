<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_audit_log( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$users_table = $prefix . 'users';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) UNSIGNED NOT NULL,
		user_id bigint(20) UNSIGNED DEFAULT NULL,
		action varchar(100) NOT NULL,
		entity_type varchar(50) NOT NULL,
		entity_id bigint(20) UNSIGNED DEFAULT NULL,
		old_values longtext DEFAULT NULL,
		new_values longtext DEFAULT NULL,
		ip_address varchar(45) DEFAULT NULL,
		user_agent varchar(500) DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_user (user_id),
		KEY idx_action (action),
		KEY idx_entity (entity_type, entity_id),
		KEY idx_created (created_at),
		KEY fk_audit_customer (customer_id),
		KEY fk_audit_user (user_id)
	) {$charset_collate};";
}
