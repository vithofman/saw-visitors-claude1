<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_contact_persons($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		first_name VARCHAR(100) NOT NULL,
		last_name VARCHAR(100) NOT NULL,
		position VARCHAR(100) DEFAULT NULL,
		email VARCHAR(255) DEFAULT NULL,
		phone VARCHAR(50) DEFAULT NULL,
		display_order INT DEFAULT 0,
		is_visible TINYINT(1) DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_visible (customer_id, is_visible),
		CONSTRAINT fk_contact_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='Kontaktní osoby zákazníka';";
}
