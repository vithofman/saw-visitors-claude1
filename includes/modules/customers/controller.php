<?php
/**
 * Customers Module Controller
 * 
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Customers_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Customers_Model($this->config);
        
        add_action('wp_ajax_saw_get_customers_for_switcher', [$this, 'ajax_get_customers_for_switcher']);
        add_action('wp_ajax_saw_switch_customer', [$this, 'ajax_switch_customer']);
        add_action('wp_ajax_saw_get_customers_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_customers', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_customers', [$this, 'ajax_delete']);
    }
    
    protected function before_save($data) {
        if (!empty($_FILES['logo']['name'])) {
            $upload = $this->handle_logo_upload($_FILES['logo']);
            
            if (is_wp_error($upload)) {
                wp_die($upload->get_error_message());
            }
            
            $data['logo_url'] = $upload['url'];
            
            if (!empty($data['id'])) {
                $existing = $this->model->get_by_id($data['id']);
                if (!empty($existing['logo_url'])) {
                    $this->delete_old_logo($existing['logo_url']);
                }
            }
        }
        
        return $data;
    }
    
    protected function after_save($id) {
        delete_transient('customers_list');
        delete_transient('customers_count');
        
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log("SAW: Customer saved - ID: {$id}");
        }
    }
    
    protected function before_delete($id) {
        $customer = $this->model->get_by_id($id);
        
        if (!empty($customer['logo_url'])) {
            $this->delete_old_logo($customer['logo_url']);
        }
        
        return true;
    }
    
    private function handle_logo_upload($file) {
        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime, $allowed_mimes)) {
            return new WP_Error('invalid_mime', 'Neplatný typ souboru. Povolené: JPG, PNG, GIF');
        }
        
        if ($file['size'] > 2097152) {
            return new WP_Error('file_too_large', 'Soubor je příliš velký (max 2MB)');
        }
        
        $upload_dir = wp_upload_dir();
        $customer_dir = $upload_dir['basedir'] . '/saw-customers/';
        
        if (!file_exists($customer_dir)) {
            wp_mkdir_p($customer_dir);
        }
        
        $filename = time() . '_' . sanitize_file_name($file['name']);
        $filepath = $customer_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return [
                'url' => $upload_dir['baseurl'] . '/saw-customers/' . $filename,
                'path' => $filepath,
            ];
        }
        
        return new WP_Error('upload_failed', 'Nahrání souboru selhalo');
    }
    
    private function delete_old_logo($logo_url) {
        $upload_dir = wp_upload_dir();
        $logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $logo_url);
        
        if (file_exists($logo_path)) {
            @unlink($logo_path);
        }
    }
    
    protected function format_detail_data($item) {
        $status_labels = [
            'potential' => 'Potenciální',
            'active' => 'Aktivní',
            'inactive' => 'Neaktivní',
        ];
        
        $language_labels = [
            'cs' => 'Čeština',
            'en' => 'English',
            'de' => 'Deutsch',
        ];
        
        $subscription_labels = [
            'free' => 'Zdarma',
            'basic' => 'Basic',
            'pro' => 'Pro',
            'enterprise' => 'Enterprise',
        ];
        
        $item['status_label'] = $status_labels[$item['status']] ?? $item['status'];
        $item['admin_language_label'] = $language_labels[$item['admin_language_default'] ?? 'cs'] ?? 'Čeština';
        $item['subscription_type_label'] = $subscription_labels[$item['subscription_type'] ?? 'free'] ?? 'Zdarma';
        
        $operational_parts = array_filter([
            trim(($item['address_street'] ?? '') . ' ' . ($item['address_number'] ?? '')),
            ($item['address_city'] ?? '') . ' ' . ($item['address_zip'] ?? '')
        ]);
        $item['formatted_operational_address'] = implode(', ', $operational_parts);
        
        $billing_parts = array_filter([
            trim(($item['billing_address_street'] ?? '') . ' ' . ($item['billing_address_number'] ?? '')),
            ($item['billing_address_city'] ?? '') . ' ' . ($item['billing_address_zip'] ?? '')
        ]);
        $item['formatted_billing_address'] = implode(', ', $billing_parts);
        
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        return $item;
    }
    
    public function ajax_get_customers_for_switcher() {
        check_ajax_referer('saw_customer_switcher_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění.']);
        }
        
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        
        $customers = $this->model->get_all([
            'search' => $search,
            'orderby' => 'name',
            'order' => 'ASC',
            'per_page' => 50,
        ]);
        
        wp_send_json_success([
            'customers' => $customers['items'] ?? $customers,
        ]);
    }
    
    public function ajax_switch_customer() {
        check_ajax_referer('saw_customer_switcher_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění.']);
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if ($customer_id <= 0) {
            wp_send_json_error(['message' => 'Neplatné ID zákazníka.']);
        }
        
        $customer = $this->model->get_by_id($customer_id);
        
        if (!$customer) {
            wp_send_json_error(['message' => 'Zákazník nenalezen.']);
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['saw_current_customer_id'] = $customer_id;
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'saw_current_customer_id', $customer_id);
        }
        
        wp_send_json_success([
            'message' => 'Zákazník byl přepnut.',
            'customer' => [
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'address' => ($customer['address_street'] ?? '') . ', ' . ($customer['address_city'] ?? ''),
                'logo_url' => $customer['logo_url'] ?? '',
            ],
        ]);
    }
}

add_action('init', function() {
    if (!class_exists('SAW_Module_Customers_Controller')) {
        return;
    }
    new SAW_Module_Customers_Controller();
}, 5);