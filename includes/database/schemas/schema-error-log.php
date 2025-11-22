<?php
if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_error_log($table_name, $prefix, $charset_collate) {
    $customers_table = $prefix . 'saw_customers';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id BIGINT(20) UNSIGNED DEFAULT NULL,
        error_level ENUM('debug', 'info', 'warning', 'error', 'critical') DEFAULT 'error',
        message TEXT NOT NULL,
        context LONGTEXT DEFAULT NULL,
        stack_trace LONGTEXT DEFAULT NULL,
        file VARCHAR(500) DEFAULT NULL,
        line INT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_customer (customer_id),
        KEY idx_level (error_level),
        KEY idx_created (created_at)
    ) {$charset_collate} COMMENT='Error logging';";
}