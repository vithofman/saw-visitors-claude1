<?php
if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_training_content($table_name, $prefix, $charset_collate) {
    $customers_table = $prefix . 'saw_customers';
    $branches_table = $prefix . 'saw_branches';
    $languages_table = $prefix . 'saw_training_languages';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id BIGINT(20) UNSIGNED NOT NULL,
        branch_id BIGINT(20) UNSIGNED NOT NULL,
        language_id BIGINT(20) UNSIGNED NOT NULL,
        video_url VARCHAR(500) DEFAULT NULL,
        pdf_map_path VARCHAR(500) DEFAULT NULL,
        risks_text LONGTEXT DEFAULT NULL,
        additional_text LONGTEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_content (customer_id, branch_id, language_id),
        KEY idx_customer (customer_id),
        KEY idx_branch (branch_id),
        KEY idx_language (language_id),
        CONSTRAINT fk_training_content_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
        CONSTRAINT fk_training_content_branch FOREIGN KEY (branch_id) REFERENCES {$branches_table}(id) ON DELETE CASCADE,
        CONSTRAINT fk_training_content_language FOREIGN KEY (language_id) REFERENCES {$languages_table}(id) ON DELETE CASCADE
    ) {$charset_collate} COMMENT='Hlavní obsah školení';";
}