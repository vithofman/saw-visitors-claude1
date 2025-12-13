<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_email_logs($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		branch_id BIGINT(20) UNSIGNED DEFAULT NULL,
		recipient_email VARCHAR(255) NOT NULL,
		recipient_name VARCHAR(255) DEFAULT NULL,
		recipient_type ENUM('visitor', 'host', 'user', 'contact', 'other') NOT NULL DEFAULT 'other',
		recipient_id BIGINT(20) UNSIGNED DEFAULT NULL,
		email_type VARCHAR(50) NOT NULL,
		subject VARCHAR(255) NOT NULL,
		body_html LONGTEXT DEFAULT NULL,
		body_text TEXT DEFAULT NULL,
		language VARCHAR(10) NOT NULL DEFAULT 'cs',
		visit_id BIGINT(20) UNSIGNED DEFAULT NULL,
		visitor_id BIGINT(20) UNSIGNED DEFAULT NULL,
		status ENUM('sent', 'failed', 'queued') NOT NULL DEFAULT 'sent',
		error_message TEXT DEFAULT NULL,
		sent_by BIGINT(20) UNSIGNED DEFAULT NULL,
		headers TEXT DEFAULT NULL,
		meta JSON DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		sent_at DATETIME DEFAULT NULL,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_branch (customer_id, branch_id),
		KEY idx_recipient (recipient_email(100)),
		KEY idx_type (email_type),
		KEY idx_visit (visit_id),
		KEY idx_visitor (visitor_id),
		KEY idx_status (status),
		KEY idx_created (created_at),
		KEY idx_sent_by (sent_by)
	) {$charset_collate};";
}