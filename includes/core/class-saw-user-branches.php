<?php
/**
 * SAW User Branches Helper
 * 
 * Manages many-to-many relationship between users and branches.
 * Used for Super Manager role that can access multiple branches.
 * 
 * @package SAW_Visitors
 * @since 5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_User_Branches {
    
    /**
     * Get all branches assigned to user
     * 
     * @param int $user_id SAW user ID (not wp_user_id!)
     * @return array Array of branch objects
     */
    public static function get_branches_for_user($user_id) {
        global $wpdb;
        
        $user_id = intval($user_id);
        
        if (!$user_id) {
            return [];
        }
        
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT b.* 
             FROM {$wpdb->prefix}saw_branches b
             INNER JOIN {$wpdb->prefix}saw_user_branches ub ON b.id = ub.branch_id
             WHERE ub.user_id = %d AND b.is_active = 1
             ORDER BY b.is_headquarters DESC, b.name ASC",
            $user_id
        ), ARRAY_A);
        
        return $branches ?: [];
    }
    
    /**
     * Get branch IDs for user (just IDs, not full objects)
     * 
     * @param int $user_id SAW user ID
     * @return array Array of branch IDs
     */
    public static function get_branch_ids_for_user($user_id) {
        global $wpdb;
        
        $user_id = intval($user_id);
        
        if (!$user_id) {
            return [];
        }
        
        $branch_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT branch_id 
             FROM {$wpdb->prefix}saw_user_branches 
             WHERE user_id = %d
             ORDER BY branch_id ASC",
            $user_id
        ));
        
        return array_map('intval', $branch_ids);
    }
    
    /**
     * Assign branches to user (replaces all existing assignments)
     * 
     * @param int $user_id SAW user ID
     * @param array $branch_ids Array of branch IDs
     * @return bool Success
     */
    public static function assign_branches($user_id, $branch_ids) {
        global $wpdb;
        
        $user_id = intval($user_id);
        
        if (!$user_id) {
            return false;
        }
        
        if (!is_array($branch_ids)) {
            $branch_ids = [];
        }
        
        $branch_ids = array_map('intval', $branch_ids);
        $branch_ids = array_filter($branch_ids);
        $branch_ids = array_unique($branch_ids);
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $wpdb->delete(
                $wpdb->prefix . 'saw_user_branches',
                ['user_id' => $user_id],
                ['%d']
            );
            
            if (!empty($branch_ids)) {
                foreach ($branch_ids as $branch_id) {
                    $wpdb->insert(
                        $wpdb->prefix . 'saw_user_branches',
                        [
                            'user_id' => $user_id,
                            'branch_id' => $branch_id,
                            'created_at' => current_time('mysql')
                        ],
                        ['%d', '%d', '%s']
                    );
                }
            }
            
            $wpdb->query('COMMIT');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[SAW_User_Branches] Assigned %d branches to user %d',
                    count($branch_ids),
                    $user_id
                ));
            }
            
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[SAW_User_Branches] Error: ' . $e->getMessage());
            }
            
            return false;
        }
    }
    
    /**
     * Check if user has access to specific branch
     * 
     * @param int $user_id SAW user ID
     * @param int $branch_id Branch ID
     * @return bool
     */
    public static function is_user_allowed_branch($user_id, $branch_id) {
        global $wpdb;
        
        $user_id = intval($user_id);
        $branch_id = intval($branch_id);
        
        if (!$user_id || !$branch_id) {
            return false;
        }
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}saw_user_branches 
             WHERE user_id = %d AND branch_id = %d",
            $user_id,
            $branch_id
        ));
        
        return (bool) $exists;
    }
    
    /**
     * Get count of branches assigned to user
     * 
     * @param int $user_id SAW user ID
     * @return int
     */
    public static function get_branch_count($user_id) {
        global $wpdb;
        
        $user_id = intval($user_id);
        
        if (!$user_id) {
            return 0;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}saw_user_branches 
             WHERE user_id = %d",
            $user_id
        ));
        
        return intval($count);
    }
    
    /**
     * Remove all branch assignments for user
     * 
     * @param int $user_id SAW user ID
     * @return bool
     */
    public static function clear_branches($user_id) {
        global $wpdb;
        
        $user_id = intval($user_id);
        
        if (!$user_id) {
            return false;
        }
        
        $wpdb->delete(
            $wpdb->prefix . 'saw_user_branches',
            ['user_id' => $user_id],
            ['%d']
        );
        
        return true;
    }
}