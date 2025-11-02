<?php
if (!defined('ABSPATH')) { exit; }
function saw_get_schema_training_language_branches($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		language_id BIGINT(20) UNSIGNED NOT NULL,
		branch_id BIGINT(20) UNSIGNED NOT NULL,
		is_default TINYINT(1) NOT NULL DEFAULT 0,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		display_order INT NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uk_lang_branch (language_id, branch_id),
		KEY idx_branch (branch_id),
		KEY idx_active (branch_id, is_active)
	) {$charset_collate} COMMENT='Language activation per branch';";
}