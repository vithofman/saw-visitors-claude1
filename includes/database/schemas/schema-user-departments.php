<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_user_departments($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	$users_table = $prefix . 'users';
	$departments_table = $prefix . 'departments';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		user_id BIGINT(20) UNSIGNED NOT NULL,
		department_id BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_user_dept (user_id, department_id),
		KEY idx_customer (customer_id),
		KEY idx_user (user_id),
		KEY idx_department (department_id),
		CONSTRAINT fk_userdept_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_userdept_user FOREIGN KEY (user_id) REFERENCES {$users_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_userdept_dept FOREIGN KEY (department_id) REFERENCES {$departments_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='M:N user departments';";
}
