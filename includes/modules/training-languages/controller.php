<?php
/**
 * Training Languages Controller
 *
 * @package    SAW_Visitors
 * @subpackage Modules/TrainingLanguages
 * @version    3.9.0 - FIXED: Type casting + debugging for branches
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
     * Prepare form data from POST - FIXED TYPE CASTING
     */
    protected function prepare_form_data($post) {
        $data = [];
        
        $text_fields = ['language_code', 'language_name', 'flag_emoji'];
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        // 🔥 DEBUG: Log what arrives from POST
        error_log('=== TRAINING LANGUAGES DEBUG ===');
        error_log('POST branches raw: ' . print_r($post['branches'] ?? [], true));
        
        if (!empty($post['branches'])) {
            $branches = [];
            foreach ($post['branches'] as $branch_id => $branch_data) {
                // 🔥 CRITICAL FIX: Type casting - HTML sends string "1", not integer
                $is_active = intval($branch_data['active'] ?? 0);
                
                error_log("Branch #{$branch_id}: active={$is_active}, is_default=" . intval($branch_data['is_default'] ?? 0));
                
                if ($is_active === 1) {
                    $branches[intval($branch_id)] = [
                        'active' => 1,
                        'is_default' => intval($branch_data['is_default'] ?? 0),
                        'display_order' => intval($branch_data['display_order'] ?? 0),
                    ];
                }
            }
            $data['branches'] = $branches;
            error_log('Processed branches count: ' . count($branches));
        } else {
            $data['branches'] = [];
            error_log('NO branches in POST data!');
        }
        
        error_log('=== END DEBUG ===');
        
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
            '3.9.0'
        );
        
        wp_enqueue_script(
            'saw-module-training-languages',
            SAW_VISITORS_PLUGIN_URL . 'includes/modules/training-languages/scripts.js',
            ['jquery'],
            '3.9.0',
            true
        );
    }
}