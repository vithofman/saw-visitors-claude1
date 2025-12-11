<?php
/**
 * SAW Notifications Database Schema
 *
 * Defines the structure for storing user notifications.
 * Notifications are user-specific and tied to visits/visitors.
 *
 * @package    SAW_Visitors
 * @subpackage Database
 * @version    1.0.0
 * @since      1.0.0
 */

if (!defined('ABSPATH')) { 
    exit; 
}

/**
 * Get notifications table schema
 *
 * @param string $table_name      Full table name with prefix
 * @param string $prefix          Table prefix (wp_saw_)
 * @param string $charset_collate Database charset collation
 * @return string SQL CREATE TABLE statement
 */
function saw_get_schema_notifications($table_name, $prefix, $charset_collate) {
    $users_table = $prefix . 'users';
    $visits_table = $prefix . 'visits';
    $visitors_table = $prefix . 'visitors';
    $customers_table = $prefix . 'customers';
    $branches_table = $prefix . 'branches';
    
    return "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        
        -- ===== RECIPIENT =====
        user_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'SAW user ID (saw_users.id)',
        customer_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Customer for data isolation',
        branch_id BIGINT(20) UNSIGNED NULL COMMENT 'Branch context (NULL = all branches)',
        
        -- ===== NOTIFICATION TYPE =====
        type ENUM(
            'visit_assigned',      -- Přiřazení jako hostitel
            'visit_today',         -- Návštěva dnes (ranní reminder)
            'visit_tomorrow',      -- Návštěva zítra
            'visitor_checkin',     -- Návštěvník přišel (check-in)
            'visitor_checkout',    -- Návštěvník odešel (check-out)
            'visit_rescheduled',   -- Změna data návštěvy
            'visit_cancelled',     -- Zrušení návštěvy
            'visit_confirmed',     -- Návštěva potvrzena (invitation completed)
            'training_completed',  -- Školení dokončeno
            'system'               -- Systémové oznámení
        ) NOT NULL,
        
        -- ===== PRIORITY =====
        priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
        
        -- ===== CONTENT =====
        title VARCHAR(255) NOT NULL COMMENT 'Notification title',
        message TEXT NOT NULL COMMENT 'Notification message',
        
        -- ===== RELATED ENTITIES =====
        visit_id BIGINT(20) UNSIGNED NULL COMMENT 'Related visit',
        visitor_id BIGINT(20) UNSIGNED NULL COMMENT 'Related visitor',
        
        -- ===== ACTION URL =====
        action_url VARCHAR(500) NULL COMMENT 'URL to navigate on click',
        action_type ENUM('visit_detail', 'visitor_detail', 'calendar', 'dashboard', 'external') NULL,
        
        -- ===== STATUS =====
        is_read TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = unread, 1 = read',
        read_at DATETIME NULL COMMENT 'When notification was read',
        
        -- ===== METADATA =====
        meta JSON NULL COMMENT 'Additional data (visitor name, company, etc.)',
        
        -- ===== TIMESTAMPS =====
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NULL COMMENT 'Auto-delete after this date',
        
        PRIMARY KEY (id),
        
        -- Performance indexes
        KEY idx_user_unread (user_id, is_read, created_at DESC),
        KEY idx_user_customer (user_id, customer_id),
        KEY idx_customer_branch (customer_id, branch_id),
        KEY idx_type (type),
        KEY idx_priority (priority),
        KEY idx_visit (visit_id),
        KEY idx_visitor (visitor_id),
        KEY idx_created (created_at DESC),
        KEY idx_expires (expires_at),
        
        -- Composite for common queries
        KEY idx_user_unread_priority (user_id, is_read, priority, created_at DESC)
        
    ) {$charset_collate} COMMENT='User notifications for visit events';";
}
