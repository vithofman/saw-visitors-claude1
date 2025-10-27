<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_contact_persons( $table_name, $prefix, $charset_collate ) {
	$departments_table = $prefix . 'departments';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		department_id bigint(20) UNSIGNED NOT NULL,
		name varchar(255) NOT NULL,
		phone varchar(50) DEFAULT NULL,
		email varchar(255) DEFAULT NULL,
		position varchar(100) DEFAULT NULL,
		is_active tinyint(1) NOT NULL DEFAULT 1,
		display_order int(11) NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_department (department_id),
		KEY idx_order (display_order),
		KEY fk_contact_dept (department_id)
	) {$charset_collate};";
}
