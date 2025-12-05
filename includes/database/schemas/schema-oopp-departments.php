<?php
if (!defined('ABSPATH')) { exit; }
function saw_get_schema_oopp_departments($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		oopp_id BIGINT(20) UNSIGNED NOT NULL,
		department_id BIGINT(20) UNSIGNED NOT NULL,
		PRIMARY KEY (oopp_id, department_id),
		KEY idx_department (department_id)
	) {$charset_collate} COMMENT='Vazba OOPP na oddělení (prázdné = všechna)';";
}

