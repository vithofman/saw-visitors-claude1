<?php
if (!defined('ABSPATH')) { exit; }
function saw_get_schema_oopp_groups($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
		code VARCHAR(10) NOT NULL COMMENT 'I, II, III, IV, V, VI, VII, VIII',
		name VARCHAR(255) NOT NULL,
		display_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uk_code (code),
		KEY idx_order (display_order)
	) {$charset_collate} COMMENT='Skupiny OOPP dle nařízení vlády';";
}

