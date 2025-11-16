<?php
if (!defined('ABSPATH')) exit;

if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}

if (!trait_exists('SAW_AJAX_Handlers')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
}

class SAW_Module_Visitors_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Visitors_Model($this->config);
    }
    
    public function index() {
        $this->render_list_view();
    }
    
    protected function prepare_form_data($post) {
        // POUZE pole která jsou ve fields configu!
        $data = array();
        
        foreach ($this->config['fields'] as $field_name => $field_config) {
            if (isset($post[$field_name])) {
                $sanitize = $field_config['sanitize'] ?? 'sanitize_text_field';
                $data[$field_name] = $sanitize($post[$field_name]);
            } elseif ($field_config['type'] === 'checkbox') {
                $data[$field_name] = 0;
            }
        }
        
        return $data;
    }
    
    protected function after_save($id) {
        if (isset($_POST['certificates']) && is_array($_POST['certificates'])) {
            $this->model->save_certificates($id, $_POST['certificates']);
        }
    }
    
    protected function format_detail_data($item) {
        if (empty($item)) {
            return $item;
        }
        
        if (!empty($item['visit_id'])) {
            $item['visit_data'] = $this->model->get_visit_data($item['visit_id']);
            
            // Načtení hostů
            if (!empty($item['visit_data'])) {
                global $wpdb;
                $hosts = $wpdb->get_results($wpdb->prepare(
                    "SELECT u.id, u.first_name, u.last_name, u.email
                     FROM %i vh
                     INNER JOIN %i u ON vh.user_id = u.id
                     WHERE vh.visit_id = %d
                     ORDER BY u.last_name, u.first_name",
                    $wpdb->prefix . 'saw_visit_hosts',
                    $wpdb->prefix . 'saw_users',
                    $item['visit_id']
                ), ARRAY_A);
                
                $item['visit_data']['hosts'] = $hosts;
            }
        }
        
        if (!empty($item['id'])) {
            $item['certificates'] = $this->model->get_certificates($item['id']);
        }
        
        return $item;
    }
}
