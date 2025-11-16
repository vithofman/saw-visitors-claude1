<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_visits($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		branch_id BIGINT(20) UNSIGNED NOT NULL,
		company_id BIGINT(20) UNSIGNED NULL,
		visit_type ENUM('planned', 'walk_in') NOT NULL,
		status ENUM('draft', 'pending', 'confirmed', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
		pin_code VARCHAR(6) NULL,
		invitation_email VARCHAR(255) NULL,
		invitation_token VARCHAR(64) NULL,
		invitation_sent_at DATETIME NULL,
		invitation_confirmed_at DATETIME NULL,
		invitation_token_expires_at DATETIME NULL,
		reminder_sent_at DATETIME NULL,
		risks_text LONGTEXT NULL,
		risks_document_path VARCHAR(500) NULL,
		risks_document_name VARCHAR(255) NULL,
		purpose TEXT NULL,
		notes TEXT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		created_by BIGINT(20) UNSIGNED NULL,
		PRIMARY KEY (id),
		UNIQUE KEY uk_token (invitation_token),
		KEY idx_customer_branch (customer_id, branch_id),
		KEY idx_company (company_id),
		KEY idx_status (status),
		KEY idx_pin (pin_code, customer_id)
	) {$charset_collate};";
}