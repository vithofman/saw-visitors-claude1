<?php
/**
 * AJAX Handlers Trait - FIXED VERSION v1.5.0
 * 
 * CRITICAL FIXES:
 * - ✅ ajax_get_detail() validates customer isolation ONLY if entity has it
 * - ✅ Checks config['has_customer_isolation'] flag before validation
 * - ✅ Global entities (account-types, etc.) skip isolation check
 * - ✅ FIXED: Uses SAW_Auth directly instead of $this->get_current_user_role()
 * 
 * @package SAW_Visitors
 * @version 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait SAW_AJAX_Handlers 
{
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
    
    public function ajax_get_detail() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!$this->can_view_detail()) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Neplatné ID']);
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AJAX Detail] Request - ID: %d, Entity: %s, User: %d, Has Isolation: %s',
                $id,
                $this->entity,
                get_current_user_id(),
                isset($this->config['has_customer_isolation']) ? ($this->config['has_customer_isolation'] ? 'YES' : 'NO') : 'DEFAULT'
            ));
        }
        
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
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AJAX Detail] SUCCESS - ID: %d, Entity: %s',
                $id,
                $this->entity
            ));
        }
        
        $item = $this->format_detail_data($item);
        
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
            wp_send_json_success([
                $this->entity => $item,
                'customer' => $item,
                'item' => $item,
            ]);
        }
    }
    
    /**
     * ✅ CRITICAL FIX: Check access with has_customer_isolation support
     */
    protected function can_access_item($item) {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        $has_isolation = isset($this->config['has_customer_isolation']) 
            ? $this->config['has_customer_isolation'] 
            : true;
        
        if (!$has_isolation) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AJAX Handlers] Entity %s has NO customer isolation - allowing access',
                    $this->entity
                ));
            }
            return true;
        }
        
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
        
        return true;
    }
    
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
    
    protected function format_detail_data($item) {
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    protected function format_search_result($item) {
        return $item;
    }
}