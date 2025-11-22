<?php
/**
 * AJAX Handlers Trait
 *
 * Provides common AJAX handlers for module controllers.
 * Includes search, delete, and detail view handlers with permission checks.
 *
 * @package    SAW_Visitors
 * @subpackage Base
 * @version    5.1.0 - Unified nonce verification
 * @since      1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW_AJAX_Handlers Trait
 *
 * Reusable AJAX handlers with security and permission checks.
 * Used by module controllers via 'use SAW_AJAX_Handlers'.
 *
 * @since 1.0.0
 */
trait SAW_AJAX_Handlers 
{
    /**
     * AJAX search handler
     *
     * Handles search requests for module entities.
     * Requires 'list' permission.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_search() {
        saw_verify_ajax_unified();
        
        if (!$this->can_perform_action('list')) {
            wp_send_json_error(['message' => __('Nedostatečná oprávnění', 'saw-visitors')]);
            return;
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        
        if (empty($search)) {
            wp_send_json_error(['message' => __('Prázdný vyhledávací dotaz', 'saw-visitors')]);
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
     * AJAX delete handler
     *
     * Handles delete requests for module entities.
     * Requires 'delete' permission and validates item access.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_delete() {
        saw_verify_ajax_unified();
        
        if (!$this->can_perform_action('delete')) {
            wp_send_json_error(['message' => __('Nedostatečná oprávnění', 'saw-visitors')]);
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => __('Neplatné ID', 'saw-visitors')]);
            return;
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: entity name */
                    __('%s nenalezen', 'saw-visitors'),
                    ucfirst($this->entity)
                )
            ]);
            return;
        }
        
        if (!$this->can_access_item($item)) {
            wp_send_json_error(['message' => __('Nemáte oprávnění k tomuto záznamu', 'saw-visitors')]);
            return;
        }
        
        $before_delete_result = $this->before_delete($id);
        
        if (is_wp_error($before_delete_result)) {
            wp_send_json_error(['message' => $before_delete_result->get_error_message()]);
            return;
        }
        
        if ($before_delete_result === false) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: entity name */
                    __('Nelze smazat %s', 'saw-visitors'),
                    $this->entity
                )
            ]);
            return;
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }
        
        $this->after_delete($id);
        
        wp_send_json_success([
            'message' => sprintf(
                /* translators: %s: entity name */
                __('%s byl úspěšně smazán', 'saw-visitors'),
                ucfirst($this->entity)
            )
        ]);
    }
    
    /**
     * AJAX get detail handler
     *
     * Handles detail view requests for module entities.
     * Requires 'view' permission and validates item access.
     * Renders detail modal template.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_get_detail() {
        saw_verify_ajax_unified();
        
        if (!$this->can_perform_action('view')) {
            wp_send_json_error(['message' => __('Nedostatečná oprávnění', 'saw-visitors')]);
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => __('Neplatné ID', 'saw-visitors')]);
            return;
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: entity name */
                    __('%s nenalezen', 'saw-visitors'),
                    ucfirst($this->entity)
                )
            ]);
            return;
        }
        
        if (!$this->can_access_item($item)) {
            wp_send_json_error(['message' => __('Nemáte oprávnění k tomuto záznamu', 'saw-visitors')]);
            return;
        }
        
        $item = $this->format_detail_data($item);
        
        $template_path = $this->config['path'] . 'detail-modal-template.php';
        
        if (!file_exists($template_path)) {
            wp_send_json_error(['message' => __('Template nebyl nalezen', 'saw-visitors')]);
            return;
        }
        
        ob_start();
        
        try {
            require $template_path;
            $html = ob_get_clean();
            
            if (empty($html)) {
                throw new Exception(__('Template vygeneroval prázdný obsah', 'saw-visitors'));
            }
            
            if (strlen($html) < 50) {
                throw new Exception(__('Template vygeneroval příliš krátký obsah', 'saw-visitors'));
            }
            
            wp_send_json_success([
                'html' => $html,
                'item' => $item,
            ]);
            
        } catch (Exception $e) {
            ob_end_clean();
            
            wp_send_json_error([
                'message' => sprintf(
                    /* translators: %s: error message */
                    __('Chyba při zobrazení detailu: %s', 'saw-visitors'),
                    $e->getMessage()
                )
            ]);
        }
    }
    
    /**
     * Check if user can access item
     *
     * Validates customer isolation if enabled for entity.
     * Super admins bypass this check.
     *
     * @since 1.0.0
     * @param array $item Item data
     * @return bool True if accessible
     */
    protected function can_access_item($item) {
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin') {
            return true;
        }
        
        // Check if entity has customer isolation
        $has_isolation = isset($this->config['has_customer_isolation']) 
            ? $this->config['has_customer_isolation'] 
            : true;
        
        if (!$has_isolation) {
            return true;
        }
        
        // Validate customer isolation
        if (isset($item['customer_id'])) {
            $current_customer_id = null;
            
            if (class_exists('SAW_Context')) {
                $current_customer_id = SAW_Context::get_customer_id();
            }
            
            if (!$current_customer_id) {
                return false;
            }
            
            return (int)$item['customer_id'] === (int)$current_customer_id;
        }
        
        return true;
    }
    
    /**
     * Check if user can perform action
     *
     * Universal permission check method.
     * Loads SAW_Permissions if not available.
     *
     * @since 2.2.0
     * @param string $action Action name (list, view, create, edit, delete)
     * @return bool True if allowed
     */
    protected function can_perform_action($action) {
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin') {
            return true;
        }
        
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        if (!class_exists('SAW_Permissions') || empty($role)) {
            return false;
        }
        
        return SAW_Permissions::check($role, $this->entity, $action);
    }
    
    /**
     * Format detail data
     *
     * Formats dates and other data for detail view.
     * Override in controller for custom formatting.
     *
     * @since 1.0.0
     * @param array $item Item data
     * @return array Formatted item data
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
     *
     * Formats single search result item.
     * Override in controller for custom formatting.
     *
     * @since 1.0.0
     * @param array $item Item data
     * @return array Formatted item data
     */
    protected function format_search_result($item) {
        return $item;
    }
}