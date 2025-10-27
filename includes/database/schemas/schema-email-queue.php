<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_email_queue( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) UNSIGNED NOT NULL,
		recipient_email varchar(255) NOT NULL,
		recipient_name varchar(255) DEFAULT NULL,
		subject varchar(500) NOT NULL,
		body longtext NOT NULL,
		headers longtext DEFAULT NULL,
		attachments longtext DEFAULT NULL,
		priority enum('high','normal','low') DEFAULT 'normal',
		status enum('pending','processing','sent','failed') DEFAULT 'pending',
		attempts int(11) UNSIGNED DEFAULT 0,
		max_attempts int(11) UNSIGNED DEFAULT 3,
		last_error text DEFAULT NULL,
		sent_at datetime DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_status (status),
		KEY idx_priority (priority),
		KEY idx_created (created_at),
		KEY idx_pending (status, priority, created_at),
		KEY fk_email_customer (customer_id)
	) {$charset_collate};";
}
