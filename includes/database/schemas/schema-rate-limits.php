<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function saw_get_schema_rate_limits( $table_name, $prefix, $charset_collate ) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		identifier VARCHAR(255) NOT NULL COMMENT 'IP nebo email',
		action VARCHAR(100) NOT NULL COMMENT 'login, api_call, etc.',
		attempts INT UNSIGNED DEFAULT 1,
		first_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		last_attempt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		locked_until DATETIME DEFAULT NULL,
		PRIMARY KEY (id),
		UNIQUE KEY idx_identifier_action (identifier, action),
		KEY idx_locked (locked_until)
	) {$charset_collate} COMMENT='Rate limiting (hybrid)';";
}
