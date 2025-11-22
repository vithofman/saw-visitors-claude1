<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_visit_daily_logs($table_name, $prefix, $charset_collate) {
	$visits_table = $prefix . 'visits';
	$visitors_table = $prefix . 'visitors';
	$customers_table = $prefix . 'customers';
	$branches_table = $prefix . 'branches';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		visit_id BIGINT(20) UNSIGNED NOT NULL,
		visitor_id BIGINT(20) UNSIGNED NOT NULL,
		
		-- ✅ PŘIDÁNO: customer_id a branch_id pro data isolation
		customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Denormalizace z visits/visitors',
		branch_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Denormalizace z visits/visitors',
		
		log_date DATE NOT NULL COMMENT 'Den (2025-01-14)',
		
		checked_in_at DATETIME NULL COMMENT 'Kdy přišel DNES',
		checked_out_at DATETIME NULL COMMENT 'Kdy odešel DNES',
		
		manual_checkout TINYINT(1) DEFAULT 0,
		manual_checkout_by BIGINT(20) UNSIGNED NULL,
		manual_checkout_reason TEXT NULL,
		
		notes TEXT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_visit_visitor_date (visit_id, visitor_id, log_date),
		KEY idx_visit (visit_id),
		KEY idx_visitor (visitor_id),
		KEY idx_customer (customer_id),
		KEY idx_branch (branch_id),
		KEY idx_customer_branch (customer_id, branch_id),
		KEY idx_date (log_date),
		KEY idx_active (log_date, checked_in_at, checked_out_at)
	) {$charset_collate};";
}