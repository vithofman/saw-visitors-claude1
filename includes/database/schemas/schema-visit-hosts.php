<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_visit_hosts($table_name, $prefix, $charset_collate) {
	$visits_table = $prefix . 'visits';
	$users_table = $prefix . 'users';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		visit_id BIGINT(20) UNSIGNED NOT NULL,
		user_id BIGINT(20) UNSIGNED NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY uk_visit_user (visit_id, user_id),
		KEY idx_visit (visit_id),
		KEY idx_user (user_id),
		CONSTRAINT fk_host_visit FOREIGN KEY (visit_id) REFERENCES {$visits_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_host_user FOREIGN KEY (user_id) REFERENCES {$users_table}(id) ON DELETE CASCADE
	) {$charset_collate};";
}
