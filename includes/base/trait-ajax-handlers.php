<?php
/**
 * AJAX Handlers Trait
 * 
 * @package SAW_Visitors
 * @version 2.1.0 - Permissions Fix for All Roles
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
        
        $item = $this->format_detail_data($item);
        
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
        
        ob_start();
        
        try {
            require $template_path;
            $html = ob_get_clean();
            
            if (empty($html)) {
                throw new Exception('Template rendered empty content');
            }
            
            if (strlen($html) < 50) {
                throw new Exception('Template rendered too short content (less than 50 bytes)');
            }
            
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
            ob_end_clean();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[AJAX Detail] Template rendering error - ID: %d, Entity: %s, Error: %s',
                    $id,
                    $this->entity,
                    $e->getMessage()
                ));
            }
            
            wp_send_json_error([
                'message' => 'Chyba při zobrazení detailu: ' . $e->getMessage()
            ]);
        }
    }
    
    protected function can_access_item($item) {
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin') {
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
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin') {
            return true;
        }
        
        if (!class_exists('SAW_Permissions')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
        }
        
        if (!class_exists('SAW_Permissions')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Handlers] SAW_Permissions not found for can_view_detail');
            }
            return false;
        }
        
        if (empty($role)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Handlers] No role found for can_view_detail');
            }
            return false;
        }
        
        $has_permission = SAW_Permissions::check($role, $this->entity, 'view');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AJAX Handlers] can_view_detail - Role: %s, Entity: %s, Result: %s',
                $role,
                $this->entity,
                $has_permission ? 'ALLOWED' : 'DENIED'
            ));
        }
        
        return $has_permission;
    }
    
    protected function can_search() {
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin') {
            return true;
        }
        
        if (!class_exists('SAW_Permissions')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
        }
        
        if (!class_exists('SAW_Permissions')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Handlers] SAW_Permissions not found for can_search');
            }
            return false;
        }
        
        if (empty($role)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Handlers] No role found for can_search');
            }
            return false;
        }
        
        $has_permission = SAW_Permissions::check($role, $this->entity, 'list');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AJAX Handlers] can_search - Role: %s, Entity: %s, Result: %s',
                $role,
                $this->entity,
                $has_permission ? 'ALLOWED' : 'DENIED'
            ));
        }
        
        return $has_permission;
    }
    
    protected function can_delete() {
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin') {
            return true;
        }
        
        if (!class_exists('SAW_Permissions')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
        }
        
        if (!class_exists('SAW_Permissions')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Handlers] SAW_Permissions not found for can_delete');
            }
            return false;
        }
        
        if (empty($role)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[AJAX Handlers] No role found for can_delete');
            }
            return false;
        }
        
        $has_permission = SAW_Permissions::check($role, $this->entity, 'delete');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[AJAX Handlers] can_delete - Role: %s, Entity: %s, Result: %s',
                $role,
                $this->entity,
                $has_permission ? 'ALLOWED' : 'DENIED'
            ));
        }
        
        return $has_permission;
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