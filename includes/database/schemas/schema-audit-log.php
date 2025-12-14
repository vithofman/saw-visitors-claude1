<?php
if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_audit_log($table_name, $prefix, $charset_collate) {
    $customers_table = $prefix . 'saw_customers';
    $users_table = $prefix . 'saw_users';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id BIGINT(20) UNSIGNED NOT NULL,
        branch_id BIGINT(20) UNSIGNED DEFAULT NULL,
        user_id BIGINT(20) UNSIGNED DEFAULT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) NOT NULL,
        entity_id BIGINT UNSIGNED DEFAULT NULL,
        old_values LONGTEXT DEFAULT NULL,
        new_values LONGTEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        details TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_customer (customer_id),
        KEY idx_branch (branch_id),
        KEY idx_user (user_id),
		KEY idx_action (action),
		KEY idx_entity (entity_type, entity_id),
		KEY idx_created (created_at)
	) {$charset_collate} COMMENT='Audit log';";
}