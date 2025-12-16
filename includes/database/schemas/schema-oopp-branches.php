<?php
if (!defined('ABSPATH')) { exit; }
function saw_get_schema_oopp_branches($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		oopp_id BIGINT(20) UNSIGNED NOT NULL,
		branch_id BIGINT(20) UNSIGNED NOT NULL,
		PRIMARY KEY (oopp_id, branch_id),
		KEY idx_branch (branch_id)
	) {$charset_collate} COMMENT='Omezení OOPP na pobočky (prázdné = všechny)';";
}


