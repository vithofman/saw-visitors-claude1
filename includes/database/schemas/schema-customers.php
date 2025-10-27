<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_customers( $table_name, $prefix, $charset_collate ) {
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name varchar(255) NOT NULL,
		ico varchar(20) DEFAULT NULL,
		address text DEFAULT NULL,
		logo_url varchar(500) DEFAULT NULL,
		primary_color varchar(7) DEFAULT '#1e40af',
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_name (name(50)),
		KEY idx_ico (ico)
	) {$charset_collate};";
}
