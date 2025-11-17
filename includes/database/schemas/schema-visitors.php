<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_visitors($table_name, $prefix, $charset_collate) {
	$visits_table = $prefix . 'visits';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		visit_id BIGINT(20) UNSIGNED NOT NULL,
		first_name VARCHAR(100) NOT NULL,
		last_name VARCHAR(100) NOT NULL,
		position VARCHAR(100) NULL,
		email VARCHAR(255) NULL,
		phone VARCHAR(50) NULL,
		participation_status ENUM('planned', 'confirmed', 'no_show') NOT NULL DEFAULT 'planned',
		
		training_required TINYINT(1) DEFAULT 1,
		training_skipped TINYINT(1) DEFAULT 0 COMMENT 'Absolvoval do 1 roku',
		training_started_at DATETIME NULL,
		training_completed_at DATETIME NULL,
		training_step_video TINYINT(1) DEFAULT 0,
		training_step_map TINYINT(1) DEFAULT 0,
		training_step_risks TINYINT(1) DEFAULT 0,
		training_step_additional TINYINT(1) DEFAULT 0,
		training_step_department TINYINT(1) DEFAULT 0,
		
		first_checkin_at DATETIME NULL COMMENT 'První check-in první den',
		last_checkout_at DATETIME NULL COMMENT 'Poslední check-out poslední den',
		
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_visit (visit_id),
		KEY idx_participation (participation_status),
		KEY idx_name (last_name, first_name),
		CONSTRAINT fk_visitor_visit FOREIGN KEY (visit_id) REFERENCES {$visits_table}(id) ON DELETE CASCADE
	) {$charset_collate};";
}