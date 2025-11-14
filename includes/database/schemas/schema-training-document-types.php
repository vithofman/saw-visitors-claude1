<?php
if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_training_document_types($table_name, $prefix, $charset_collate) {
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT DEFAULT NULL,
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_sort (sort_order)
    ) {$charset_collate} COMMENT='Typy dokumentů pro školení';";
}