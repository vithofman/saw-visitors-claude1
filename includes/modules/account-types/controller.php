<?php
/**
 * Account Types Module Controller
 *
 * Updated for SAW Table component integration.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.0.0 - SAW Table Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    /**
     * Color picker component
     * @var SAW_Color_Picker|null
     */
    private $color_picker;
    
    /**
     * Constructor
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/account-types/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Account_Types_Model($this->config);
        
        // Color picker component
        if (file_exists(SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/class-saw-color-picker.php')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/class-saw-color-picker.php';
            $this->color_picker = new SAW_Color_Picker();
        }
        
        // Register AJAX handlers
        $this->register_ajax_handlers();
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Detail sidebar
        add_action('wp_ajax_saw_get_account_types_detail', [$this, 'ajax_get_detail']);
        
        // Search
        add_action('wp_ajax_saw_search_account_types', [$this, 'ajax_search']);
        
        // CRUD
        add_action('wp_ajax_saw_create_account_types', [$this, 'ajax_create']);
        add_action('wp_ajax_saw_update_account_types', [$this, 'ajax_update']);
        add_action('wp_ajax_saw_delete_account_types', [$this, 'ajax_delete']);
        
        // Form sidebar
        add_action('wp_ajax_saw_load_sidebar_account_types', [$this, 'ajax_load_sidebar']);
        
        // Navigation
        add_action('wp_ajax_saw_get_adjacent_account_types', [$this, 'ajax_get_adjacent_id']);
    }
    
    /**
     * Render list view
     */
    public function index() {
        $this->render_list_view();
    }
    
    /**
     * Render list view with SAW Table
     */
    protected function render_list_view() {
        // Get current tab
        $current_tab = $this->get_current_tab();
        
        // Build query args
        $args = $this->build_list_query_args();
        
        // Get items
        $items = $this->model->get_all($args);
        
        // Enrich items with computed data
        $items = $this->enrich_items($items);
        
        // Get tab counts
        $tab_counts = $this->get_tab_counts();
        
        // Total count
        $total = $tab_counts[$current_tab ?? 'all'] ?? count($items);
        
        // Detail/Form sidebar
        $sidebar_mode = $this->get_sidebar_mode();
        $detail_item = null;
        $form_item = null;
        $related_data = [];
        
        if ($sidebar_mode === 'detail') {
            $detail_id = $this->get_detail_id();
            if ($detail_id) {
                $detail_item = $this->model->get_by_id($detail_id);
                if ($detail_item) {
                    $detail_item = $this->format_detail_item($detail_item);
                    $related_data = $this->load_related_data($detail_id);
                }
            }
        } elseif ($sidebar_mode === 'form') {
            $edit_id = $this->get_edit_id();
            if ($edit_id) {
                $form_item = $this->model->get_by_id($edit_id);
            }
        }
        
        // Prepare template variables
        $template_vars = [
            'items' => $items,
            'total' => $total,
            'current_tab' => $current_tab,
            'tab_counts' => $tab_counts,
            'detail_item' => $detail_item,
            'form_item' => $form_item,
            'sidebar_mode' => $sidebar_mode,
            'related_data' => $related_data,
            'config' => $this->config,
        ];
        
        // Extract for template
        extract($template_vars);
        
        // Include template
        include $this->config['path'] . 'list-template.php';
    }
    
    /**
     * Enrich items with computed data
     */
    protected function enrich_items($items) {
        foreach ($items as &$item) {
            // Count customers with this account type
            $item['customers_count'] = $this->count_customers($item['id']);
        }
        return $items;
    }
    
    /**
     * Format item for detail sidebar
     */
    protected function format_detail_item($item) {
        // Count customers
        $item['customers_count'] = $this->count_customers($item['id']);
        
        // Format dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
        }
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Load related data for detail sidebar
     */
    protected function load_related_data($id) {
        global $wpdb;
        
        $related = [];
        
        // Get customers with this account type (max 10)
        $related['customers'] = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, status 
             FROM {$wpdb->prefix}saw_customers 
             WHERE account_type_id = %d 
             ORDER BY name ASC 
             LIMIT 10",
            $id
        ), ARRAY_A) ?: [];
        
        return $related;
    }
    
    /**
     * Count customers with account type
     */
    protected function count_customers($account_type_id) {
        global $wpdb;
        
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_customers WHERE account_type_id = %d",
            $account_type_id
        ));
    }
    
    /**
     * Get display name for detail header
     */
    public function get_display_name($item) {
        return $item['display_name'] ?? $item['name'] ?? 'Typ účtu #' . $item['id'];
    }
    
    /**
     * AJAX: Get detail sidebar content
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
        
        $item = $this->format_detail_item($item);
        $related_data = $this->load_related_data($id);
        
        // Load SAW Table component
        $autoload = SAW_VISITORS_PLUGIN_DIR . 'includes/components/saw-table/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        
        // Render detail
        $html = '';
        if (function_exists('saw_table_render_detail')) {
            $html = saw_table_render_detail($this->config, $item, $related_data, 'account_types');
        } elseif (class_exists('SAW_Detail_Renderer')) {
            $html = SAW_Detail_Renderer::render($this->config, $item, $related_data, 'account_types');
        }
        
        wp_send_json_success([
            'html' => $html,
            'item' => $item,
        ]);
    }
    
    /**
     * AJAX: Load form sidebar
     */
    public function ajax_load_sidebar() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        $mode = sanitize_text_field($_POST['mode'] ?? 'create');
        $id = intval($_POST['id'] ?? 0);
        
        $item = null;
        if ($mode === 'edit' && $id) {
            $item = $this->model->get_by_id($id);
            if (!$item) {
                wp_send_json_error(['message' => 'Záznam nenalezen']);
            }
        }
        
        // Load SAW Table component
        $autoload = SAW_VISITORS_PLUGIN_DIR . 'includes/components/saw-table/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }
        
        // Render form
        $html = '';
        if (function_exists('saw_table_render_form')) {
            $html = saw_table_render_form($this->config, $item, 'account_types');
        } elseif (class_exists('SAW_Form_Renderer')) {
            $html = SAW_Form_Renderer::render($this->config, $item, 'account_types');
        }
        
        wp_send_json_success([
            'html' => $html,
            'item' => $item,
            'mode' => $mode,
        ]);
    }
    
    /**
     * AJAX: Create account type
     */
    public function ajax_create() {
        check_ajax_referer('saw_create_account_types', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        $data = $this->sanitize_form_data($_POST);
        
        // Validate
        $errors = $this->validate_form_data($data);
        if (!empty($errors)) {
            wp_send_json_error([
                'message' => 'Opravte chyby ve formuláři',
                'errors' => $errors,
            ]);
        }
        
        // Create
        $result = $this->model->create($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success([
            'message' => 'Typ účtu byl vytvořen',
            'id' => $result,
        ]);
    }
    
    /**
     * AJAX: Update account type
     */
    public function ajax_update() {
        check_ajax_referer('saw_update_account_types', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            wp_send_json_error(['message' => 'Chybí ID']);
        }
        
        $data = $this->sanitize_form_data($_POST);
        
        // Validate
        $errors = $this->validate_form_data($data, $id);
        if (!empty($errors)) {
            wp_send_json_error([
                'message' => 'Opravte chyby ve formuláři',
                'errors' => $errors,
            ]);
        }
        
        // Update
        $result = $this->model->update($id, $data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success([
            'message' => 'Typ účtu byl aktualizován',
            'id' => $id,
        ]);
    }
    
    /**
     * AJAX: Delete account type
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
        
        // Check if used by customers
        $customers_count = $this->count_customers($id);
        if ($customers_count > 0) {
            wp_send_json_error([
                'message' => sprintf('Nelze smazat - typ účtu používá %d zákazníků', $customers_count)
            ]);
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => 'Typ účtu byl smazán']);
    }
    
    /**
     * Sanitize form data
     */
    protected function sanitize_form_data($data) {
        return [
            'name' => sanitize_key($data['name'] ?? ''),
            'display_name' => sanitize_text_field($data['display_name'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'color' => sanitize_hex_color($data['color'] ?? '#3b82f6'),
            'price' => intval($data['price'] ?? 0),
            'price_yearly' => intval($data['price_yearly'] ?? 0),
            'max_users' => intval($data['max_users'] ?? 0),
            'max_branches' => intval($data['max_branches'] ?? 0),
            'max_visitors_monthly' => intval($data['max_visitors_monthly'] ?? 0),
            'has_api_access' => intval($data['has_api_access'] ?? 0),
            'has_custom_branding' => intval($data['has_custom_branding'] ?? 0),
            'has_advanced_reports' => intval($data['has_advanced_reports'] ?? 0),
            'has_sso' => intval($data['has_sso'] ?? 0),
            'has_priority_support' => intval($data['has_priority_support'] ?? 0),
            'sort_order' => intval($data['sort_order'] ?? 0),
            'is_active' => intval($data['is_active'] ?? 0),
        ];
    }
    
    /**
     * Validate form data
     */
    protected function validate_form_data($data, $id = null) {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Systémový název je povinný';
        } elseif (!preg_match('/^[a-z0-9_-]+$/', $data['name'])) {
            $errors['name'] = 'Pouze malá písmena, čísla, pomlčky a podtržítka';
        } else {
            // Check uniqueness
            $existing = $this->model->get_by_field('name', $data['name']);
            if ($existing && (!$id || $existing['id'] != $id)) {
                $errors['name'] = 'Tento systémový název již existuje';
            }
        }
        
        if (empty($data['display_name'])) {
            $errors['display_name'] = 'Zobrazovaný název je povinný';
        }
        
        return $errors;
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        // Color picker
        if ($this->color_picker) {
            $this->color_picker->enqueue();
        }
        
        // SAW Table assets
        if (function_exists('saw_table_enqueue_assets')) {
            saw_table_enqueue_assets();
        }
    }
    
    /**
     * Get tab counts
     */
    protected function get_tab_counts() {
        $counts = [];
        
        $counts['all'] = $this->model->count();
        $counts['active'] = $this->model->count(['is_active' => 1]);
        $counts['inactive'] = $this->model->count(['is_active' => 0]);
        
        return $counts;
    }
    
    /**
     * Build list query args from request
     */
    protected function build_list_query_args() {
        $args = [];
        
        // Tab filter
        $tab = $this->get_current_tab();
        if ($tab === 'active') {
            $args['is_active'] = 1;
        } elseif ($tab === 'inactive') {
            $args['is_active'] = 0;
        }
        
        // Search
        if (!empty($_GET['search'])) {
            $args['search'] = sanitize_text_field($_GET['search']);
        }
        
        // Order
        $args['orderby'] = sanitize_key($_GET['orderby'] ?? $this->config['list_config']['default_orderby'] ?? 'sort_order');
        $args['order'] = strtoupper(sanitize_key($_GET['order'] ?? $this->config['list_config']['default_order'] ?? 'ASC'));
        
        return $args;
    }
    
    /**
     * Get current tab from URL
     */
    protected function get_current_tab() {
        if (!isset($_GET['tab'])) {
            return $this->config['tabs']['default_tab'] ?? 'all';
        }
        
        $tab = sanitize_key($_GET['tab']);
        $valid_tabs = array_keys($this->config['tabs']['tabs'] ?? []);
        
        return in_array($tab, $valid_tabs) ? $tab : 'all';
    }
    
    /**
     * Get sidebar mode from URL
     */
    protected function get_sidebar_mode() {
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        
        if (preg_match('/\/create\/?$/', $path)) {
            return 'form';
        }
        
        if (preg_match('/\/(\d+)\/edit\/?$/', $path)) {
            return 'form';
        }
        
        if (preg_match('/\/(\d+)\/?$/', $path)) {
            return 'detail';
        }
        
        return null;
    }
    
    /**
     * Get detail ID from URL
     */
    protected function get_detail_id() {
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        
        if (preg_match('/\/(\d+)\/?$/', $path, $matches)) {
            return intval($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Get edit ID from URL
     */
    protected function get_edit_id() {
        $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        
        if (preg_match('/\/(\d+)\/edit\/?$/', $path, $matches)) {
            return intval($matches[1]);
        }
        
        return null;
    }
}
