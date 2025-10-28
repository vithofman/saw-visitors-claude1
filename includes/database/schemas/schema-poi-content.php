<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_poi_content($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	$pois_table = $prefix . 'pois';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		poi_id BIGINT(20) UNSIGNED NOT NULL,
		language VARCHAR(5) NOT NULL,
		title VARCHAR(255) NOT NULL,
		subtitle VARCHAR(255) DEFAULT NULL,
		description LONGTEXT DEFAULT NULL,
		safety_instructions LONGTEXT DEFAULT NULL,
		interesting_facts TEXT DEFAULT NULL,
		technical_specs TEXT DEFAULT NULL,
		meta_description VARCHAR(500) DEFAULT NULL,
		is_published TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_poi_language (customer_id, poi_id, language),
		KEY idx_customer (customer_id),
		KEY idx_poi (poi_id),
		KEY idx_language (language),
		KEY idx_published (customer_id, is_published),
		CONSTRAINT fk_poicontent_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_poicontent_poi FOREIGN KEY (poi_id) REFERENCES {$pois_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='POI obsah';";
}
