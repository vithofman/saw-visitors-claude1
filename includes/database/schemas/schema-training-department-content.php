<?php
if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_training_department_content($table_name, $prefix, $charset_collate) {
    $training_content_table = $prefix . 'saw_training_content';
    $departments_table = $prefix . 'saw_departments';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id BIGINT(20) UNSIGNED NOT NULL,
        branch_id BIGINT(20) UNSIGNED NOT NULL,
        training_content_id BIGINT(20) UNSIGNED NOT NULL,
        department_id BIGINT(20) UNSIGNED NOT NULL,
        text_content LONGTEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_dept_content (training_content_id, department_id),
        KEY idx_customer_branch (customer_id, branch_id),
        KEY idx_customer (customer_id),
        KEY idx_branch (branch_id),
        KEY idx_training_content (training_content_id),
        KEY idx_department (department_id),
        CONSTRAINT fk_dept_content_training FOREIGN KEY (training_content_id) REFERENCES {$training_content_table}(id) ON DELETE CASCADE,
        CONSTRAINT fk_dept_content_department FOREIGN KEY (department_id) REFERENCES {$departments_table}(id) ON DELETE CASCADE
    ) {$charset_collate} COMMENT='Obsah školení pro oddělení';";
}