<?php
/**
 * AJAX Handlers Trait - FIXED
 * 
 * Trait obsahuje základní AJAX handlery pro moduly:
 * - ajax_get_detail() - Načte detail záznamu pro modal
 * - ajax_search() - Vyhledávání v tabulce
 * - ajax_delete() - Smazání záznamu
 * 
 * ✅ OPRAVENO: Používá SAW_Permissions místo current_user_can('manage_options')
 * 
 * @package SAW_Visitors
 * @version 1.2.0
 * @since   4.9.0
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
        
        // ✅ Check permissions via SAW
        if (!$this->can_search()) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        if (empty($search)) {
            wp_send_json_error(['message' => 'Prázdný vyhledávací dotaz']);
        }
        
        $results = $this->model->search($search);
        
        if (is_wp_error($results)) {
            wp_send_json_error(['message' => $results->get_error_message()]);
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
        
        // ✅ Check permissions via SAW
        if (!$this->can_delete()) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Neplatné ID']);
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(['message' => ucfirst($this->entity) . ' not found']);
        }
        
        $before_delete_result = $this->before_delete($id);
        
        if (is_wp_error($before_delete_result)) {
            wp_send_json_error(['message' => $before_delete_result->get_error_message()]);
        }
        
        if ($before_delete_result === false) {
            wp_send_json_error(['message' => 'Cannot delete ' . $this->entity]);
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        $this->after_delete($id);
        
        wp_send_json_success(['message' => ucfirst($this->entity) . ' deleted successfully']);
    }
    
    /**
     * AJAX: Get item detail for modal
     * 
     * ✅ OPRAVENO: Používá SAW_Permissions místo manage_options
     */
    public function ajax_get_detail() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        // ✅ CRITICAL FIX: Check permissions via SAW, not manage_options
        if (!$this->can_view_detail()) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Neplatné ID']);
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(['message' => ucfirst($this->entity) . ' nenalezen']);
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
     * ✅ NEW: Check if current user can view detail
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
     * ✅ NEW: Check if current user can search
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
     * ✅ NEW: Check if current user can delete
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
     */
    protected function format_search_result($item) {
        return $item;
    }
}