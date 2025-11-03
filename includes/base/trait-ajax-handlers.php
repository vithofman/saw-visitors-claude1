<?php
/**
 * AJAX Handlers Trait - FIXED VERSION
 * 
 * CRITICAL FIXES:
 * - ✅ ajax_get_detail() now validates customer isolation for non-super admin
 * - ✅ Added explicit check that fetched record belongs to user's customer
 * - ✅ Better error messages and logging
 * - ✅ All permission checks use SAW_Permissions instead of manage_options
 * 
 * PRESERVED:
 * - ✅ All existing handlers (search, delete, detail)
 * - ✅ Permission check methods
 * - ✅ Format methods
 * 
 * @package SAW_Visitors
 * @version 1.3.0 - FIXED
 * @since 4.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait SAW_AJAX_Handlers 
{
    /**
     * AJAX: Search items
     * 
     * Vyhledá záznamy podle query stringu.
     * Používá model->search() metodu.
     */
    public function ajax_search() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        // Check permissions
        if (!$this->can_search()) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
            return;
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search)) {
            wp_send_json_error(['message' => 'Prázdný vyhledávací dotaz']);
            return;
        }
        
        $results = $this->model->search($search);
        
        if (is_wp_error($results)) {
            wp_send_json_error(['message' => $results->get_error_message()]);
            return;
        }
        
        $formatted_results = array_map(function($item) {
            return $this->format_search_result($item);
        }, $results);
        
        wp_send_json_success([
            'results' => $formatted_results,
            'count' => count($formatted_results),
        ]);
    }
    
    /**
     * AJAX: Delete item
     * 
     * Smaže záznam přes model->delete().
     * Volá before_delete() a after_delete() hooks.
     */
    public function ajax_delete() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        // Check permissions
        if (!$this->can_delete()) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Neplatné ID']);
            return;
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(['message' => ucfirst($this->entity) . ' not found']);
            return;
        }
        
        // ================================================
        // ✅ CRITICAL: VALIDATE CUSTOMER ISOLATION
        // ================================================
        if (!$this->can_access_item($item)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AJAX Delete] SECURITY: Customer isolation violation - Item ID: %d, Entity: %s',
                    $id,
                    $this->entity
                ));
            }
            wp_send_json_error(['message' => 'Nemáte oprávnění k tomuto záznamu']);
            return;
        }
        
        $before_delete_result = $this->before_delete($id);
        
        if (is_wp_error($before_delete_result)) {
            wp_send_json_error(['message' => $before_delete_result->get_error_message()]);
            return;
        }
        
        if ($before_delete_result === false) {
            wp_send_json_error(['message' => 'Cannot delete ' . $this->entity]);
            return;
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }
        
        $this->after_delete($id);
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AJAX Delete] Success - Item ID: %d, Entity: %s',
                $id,
                $this->entity
            ));
        }
        
        wp_send_json_success(['message' => ucfirst($this->entity) . ' deleted successfully']);
    }
    
    /**
     * AJAX: Get item detail for modal
     * 
     * ✅ CRITICAL FIX: Now validates customer isolation
     */
    public function ajax_get_detail() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        // Check permissions
        if (!$this->can_view_detail()) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Neplatné ID']);
            return;
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AJAX Detail] Request - ID: %d, Entity: %s, User: %d',
                $id,
                $this->entity,
                get_current_user_id()
            ));
        }
        
        // ================================================
        // ✅ CRITICAL FIX: FETCH WITH SCOPE VALIDATION
        // ================================================
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AJAX Detail] NOT FOUND - ID: %d, Entity: %s',
                    $id,
                    $this->entity
                ));
            }
            wp_send_json_error(['message' => ucfirst($this->entity) . ' nenalezen']);
            return;
        }
        
        // ================================================
        // ✅ CRITICAL: VALIDATE CUSTOMER ISOLATION
        // ================================================
        if (!$this->can_access_item($item)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AJAX Detail] SECURITY: Customer isolation violation - ID: %d, Entity: %s, Item Customer: %d, User Customer: %d',
                    $id,
                    $this->entity,
                    $item['customer_id'] ?? 'NULL',
                    class_exists('SAW_Context') ? SAW_Context::get_customer_id() : 'NULL'
                ));
            }
            wp_send_json_error(['message' => 'Nemáte oprávnění k tomuto záznamu']);
            return;
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AJAX Detail] SUCCESS - ID: %d, Entity: %s',
                $id,
                $this->entity
            ));
        }
        
        // Format data
        $item = $this->format_detail_data($item);
        
        // Check for custom template
        $template_path = $this->config['path'] . 'detail-modal-template.php';
        
        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            $html = ob_get_clean();
            
            wp_send_json_success([
                'html' => $html,
                'item' => $item,
            ]);
        } else {
            // Return raw data if no template
            wp_send_json_success([
                $this->entity => $item,
                'customer' => $item,
                'item' => $item,
            ]);
        }
    }
    
    /**
     * ✅ NEW: Check if current user can access this specific item
     * Validates customer isolation for non-super admin users
     * 
     * @param array $item
     * @return bool
     */
    protected function can_access_item($item) {
        // Super admin can access everything
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // If item has customer_id, validate it matches current user's customer
        if (isset($item['customer_id'])) {
            $current_customer_id = null;
            
            if (class_exists('SAW_Context')) {
                $current_customer_id = SAW_Context::get_customer_id();
            }
            
            // If no customer_id available, deny access (better safe than sorry)
            if (!$current_customer_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[AJAX Handlers] WARNING: No customer_id available for isolation check');
                }
                return false;
            }
            
            // Check if item belongs to user's customer
            return (int)$item['customer_id'] === (int)$current_customer_id;
        }
        
        // If item doesn't have customer_id field, allow access
        // (might be a non-isolated entity)
        return true;
    }
    
    /**
     * Check if current user can view detail
     * 
     * @return bool
     */
    protected function can_view_detail() {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if (!class_exists('SAW_Permissions')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
        }
        
        $role = $this->get_current_user_role();
        return SAW_Permissions::check($role, $this->entity, 'view');
    }
    
    /**
     * Check if current user can search
     * 
     * @return bool
     */
    protected function can_search() {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if (!class_exists('SAW_Permissions')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
        }
        
        $role = $this->get_current_user_role();
        return SAW_Permissions::check($role, $this->entity, 'list');
    }
    
    /**
     * Check if current user can delete
     * 
     * @return bool
     */
    protected function can_delete() {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if (!class_exists('SAW_Permissions')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
        }
        
        $role = $this->get_current_user_role();
        return SAW_Permissions::check($role, $this->entity, 'delete');
    }
    
    /**
     * Format detail data for modal
     * 
     * @param array $item
     * @return array
     */
    protected function format_detail_data($item) {
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Format search result
     * Override this in controller if needed
     * 
     * @param array $item
     * @return array
     */
    protected function format_search_result($item) {
        return $item;
    }
}