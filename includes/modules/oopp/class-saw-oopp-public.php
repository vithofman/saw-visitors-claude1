<?php
/**
 * OOPP Public Helper
 * 
 * Provides methods for loading OOPP in public flows (invitation, terminal).
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/OOPP
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_OOPP_Public {
    
    /**
     * Get department IDs from visit hosts
     * 
     * Logic:
     * 1. Get host user_ids from saw_visit_hosts
     * 2. Get department_ids from saw_user_departments
     * 3. If host has no departments (admin), get all branch departments
     * 
     * @param int $visit_id Visit ID
     * @param int $customer_id Customer ID
     * @param int $branch_id Branch ID
     * @return array Array of department IDs
     */
    public static function get_department_ids_from_hosts($visit_id, $customer_id, $branch_id) {
        global $wpdb;
        
        $department_ids = [];
        
        // Get host user IDs
        $host_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
            $visit_id
        ));
        
        error_log("[OOPP Public] Visit #{$visit_id} has " . count($host_ids) . " hosts");
        
        if (empty($host_ids)) {
            // No hosts - return empty (will fallback to branch)
            return [];
        }
        
        foreach ($host_ids as $host_id) {
            // Get departments for this host
            $host_dept_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
                $host_id
            ));
            
            if (empty($host_dept_ids)) {
                // Host has no departments (admin/super_manager) → all branch departments
                error_log("[OOPP Public] Host #{$host_id} has no departments - using all branch departments");
                $all_dept_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}saw_departments 
                     WHERE customer_id = %d AND branch_id = %d AND is_active = 1",
                    $customer_id,
                    $branch_id
                ));
                $department_ids = array_merge($department_ids, $all_dept_ids);
            } else {
                error_log("[OOPP Public] Host #{$host_id} has departments: " . implode(',', $host_dept_ids));
                $department_ids = array_merge($department_ids, $host_dept_ids);
            }
        }
        
        return array_unique($department_ids);
    }
    
    /**
     * Get OOPP items for a visitor based on hosts' departments or branch
     * 
     * Logic:
     * 1. Get department IDs from hosts
     * 2. Get OOPP assigned to those departments
     * 3. Fallback: Get OOPP assigned to branch (without department restrictions)
     * 
     * @param int $customer_id Customer ID
     * @param int $branch_id Branch ID
     * @param int $visit_id Visit ID (to get hosts' departments)
     * @param string $language_code Language code (default: 'cs')
     * @return array Array of OOPP items with all details
     */
    public static function get_for_visitor($customer_id, $branch_id, $visit_id, $language_code = 'cs') {
        global $wpdb;
        
        $oopp_items = [];
        
        // =====================================================
        // STEP 1: Get department IDs from hosts
        // =====================================================
        $department_ids = self::get_department_ids_from_hosts($visit_id, $customer_id, $branch_id);
        
        // =====================================================
        // STEP 2: Get OOPP for those departments
        // =====================================================
        if (!empty($department_ids)) {
            $placeholders = implode(',', array_fill(0, count($department_ids), '%d'));
            $params = array_merge([$customer_id, $language_code], $department_ids);
            
            $oopp_items = $wpdb->get_results($wpdb->prepare("
                SELECT DISTINCT 
                    o.id, o.customer_id, o.group_id, o.image_path,
                    o.is_active,
                    g.code as group_code,
                    g.name as group_name,
                    t.name as name,
                    t.standards as standards,
                    t.risk_description as risk_description,
                    t.protective_properties as protective_properties,
                    t.usage_instructions as usage_instructions
                FROM {$wpdb->prefix}saw_oopp o
                INNER JOIN {$wpdb->prefix}saw_oopp_groups g ON o.group_id = g.id
                INNER JOIN {$wpdb->prefix}saw_oopp_departments od ON o.id = od.oopp_id
                LEFT JOIN {$wpdb->prefix}saw_oopp_translations t ON o.id = t.oopp_id AND t.language_code = %s
                WHERE o.customer_id = %d
                  AND o.is_active = 1
                  AND od.department_id IN ({$placeholders})
                ORDER BY g.display_order ASC, t.name ASC
            ", ...$params), ARRAY_A);
            
            error_log("[OOPP Public] Found " . count($oopp_items) . " OOPP for departments: " . implode(',', $department_ids));
        }
        
        // =====================================================
        // STEP 3: Fallback - Get OOPP for branch (no department restrictions)
        // =====================================================
        if (empty($oopp_items)) {
            $oopp_items = $wpdb->get_results($wpdb->prepare("
                SELECT DISTINCT 
                    o.id, o.customer_id, o.group_id, o.image_path,
                    o.is_active,
                    g.code as group_code,
                    g.name as group_name,
                    t.name as name,
                    t.standards as standards,
                    t.risk_description as risk_description,
                    t.protective_properties as protective_properties,
                    t.usage_instructions as usage_instructions
                FROM {$wpdb->prefix}saw_oopp o
                INNER JOIN {$wpdb->prefix}saw_oopp_groups g ON o.group_id = g.id
                INNER JOIN {$wpdb->prefix}saw_oopp_branches ob ON o.id = ob.oopp_id
                LEFT JOIN {$wpdb->prefix}saw_oopp_departments od ON o.id = od.oopp_id
                LEFT JOIN {$wpdb->prefix}saw_oopp_translations t ON o.id = t.oopp_id AND t.language_code = %s
                WHERE o.customer_id = %d
                  AND o.is_active = 1
                  AND ob.branch_id = %d
                  AND od.oopp_id IS NULL
                ORDER BY g.display_order ASC, t.name ASC
            ", $language_code, $customer_id, $branch_id), ARRAY_A);
            
            error_log("[OOPP Public] Fallback: Found " . count($oopp_items) . " branch-wide OOPP for branch #{$branch_id}");
        }
        
        // =====================================================
        // STEP 4: Process results - add image URLs
        // =====================================================
        $upload_dir = wp_upload_dir();
        
        foreach ($oopp_items as &$item) {
            if (!empty($item['image_path'])) {
                $item['image_url'] = $upload_dir['baseurl'] . '/' . ltrim($item['image_path'], '/');
            } else {
                $item['image_url'] = '';
            }
            $item['group_display'] = $item['group_code'] . '. ' . $item['group_name'];
        }
        
        return $oopp_items;
    }
    
    /**
     * Check if there are any OOPP for given visit
     * 
     * @param int $customer_id Customer ID
     * @param int $branch_id Branch ID
     * @param int $visit_id Visit ID
     * @return bool True if OOPP exist
     */
    public static function has_oopp($customer_id, $branch_id, $visit_id) {
        $items = self::get_for_visitor($customer_id, $branch_id, $visit_id);
        return !empty($items);
    }
}

