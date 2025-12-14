<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_visits($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		branch_id BIGINT(20) UNSIGNED NOT NULL,
		company_id BIGINT(20) UNSIGNED NULL COMMENT 'NULL = fyzická osoba',
		action_name VARCHAR(255) NULL COMMENT 'Název akce (např. Dláždění parkoviště)',
		visit_type ENUM('planned', 'walk_in') NOT NULL,
		status ENUM('draft', 'pending', 'confirmed', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
		planned_date_from DATE DEFAULT NULL,
		planned_date_to DATE DEFAULT NULL,
		started_at DATETIME NULL COMMENT 'První check-in prvního dne',
		completed_at DATETIME NULL COMMENT 'Poslední check-out posledního dne',
		
		pin_code VARCHAR(6) NULL,
		pin_expires_at DATETIME DEFAULT NULL COMMENT 'Do kdy je PIN platný (24h od posledního použití)',
		invitation_email VARCHAR(255) NULL,
		invitation_token VARCHAR(64) NULL,
		invitation_sent_at DATETIME NULL,
		invitation_confirmed_at DATETIME NULL,
		invitation_token_expires_at DATETIME NULL,
		reminder_sent_at DATETIME NULL,
		risks_text LONGTEXT NULL,
		risks_document_path VARCHAR(500) NULL,
		risks_document_name VARCHAR(255) NULL,
		risks_status ENUM('pending', 'completed', 'missing') DEFAULT 'pending' COMMENT 'pending = čeká se na rizika (před dnem návštěvy), completed = rizika nahraná, missing = rizika chybí (v den návštěvy nebo později)',
		purpose TEXT NULL,
		notes TEXT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		created_by BIGINT(20) UNSIGNED NULL,
		created_by_email VARCHAR(255) NULL COMMENT 'Email uživatele, který vytvořil záznam',
		updated_by VARCHAR(255) NULL COMMENT 'Email uživatele, který naposledy aktualizoval záznam',
		PRIMARY KEY (id),
		UNIQUE KEY uk_token (invitation_token),
		UNIQUE KEY uk_pin (pin_code, customer_id),
		KEY idx_customer_branch (customer_id, branch_id),
		KEY idx_company (company_id),
		KEY idx_status (status),
		KEY idx_started (started_at),
		KEY idx_in_progress (status, started_at),
		KEY idx_dates (planned_date_from, planned_date_to),
		KEY idx_pin_expires (pin_code, pin_expires_at)
	) {$charset_collate};";
}