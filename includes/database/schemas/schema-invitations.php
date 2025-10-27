<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function saw_get_schema_invitations( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$companies_table = $prefix . 'companies';
	
	return "CREATE TABLE {$table_name} (
		id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id bigint(20) UNSIGNED NOT NULL,
		company_id bigint(20) UNSIGNED DEFAULT NULL,
		visit_date date NOT NULL,
		visit_time_from time DEFAULT NULL,
		visit_time_to time DEFAULT NULL,
		purpose text DEFAULT NULL,
		status enum('draft','sent','confirmed','cancelled','completed') NOT NULL DEFAULT 'draft',
		draft_token varchar(64) DEFAULT NULL,
		created_by bigint(20) UNSIGNED DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY idx_draft_token (draft_token),
		KEY idx_customer (customer_id),
		KEY idx_company (company_id),
		KEY idx_visit_date (visit_date),
		KEY idx_status (status),
		KEY fk_invitation_customer (customer_id),
		KEY fk_invitation_company (company_id)
	) {$charset_collate};";
}
