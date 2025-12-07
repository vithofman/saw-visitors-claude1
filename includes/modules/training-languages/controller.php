<?php
/**
 * Training Languages Controller
 *
 * @package    SAW_Visitors
 * @subpackage Modules/TrainingLanguages
 * @version    5.0.0 - ADDED: get_display_name, get_detail_header_meta for blue header
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Training_Languages_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    /** @var array Translation strings */
    private $translations = array();
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/training-languages/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Training_Languages_Model($this->config);
        
        if (file_exists($module_path . 'class-auto-setup.php')) {
            require_once $module_path . 'class-auto-setup.php';
        }
        
        // Initialize translations
        $this->init_translations();
        
        // Enqueue assets with high priority
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'), 999);
    }
    
    /**
     * Initialize translations
     */
    private function init_translations() {
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        
        $this->translations = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'training_languages') 
            : array();
    }
    
    /**
     * Get translation
     * 
     * @param string $key Translation key
     * @param string $fallback Fallback text
     * @return string
     */
    private function tr($key, $fallback = null) {
        return $this->translations[$key] ?? $fallback ?? $key;
    }
    
    /**
     * Main Entry Point
     */
    public function index() {
        $sidebar_data = $this->process_sidebar_context();
        if ($sidebar_data === null) return;
        
        $this->config = array_merge($this->config, $sidebar_data);
        
        if ($this->get_sidebar_mode() === 'create') {
            $this->config['available_branches'] = $this->model->get_available_branches();
            $this->config['languages_data'] = require $this->config['path'] . 'languages-data.php';
        } 
        elseif ($this->get_sidebar_mode() === 'edit') {
            $this->config['languages_data'] = require $this->config['path'] . 'languages-data.php';
        }
        
        $this->render_list_view();
    }
    
    /**
     * Get display name for detail header
     * 
     * @param array $item Item data
     * @return string Display name
     */
    public function get_display_name($item) {
        return $item['language_name'] ?? $this->tr('singular', 'Jazyk školení');
    }
    
    /**
     * Get header meta for detail sidebar (blue header)
     * 
     * Shows: flag emoji, language code, branches count
     * NO ID displayed!
     * 
     * @param array $item Item data
     * @return string HTML for header meta
     */
    public function get_detail_header_meta($item) {
        if (empty($item)) {
            return '';
        }
        
        $meta = array();
        
        // Flag emoji (large)
        if (!empty($item['flag_emoji'])) {
            $meta[] = '<span class="saw-header-flag">' . esc_html($item['flag_emoji']) . '</span>';
        }
        
        // Language code
        if (!empty($item['language_code'])) {
            $meta[] = '<span class="saw-badge-transparent saw-badge-code">' . esc_html(strtoupper($item['language_code'])) . '</span>';
        }
        
        // Branches count
        $branches_count = 0;
        if (!empty($item['active_branches'])) {
            $branches_count = count($item['active_branches']);
        }
        $meta[] = '<span class="saw-badge-transparent">' . $branches_count . ' ' . esc_html($this->tr('header_branches', 'poboček')) . '</span>';
        
        // Required badge for Czech
        if (($item['language_code'] ?? '') === 'cs') {
            $meta[] = '<span class="saw-badge-transparent saw-badge-info">' . esc_html($this->tr('badge_required', 'Povinný')) . '</span>';
        }
        
        return implode(' ', $meta);
    }
    
    /**
     * Format detail data
     * 
     * @param array $item Item data
     * @return array Formatted item
     */
    protected function format_detail_data($item) {
        if (empty($item)) {
            return $item;
        }
        
        // Format dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    /**
     * Prepare form data from POST
     */
    protected function prepare_form_data($post) {
        $data = [];
        
        $text_fields = ['language_code', 'language_name', 'flag_emoji'];
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        if (!empty($post['branches'])) {
            $branches = [];
            foreach ($post['branches'] as $branch_id => $branch_data) {
                $is_active = intval($branch_data['active'] ?? 0);
                
                if ($is_active === 1) {
                    $branches[intval($branch_id)] = [
                        'active' => 1,
                        'is_default' => intval($branch_data['is_default'] ?? 0),
                        'display_order' => intval($branch_data['display_order'] ?? 0),
                    ];
                }
            }
            $data['branches'] = $branches;
        } else {
            $data['branches'] = [];
        }
        
        return $data;
    }
    
    /**
     * Before save hook
     */
    protected function before_save($data) {
        if (empty($data['customer_id'])) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        return $data;
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        wp_enqueue_style('dashicons');
        SAW_Asset_Loader::enqueue_module('training-languages');
    }
}