<?php
if (!defined('ABSPATH')) { 
    exit; 
}

function saw_get_schema_user_branches($table_name, $prefix, $charset_collate) {
    $users_table = $prefix . 'saw_users';
    $branches_table = $prefix . 'saw_branches';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Odkaz na saw_users.id',
        branch_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Odkaz na saw_branches.id',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
		UNIQUE KEY idx_user_branch (user_id, branch_id),
		KEY idx_user (user_id),
		KEY idx_branch (branch_id)
	) {$charset_collate} COMMENT='M:N: Users <-> Branches (pro super_manager)';";
}