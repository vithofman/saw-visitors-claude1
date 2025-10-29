<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_account_types($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(50) NOT NULL,
		display_name VARCHAR(100) NOT NULL,
		color VARCHAR(7) NOT NULL DEFAULT '#6b7280',
		price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
		features TEXT DEFAULT NULL,
		sort_order INT(11) NOT NULL DEFAULT 0,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY name (name),
		KEY idx_active (is_active),
		KEY idx_sort (sort_order)
	) {$charset_collate};";
}