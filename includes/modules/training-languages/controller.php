<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Training_Languages_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Training_Languages_Model($this->config);

	require_once __DIR__ . '/class-auto-setup.php';
        
        add_action('wp_ajax_saw_get_training_languages_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_training_languages', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_training_languages', [$this, 'ajax_delete']);
        add_action('wp_ajax_saw_get_branches_for_language', [$this, 'ajax_get_branches_for_language']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function enqueue_assets() {
    }
    
    protected function before_save($data) {
        if (!empty($_POST['branches'])) {
            $branches = [];
            
            foreach ($_POST['branches'] as $branch_id => $branch_data) {
                if (!empty($branch_data['active'])) {
                    $branches[$branch_id] = [
                        'active' => 1,
                        'is_default' => !empty($branch_data['is_default']) ? 1 : 0,
                        'display_order' => intval($branch_data['display_order'] ?? 0),
                    ];
                }
            }
            
            $data['branches'] = $branches;
        }
        
        return $data;
    }
    
    protected function after_save($id) {
        if (!empty($this->config['customer_id'])) {
            delete_transient('training_languages_customer_' . $this->config['customer_id']);
        }
    }
    
    public function ajax_get_branches_for_language() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_ajax_nonce')) {
            wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
        }
        
        $language_id = isset($_POST['language_id']) ? intval($_POST['language_id']) : 0;
        
        if (!$language_id) {
            wp_send_json_error(['message' => 'Chybí ID jazyka']);
        }
        
        $branches = $this->model->get_branches_for_language($language_id);
        
        wp_send_json_success([
            'branches' => $branches
        ]);
    }
}