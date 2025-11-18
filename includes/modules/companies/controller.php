<?php
/**
 * Companies Module Controller
 * 
 * @version 2.0.0 - REFACTORED: Using render_list_view()
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}

if (!trait_exists('SAW_AJAX_Handlers')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
}

class SAW_Module_Companies_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/companies/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Companies_Model($this->config);
    }
    
    /**
     * ✅ NEW: Using render_list_view() from Base Controller
     */
    public function index() {
        $this->render_list_view();
    }
    
    protected function prepare_form_data($post) {
        $data = array();
        
        if (isset($post['customer_id'])) {
            $data['customer_id'] = intval($post['customer_id']);
        }
        
        if (isset($post['branch_id'])) {
            $data['branch_id'] = intval($post['branch_id']);
        }
        
        $text_fields = array('name', 'ico', 'street', 'city', 'zip', 'country', 'phone');
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        if (isset($post['email'])) {
            $data['email'] = sanitize_email($post['email']);
        }
        
        if (isset($post['website'])) {
            $data['website'] = esc_url_raw($post['website']);
        }
        
        $data['is_archived'] = isset($post['is_archived']) ? 1 : 0;
        
        return $data;
    }
    
    protected function before_save($data) {
        if (empty($data['customer_id'])) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        return $data;
    }
    
    protected function format_detail_data($item) {
        global $wpdb;
        
        if (!empty($item['branch_id'])) {
            $branch = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM %i WHERE id = %d",
                $wpdb->prefix . 'saw_branches',
                $item['branch_id']
            ), ARRAY_A);
            
            if ($branch) {
                $item['branch_name'] = $branch['name'];
            }
        }
        
        return $item;
    }
    
    protected function get_display_name($item) {
        return $item['name'] ?? 'Nová firma';
    }
    
    /**
     * ✅ AJAX: Inline create handler
     */
    public function ajax_inline_create() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!$this->can('create')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění vytvářet firmy'));
        }
        
        $data = $this->prepare_form_data($_POST);
        
        $data = $this->before_save($data);
        if (is_wp_error($data)) {
            wp_send_json_error(array('message' => $data->get_error_message()));
        }
        
        $validation = $this->model->validate($data);
        if (is_wp_error($validation)) {
            $errors = $validation->get_error_data();
            wp_send_json_error(array('message' => implode('<br>', $errors)));
        }
        
        $result = $this->model->create($data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        $item = $this->model->get_by_id($result);
        
        wp_send_json_success(array(
            'id' => $result,
            'name' => $this->get_display_name($item),
        ));
    }
}