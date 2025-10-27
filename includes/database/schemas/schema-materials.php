<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_materials( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) UNSIGNED NOT NULL,
		title varchar(255) NOT NULL,
		type enum('video','pdf','wysiwyg') NOT NULL,
		content_cs longtext DEFAULT NULL,
		content_en longtext DEFAULT NULL,
		content_de longtext DEFAULT NULL,
		file_url varchar(500) DEFAULT NULL,
		display_order int(11) NOT NULL DEFAULT 0,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_type (type),
		KEY idx_order (display_order),
		KEY fk_material_customer (customer_id)
	) {$charset_collate};";
}
