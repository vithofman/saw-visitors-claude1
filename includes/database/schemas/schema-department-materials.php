<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_department_materials( $table_name, $prefix, $charset_collate ) {
	$departments_table = $prefix . 'departments';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		department_id bigint(20) UNSIGNED NOT NULL,
		title varchar(255) NOT NULL,
		content_cs longtext DEFAULT NULL,
		content_en longtext DEFAULT NULL,
		content_de longtext DEFAULT NULL,
		display_order int(11) NOT NULL DEFAULT 0,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_department (department_id),
		KEY idx_order (display_order),
		KEY fk_deptmat_dept (department_id)
	) {$charset_collate};";
}
