<?php
/**
 * SAW Visit Action Documents Schema
 * 
 * Stores documents attached to visit-specific actions.
 *
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_visit_action_documents($table_name, $prefix, $charset_collate) {
    $visits_table = $prefix . 'visits';
    $customers_table = $prefix . 'customers';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        visit_id BIGINT(20) UNSIGNED NOT NULL,
        customer_id BIGINT(20) UNSIGNED NOT NULL,
        
        -- Soubor
        file_path VARCHAR(500) NOT NULL COMMENT 'Relativní cesta od wp-content/uploads',
        file_name VARCHAR(255) NOT NULL COMMENT 'Původní název souboru',
        file_size BIGINT(20) UNSIGNED NULL COMMENT 'Velikost v bytes',
        mime_type VARCHAR(100) NULL,
        
        -- Řazení
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        
        -- Audit
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        uploaded_by VARCHAR(255) NULL COMMENT 'Email uživatele',
        
        PRIMARY KEY (id),
        
        -- Indexy
        KEY idx_visit (visit_id),
        KEY idx_customer (customer_id),
        KEY idx_sort (visit_id, sort_order),
        
        -- Foreign keys
        CONSTRAINT fk_action_docs_visit 
            FOREIGN KEY (visit_id) 
            REFERENCES {$visits_table}(id) 
            ON DELETE CASCADE,
        CONSTRAINT fk_action_docs_customer 
            FOREIGN KEY (customer_id) 
            REFERENCES {$customers_table}(id) 
            ON DELETE CASCADE
    ) {$charset_collate} COMMENT='Dokumenty ke specifickým akcím návštěv';";
}

