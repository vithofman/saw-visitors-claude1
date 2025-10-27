<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function saw_get_schema_invitations( $table_name, $prefix, $charset_collate ) {
	$customers_table = $prefix . 'customers';
	$companies_table = $prefix . 'companies';
	$users_table = $prefix . 'users';
	
	return "CREATE TABLE {$table_name} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		customer_id BIGINT(20) UNSIGNED NOT NULL,
		company_id BIGINT(20) UNSIGNED DEFAULT NULL,
		responsible_manager_id BIGINT(20) UNSIGNED DEFAULT NULL,
		visit_date DATE NOT NULL,
		visit_time TIME DEFAULT NULL,
		purpose TEXT DEFAULT NULL,
		status ENUM('draft', 'sent', 'confirmed', 'completed', 'cancelled') DEFAULT 'draft',
		token VARCHAR(255) DEFAULT NULL,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY idx_customer (customer_id),
		KEY idx_company (company_id),
		KEY idx_manager (responsible_manager_id),
		KEY idx_date (customer_id, visit_date),
		KEY idx_status (customer_id, status),
		KEY idx_token (token),
		CONSTRAINT fk_inv_customer FOREIGN KEY (customer_id) REFERENCES {$customers_table}(id) ON DELETE CASCADE,
		CONSTRAINT fk_inv_company FOREIGN KEY (company_id) REFERENCES {$companies_table}(id) ON DELETE SET NULL,
		CONSTRAINT fk_inv_manager FOREIGN KEY (responsible_manager_id) REFERENCES {$users_table}(id) ON DELETE SET NULL
	) {$charset_collate} COMMENT='Pozvánky (draft mode workflow)';";
}
