<?php
if (!defined('ABSPATH')) { exit; }
function saw_get_schema_training_languages($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		language_code VARCHAR(10) NOT NULL,
		language_name VARCHAR(50) NOT NULL,
		flag_emoji VARCHAR(10) NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		created_by VARCHAR(255) NULL COMMENT 'Email uživatele, který vytvořil záznam',
		updated_by VARCHAR(255) NULL COMMENT 'Email uživatele, který naposledy aktualizoval záznam',
		PRIMARY KEY (id),
		UNIQUE KEY uk_customer_code (customer_id, language_code),
		KEY idx_customer (customer_id)
	) {$charset_collate} COMMENT='Training content languages';";
}