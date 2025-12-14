<?php
if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_training_documents($table_name, $prefix, $charset_collate) {
    $document_types_table = $prefix . 'saw_training_document_types';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        customer_id BIGINT(20) UNSIGNED NOT NULL,
        branch_id BIGINT(20) UNSIGNED NOT NULL,
        document_type ENUM('risks','additional','department') NOT NULL,
        document_type_id BIGINT(20) UNSIGNED DEFAULT NULL,
        reference_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'ID z training_content (risks/additional) nebo training_department_content (department)',
        file_path VARCHAR(500) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_size BIGINT(20) UNSIGNED DEFAULT NULL,
        mime_type VARCHAR(100) DEFAULT NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by VARCHAR(255) NULL COMMENT 'Email uživatele, který vytvořil záznam',
        updated_by VARCHAR(255) NULL COMMENT 'Email uživatele, který naposledy aktualizoval záznam',
        PRIMARY KEY (id),
        KEY idx_customer_branch (customer_id, branch_id),
        KEY idx_customer (customer_id),
        KEY idx_branch (branch_id),
        KEY idx_document_type (document_type),
		KEY idx_document_type_id (document_type_id),
		KEY idx_reference (reference_id),
		KEY idx_type_reference (document_type, reference_id)
	) {$charset_collate} COMMENT='Dokumenty pro školení';";
}