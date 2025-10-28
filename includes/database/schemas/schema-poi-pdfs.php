<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_poi_pdfs($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	$pois_table = $prefix . 'pois';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		poi_id BIGINT(20) UNSIGNED NOT NULL,
		language VARCHAR(5) NOT NULL,
		document_type ENUM('manual', 'specification', 'safety', 'certificate', 'diagram', 'other') DEFAULT 'other',
		title VARCHAR(255) NOT NULL,
		description TEXT DEFAULT NULL,
		file_path VARCHAR(500) NOT NULL,
		file_url VARCHAR(1000) DEFAULT NULL,
		file_size BIGINT UNSIGNED DEFAULT NULL,
		file_hash VARCHAR(64) DEFAULT NULL,
		page_count INT UNSIGNED DEFAULT NULL,
		is_downloadable TINYINT(1) DEFAULT 1,
		requires_auth TINYINT(1) DEFAULT 0,
		version VARCHAR(20) DEFAULT NULL,
		display_order INT DEFAULT 0,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_poi (poi_id),
		KEY idx_language (language),
		KEY idx_type (customer_id, document_type),
		KEY idx_hash (file_hash),
		KEY idx_order (poi_id, display_order),
		CONSTRAINT fk_poipdf_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_poipdf_poi FOREIGN KEY (poi_id) REFERENCES {$pois_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='POI PDF dokumenty';";
}
