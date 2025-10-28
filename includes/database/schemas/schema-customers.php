<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_customers($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		name VARCHAR(255) NOT NULL,
		ico VARCHAR(20) DEFAULT NULL,
		address TEXT DEFAULT NULL,
		notes TEXT DEFAULT NULL,
		logo_url VARCHAR(500) DEFAULT NULL,
		primary_color VARCHAR(7) DEFAULT '#1e40af',
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_name (name(50)),
		KEY idx_ico (ico)
	) {$charset_collate} COMMENT='Zákazníci (multi-tenant root)';";
}