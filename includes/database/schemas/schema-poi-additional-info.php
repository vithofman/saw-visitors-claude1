<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_poi_additional_info($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	$pois_table = $prefix . 'pois';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		poi_id BIGINT(20) UNSIGNED NOT NULL,
		language VARCHAR(5) NOT NULL,
		info_type ENUM('hours', 'pricing', 'contact', 'accessibility', 'facilities', 'custom') DEFAULT 'custom',
		title VARCHAR(255) NOT NULL,
		content LONGTEXT NOT NULL,
		structured_data LONGTEXT DEFAULT NULL,
		icon VARCHAR(100) DEFAULT NULL,
		display_order INT DEFAULT 0,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_poi (poi_id),
		KEY idx_language (language),
		KEY idx_type (customer_id, info_type),
		KEY idx_order (poi_id, display_order),
		KEY idx_active (customer_id, is_active),
		CONSTRAINT fk_poiinfo_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_poiinfo_poi FOREIGN KEY (poi_id) REFERENCES {$pois_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='POI dodatečné informace';";
}
