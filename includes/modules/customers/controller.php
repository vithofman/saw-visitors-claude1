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
        delete_transient('customers_for_switcher');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_customer_switcher')) {
            wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
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
        
        if (!$customers) {
            wp_send_json_success([
                'customers' => [],
                'current_customer_id' => $this->get_current_customer_id()
            ]);
            return;
        }
        
        $formatted = array_map(function($customer) {
            $logo_url = '';
            if (!empty($customer['logo_url'])) {
                if (strpos($customer['logo_url'], 'http') === 0) {
                    $logo_url = $customer['logo_url'];
                } else {
                    $upload_dir = wp_upload_dir();
                    $logo_url = $upload_dir['baseurl'] . '/' . ltrim($customer['logo_url'], '/');
                }
            }
            
            return [
                'id' => intval($customer['id']),
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'logo_url' => $logo_url,
                'primary_color' => $customer['primary_color'] ?? '#2563eb'
            ];
        }, $customers);
        
        wp_send_json_success([
            'customers' => $formatted,
            'current_customer_id' => $this->get_current_customer_id()
        ]);
    }
    
    public function ajax_switch_customer() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_customer_switcher')) {
            wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if ($customer_id <= 0) {
            wp_send_json_error(['message' => 'Neplatné ID zákazníka']);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_customers';
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM {$table} WHERE id = %d AND status = 'active'",
            $customer_id
        ), ARRAY_A);
        
        if (!$customer) {
            wp_send_json_error(['message' => 'Zákazník nebyl nalezen']);
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['saw_current_customer_id'] = $customer_id;
        
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'saw_current_customer_id', $customer_id);
        }
        
        delete_transient('branches_for_switcher_' . $customer_id);
        
        $this->handle_branch_on_customer_switch($customer_id);
        
        wp_send_json_success([
            'message' => 'Zákazník byl úspěšně přepnut',
            'customer_id' => $customer_id,
            'customer_name' => $customer['name']
        ]);
    }
    
    private function handle_branch_on_customer_switch($customer_id) {
        global $wpdb;
        $branches_table = $wpdb->prefix . 'saw_branches';
        
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$branches_table} WHERE customer_id = %d AND is_active = 1 LIMIT 2",
            $customer_id
        ), ARRAY_A);
        
        $branch_count = count($branches);
        
        if ($branch_count === 1) {
            $branch_id = intval($branches[0]['id']);
            
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['saw_current_branch_id'] = $branch_id;
            
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                update_user_meta($user_id, 'saw_current_branch_id', $branch_id);
                update_user_meta($user_id, 'saw_branch_customer_' . $customer_id, $branch_id);
            }
        } else {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            unset($_SESSION['saw_current_branch_id']);
            
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                delete_user_meta($user_id, 'saw_current_branch_id');
            }
        }
    }
    
    private function get_current_customer_id() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['saw_current_customer_id'])) {
            return intval($_SESSION['saw_current_customer_id']);
        }
        
        if (is_user_logged_in()) {
            $meta_id = get_user_meta(get_current_user_id(), 'saw_current_customer_id', true);
            if ($meta_id) {
                $_SESSION['saw_current_customer_id'] = intval($meta_id);
                return intval($meta_id);
            }
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_customers';
        $first_customer = $wpdb->get_var(
            "SELECT id FROM {$table} WHERE status = 'active' ORDER BY name ASC LIMIT 1"
        );
        
        if ($first_customer) {
            $_SESSION['saw_current_customer_id'] = intval($first_customer);
            return intval($first_customer);
        }
        
        return null;
    }
}