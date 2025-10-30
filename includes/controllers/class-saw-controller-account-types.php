<?php
if (!defined('ABSPATH')) exit;

require_once SAW_VISITORS_PLUGIN_DIR . 'includes/models/class-saw-model-account-type.php';
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';

class SAW_Controller_Account_Types {
    private $model;
    
    public function __construct() {
        $this->model = new SAW_Model_Account_Type();
        add_action('wp_ajax_saw_account_type_save', [$this, 'ajax_save']);
        add_action('wp_ajax_saw_account_type_delete', [$this, 'ajax_delete']);
    }
    
    public function index() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', ['response' => 403]);
        }
        
        $this->enqueue_assets();
        
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'sort_order';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        $is_active_filter = isset($_GET['is_active']) ? intval($_GET['is_active']) : null;
        
        $args = [
            'page' => $page,
            'per_page' => $per_page,
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order
        ];
        
        if ($is_active_filter !== null) {
            $args['is_active'] = $is_active_filter;
        }
        
        $result = $this->model->get_all($args);
        $account_types = $result['items'];
        $total_account_types = $result['total'];
        $total_pages = ceil($total_account_types / $per_page);
        
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/account-types/list.php';
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer = $this->get_current_customer_data();
            $layout->render($content, 'Account Types', 'settings-account-types', $user, $customer);
        } else {
            echo $content;
        }
    }
    
    public function create() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', ['response' => 403]);
        }
        
        $this->enqueue_assets();
        
        ob_start();
        $account_type = null;
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/account-types/form.php';
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer = $this->get_current_customer_data();
            $layout->render($content, 'Nový Account Type', 'settings-account-types', $user, $customer);
        } else {
            echo $content;
        }
    }
    
    public function edit($id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', ['response' => 403]);
        }
        
        $this->enqueue_assets();
        
        $account_type = $this->model->get_by_id($id);
        
        if (!$account_type) {
            wp_die('Account type nenalezen.');
        }
        
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/account-types/form.php';
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer = $this->get_current_customer_data();
            $layout->render($content, 'Upravit Account Type', 'settings-account-types', $user, $customer);
        } else {
            echo $content;
        }
    }
    
    public function ajax_save() {
        check_ajax_referer('saw_account_type_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'display_name' => sanitize_text_field($_POST['display_name']),
            'color' => sanitize_hex_color($_POST['color']),
            'price' => floatval($_POST['price']),
            'features' => sanitize_textarea_field($_POST['features']),
            'sort_order' => intval($_POST['sort_order']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if ($id > 0) {
            $result = $this->model->update($id, $data);
            $message = 'Account type updated successfully';
        } else {
            $result = $this->model->create($data);
            $message = 'Account type created successfully';
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => $message]);
    }
    
    public function ajax_delete() {
        check_ajax_referer('saw_admin_table_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Invalid ID']);
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => 'Account type deleted']);
    }
    
    private function enqueue_assets() {
        wp_enqueue_style(
            'saw-admin-table',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/global/saw-admin-table.css',
            [],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-account-types',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/pages/saw-account-types.css',
            ['saw-admin-table'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-admin-table',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/global/saw-admin-table.js',
            ['jquery'],
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_enqueue_script(
            'saw-admin-table-ajax',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/global/saw-admin-table-ajax.js',
            ['jquery', 'saw-admin-table'],
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_enqueue_script(
            'saw-account-types',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/pages/saw-account-types.js',
            ['jquery', 'saw-admin-table'],
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script('saw-account-types', 'sawAccountTypesData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_account_type_nonce')
        ]);
    }
    
    private function get_current_user_data() {
        if (is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            return [
                'id' => $wp_user->ID,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'role' => current_user_can('manage_options') ? 'super_admin' : 'admin',
            ];
        }
        return ['id' => 0, 'name' => 'Guest', 'email' => '', 'role' => 'guest'];
    }
    
    private function get_current_customer_data() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $customer_id = isset($_SESSION['saw_current_customer_id']) ? intval($_SESSION['saw_current_customer_id']) : 0;
        
        if (!$customer_id && is_user_logged_in()) {
            $customer_id = intval(get_user_meta(get_current_user_id(), 'saw_current_customer_id', true));
        }
        
        if ($customer_id > 0) {
            global $wpdb;
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
                $customer_id
            ), ARRAY_A);
            
            if ($customer) {
                return [
                    'id' => $customer['id'],
                    'name' => $customer['name'],
                    'ico' => $customer['ico'] ?? '',
                    'address' => ($customer['address_street'] ?? '') . ', ' . ($customer['address_city'] ?? ''),
                    'logo_url' => $customer['logo_url'] ?? '',
                ];
            }
        }
        
        global $wpdb;
        $customer = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        
        if ($customer) {
            return [
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'address' => ($customer['address_street'] ?? '') . ', ' . ($customer['address_city'] ?? ''),
                'logo_url' => $customer['logo_url'] ?? '',
            ];
        }
        
        return ['id' => 0, 'name' => 'Žádný zákazník', 'ico' => '', 'address' => '', 'logo_url' => ''];
    }
}