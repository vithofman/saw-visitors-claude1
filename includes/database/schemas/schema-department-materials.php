<?php
/**
 * Schema: saw_department_materials
 * WYSIWYG školící materiály pro oddělení - DYNAMICKÉ JAZYKY
 * @version 4.6.1
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function saw_get_schema_department_materials( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$departments_table = $prefix . 'departments';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		department_id BIGINT(20) UNSIGNED NOT NULL,
		language VARCHAR(5) NOT NULL COMMENT 'cs, en, de...',
		title VARCHAR(255) NOT NULL,
		content LONGTEXT DEFAULT NULL COMMENT 'WYSIWYG HTML obsah',
		display_order INT DEFAULT 0,
		is_active TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_dept_language_order (department_id, language, display_order),
		KEY idx_customer (customer_id),
		KEY idx_department (department_id),
		KEY idx_language (language),
		CONSTRAINT fk_deptmat_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_deptmat_dept FOREIGN KEY (department_id) REFERENCES {$departments_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='Školící materiály oddělení (WYSIWYG)';";
}
