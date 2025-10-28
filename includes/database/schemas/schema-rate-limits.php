<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_rate_limits($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		identifier VARCHAR(255) NOT NULL,
		endpoint VARCHAR(255) NOT NULL,
		request_count INT UNSIGNED NOT NULL DEFAULT 1,
		window_start DATETIME NOT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_identifier_endpoint (identifier, endpoint, window_start),
		KEY idx_window (window_start)
	) {$charset_collate} COMMENT='Rate limiting';";
}
