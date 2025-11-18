<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_visit_daily_logs($table_name, $prefix, $charset_collate) {
	$visits_table = $prefix . 'visits';
	$visitors_table = $prefix . 'visitors';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		visit_id BIGINT(20) UNSIGNED NOT NULL,
		visitor_id BIGINT(20) UNSIGNED NOT NULL,
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
		KEY idx_date (log_date),
		KEY idx_active (log_date, checked_in_at, checked_out_at) COMMENT 'Požární poplach',
		CONSTRAINT fk_daily_visit FOREIGN KEY (visit_id) REFERENCES {$visits_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_daily_visitor FOREIGN KEY (visitor_id) REFERENCES {$visitors_table}(id) ON DELETE CASCADE
	) {$charset_collate};";
}