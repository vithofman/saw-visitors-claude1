<?php
if (!defined('ABSPATH')) { exit; }
function saw_get_schema_branches($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		name VARCHAR(255) NOT NULL,
		code VARCHAR(50) NULL,
		street VARCHAR(255) NULL,
		city VARCHAR(100) NULL,
		postal_code VARCHAR(20) NULL,
		country VARCHAR(2) NULL DEFAULT 'CZ',
		latitude DECIMAL(10, 8) NULL,
		longitude DECIMAL(11, 8) NULL,
		phone VARCHAR(50) NULL,
		email VARCHAR(100) NULL,
		image_url VARCHAR(500) NULL,
		image_thumbnail VARCHAR(500) NULL,
		notes TEXT NULL,
		description TEXT NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		is_headquarters TINYINT(1) NOT NULL DEFAULT 0,
		sort_order INT NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		created_by VARCHAR(255) NULL COMMENT 'Email uživatele, který vytvořil záznam',
		updated_by VARCHAR(255) NULL COMMENT 'Email uživatele, který naposledy aktualizoval záznam',
		PRIMARY KEY (id),
		UNIQUE KEY idx_customer_code (customer_id, code),
		KEY idx_customer (customer_id),
		KEY idx_active (is_active),
		KEY idx_headquarters (is_headquarters),
		KEY idx_sort (customer_id, sort_order)
	) {$charset_collate} COMMENT='Customer branches';";
}