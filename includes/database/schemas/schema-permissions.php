<?php
if (!defined('ABSPATH')) { exit; }
function saw_get_schema_permissions($table_name, $prefix, $charset_collate) {
	return "CREATE TABLE {$table_name} (
		id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
		role VARCHAR(50) NOT NULL COMMENT 'SAW role (super_admin, admin, super_manager, manager, terminal)',
		module VARCHAR(50) NOT NULL COMMENT 'Module slug (users, branches, departments, etc.)',
		action VARCHAR(50) NOT NULL COMMENT 'Action (list, view, create, edit, delete)',
		allowed TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = allowed, 0 = denied',
		scope ENUM('all', 'customer', 'branch', 'department', 'own') NOT NULL DEFAULT 'all' COMMENT 'Data isolation scope',
		created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY unique_permission (role, module, action),
		KEY idx_role (role),
		KEY idx_module (module),
		KEY idx_action (action),
		KEY idx_allowed (allowed)
	) {$charset_collate} COMMENT='Role-based permissions with data isolation';";
}