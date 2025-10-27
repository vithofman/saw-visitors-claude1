<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_invitation_departments( $table_name, $prefix, $charset_collate ) {
	$invitations_table = $prefix . 'invitations';
	$departments_table = $prefix . 'departments';
	
	return "CREATE TABLE {$table_name} (
		invitation_id bigint(20) UNSIGNED NOT NULL,
		department_id bigint(20) UNSIGNED NOT NULL,
		PRIMARY KEY (invitation_id, department_id),
		KEY idx_department (department_id),
		KEY fk_invdept_invitation (invitation_id),
		KEY fk_invdept_dept (department_id)
	) {$charset_collate};";
}
