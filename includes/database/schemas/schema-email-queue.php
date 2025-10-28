<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_email_queue($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		to_email VARCHAR(255) NOT NULL,
		cc_email VARCHAR(1000) DEFAULT NULL,
		subject VARCHAR(500) NOT NULL,
		body LONGTEXT NOT NULL,
		attachments LONGTEXT DEFAULT NULL,
		priority ENUM('low', 'normal', 'high') DEFAULT 'normal',
		status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
		attempts INT UNSIGNED DEFAULT 0,
		error_message TEXT DEFAULT NULL,
		sent_at DATETIME DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_status (status),
		KEY idx_priority (priority, created_at),
		CONSTRAINT fk_email_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='Email queue';";
}
