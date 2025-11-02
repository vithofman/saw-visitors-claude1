<?php
if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_user_departments($table_name, $prefix, $charset_collate) {
    $users_table = $prefix . 'saw_users';
    $departments_table = $prefix . 'saw_departments';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        department_id BIGINT(20) UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_user_dept (user_id, department_id),
        KEY idx_user (user_id),
        KEY idx_department (department_id)
    ) {$charset_collate} COMMENT='Vazba uživatelů na oddělení';";
}