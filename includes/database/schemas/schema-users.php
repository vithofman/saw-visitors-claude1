<?php
if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_users($table_name, $prefix, $wp_users_table, $charset_collate) {
    $customers_table = $prefix . 'saw_customers';
    $branches_table = $prefix . 'saw_branches';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        wp_user_id BIGINT(20) UNSIGNED NOT NULL,
        customer_id BIGINT(20) UNSIGNED NULL,
        branch_id BIGINT(20) UNSIGNED NULL,
        email VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NULL,
        last_name VARCHAR(100) NULL,
        role ENUM('super_admin', 'admin', 'super_manager', 'manager', 'terminal') NOT NULL,
        pin VARCHAR(255) NULL COMMENT 'Hashed PIN pro terminál',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_wp_user (wp_user_id),
        UNIQUE KEY idx_customer_email (customer_id, email),
        KEY idx_customer (customer_id),
        KEY idx_branch (branch_id),
        KEY idx_customer_branch (customer_id, branch_id),
        KEY idx_role (role),
        KEY idx_active (is_active)
    ) {$charset_collate} COMMENT='SAW uživatelé s vazbou na WP';";
}