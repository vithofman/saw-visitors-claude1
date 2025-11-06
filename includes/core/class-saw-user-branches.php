<?php
/**
 * SAW User Branches Helper - Many-to-Many Relationship Manager
 *
 * Manages many-to-many relationship between users and branches.
 * Used for Super Manager role that can access multiple branches.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * User branches relationship class
 *
 * @since 1.0.0
 */
class SAW_User_Branches {
    
    /**
     * Get all branches assigned to user
     *
     * @since 1.0.0
     * @param int $user_id SAW user ID (not wp_user_id!)
     * @return array Array of branch objects
     */
    public static function get_branches_for_user($user_id) {
        global $wpdb;
        
        $user_id = absint($user_id);
        
        if (!$user_id) {
            return [];
        }
        
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT b.* 
             FROM %i b
             INNER JOIN %i ub ON b.id = ub.branch_id
             WHERE ub.user_id = %d AND b.is_active = 1
             ORDER BY b.is_headquarters DESC, b.name ASC",
            $wpdb->prefix . 'saw_branches',
            $wpdb->prefix . 'saw_user_branches',
            $user_id
        ), ARRAY_A);
        
        return $branches ?: [];
    }
    
    /**
     * Get branch IDs for user (just IDs, not full objects)
     *
     * @since 1.0.0
     * @param int $user_id SAW user ID
     * @return array Array of branch IDs
     */
    public static function get_branch_ids_for_user($user_id) {
        global $wpdb;
        
        $user_id = absint($user_id);
        
        if (!$user_id) {
            return [];
        }
        
        $branch_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT branch_id FROM %i WHERE user_id = %d ORDER BY branch_id ASC",
            $wpdb->prefix . 'saw_user_branches',
            $user_id
        ));
        
        return array_map('absint', $branch_ids);
    }
    
    /**
     * Assign branches to user (replaces all existing assignments)
     *
     * @since 1.0.0
     * @param int   $user_id    SAW user ID
     * @param array $branch_ids Array of branch IDs
     * @return bool Success status
     */
    public static function assign_branches($user_id, $branch_ids) {
        global $wpdb;
        
        $user_id = absint($user_id);
        
        if (!$user_id) {
            return false;
        }
        
        if (!is_array($branch_ids)) {
            $branch_ids = [];
        }
        
        $branch_ids = array_map('absint', $branch_ids);
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
                $table = $wpdb->prefix . 'saw_user_branches';
                
                foreach ($branch_ids as $branch_id) {
                    $wpdb->insert(
                        $table,
                        [
                            'user_id'    => $user_id,
                            'branch_id'  => $branch_id,
                            'created_at' => current_time('mysql')
                        ],
                        ['%d', '%d', '%s']
                    );
                }
            }
            
            $wpdb->query('COMMIT');
            
            if (class_exists('SAW_Audit')) {
                SAW_Audit::log([
                    'action'  => 'user_branches_assigned',
                    'user_id' => $user_id,
                    'details' => sprintf('Assigned %d branches', count($branch_ids))
                ]);
            }
            
            return true;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return false;
        }
    }
    
    /**
     * Check if user has access to specific branch
     *
     * @since 1.0.0
     * @param int $user_id   SAW user ID
     * @param int $branch_id Branch ID
     * @return bool
     */
    public static function is_user_allowed_branch($user_id, $branch_id) {
        global $wpdb;
        
        $user_id = absint($user_id);
        $branch_id = absint($branch_id);
        
        if (!$user_id || !$branch_id) {
            return false;
        }
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE user_id = %d AND branch_id = %d",
            $wpdb->prefix . 'saw_user_branches',
            $user_id,
            $branch_id
        ));
        
        return (bool) $exists;
    }
    
    /**
     * Get count of branches assigned to user
     *
     * @since 1.0.0
     * @param int $user_id SAW user ID
     * @return int Number of assigned branches
     */
    public static function get_branch_count($user_id) {
        global $wpdb;
        
        $user_id = absint($user_id);
        
        if (!$user_id) {
            return 0;
        }
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE user_id = %d",
            $wpdb->prefix . 'saw_user_branches',
            $user_id
        ));
        
        return absint($count);
    }
    
    /**
     * Remove all branch assignments for user
     *
     * @since 1.0.0
     * @param int $user_id SAW user ID
     * @return bool Success status
     */
    public static function clear_branches($user_id) {
        global $wpdb;
        
        $user_id = absint($user_id);
        
        if (!$user_id) {
            return false;
        }
        
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'saw_user_branches',
            ['user_id' => $user_id],
            ['%d']
        );
        
        if ($deleted && class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action'  => 'user_branches_cleared',
                'user_id' => $user_id,
                'details' => sprintf('Cleared %d branch assignments', $deleted)
            ]);
        }
        
        return $deleted !== false;
    }
    
    /**
     * Get users assigned to specific branch
     *
     * @since 1.0.0
     * @param int $branch_id Branch ID
     * @return array Array of user IDs
     */
    public static function get_users_for_branch($branch_id) {
        global $wpdb;
        
        $branch_id = absint($branch_id);
        
        if (!$branch_id) {
            return [];
        }
        
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM %i WHERE branch_id = %d ORDER BY user_id ASC",
            $wpdb->prefix . 'saw_user_branches',
            $branch_id
        ));
        
        return array_map('absint', $user_ids);
    }
    
    /**
     * Add single branch to user (without removing existing)
     *
     * @since 1.0.0
     * @param int $user_id   SAW user ID
     * @param int $branch_id Branch ID
     * @return bool Success status
     */
    public static function add_branch($user_id, $branch_id) {
        global $wpdb;
        
        $user_id = absint($user_id);
        $branch_id = absint($branch_id);
        
        if (!$user_id || !$branch_id) {
            return false;
        }
        
        if (self::is_user_allowed_branch($user_id, $branch_id)) {
            return true;
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_user_branches',
            [
                'user_id'    => $user_id,
                'branch_id'  => $branch_id,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s']
        );
        
        return $result !== false;
    }
    
    /**
     * Remove single branch from user
     *
     * @since 1.0.0
     * @param int $user_id   SAW user ID
     * @param int $branch_id Branch ID
     * @return bool Success status
     */
    public static function remove_branch($user_id, $branch_id) {
        global $wpdb;
        
        $user_id = absint($user_id);
        $branch_id = absint($branch_id);
        
        if (!$user_id || !$branch_id) {
            return false;
        }
        
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'saw_user_branches',
            [
                'user_id'   => $user_id,
                'branch_id' => $branch_id
            ],
            ['%d', '%d']
        );
        
        return $deleted !== false;
    }
}