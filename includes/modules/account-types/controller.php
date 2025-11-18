<?php
/**
 * Account Types Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     3.0.0 - REFACTORED: New architecture with render_list_view()
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
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/class-saw-color-picker.php';
        $this->color_picker = new SAW_Color_Picker();
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_color_picker_assets'));
    }
    
    /**
     * ✅ NEW: Using render_list_view() from Base Controller
     */
    public function index() {
        $this->render_list_view();
    }
    
    /**
     * Enqueue color picker assets
     */
    public function enqueue_color_picker_assets() {
        $this->color_picker->enqueue_assets();
    }
    
    /**
     * ✅ OVERRIDE: Enqueue module assets
     */
    protected function enqueue_assets() {
        // Enqueue color picker
        $this->color_picker->enqueue_assets();
        
        // Enqueue module-specific JS if exists
        $js_path = $this->config['path'] . 'scripts.js';
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'saw-module-account-types',
                SAW_VISITORS_PLUGIN_URL . 'includes/modules/account-types/scripts.js',
                array('jquery'),
                SAW_VISITORS_VERSION,
                true
            );
        }
        
        // Module CSS handled by SAW_Module_Style_Manager
        if (class_exists('SAW_Asset_Manager')) {
            SAW_Asset_Manager::enqueue_module('account-types');
        }
    }
    
    /**
     * Prepare form data from POST
     */
    protected function prepare_form_data($post) {
        $data = array();
        
        // Text fields (remove 'description' - not in DB)
        $text_fields = array('name', 'display_name', 'color');
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        // Price field
        if (isset($post['price'])) {
            $data['price'] = floatval($post['price']);
        }
        
        // Features field (convert textarea to array)
        if (isset($post['features'])) {
            $features_text = sanitize_textarea_field($post['features']);
            $features_array = array_filter(
                array_map('trim', explode("\n", $features_text)),
                function($line) {
                    return !empty($line);
                }
            );
            $data['features'] = $features_array;
        }
        
        // Sort order field
        if (isset($post['sort_order'])) {
            $data['sort_order'] = intval($post['sort_order']);
        }
        
        // Is active checkbox
        $data['is_active'] = isset($post['is_active']) ? 1 : 0;
        
        return $data;
    }
    
    /**
     * Before save hook - validate color format
     */
    protected function before_save($data) {
        if (!empty($data['color']) && !preg_match('/^#[0-9a-f]{6}$/i', $data['color'])) {
            return new WP_Error('invalid_color', __('Neplatný formát barvy', 'saw-visitors'));
        }
        
        return $data;
    }
    
    /**
     * Format detail data for sidebar
     */
    protected function format_detail_data($item) {
        // Already formatted in model's get_by_id()
        return $item;
    }
    
    /**
     * After save hook - invalidate cache
     */
    protected function after_save($id) {
        delete_transient('account_types_list');
    }
}