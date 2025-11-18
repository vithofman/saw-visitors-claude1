<?php
/**
 * Training Languages Controller - CLEANED
 *
 * @package    SAW_Visitors
 * @subpackage Modules/TrainingLanguages
 * @version    3.4.0 - CLEANED: Removed redundant create/edit methods to fix Nonce
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Training_Languages_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
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
    }
    
    /**
     * Main Entry Point
     */
    public function index() {
        // Base controller handles logic via process_sidebar_context()
        // This handles POST requests for Create and Edit automatically!
        $sidebar_data = $this->process_sidebar_context();
        if ($sidebar_data === null) return; // Redirected after save
        
        $this->config = array_merge($this->config, $sidebar_data);
        
        // Load extra data for sidebars
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
                if (!empty($branch_data['active'])) {
                    $branches[$branch_id] = [
                        'active' => 1,
                        'is_default' => !empty($branch_data['is_default']) ? 1 : 0,
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
    protected function enqueue_assets() {
        wp_enqueue_style(
            'saw-module-training-languages',
            SAW_VISITORS_PLUGIN_URL . 'includes/modules/training-languages/styles.css',
            ['saw-admin-table-component'],
            '3.4.0'
        );
        
        wp_enqueue_script(
            'saw-module-training-languages',
            SAW_VISITORS_PLUGIN_URL . 'includes/modules/training-languages/scripts.js',
            ['jquery'],
            '3.4.0',
            true
        );
    }
}