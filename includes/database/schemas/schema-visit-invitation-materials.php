<?php
if (!defined('ABSPATH')) exit;

function saw_get_schema_visit_invitation_materials($table_name, $prefix, $charset_collate) {
    $visits_table = $prefix . 'visits';
    $customers_table = $prefix . 'customers';
    $branches_table = $prefix . 'branches';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        visit_id BIGINT(20) UNSIGNED NOT NULL,
        customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Denormalizace z visits',
        branch_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Denormalizace z visits',
        company_id BIGINT(20) UNSIGNED NULL COMMENT 'Denormalizace z visits (NULL = fyzická osoba)',
        material_type ENUM('text', 'document') NOT NULL,
        text_content LONGTEXT NULL,
        file_path VARCHAR(500) NULL COMMENT 'Relativní cesta od wp-content/uploads',
        file_name VARCHAR(255) NULL,
        file_size BIGINT(20) UNSIGNED NULL,
        mime_type VARCHAR(100) NULL,
        uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_visit (visit_id),
        KEY idx_customer (customer_id),
        KEY idx_branch (branch_id),
        KEY idx_company (company_id),
        KEY idx_customer_branch (customer_id, branch_id),
        KEY idx_material_type (material_type),
        CONSTRAINT fk_invitation_materials_visit 
            FOREIGN KEY (visit_id) 
            REFERENCES {$visits_table}(id) 
            ON DELETE CASCADE,
        CONSTRAINT fk_invitation_materials_customer 
            FOREIGN KEY (customer_id) 
            REFERENCES {$customers_table}(id) 
            ON DELETE CASCADE,
        CONSTRAINT fk_invitation_materials_branch 
            FOREIGN KEY (branch_id) 
            REFERENCES {$branches_table}(id) 
            ON DELETE CASCADE
    ) {$charset_collate} COMMENT='Materiály nahrané pozvanými - texty a dokumenty o rizicích';";
}

