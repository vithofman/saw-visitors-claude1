<?php
/**
 * AJAX Handlers Trait - ENHANCED VERSION v2.0.0
 * 
 * WHAT'S NEW in v2.0.0:
 * - ✅ Try-catch for template rendering (prevents 500 errors)
 * - ✅ HTML length validation (min 50 bytes)
 * - ✅ Enhanced debug logging for troubleshooting
 * - ✅ Better error messages for template issues
 * - ✅ Backwards compatible with all existing modules
 * 
 * PREVIOUS FIXES (v1.5.0):
 * - ✅ ajax_get_detail() validates customer isolation ONLY if entity has it
 * - ✅ Checks config['has_customer_isolation'] flag before validation
 * - ✅ Global entities (account-types, etc.) skip isolation check
 * - ✅ Uses SAW_Auth directly instead of $this->get_current_user_role()
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait SAW_AJAX_Handlers 
{
    /**
     * AJAX: Search handler
     */
    public function ajax_search() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
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
     * AJAX: Delete handler
     */
    public function ajax_delete() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
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
        
        if (!$this->can_access_item($item)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AJAX Delete] SECURITY: Access denied - Item ID: %d, Entity: %s',
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
     * AJAX: Get detail for modal
     * 
     * ✅ NEW in v2.0.0: Enhanced error handling and validation
     */
    public function ajax_get_detail() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        // Permission check
        if (!$this->can_view_detail()) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
            return;
        }
        
        // Validate ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Neplatné ID']);
            return;
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AJAX Detail] Request - ID: %d, Entity: %s, User: %d, Has Isolation: %s',
                $id,
                $this->entity,
                get_current_user_id(),
                isset($this->config['has_customer_isolation']) ? ($this->config['has_customer_isolation'] ? 'YES' : 'NO') : 'DEFAULT'
            ));
        }
        
        // Load item from database
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
        
        // Customer isolation check
        if (!$this->can_access_item($item)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AJAX Detail] SECURITY: Access denied - ID: %d, Entity: %s, Item Customer: %s, User Customer: %s',
                    $id,
                    $this->entity,
                    isset($item['customer_id']) ? $item['customer_id'] : 'NONE',
                    class_exists('SAW_Context') ? SAW_Context::get_customer_id() : 'NULL'
                ));
            }
            wp_send_json_error(['message' => 'Nemáte oprávnění k tomuto záznamu']);
            return;
        }
        
        // Format data (dates, badges, etc.)
        $item = $this->format_detail_data($item);
        
        // Check template existence
        $template_path = $this->config['path'] . 'detail-modal-template.php';
        
        if (!file_exists($template_path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AJAX Detail] Template NOT FOUND: %s',
                    $template_path
                ));
            }
            wp_send_json_error(['message' => 'Template nebyl nalezen']);
            return;
        }
        
        // ✅ NEW: Try-catch for template rendering
        ob_start();
        
        try {
            require $template_path;
            $html = ob_get_clean();
            
            // ✅ NEW: Validate HTML output
            if (empty($html)) {
                throw new Exception('Template rendered empty content');
            }
            
            // ✅ NEW: Check minimum HTML length (prevents partial renders)
            if (strlen($html) < 50) {
                throw new Exception('Template rendered too short content (less than 50 bytes)');
            }
            
            // Success logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AJAX Detail] SUCCESS - ID: %d, Entity: %s, HTML: %d bytes',
                    $id,
                    $this->entity,
                    strlen($html)
                ));
            }
            
            wp_send_json_success([
                'html' => $html,
                'item' => $item,
            ]);
            
        } catch (Exception $e) {
            // Clean output buffer on error
            ob_end_clean();
            
            // Error logging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AJAX Detail] Template rendering error - ID: %d, Entity: %s, Error: %s',
                    $id,
                    $this->entity,
                    $e->getMessage()
                ));
            }
            
            // User-friendly error message
            wp_send_json_error([
                'message' => 'Chyba při zobrazení detailu: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Check if user can access specific item (customer isolation)
     * 
     * @param array $item
     * @return bool
     */
    protected function can_access_item($item) {
        // Super admin can access everything
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check if entity has customer isolation
        $has_isolation = isset($this->config['has_customer_isolation']) 
            ? $this->config['has_customer_isolation'] 
            : true;
        
        // Global entities (no isolation) - allow access
        if (!$has_isolation) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AJAX Handlers] Entity %s has NO customer isolation - allowing access',
                    $this->entity
                ));
            }
            return true;
        }
        
        // Check customer_id match
        if (isset($item['customer_id'])) {
            $current_customer_id = null;
            
            if (class_exists('SAW_Context')) {
                $current_customer_id = SAW_Context::get_customer_id();
            }
            
            if (!$current_customer_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[AJAX Handlers] WARNING: No customer_id available for isolation check');
                }
                return false;
            }
            
            return (int)$item['customer_id'] === (int)$current_customer_id;
        }
        
        // No customer_id field - allow access
        return true;
    }
    
    /**
     * Check if user can view detail
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
        
        if (!class_exists('SAW_Auth')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-auth.php';
        }
        
        $auth = new SAW_Auth();
        $role = $auth->get_current_user_role();
        
        return SAW_Permissions::check($role, $this->entity, 'view');
    }
    
    /**
     * Check if user can search
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
        
        if (!class_exists('SAW_Auth')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-auth.php';
        }
        
        $auth = new SAW_Auth();
        $role = $auth->get_current_user_role();
        
        return SAW_Permissions::check($role, $this->entity, 'list');
    }
    
    /**
     * Check if user can delete
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
        
        if (!class_exists('SAW_Auth')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-auth.php';
        }
        
        $auth = new SAW_Auth();
        $role = $auth->get_current_user_role();
        
        return SAW_Permissions::check($role, $this->entity, 'delete');
    }
    
    /**
     * Format detail data (dates, badges, etc.)
     * Override in controller if needed
     * 
     * @param array $item
     * @return array
     */
    protected function format_detail_data($item) {
        // Format created_at
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        // Format updated_at
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Format search result
     * Override in controller if needed
     * 
     * @param array $item
     * @return array
     */
    protected function format_search_result($item) {
        return $item;
    }
}