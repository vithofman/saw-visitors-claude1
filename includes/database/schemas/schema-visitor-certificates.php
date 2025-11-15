<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_visitor_certificates($table_name, $prefix, $charset_collate) {
	$visitors_table = $prefix . 'visitors';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		visitor_id BIGINT(20) UNSIGNED NOT NULL,
		certificate_name VARCHAR(200) NOT NULL,
		certificate_number VARCHAR(100) NULL,
		valid_until DATE NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_visitor (visitor_id),
		KEY idx_valid (valid_until),
		CONSTRAINT fk_cert_visitor FOREIGN KEY (visitor_id) REFERENCES {$visitors_table}(id) ON DELETE CASCADE
	) {$charset_collate};";
}
