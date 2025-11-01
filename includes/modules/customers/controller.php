<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Customers_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $file_uploader;
    private $color_picker;
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Customers_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/class-saw-color-picker.php';
        $this->color_picker = new SAW_Color_Picker();
        
        add_action('wp_ajax_saw_get_customers_for_switcher', [$this, 'ajax_get_customers_for_switcher']);
        add_action('wp_ajax_saw_switch_customer', [$this, 'ajax_switch_customer']);
        add_action('wp_ajax_saw_get_customers_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_customers', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_customers', [$this, 'ajax_delete']);
        
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    public function enqueue_assets() {
        $this->file_uploader->enqueue_assets();
        $this->color_picker->enqueue_assets();
    }
    
    protected function before_save($data) {
        if ($this->file_uploader->should_remove_file('logo')) {
            if (!empty($data['id'])) {
                $existing = $this->model->get_by_id($data['id']);
                if (!empty($existing['logo_url'])) {
                    $this->file_uploader->delete($existing['logo_url']);
                }
            }
            $data['logo_url'] = '';
        }
        
        if (!empty($_FILES['logo']['name'])) {
            $upload = $this->file_uploader->upload($_FILES['logo'], 'customers');
            
            if (is_wp_error($upload)) {
                wp_die($upload->get_error_message());
            }
            
            $data['logo_url'] = $upload['url'];
            
            if (!empty($data['id'])) {
                $existing = $this->model->get_by_id($data['id']);
                if (!empty($existing['logo_url']) && $existing['logo_url'] !== $data['logo_url']) {
                    $this->file_uploader->delete($existing['logo_url']);
                }
            }
        }
        
        return $data;
    }
    
    protected function after_save($id) {
        delete_transient('customers_list');
        delete_transient('customers_for_switcher');
    }
    
    public function ajax_get_customers_for_switcher() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        $cached = get_transient('customers_for_switcher');
        if ($cached !== false) {
            wp_send_json_success($cached);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_customers';
        
        $customers = $wpdb->get_results(
            "SELECT id, name, ico, logo_url, primary_color 
             FROM {$table} 
             WHERE status = 'active' 
             ORDER BY name ASC",
            ARRAY_A
        );
        
        $formatted = array_map(function($customer) {
            return [
                'value' => $customer['id'],
                'label' => $customer['name'],
                'icon' => !empty($customer['logo_url']) ? $customer['logo_url'] : '',
                'meta' => !empty($customer['ico']) ? 'IČO: ' . $customer['ico'] : ''
            ];
        }, $customers);
        
        set_transient('customers_for_switcher', $formatted, HOUR_IN_SECONDS);
        
        wp_send_json_success($formatted);
    }
    
    public function ajax_switch_customer() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        $customer_id = intval($_POST['customer_id']);
        
        if ($customer_id <= 0) {
            wp_send_json_error(['message' => 'Neplatné ID zákazníka']);
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['saw_current_customer_id'] = $customer_id;
        
        wp_send_json_success([
            'message' => 'Zákazník byl úspěšně přepnut',
            'customer_id' => $customer_id
        ]);
    }
}