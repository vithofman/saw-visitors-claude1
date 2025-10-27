<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_visitors( $table_name, $prefix, $charset_collate ) {
	$invitations_table = $prefix . 'invitations';
	$customers_table = $prefix . 'customers';
	$companies_table = $prefix . 'companies';
	$departments_table = $prefix . 'departments';
	$users_table = $prefix . 'users';
	$uploaded_docs_table = $prefix . 'uploaded_docs';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		invitation_id bigint(20) UNSIGNED DEFAULT NULL,
		customer_id bigint(20) UNSIGNED NOT NULL,
		company_id bigint(20) UNSIGNED DEFAULT NULL,
		department_id bigint(20) UNSIGNED DEFAULT NULL,
		responsible_manager_id bigint(20) UNSIGNED DEFAULT NULL,
		first_name varchar(100) NOT NULL,
		last_name varchar(100) NOT NULL,
		email varchar(255) DEFAULT NULL,
		phone varchar(50) DEFAULT NULL,
		position varchar(100) DEFAULT NULL,
		is_planned tinyint(1) DEFAULT 1,
		is_walk_in tinyint(1) DEFAULT 0,
		attended tinyint(1) DEFAULT 0,
		training_completed tinyint(1) DEFAULT 0,
		training_completed_at datetime DEFAULT NULL,
		training_language varchar(5) DEFAULT NULL,
		training_version int(11) UNSIGNED DEFAULT NULL,
		dept_training_version int(11) UNSIGNED DEFAULT NULL,
		training_skipped tinyint(1) DEFAULT 0,
		risk_document_id bigint(20) UNSIGNED DEFAULT NULL,
		token varchar(255) NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_token (token),
		KEY idx_invitation (invitation_id),
		KEY idx_customer (customer_id),
		KEY idx_company (company_id),
		KEY idx_department (department_id),
		KEY idx_manager (responsible_manager_id),
		KEY idx_email (email),
		KEY idx_attended (customer_id, attended),
		KEY idx_training_completed (customer_id, training_completed),
		KEY idx_training_check (email, customer_id, training_completed, training_version, training_completed_at),
		KEY fk_visitor_invitation (invitation_id),
		KEY fk_visitor_customer (customer_id),
		KEY fk_visitor_company (company_id),
		KEY fk_visitor_department (department_id),
		KEY fk_visitor_manager (responsible_manager_id),
		KEY fk_visitor_riskdoc (risk_document_id)
	) {$charset_collate};";
}
