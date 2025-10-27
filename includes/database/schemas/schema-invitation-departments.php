<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function saw_get_schema_invitation_departments( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$invitations_table = $prefix . 'invitations';
	$departments_table = $prefix . 'departments';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		invitation_id BIGINT(20) UNSIGNED NOT NULL,
		department_id BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_inv_dept (invitation_id, department_id),
		KEY idx_customer (customer_id),
		KEY idx_invitation (invitation_id),
		KEY idx_department (department_id),
		CONSTRAINT fk_invdept_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_invdept_inv FOREIGN KEY (invitation_id) REFERENCES {$invitations_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_invdept_dept FOREIGN KEY (department_id) REFERENCES {$departments_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='M:N: pozvánky ↔ oddělení';";
}
