<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function saw_get_schema_uploaded_docs( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		uploader_type ENUM('admin', 'visitor') NOT NULL,
		uploader_id BIGINT UNSIGNED DEFAULT NULL,
		file_path VARCHAR(500) NOT NULL,
		file_size BIGINT UNSIGNED DEFAULT NULL,
		mime_type VARCHAR(100) DEFAULT NULL,
		original_filename VARCHAR(255) DEFAULT NULL,
		category ENUM('risk_document', 'visitor_upload', 'training_material', 'other') DEFAULT 'other',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_uploader (uploader_type, uploader_id),
		KEY idx_category (customer_id, category),
		CONSTRAINT fk_upload_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='Nahrané soubory (admin + visitor)';";
}
