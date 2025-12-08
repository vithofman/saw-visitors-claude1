<?php
/**
 * Account Types Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     3.2.0 - FIXED: All AJAX handlers, tab counts, prev/next
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Account_Types_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $color_picker;
    
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
        add_action('wp_ajax_saw_get_account_types_detail', array($this, 'ajax_get_detail'));
        add_action('wp_ajax_saw_search_account_types', array($this, 'ajax_search'));
        add_action('wp_ajax_saw_delete_account_types', array($this, 'ajax_delete'));
        add_action('wp_ajax_saw_load_sidebar_account_types', array($this, 'ajax_load_sidebar'));
        add_action('wp_ajax_saw_get_adjacent_account_types', array($this, 'ajax_get_adjacent_id'));
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Render list view
     */
    public function index() {
        $this->render_list_view();
    }
    
    /**
     * Get display name for detail header
     */
    public function get_display_name($item) {
        return $item['display_name'] ?? 'Typ účtu';
    }
    
    /**
     * Get header meta for detail sidebar
     */
    protected function get_detail_header_meta($item) {
        if (empty($item)) {
            return '';
        }
        
        $meta_parts = array();
        
        // Color swatch
        if (!empty($item['color'])) {
            $meta_parts[] = sprintf(
                '<span style="display: inline-block; width: 18px; height: 18px; border-radius: 4px; background-color: %s; border: 2px solid rgba(255,255,255,0.3); vertical-align: middle; margin-right: 4px;"></span>',
                esc_attr($item['color'])
            );
        }
        
        // Internal name
        if (!empty($item['name'])) {
            $meta_parts[] = sprintf(
                '<span class="saw-badge-transparent saw-badge-secondary" style="font-family: monospace; text-transform: uppercase;">%s</span>',
                esc_html($item['name'])
            );
        }
        
        // Status badge
        if (!empty($item['is_active'])) {
            $meta_parts[] = '<span class="saw-badge-transparent saw-badge-success">✓ Aktivní</span>';
        } else {
            $meta_parts[] = '<span class="saw-badge-transparent saw-badge-secondary">✕ Neaktivní</span>';
        }
        
        return implode(' ', $meta_parts);
    }
    
    /**
     * Get tab counts for list view
     */
    protected function get_tab_counts() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'saw_account_types';
        
        $counts = array();
        
        // All
        $counts['all'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        
        // Active
        $counts['active'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_active = 1");
        
        // Inactive
        $counts['inactive'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_active = 0");
        
        return $counts;
    }
    
    /**
     * Delete handler with in-use check
     */
    public function ajax_delete() {
        saw_verify_ajax_unified();
        
        if (!$this->can('delete')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění mazat záznamy'));
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => 'Neplatné ID'));
            return;
        }
        
        // Check if in use
        global $wpdb;
        $in_use = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_customers WHERE account_type_id = %d",
            $id
        ));
        
        if ($in_use > 0) {
            wp_send_json_error(array(
                'message' => 'Tento typ účtu nelze smazat, protože je přiřazen ' . $in_use . ' zákazníkům'
            ));
            return;
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        $this->after_delete($id);
        
        wp_send_json_success(array('message' => 'Typ účtu byl úspěšně smazán'));
    }
    
    /**
     * Prepare form data from POST
     */
    protected function prepare_form_data($post) {
        $data = array();
        
        // Text fields
        if (isset($post['name'])) {
            $data['name'] = sanitize_text_field($post['name']);
        }
        if (isset($post['display_name'])) {
            $data['display_name'] = sanitize_text_field($post['display_name']);
        }
        if (isset($post['color'])) {
            $data['color'] = sanitize_hex_color($post['color']);
        }
        
        // Price
        if (isset($post['price'])) {
            $data['price'] = floatval($post['price']);
        }
        
        // Features (textarea to JSON array)
        if (isset($post['features'])) {
            $features_text = sanitize_textarea_field($post['features']);
            $features_array = array_filter(
                array_map('trim', explode("\n", $features_text)),
                function($line) { return !empty($line); }
            );
            $data['features'] = $features_array;
        }
        
        // Sort order
        if (isset($post['sort_order'])) {
            $data['sort_order'] = intval($post['sort_order']);
        }
        
        // Is active
        $data['is_active'] = isset($post['is_active']) ? 1 : 0;
        
        return $data;
    }
    
    /**
     * Before save validation
     */
    protected function before_save($data) {
        if (!empty($data['color']) && !preg_match('/^#[0-9a-f]{6}$/i', $data['color'])) {
            return new WP_Error('invalid_color', 'Neplatný formát barvy');
        }
        return $data;
    }
    
    /**
     * After save - clear cache
     */
    protected function after_save($id) {
        delete_transient('account_types_list');
        delete_transient('account_types_dropdown');
    }
    
    /**
     * After delete - clear cache
     */
    protected function after_delete($id) {
        delete_transient('account_types_list');
        delete_transient('account_types_dropdown');
    }
    
    /**
     * Format detail data
     */
    protected function format_detail_data($item) {
        // Features
        if (!empty($item['features'])) {
            $features = json_decode($item['features'], true);
            $item['features_array'] = is_array($features) ? $features : array();
        } else {
            $item['features_array'] = array();
        }
        
        // Price
        $price = floatval($item['price'] ?? 0);
        $item['price_formatted'] = $price > 0 
            ? number_format($price, 0, ',', ' ') . ' Kč' 
            : 'Zdarma';
        
        // Customers count
        global $wpdb;
        $item['customers_count'] = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_customers WHERE account_type_id = %d",
            $item['id']
        ));
        
        return $item;
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        if ($this->color_picker) {
            $this->color_picker->enqueue_assets();
        }
        
        if (class_exists('SAW_Asset_Loader')) {
            SAW_Asset_Loader::enqueue_module('account-types');
        }
    }
}
