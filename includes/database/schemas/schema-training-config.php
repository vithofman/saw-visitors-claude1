<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_training_config($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		training_version INT UNSIGNED NOT NULL DEFAULT 1,
		skip_threshold_days INT UNSIGNED DEFAULT 365,
		require_quiz TINYINT(1) DEFAULT 0,
		passing_score INT UNSIGNED DEFAULT 80,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_customer_version (customer_id, training_version),
		KEY idx_customer (customer_id),
		CONSTRAINT fk_training_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE
	) {$charset_collate} COMMENT='Training config';";
}
