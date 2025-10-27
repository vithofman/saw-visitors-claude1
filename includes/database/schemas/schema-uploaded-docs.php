<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_uploaded_docs( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) UNSIGNED NOT NULL,
		document_type enum('training_material','risk_analysis','visitor_upload','company_logo') NOT NULL,
		file_path varchar(500) NOT NULL,
		file_name varchar(255) NOT NULL,
		file_size bigint(20) UNSIGNED NOT NULL,
		mime_type varchar(100) NOT NULL,
		uploaded_by_type enum('admin','manager','visitor','system') NOT NULL,
		uploaded_by_id bigint(20) UNSIGNED DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_type (document_type),
		KEY idx_uploaded_by (uploaded_by_type, uploaded_by_id),
		KEY fk_uploaded_customer (customer_id)
	) {$charset_collate};";
}
