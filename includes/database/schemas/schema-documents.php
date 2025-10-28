<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_documents($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		language VARCHAR(5) NOT NULL,
		category ENUM('evacuation', 'fire', 'first_aid', 'hazmat', 'ppe', 'general', 'other') DEFAULT 'general',
		title VARCHAR(255) NOT NULL,
		description TEXT DEFAULT NULL,
		file_path VARCHAR(500) NOT NULL,
		file_size BIGINT UNSIGNED DEFAULT NULL,
		mime_type VARCHAR(100) DEFAULT NULL,
		display_order INT DEFAULT 0,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_language (language),
		KEY idx_category (customer_id, category),
		KEY idx_order (customer_id, display_order),
		CONSTRAINT fk_document_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='Dokumenty';";
}
