<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_visitor_certificates($table_name, $prefix, $charset_collate) {
	$visitors_table = $prefix . 'visitors';
	$customers_table = $prefix . 'customers';
	$branches_table = $prefix . 'branches';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		visitor_id BIGINT(20) UNSIGNED NOT NULL,
		
		-- ✅ PŘIDÁNO: customer_id a branch_id pro data isolation
		customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Denormalizace z visitors',
		branch_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Denormalizace z visitors',
		
		certificate_name VARCHAR(200) NOT NULL,
		certificate_number VARCHAR(100) NULL,
		valid_until DATE NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_visitor (visitor_id),
		KEY idx_customer (customer_id),
		KEY idx_branch (branch_id),
		KEY idx_customer_branch (customer_id, branch_id),
		KEY idx_valid (valid_until)
	) {$charset_collate};";
}
