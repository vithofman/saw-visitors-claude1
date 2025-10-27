<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_user_departments( $table_name, $prefix, $charset_collate ) {
	$users_table = $prefix . 'users';
	$departments_table = $prefix . 'departments';
	
	return "CREATE TABLE {$table_name} (
		user_id bigint(20) UNSIGNED NOT NULL,
		department_id bigint(20) UNSIGNED NOT NULL,
		PRIMARY KEY (user_id, department_id),
		KEY idx_department (department_id),
		KEY fk_userdept_user (user_id),
		KEY fk_userdept_dept (department_id)
	) {$charset_collate};";
}
