<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_companies( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) UNSIGNED NOT NULL,
		name varchar(255) NOT NULL,
		ico varchar(20) DEFAULT NULL,
		address text DEFAULT NULL,
		contact_person varchar(255) DEFAULT NULL,
		contact_email varchar(255) DEFAULT NULL,
		contact_phone varchar(50) DEFAULT NULL,
		notes text DEFAULT NULL,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_name (name(50)),
		KEY idx_ico (ico),
		FULLTEXT KEY ft_name (name),
		KEY fk_company_customer (customer_id)
	) {$charset_collate};";
}
