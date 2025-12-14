<?php
/**
 * SAW Visit Action Info Schema
 * 
 * Stores action-specific information for visits.
 * One visit can have at most one action info record.
 *
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_visit_action_info($table_name, $prefix, $charset_collate) {
    $visits_table = $prefix . 'visits';
    $customers_table = $prefix . 'customers';
    $branches_table = $prefix . 'branches';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        visit_id BIGINT(20) UNSIGNED NOT NULL,
        
        -- Denormalizace pro data isolation
        customer_id BIGINT(20) UNSIGNED NOT NULL,
        branch_id BIGINT(20) UNSIGNED NOT NULL,
        
        -- Obsah (HTML z WYSIWYG editoru)
        content_text LONGTEXT NULL COMMENT 'Specifické pokyny pro akci (HTML)',
        
        -- Audit
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        created_by VARCHAR(255) NULL COMMENT 'Email uživatele',
        updated_by VARCHAR(255) NULL COMMENT 'Email uživatele',
        
        PRIMARY KEY (id),
        
        -- Jedna návštěva = max jedna akce info
        UNIQUE KEY uk_visit (visit_id),
        
        -- Indexy
        KEY idx_customer (customer_id),
        KEY idx_branch (branch_id),
        KEY idx_customer_branch (customer_id, branch_id),
        
        -- Foreign keys
        CONSTRAINT fk_action_info_visit 
            FOREIGN KEY (visit_id) 
            REFERENCES {$visits_table}(id) 
            ON DELETE CASCADE,
        CONSTRAINT fk_action_info_customer 
            FOREIGN KEY (customer_id) 
            REFERENCES {$customers_table}(id) 
            ON DELETE CASCADE,
        CONSTRAINT fk_action_info_branch 
            FOREIGN KEY (branch_id) 
            REFERENCES {$branches_table}(id) 
            ON DELETE CASCADE
    ) {$charset_collate} COMMENT='Specifické informace pro návštěvy - text';";
}

