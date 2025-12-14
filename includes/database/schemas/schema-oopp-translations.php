<?php
if (!defined('ABSPATH')) { exit; }
function saw_get_schema_oopp_translations($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		oopp_id BIGINT(20) UNSIGNED NOT NULL,
		language_code VARCHAR(10) NOT NULL,
		name VARCHAR(255) NOT NULL,
		standards TEXT NULL,
		risk_description TEXT NULL,
		protective_properties TEXT NULL,
		usage_instructions TEXT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uk_oopp_language (oopp_id, language_code),
		KEY idx_oopp (oopp_id),
		KEY idx_language (language_code)
	) {$charset_collate} COMMENT='OOPP překlady';";
}

