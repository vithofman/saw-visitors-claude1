<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_poi_media($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	$pois_table = $prefix . 'pois';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		poi_id BIGINT(20) UNSIGNED NOT NULL,
		media_type ENUM('image', 'audio', 'video', 'youtube', 'vimeo') NOT NULL,
		language VARCHAR(5) DEFAULT NULL,
		file_path VARCHAR(500) NOT NULL,
		file_url VARCHAR(1000) DEFAULT NULL,
		file_size BIGINT UNSIGNED DEFAULT NULL,
		mime_type VARCHAR(100) DEFAULT NULL,
		external_url VARCHAR(1000) DEFAULT NULL,
		external_id VARCHAR(100) DEFAULT NULL,
		title VARCHAR(255) DEFAULT NULL,
		description TEXT DEFAULT NULL,
		alt_text VARCHAR(500) DEFAULT NULL,
		duration_seconds INT UNSIGNED DEFAULT NULL,
		transcript LONGTEXT DEFAULT NULL,
		width INT UNSIGNED DEFAULT NULL,
		height INT UNSIGNED DEFAULT NULL,
		thumbnail_path VARCHAR(500) DEFAULT NULL,
		display_order INT DEFAULT 0,
		is_featured TINYINT(1) DEFAULT 0,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_poi (poi_id),
		KEY idx_type (customer_id, media_type),
		KEY idx_language (language),
		KEY idx_featured (customer_id, poi_id, is_featured),
		KEY idx_order (poi_id, display_order),
		CONSTRAINT fk_poimedia_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_poimedia_poi FOREIGN KEY (poi_id) REFERENCES {$pois_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='POI média';";
}
