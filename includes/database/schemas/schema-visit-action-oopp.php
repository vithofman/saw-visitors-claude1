<?php
/**
 * SAW Visit Action OOPP Schema
 * 
 * Pivot table linking visits to action-specific OOPP items.
 *
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_visit_action_oopp($table_name, $prefix, $charset_collate) {
    $visits_table = $prefix . 'visits';
    $oopp_table = $prefix . 'oopp';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        visit_id BIGINT(20) UNSIGNED NOT NULL,
        oopp_id BIGINT(20) UNSIGNED NOT NULL,
        
        -- Vlastnosti přiřazení
        is_required TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = povinné, 0 = doporučené',
        sort_order INT UNSIGNED NOT NULL DEFAULT 0,
        note VARCHAR(255) NULL COMMENT 'Volitelná poznámka k OOPP',
        
        -- Audit
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(255) NULL,
        
        PRIMARY KEY (id),
        
        -- Unikátní kombinace
        UNIQUE KEY uk_visit_oopp (visit_id, oopp_id),
        
        -- Indexy
        KEY idx_visit (visit_id),
        KEY idx_oopp (oopp_id),
        KEY idx_sort (visit_id, sort_order),
        
        -- Foreign keys
        CONSTRAINT fk_action_oopp_visit 
            FOREIGN KEY (visit_id) 
            REFERENCES {$visits_table}(id) 
            ON DELETE CASCADE,
        CONSTRAINT fk_action_oopp_oopp 
            FOREIGN KEY (oopp_id) 
            REFERENCES {$oopp_table}(id) 
            ON DELETE CASCADE
    ) {$charset_collate} COMMENT='Vazba návštěv na specifické OOPP';";
}

