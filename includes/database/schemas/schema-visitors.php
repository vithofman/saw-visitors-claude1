<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function saw_get_schema_visitors( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$invitations_table = $prefix . 'invitations';
	$companies_table = $prefix . 'companies';
	$departments_table = $prefix . 'departments';
	$users_table = $prefix . 'users';
	$uploaded_docs_table = $prefix . 'uploaded_docs';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		invitation_id BIGINT(20) UNSIGNED DEFAULT NULL,
		company_id BIGINT(20) UNSIGNED DEFAULT NULL,
		department_id BIGINT(20) UNSIGNED DEFAULT NULL,
		responsible_manager_id BIGINT(20) UNSIGNED DEFAULT NULL,
		first_name VARCHAR(100) NOT NULL,
		last_name VARCHAR(100) NOT NULL,
		email VARCHAR(255) DEFAULT NULL,
		phone VARCHAR(50) DEFAULT NULL,
		position VARCHAR(100) DEFAULT NULL,
		is_planned TINYINT(1) DEFAULT 1,
		is_walk_in TINYINT(1) DEFAULT 0,
		attended TINYINT(1) DEFAULT 0,
		training_completed TINYINT(1) DEFAULT 0,
		training_completed_at DATETIME DEFAULT NULL,
		training_language VARCHAR(5) DEFAULT NULL,
		training_version INT UNSIGNED DEFAULT NULL,
		dept_training_version INT UNSIGNED DEFAULT NULL,
		training_skipped TINYINT(1) DEFAULT 0,
		risk_document_id BIGINT(20) UNSIGNED DEFAULT NULL,
		token VARCHAR(255) NOT NULL UNIQUE,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_invitation (invitation_id),
		KEY idx_company (company_id),
		KEY idx_department (department_id),
		KEY idx_manager (responsible_manager_id),
		KEY idx_email (email),
		KEY idx_token (token),
		KEY idx_attended (customer_id, attended),
		KEY idx_training (email, customer_id, training_completed, training_version, training_completed_at),
		CONSTRAINT fk_visitor_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_visitor_inv FOREIGN KEY (invitation_id) REFERENCES {$invitations_table}(id) ON DELETE SET NULL,
		CONSTRAINT fk_visitor_company FOREIGN KEY (company_id) REFERENCES {$companies_table}(id) ON DELETE SET NULL,
		CONSTRAINT fk_visitor_dept FOREIGN KEY (department_id) REFERENCES {$departments_table}(id) ON DELETE SET NULL,
		CONSTRAINT fk_visitor_manager FOREIGN KEY (responsible_manager_id) REFERENCES {$users_table}(id) ON DELETE SET NULL,
		CONSTRAINT fk_visitor_riskdoc FOREIGN KEY (risk_document_id) REFERENCES {$uploaded_docs_table}(id) ON DELETE SET NULL
	) {$charset_collate} COMMENT='Návštěvníci (+ training tracking)';";
}
