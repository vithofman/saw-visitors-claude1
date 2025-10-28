<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_routes($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		name VARCHAR(255) NOT NULL,
		description TEXT DEFAULT NULL,
		route_type ENUM('tour', 'emergency', 'maintenance', 'custom') DEFAULT 'tour',
		estimated_duration INT UNSIGNED DEFAULT NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_type (customer_id, route_type),
		KEY idx_active (customer_id, is_active),
		CONSTRAINT fk_route_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='Trasy';";
}
