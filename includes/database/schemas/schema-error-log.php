<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_error_log( $table_name, $prefix, $charset_collate ) {
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		error_type varchar(50) NOT NULL,
		error_message text NOT NULL,
		error_file varchar(500) DEFAULT NULL,
		error_line int(11) DEFAULT NULL,
		stack_trace longtext DEFAULT NULL,
		context longtext DEFAULT NULL,
		ip_address varchar(45) DEFAULT NULL,
		user_agent varchar(500) DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_type (error_type),
		KEY idx_created (created_at),
		KEY idx_file (error_file(255))
	) {$charset_collate};";
}
