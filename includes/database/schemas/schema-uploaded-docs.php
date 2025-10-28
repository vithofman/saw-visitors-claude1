<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_uploaded_docs($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		uploader_type ENUM('admin', 'visitor') NOT NULL,
		uploader_id BIGINT(20) UNSIGNED DEFAULT NULL,
		file_path VARCHAR(500) NOT NULL,
		file_name VARCHAR(255) NOT NULL,
		file_size BIGINT UNSIGNED DEFAULT NULL,
		mime_type VARCHAR(100) DEFAULT NULL,
		document_type VARCHAR(100) DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_uploader (uploader_type, uploader_id),
		CONSTRAINT fk_upload_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='Nahrané soubory';";
}
