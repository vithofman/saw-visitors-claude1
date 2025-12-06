<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_ui_languages($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		language_code VARCHAR(10) NOT NULL,
		language_name VARCHAR(100) NOT NULL,
		native_name VARCHAR(100) NOT NULL,
		flag_emoji VARCHAR(10) NOT NULL,
		is_default TINYINT(1) NOT NULL DEFAULT 0,
		is_fallback TINYINT(1) NOT NULL DEFAULT 0,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		sort_order INT NOT NULL DEFAULT 0,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uk_code (language_code),
		KEY idx_active (is_active),
		KEY idx_sort (sort_order)
	) {$charset_collate} COMMENT='Systémové jazyky pro UI překlady';";
}
