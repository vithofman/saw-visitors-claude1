<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_visit_schedules($table_name, $prefix, $charset_collate) {
	$visits_table = $prefix . 'visits';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		visit_id BIGINT(20) UNSIGNED NOT NULL,
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
		KEY idx_date (date),
		KEY idx_sort (visit_id, sort_order),
		CONSTRAINT fk_schedule_visit FOREIGN KEY (visit_id) 
			REFERENCES {$visits_table}(id) ON DELETE CASCADE
	) {$charset_collate};";
}