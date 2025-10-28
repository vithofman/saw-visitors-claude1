<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_customer_api_keys($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		api_key VARCHAR(64) NOT NULL UNIQUE,
		key_name VARCHAR(100) DEFAULT NULL,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		last_used_at DATETIME DEFAULT NULL,
		request_count BIGINT(20) UNSIGNED DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		expires_at DATETIME DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY idx_api_key (api_key),
		KEY idx_customer (customer_id),
		KEY idx_active (customer_id, is_active),
		CONSTRAINT fk_apikey_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='API klíče pro zákazníky';";
}
