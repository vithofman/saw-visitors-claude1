<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function saw_get_schema_visits( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$visitors_table = $prefix . 'visitors';
	$users_table = $prefix . 'users';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		visitor_id BIGINT(20) UNSIGNED NOT NULL,
		check_in_time DATETIME DEFAULT NULL,
		check_out_time DATETIME DEFAULT NULL,
		check_in_by BIGINT(20) UNSIGNED DEFAULT NULL,
		check_in_method ENUM('terminal', 'admin', 'auto') DEFAULT 'terminal',
		check_in_notes TEXT DEFAULT NULL,
		check_out_by BIGINT(20) UNSIGNED DEFAULT NULL,
		check_out_method ENUM('terminal', 'admin', 'auto', 'bulk') DEFAULT 'terminal',
		check_out_notes TEXT DEFAULT NULL,
		duration_minutes INT UNSIGNED DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_visitor (visitor_id),
		KEY idx_checkin (customer_id, check_in_time),
		KEY idx_checkout (customer_id, check_out_time),
		CONSTRAINT fk_visit_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_visit_visitor FOREIGN KEY (visitor_id) REFERENCES {$visitors_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_visit_checkinby FOREIGN KEY (check_in_by) REFERENCES {$users_table}(id) ON DELETE SET NULL,
		CONSTRAINT fk_visit_checkoutby FOREIGN KEY (check_out_by) REFERENCES {$users_table}(id) ON DELETE SET NULL
	) {$charset_collate} COMMENT='Check-in/out záznamy';";
}
