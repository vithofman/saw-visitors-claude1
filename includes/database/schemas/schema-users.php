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
        context_customer_id BIGINT(20) UNSIGNED NULL COMMENT 'Aktivní zákazník (pro super_admin switcher)',
        context_branch_id BIGINT(20) UNSIGNED NULL COMMENT 'Aktivní pobočka (pro všechny kromě manager)',
        branch_id BIGINT(20) UNSIGNED NULL,
        email VARCHAR(255) NOT NULL,
        first_name VARCHAR(100) NULL,
        last_name VARCHAR(100) NULL,
        position VARCHAR(100) NULL COMMENT 'Funkce/pozice uživatele',
        role ENUM('super_admin', 'admin', 'super_manager', 'manager', 'terminal') NOT NULL,
        language VARCHAR(5) DEFAULT 'cs' COMMENT 'Jazyk uživatelského rozhraní (cs, en)',
        pin VARCHAR(255) NULL COMMENT 'Hashed PIN pro terminál',
        password_setup_token VARCHAR(64) NULL COMMENT 'Token pro první nastavení hesla',
        password_setup_expires DATETIME NULL COMMENT 'Platnost setup tokenu',
        password_reset_token VARCHAR(64) NULL COMMENT 'Token pro reset hesla',
        password_reset_expires DATETIME NULL COMMENT 'Platnost reset tokenu',
        password_set_at DATETIME NULL COMMENT 'Kdy si poprvé nastavil heslo',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        last_login DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_wp_user (wp_user_id),
        UNIQUE KEY idx_customer_email (customer_id, email),
        KEY idx_customer (customer_id),
        KEY idx_context_customer (context_customer_id),
        KEY idx_context_branch (context_branch_id),
        KEY idx_branch (branch_id),
        KEY idx_customer_branch (customer_id, branch_id),
        KEY idx_role (role),
        KEY idx_active (is_active),
        KEY idx_password_setup_token (password_setup_token),
        KEY idx_password_reset_token (password_reset_token)
    ) {$charset_collate} COMMENT='SAW uživatelé s vazbou na WP';";
}