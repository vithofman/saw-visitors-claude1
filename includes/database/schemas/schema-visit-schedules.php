<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_visit_schedules($table_name, $prefix, $charset_collate) {
	$visits_table = $prefix . 'visits';
	$customers_table = $prefix . 'customers';
	$branches_table = $prefix . 'branches';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		visit_id BIGINT(20) UNSIGNED NOT NULL,
		
		-- ✅ PŘIDÁNO: customer_id a branch_id pro data isolation
		customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Denormalizace z visits',
		branch_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Denormalizace z visits',
		
		date DATE NOT NULL,
		time_from TIME NULL,
		time_to TIME NULL,
		sort_order INT UNSIGNED NOT NULL DEFAULT 0,
		notes TEXT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uk_visit_date (visit_id, date),
		KEY idx_visit (visit_id),
		KEY idx_customer (customer_id),
		KEY idx_branch (branch_id),
		KEY idx_customer_branch (customer_id, branch_id),
		KEY idx_date (date),
		KEY idx_sort (visit_id, sort_order)
	) {$charset_collate};";
}