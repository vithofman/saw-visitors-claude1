<?php
/**
 * AJAX Handlers Trait
 * 
 * Univerzální AJAX handlery pro vyhledávání, mazání, detail.
 * Použitelné v libovolném controlleru.
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

trait SAW_AJAX_Handlers 
{
    /**
     * AJAX: Search items
     */
    public function ajax_search() {
        check_ajax_referer('saw_' . $this->entity . '_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        
        $results = $this->model->get_all([
            'search' => $query,
            'per_page' => 10,
            'orderby' => 'name',
            'order' => 'ASC'
        ]);
        
        wp_send_json_success(['results' => $results['items'] ?? $results]);
    }
    
    /**
     * AJAX: Delete item
     */
    public function ajax_delete() {
        check_ajax_referer('saw_admin_table_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $entity = isset($_POST['entity']) ? sanitize_text_field($_POST['entity']) : '';
        
        if ($entity !== $this->entity) {
            wp_send_json_error(['message' => 'Invalid entity']);
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Invalid ID']);
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(['message' => ucfirst($this->entity) . ' not found']);
        }
        
        if (!$this->before_delete($id)) {
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
     */
    public function ajax_get_detail() {
        check_ajax_referer('saw_customer_modal_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $id_key = $this->entity . '_id';
        $id = isset($_POST[$id_key]) ? intval($_POST[$id_key]) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Invalid ID']);
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(['message' => ucfirst($this->entity) . ' not found']);
        }
        
        $item = $this->format_detail_data($item);
        
        wp_send_json_success([$this->entity => $item]);
    }
    
    /**
     * Format detail data for modal (override in child class)
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
}
