<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_departments( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) UNSIGNED NOT NULL,
		name varchar(255) NOT NULL,
		description text DEFAULT NULL,
		training_version int(11) UNSIGNED NOT NULL DEFAULT 1,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_active (is_active),
		KEY fk_dept_customer (customer_id)
	) {$charset_collate};";
}
