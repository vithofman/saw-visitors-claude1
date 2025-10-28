<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_departments($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		name VARCHAR(255) NOT NULL,
		description TEXT DEFAULT NULL,
		training_version INT UNSIGNED NOT NULL DEFAULT 1,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_active (customer_id, is_active),
		KEY idx_name (customer_id, name(50)),
		CONSTRAINT fk_dept_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='Oddělení zákazníka';";
}
