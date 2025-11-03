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
        add_action('wp_ajax_saw_switch_language', [$this, 'ajax_switch_language']);
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
    
    protected function before_delete($id) {
        global $wpdb;
        
        $branches_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d",
            $id
        ));
        
        if ($branches_count > 0) {
            return new WP_Error(
                'customer_has_branches',
                sprintf('Zákazníka nelze smazat. Má %d poboček. Nejprve je smažte.', $branches_count)
            );
        }
        
        $users_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_users WHERE customer_id = %d",
            $id
        ));
        
        if ($users_count > 0) {
            return new WP_Error(
                'customer_has_users',
                sprintf('Zákazníka nelze smazat. Má %d uživatelů. Nejprve je smažte.', $users_count)
            );
        }
        
        $visits_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE customer_id = %d",
            $id
        ));
        
        if ($visits_count > 0) {
            return new WP_Error(
                'customer_has_visits',
                sprintf('Zákazníka nelze smazat. Má %d návštěv v historii.', $visits_count)
            );
        }
        
        $invitations_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_invitations WHERE customer_id = %d",
            $id
        ));
        
        if ($invitations_count > 0) {
            return new WP_Error(
                'customer_has_invitations',
                sprintf('Zákazníka nelze smazat. Má %d pozvá nek.', $invitations_count)
            );
        }
        
        return true;
    }
    
    protected function after_delete($id) {
        $customer = $this->model->get_by_id($id);
        if (!empty($customer['logo_url'])) {
            $this->file_uploader->delete($customer['logo_url']);
        }
        
        delete_transient('customers_list');
        delete_transient('customers_for_switcher');
    }
    
    public function ajax_get_customers_for_switcher() {
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
                'current_customer_id' => SAW_Context::get_customer_id()
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
            'current_customer_id' => SAW_Context::get_customer_id()
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
        
        SAW_Context::set_customer_id($customer_id);
        
        delete_transient('branches_for_switcher_' . $customer_id);
        
        wp_send_json_success([
            'message' => 'Zákazník byl úspěšně přepnut',
            'customer_id' => $customer_id,
            'customer_name' => $customer['name']
        ]);
    }
    
    public function ajax_switch_language() {
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_language_switcher')) {
            wp_send_json_error(['message' => 'Neplatný bezpečnostní token']);
        }
        
        $language = isset($_POST['language']) ? sanitize_text_field($_POST['language']) : '';
        
        $allowed_languages = ['cs', 'en'];
        if (!in_array($language, $allowed_languages)) {
            wp_send_json_error(['message' => 'Neplatný jazyk']);
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['saw_current_language'] = $language;
        
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'saw_current_language', $language);
        }
        
        wp_send_json_success([
            'message' => 'Jazyk byl úspěšně přepnut',
            'language' => $language
        ]);
    }
}