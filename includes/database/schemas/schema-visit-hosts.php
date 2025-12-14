<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_visit_hosts($table_name, $prefix, $charset_collate) {
	$visits_table = $prefix . 'visits';
	$users_table = $prefix . 'users';
	$customers_table = $prefix . 'customers';
	$branches_table = $prefix . 'branches';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		branch_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		visit_id BIGINT(20) UNSIGNED NOT NULL,
		user_id BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		created_by VARCHAR(255) NULL COMMENT 'Email uživatele, který vytvořil záznam',
		updated_by VARCHAR(255) NULL COMMENT 'Email uživatele, který naposledy aktualizoval záznam',
		PRIMARY KEY (id),
		UNIQUE KEY uk_visit_user (visit_id, user_id),
		KEY idx_visit (visit_id),
		KEY idx_user (user_id),
		KEY idx_customer_branch (customer_id, branch_id)
	) {$charset_collate};";
}
