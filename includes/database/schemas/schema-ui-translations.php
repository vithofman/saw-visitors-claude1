<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_ui_translations($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		translation_key VARCHAR(100) NOT NULL,
		language_code VARCHAR(10) NOT NULL,
		context ENUM('terminal', 'invitation', 'admin', 'common') NOT NULL,
		section VARCHAR(50) NULL,
		translation_text TEXT NOT NULL,
		description VARCHAR(255) NULL,
		placeholders VARCHAR(255) NULL,
		created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uk_translation (translation_key, language_code, context, section),
		KEY idx_lookup (language_code, context, section),
		KEY idx_context (context),
		KEY idx_section (section),
		KEY idx_key (translation_key)
	) {$charset_collate} COMMENT='UI překlady pro terminal, invitation a admin';";
}
