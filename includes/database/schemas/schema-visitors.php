<?php
if (!defined('ABSPATH')) { exit; }

function saw_get_schema_visitors($table_name, $prefix, $charset_collate) {
	$customers_table = $prefix . 'customers';
	$companies_table = $prefix . 'companies';
	$uploaded_docs_table = $prefix . 'uploaded_docs';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		company_id BIGINT(20) UNSIGNED DEFAULT NULL,
		first_name VARCHAR(100) NOT NULL,
		last_name VARCHAR(100) NOT NULL,
		email VARCHAR(255) DEFAULT NULL,
		phone VARCHAR(50) DEFAULT NULL,
		id_number VARCHAR(50) DEFAULT NULL,
		photo_path VARCHAR(500) DEFAULT NULL,
		risk_document_id BIGINT(20) UNSIGNED DEFAULT NULL,
		training_completed TINYINT(1) DEFAULT 0,
		training_completed_at DATETIME DEFAULT NULL,
		training_version INT UNSIGNED DEFAULT NULL,
		is_blacklisted TINYINT(1) DEFAULT 0,
		blacklist_reason TEXT DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_company (company_id),
		KEY idx_email (customer_id, email),
		KEY idx_training (customer_id, training_completed),
		KEY idx_blacklist (customer_id, is_blacklisted),
		CONSTRAINT fk_visitor_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_visitor_company FOREIGN KEY (company_id) REFERENCES {$companies_table}(id) ON DELETE SET NULL,
		CONSTRAINT fk_visitor_riskdoc FOREIGN KEY (risk_document_id) REFERENCES {$uploaded_docs_table}(id) ON DELETE SET NULL
	) {$charset_collate} COMMENT='Návštěvníci';";
}
