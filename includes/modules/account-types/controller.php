<?php
/**
 * Account Types Module Controller
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.2.0 - FIXED: Correct data format handling
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
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
     * Index - render list
     */
    public function index() {
        $this->render_list_view();
    }
    
    /**
     * Render list view
     */
    protected function render_list_view() {
        // Tab
        $current_tab = $this->get_current_tab();
        
        // Query args
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
        
        // Get data from model
        $result = $this->model->get_all($args);
        
        // Extract items - handle both formats
        if (isset($result['items'])) {
            $items = $result['items'];
        } else {
            $items = is_array($result) ? $result : [];
        }
        
        // Enrich items
        foreach ($items as &$item) {
            $item['customers_count'] = $this->count_customers($item['id']);
        }
        unset($item);
        
        // Tab counts
        $tab_counts = [
            'all' => $this->model->count(),
            'active' => $this->model->count(['is_active' => 1]),
            'inactive' => $this->model->count(['is_active' => 0]),
        ];
        
        $total = $tab_counts[$current_tab] ?? count($items);
        
        // Sidebar
        $sidebar_mode = $this->get_sidebar_mode();
        $detail_item = null;
        $form_item = null;
        $related_data = [];
        
        if ($sidebar_mode === 'detail') {
            $detail_id = $this->get_detail_id();
            if ($detail_id) {
                $detail_item = $this->model->get_by_id($detail_id);
                if ($detail_item) {
                    $detail_item['customers_count'] = $this->count_customers($detail_id);
                    $related_data = $this->load_related_data($detail_id);
                }
            }
        } elseif ($sidebar_mode === 'form') {
            $edit_id = $this->get_edit_id();
            if ($edit_id) {
                $form_item = $this->model->get_by_id($edit_id);
            }
        }
        
        // Pass to template
        $config = $this->config;
        
        include $this->config['path'] . 'list-template.php';
    }
    
    /**
     * Count customers
     */
    protected function count_customers($id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_customers WHERE account_type_id = %d",
            $id
        ));
    }
    
    /**
     * Load related data
     */
    protected function load_related_data($id) {
        global $wpdb;
        return [
            'customers' => $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, status FROM {$wpdb->prefix}saw_customers WHERE account_type_id = %d ORDER BY name LIMIT 10",
                $id
            ), ARRAY_A) ?: [],
        ];
    }
    
    /**
     * Get current tab
     */
    protected function get_current_tab() {
        $tab = sanitize_key($_GET['tab'] ?? 'all');
        return in_array($tab, ['all', 'active', 'inactive']) ? $tab : 'all';
    }
    
    /**
     * Get sidebar mode
     */
    protected function get_sidebar_mode() {
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if (preg_match('/\/create\/?$/', $path)) return 'form';
        if (preg_match('/\/\d+\/edit\/?$/', $path)) return 'form';
        if (preg_match('/\/(\d+)\/?$/', $path)) return 'detail';
        return null;
    }
    
    /**
     * Get detail ID
     */
    protected function get_detail_id() {
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if (preg_match('/\/(\d+)\/?$/', $path, $m)) return intval($m[1]);
        return null;
    }
    
    /**
     * Get edit ID
     */
    protected function get_edit_id() {
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if (preg_match('/\/(\d+)\/edit\/?$/', $path, $m)) return intval($m[1]);
        return null;
    }
    
    /**
     * AJAX: Get detail
     */
    public function ajax_get_detail() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => 'Chybí ID']);
        }
        
        $item = $this->model->get_by_id($id);
        if (!$item) {
            wp_send_json_error(['message' => 'Záznam nenalezen']);
        }
        
        $item['customers_count'] = $this->count_customers($id);
        $related_data = $this->load_related_data($id);
        
        // Load SAW Table
        $autoload = SAW_VISITORS_PLUGIN_DIR . 'includes/components/saw-table/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        
        $html = '';
        if (class_exists('SAW_Detail_Renderer')) {
            $html = SAW_Detail_Renderer::render($this->config, $item, $related_data, 'account_types');
        }
        
        wp_send_json_success(['html' => $html, 'item' => $item]);
    }
    
    /**
     * AJAX: Delete
     */
    public function ajax_delete() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => 'Chybí ID']);
        }
        
        // Check usage
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
