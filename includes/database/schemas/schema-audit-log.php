<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_audit_log($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	$users_table = $prefix . 'users';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		user_id BIGINT(20) UNSIGNED DEFAULT NULL,
		action VARCHAR(100) NOT NULL,
		entity_type VARCHAR(50) NOT NULL,
		entity_id BIGINT UNSIGNED DEFAULT NULL,
		old_values LONGTEXT DEFAULT NULL,
		new_values LONGTEXT DEFAULT NULL,
		ip_address VARCHAR(45) DEFAULT NULL,
		user_agent VARCHAR(500) DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_user (user_id),
		KEY idx_action (action),
		KEY idx_entity (entity_type, entity_id),
		KEY idx_created (created_at),
		CONSTRAINT fk_audit_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES {$users_table}(id) ON DELETE SET NULL
	) {$charset_collate} COMMENT='Audit log';";
}
