<?php
/**
 * Account Types Controller
 *
 * @package SAW_Visitors
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once SAW_VISITORS_PLUGIN_DIR . 'includes/models/class-saw-model-account-type.php';
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/class-saw-component-admin-table.php';

class SAW_Controller_Account_Types {
    
    private $model;
    
    public function __construct() {
        $this->model = new SAW_Model_Account_Type();
        
        add_action('wp_ajax_saw_account_types_list', [$this, 'ajax_list']);
        add_action('wp_ajax_saw_account_type_save', [$this, 'ajax_save']);
        add_action('wp_ajax_saw_account_type_delete', [$this, 'ajax_delete']);
        add_action('wp_ajax_saw_account_type_bulk_delete', [$this, 'ajax_bulk_delete']);
    }
    
    public function index() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        wp_enqueue_style(
            'saw-account-types',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/pages/saw-account-types.css',
            ['saw-admin-table'],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-account-types',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/pages/saw-account-types.js',
            ['jquery', 'saw-admin-table', 'saw-admin-table-ajax'],
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script('saw-account-types', 'sawAccountTypesData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_account_types_nonce')
        ]);
        
        $admin_table = new SAW_Component_Admin_Table($this->get_table_config());
        
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/account-types/list.php';
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer = $this->get_current_customer_data();
            $layout->render($content, 'Account Types', 'account-types', $user, $customer);
        } else {
            echo $content;
        }
    }
    
    public function add() {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $account_type = null;
        $form_action = 'add';
        
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/account-types/form.php';
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer = $this->get_current_customer_data();
            $layout->render($content, 'New Account Type', 'account-types', $user, $customer);
        } else {
            echo $content;
        }
    }
    
    public function edit($id) {
        if (!current_user_can('manage_options')) {
            wp_die('Nemáte oprávnění.', 'Přístup zamítnut', array('response' => 403));
        }
        
        $account_type = $this->model->get_by_id($id);
        
        if (!$account_type) {
            wp_die('Account type not found');
        }
        
        $form_action = 'edit';
        
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/account-types/form.php';
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer = $this->get_current_customer_data();
            $layout->render($content, 'Edit Account Type', 'account-types', $user, $customer);
        } else {
            echo $content;
        }
    }
    
    public function ajax_list() {
        check_ajax_referer('saw_account_types_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }
        
        $args = [
            'page' => isset($_POST['page']) ? (int) $_POST['page'] : 1,
            'per_page' => isset($_POST['per_page']) ? (int) $_POST['per_page'] : 20,
            'search' => isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '',
            'orderby' => isset($_POST['orderby']) ? sanitize_text_field($_POST['orderby']) : 'sort_order',
            'order' => isset($_POST['order']) ? sanitize_text_field($_POST['order']) : 'ASC'
        ];
        
        if (isset($_POST['filters']['is_active']) && $_POST['filters']['is_active'] !== '') {
            $args['is_active'] = (int) $_POST['filters']['is_active'];
        }
        
        $result = $this->model->get_all($args);
        
        wp_send_json_success($result);
    }
    
    public function ajax_save() {
        check_ajax_referer('saw_account_types_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }
        
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'display_name' => sanitize_text_field($_POST['display_name']),
            'color' => sanitize_hex_color($_POST['color']),
            'price' => floatval($_POST['price']),
            'features' => sanitize_textarea_field($_POST['features']),
            'sort_order' => (int) $_POST['sort_order'],
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
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'errors' => $result->get_error_messages()
            ]);
        }
        
        wp_send_json_success(['message' => $message]);
    }
    
    public function ajax_delete() {
        check_ajax_referer('saw_account_types_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }
        
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        
        if (!$id) {
            wp_send_json_error(['message' => 'Invalid account type ID']);
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success(['message' => 'Account type deleted successfully']);
    }
    
    public function ajax_bulk_delete() {
        check_ajax_referer('saw_account_types_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access']);
        }
        
        $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
        
        if (empty($ids)) {
            wp_send_json_error(['message' => 'No account types selected']);
        }
        
        $deleted = 0;
        foreach ($ids as $id) {
            $result = $this->model->delete($id);
            if (!is_wp_error($result)) {
                $deleted++;
            }
        }
        
        wp_send_json_success([
            'message' => sprintf('%d account type(s) deleted successfully', $deleted)
        ]);
    }
    
    private function get_table_config() {
        return [
            'id' => 'account-types-table',
            'ajax_action' => 'saw_account_types_list',
            'columns' => [
                'id' => [
                    'label' => 'ID',
                    'sortable' => true,
                    'width' => '80px'
                ],
                'display_name' => [
                    'label' => 'Display Name',
                    'sortable' => true
                ],
                'name' => [
                    'label' => 'Internal Name',
                    'sortable' => true
                ],
                'price' => [
                    'label' => 'Price',
                    'sortable' => true,
                    'width' => '120px',
                    'formatter' => function($value) {
                        return '$' . number_format($value, 2);
                    }
                ],
                'sort_order' => [
                    'label' => 'Order',
                    'sortable' => true,
                    'width' => '100px'
                ],
                'is_active' => [
                    'label' => 'Status',
                    'sortable' => true,
                    'width' => '120px',
                    'formatter' => function($value) {
                        if ($value == 1) {
                            return '<span class="saw-status-badge saw-status-active">Active</span>';
                        }
                        return '<span class="saw-status-badge saw-status-inactive">Inactive</span>';
                    }
                ],
                'created_at' => [
                    'label' => 'Created',
                    'sortable' => true,
                    'width' => '150px',
                    'formatter' => function($value) {
                        return date('Y-m-d H:i', strtotime($value));
                    }
                ]
            ],
            'actions' => [
                'view' => true,
                'edit' => true,
                'delete' => true
            ],
            'bulk_actions' => [
                'delete' => 'Delete Selected'
            ],
            'filters' => [
                'is_active' => [
                    'label' => 'Status',
                    'type' => 'select',
                    'options' => [
                        '' => 'All',
                        '1' => 'Active',
                        '0' => 'Inactive'
                    ]
                ]
            ],
            'default_orderby' => 'sort_order',
            'default_order' => 'ASC'
        ];
    }
    
    private function get_current_user_data() {
        if (is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            return array(
                'id' => $wp_user->ID,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'role' => current_user_can('manage_options') ? 'super_admin' : 'admin',
            );
        }
        
        return array(
            'id' => 0,
            'name' => 'Guest',
            'email' => '',
            'role' => 'guest',
        );
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
                return array(
                    'id' => $customer['id'],
                    'name' => $customer['name'],
                    'ico' => $customer['ico'] ?? '',
                    'address' => ($customer['address_street'] ?? '') . ', ' . ($customer['address_city'] ?? ''),
                    'logo_url' => $customer['logo_url'] ?? '',
                );
            }
        }
        
        global $wpdb;
        $customer = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        
        if ($customer) {
            return array(
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'address' => ($customer['address_street'] ?? '') . ', ' . ($customer['address_city'] ?? ''),
                'logo_url' => $customer['logo_url'] ?? '',
            );
        }
        
        return array(
            'id' => 0,
            'name' => 'Žádný zákazník',
            'ico' => '',
            'address' => '',
            'logo_url' => '',
        );
    }
}