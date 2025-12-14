<?php
/**
 * SAW Visit Action Info Translations Schema
 * 
 * Stores translations for action-specific information.
 * Each action_info can have translations in multiple languages.
 *
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_visit_action_info_translations($table_name, $prefix, $charset_collate) {
    $action_info_table = $prefix . 'visit_action_info';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        action_info_id BIGINT(20) UNSIGNED NOT NULL,
        language_code VARCHAR(10) NOT NULL,
        
        -- Přeložitelná pole
        name VARCHAR(255) NULL COMMENT 'Název akce',
        description TEXT NULL COMMENT 'Popis akce',
        content_text LONGTEXT NULL COMMENT 'Specifické pokyny (HTML z WYSIWYG)',
        
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        
        PRIMARY KEY (id),
        UNIQUE KEY uk_action_info_language (action_info_id, language_code),
        KEY idx_action_info (action_info_id),
        KEY idx_language (language_code),
        
        CONSTRAINT fk_action_info_translations 
            FOREIGN KEY (action_info_id) 
            REFERENCES {$action_info_table}(id) 
            ON DELETE CASCADE
    ) {$charset_collate} COMMENT='Překlady specifických informací pro akce';";
}

