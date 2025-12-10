<?php
/**
 * Account Types Module Controller
 *
 * @version     6.1.0 - FIXED: render_list_view($options = []) signature
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Controller extends SAW_Base_Controller 
{
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/account-types/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Account_Types_Model($this->config);
        
        // AJAX handlers
        add_action('wp_ajax_saw_get_account_types_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_account_types', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_account_types', [$this, 'ajax_delete']);
        add_action('wp_ajax_saw_load_sidebar_account_types', [$this, 'ajax_load_sidebar']);
    }
    
    /**
     * Index - render list page
     */
    public function index() {
        $this->render_list_view();
    }
    
    /**
     * Render list view
     * 
     * FIXED: Must have $options = [] parameter to match parent!
     * 
     * @param array $options Optional overrides
     */
    protected function render_list_view($options = []) {
        // 1. Verify access
        if (method_exists($this, 'verify_module_access')) {
            $this->verify_module_access();
        }
        
        // 2. Enqueue assets
        $this->enqueue_assets();
        
        // 3. Start output buffering
        ob_start();
        
        // 4. Get current tab
        $current_tab = $this->get_current_tab();
        
        // 5. Build query args
        $args = [];
        if ($current_tab === 'active') {
            $args['is_active'] = 1;
        } elseif ($current_tab === 'inactive') {
            $args['is_active'] = 0;
        }
        
        // Search
        if (!empty($_GET['search'])) {
            $args['search'] = sanitize_text_field($_GET['search']);
        }
        
        // Order
        $args['orderby'] = sanitize_key($_GET['orderby'] ?? 'sort_order');
        $args['order'] = strtoupper(sanitize_key($_GET['order'] ?? 'ASC'));
        
        // 6. Get data from model
        $result = $this->model->get_all($args);
        $items = isset($result['items']) ? $result['items'] : (is_array($result) ? $result : []);
        
        // 7. Enrich items with customers count
        foreach ($items as &$item) {
            $item['customers_count'] = $this->count_customers($item['id']);
        }
        unset($item);
        
        // 8. Tab counts
        $tab_counts = [
            'all' => $this->model->count(),
            'active' => $this->model->count(['is_active' => 1]),
            'inactive' => $this->model->count(['is_active' => 0]),
        ];
        
        $total = count($items);
        
        // 9. Sidebar context
        $sidebar_mode = null;
        $detail_item = null;
        
        if (method_exists($this, 'get_sidebar_context')) {
            $context = $this->get_sidebar_context();
            $sidebar_mode = $context['mode'] ?? null;
            
            if ($sidebar_mode === 'detail' && !empty($context['id'])) {
                $detail_item = $this->model->get_by_id($context['id']);
                if ($detail_item) {
                    $detail_item = $this->format_detail_data($detail_item);
                }
            }
        }
        
        // 10. Prepare variables for template
        $config = $this->config;
        $entity = $this->entity;
        
        // 11. Render wrapper and flash messages
        echo '<div class="saw-module-wrapper" data-entity="' . esc_attr($this->entity) . '">';
        if (method_exists($this, 'render_flash_messages')) {
            $this->render_flash_messages();
        }
        
        // 12. Include template
        include $this->config['path'] . 'list-template.php';
        
        // 13. Close wrapper
        echo '</div>';
        
        // 14. Get content
        $content = ob_get_clean();
        
        // 15. Wrap in layout
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    /**
     * Get current tab
     */
    protected function get_current_tab() {
        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'all';
        $valid_tabs = ['all', 'active', 'inactive'];
        return in_array($tab, $valid_tabs) ? $tab : 'all';
    }
    
    /**
     * Get display name for detail header
     */
    public function get_display_name($item) {
        return $item['display_name'] ?? $item['name'] ?? 'Typ účtu';
    }
    
    /**
     * Format detail data
     */
    protected function format_detail_data($item) {
        $item['customers_count'] = $this->count_customers($item['id']);
        return $item;
    }
    
    /**
     * Count customers using this account type
     */
    protected function count_customers($account_type_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_customers WHERE account_type_id = %d",
            $account_type_id
        ));
    }
    
    /**
     * AJAX: Get detail
     */
    public function ajax_get_detail() {
        check_ajax_referer('saw_admin_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => 'Chybí ID']);
        }
        
        $item = $this->model->get_by_id($id);
        if (!$item) {
            wp_send_json_error(['message' => 'Nenalezeno']);
        }
        
        $item = $this->format_detail_data($item);
        
        wp_send_json_success(['item' => $item]);
    }
    
    /**
     * AJAX: Search
     */
    public function ajax_search() {
        check_ajax_referer('saw_admin_nonce', 'nonce');
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        $items = $this->model->get_all(['search' => $search]);
        
        wp_send_json_success(['items' => $items['items'] ?? $items]);
    }
    
    /**
     * AJAX: Delete
     */
    public function ajax_delete() {
        check_ajax_referer('saw_admin_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => 'Chybí ID']);
        }
        
        $count = $this->count_customers($id);
        if ($count > 0) {
            wp_send_json_error(['message' => "Nelze smazat - používá {$count} zákazníků"]);
        }
        
        $result = $this->model->delete($id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => 'Smazáno']);
    }
}