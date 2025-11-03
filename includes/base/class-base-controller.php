<?php
/**
 * Base Controller Class - DEBUG VERSION
 * 
 * @package SAW_Visitors
 * @version 4.0.5-DEBUG
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    protected $config;
    protected $model;
    protected $entity;
    
    public function index() {
        // ✅ DEBUG START
        error_log('====================================');
        error_log('🔥 BASE CONTROLLER: index() START');
        error_log('Entity: ' . ($this->entity ?? 'NULL'));
        error_log('Current user ID: ' . get_current_user_id());
        error_log('Is logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
        error_log('Has manage_options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));
        error_log('Has read: ' . (current_user_can('read') ? 'YES' : 'NO'));
        error_log('====================================');
        
        $this->verify_module_access();
        $this->verify_capability('list');
        $this->enqueue_assets();
        
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = $this->config['list_config']['per_page'] ?? 20;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'id';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'DESC';
        
        $filters = [
            'page' => $page,
            'per_page' => $per_page,
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order
        ];
        
        if (!empty($this->config['filter_by_customer'])) {
            $customer_id = $this->get_filtered_customer_id();
            if ($customer_id) {
                $filters['customer_id'] = $customer_id;
            }
        }
        
        if (!empty($this->config['filter_by_branch'])) {
            $branch_id = $this->get_filtered_branch_id();
            if ($branch_id !== null) {
                $filters['branch_id'] = $branch_id;
            }
        }
        
        foreach ($this->config['list_config']['filters'] ?? [] as $filter_key => $enabled) {
            if ($enabled && isset($_GET[$filter_key]) && $filter_key !== 'customer_id') {
                $filters[$filter_key] = sanitize_text_field($_GET[$filter_key]);
            }
        }
        
        $result = $this->model->get_all($filters);
        $items = $result['items'] ?? $result;
        $total_items = $result['total'] ?? count($items);
        $total_pages = ceil($total_items / $per_page);
        
        $template_path = $this->config['path'] . 'list-template.php';
        
        if (!file_exists($template_path)) {
            wp_die('List template not found: ' . $template_path);
        }
        
        $template_vars = compact('items', 'total_items', 'total_pages', 'page', 'per_page', 'search', 'orderby', 'order');
        extract($template_vars);
        
        ob_start();
        
        $style_manager = SAW_Module_Style_Manager::get_instance();
        echo $style_manager->inject_module_css($this->entity);
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        include $template_path;
        echo '</div>';
        
        echo '<script>document.dispatchEvent(new Event("sawModuleChanged"));</script>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    public function create() {
        $this->verify_module_access();
        $this->verify_capability('create');
        $this->enqueue_assets();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_save();
            return;
        }
        
        $item = null;
        $this->render_form($item);
    }
    
    public function edit($id) {
        $this->verify_module_access();
        $this->verify_capability('edit');
        $this->enqueue_assets();
        
        $id = intval($id);
        if ($id <= 0) {
            wp_die('Invalid ID', 'Bad Request', ['response' => 400]);
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_die($this->config['singular'] . ' not found', 'Not Found', ['response' => 404]);
        }
        
        if (!empty($this->config['filter_by_customer'])) {
            $customer = $this->get_current_customer_data();
            if (isset($item['customer_id']) && $item['customer_id'] != $customer['id']) {
                wp_die('Nemáte oprávnění upravovat tento záznam', 'Forbidden', ['response' => 403]);
            }
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_save($id);
            return;
        }
        
        $this->render_form($item);
    }
    
    protected function handle_save($id = 0) {
        ob_start();
        
        $this->verify_nonce();
        
        $data = $this->collect_form_data();
        
        if ($id > 0) {
            $data['id'] = $id;
        }
        
        if ($id === 0 && !empty($this->config['filter_by_customer'])) {
            $customer = $this->get_current_customer_data();
            $data['customer_id'] = $customer['id'] ?? 0;
        }
        
        $data = $this->before_save($data);
        
        $validation = $this->model->validate($data, $id);
        
        if (is_wp_error($validation)) {
            ob_end_clean();
            
            $errors = $validation->get_error_data();
            
            if (is_array($errors)) {
                $error_messages = implode('<br>', array_values($errors));
            } else {
                $error_messages = $validation->get_error_message();
            }
            
            $this->set_flash_message($error_messages, 'error');
            
            if ($id > 0) {
                $redirect_url = home_url($this->config['route'] . '/edit/' . $id . '/');
            } else {
                $redirect_url = home_url($this->config['route'] . '/new/');
            }
            
            wp_redirect($redirect_url);
            exit;
        }
        
        unset($data['id']);
        
        if ($id > 0) {
            $result = $this->model->update($id, $data);
        } else {
            $result = $this->model->create($data);
            $id = $result;
        }
        
        if (is_wp_error($result)) {
            ob_end_clean();
            
            $this->set_flash_message($result->get_error_message(), 'error');
            
            if ($id > 0) {
                $redirect_url = home_url($this->config['route'] . '/edit/' . $id . '/');
            } else {
                $redirect_url = home_url($this->config['route'] . '/new/');
            }
            
            wp_redirect($redirect_url);
            exit;
        }
        
        try {
            $this->after_save($id);
        } catch (Exception $e) {
            ob_end_clean();
            
            error_log('SAW: after_save() exception: ' . $e->getMessage());
            error_log('SAW: Stack trace: ' . $e->getTraceAsString());
            
            $this->set_flash_message('Záznam byl uložen, ale došlo k chybě: ' . $e->getMessage(), 'error');
            
            $redirect_url = home_url($this->config['route'] . '/');
            wp_redirect($redirect_url);
            exit;
        } catch (Error $e) {
            ob_end_clean();
            
            error_log('SAW: after_save() error: ' . $e->getMessage());
            error_log('SAW: Stack trace: ' . $e->getTraceAsString());
            
            $this->set_flash_message('Záznam byl uložen, ale došlo k chybě: ' . $e->getMessage(), 'error');
            
            $redirect_url = home_url($this->config['route'] . '/');
            wp_redirect($redirect_url);
            exit;
        }
        
        ob_end_clean();
        
        $this->set_flash_message('Záznam byl úspěšně uložen', 'success');
        
        $redirect_url = home_url($this->config['route'] . '/');
        wp_redirect($redirect_url);
        exit;
    }
    
    protected function collect_form_data() {
        $data = [];
        
        if (empty($this->config['fields'])) {
            foreach ($_POST as $key => $value) {
                if ($key !== 'saw_nonce' && $key !== '_wp_http_referer') {
                    $data[$key] = sanitize_text_field($value);
                }
            }
            return $data;
        }
        
        foreach ($this->config['fields'] as $field_name => $field_config) {
            if (isset($_POST[$field_name])) {
                $value = $_POST[$field_name];
                
                if (isset($field_config['sanitize']) && is_callable($field_config['sanitize'])) {
                    $data[$field_name] = call_user_func($field_config['sanitize'], $value);
                } else {
                    $data[$field_name] = sanitize_text_field($value);
                }
            } elseif ($field_config['type'] === 'checkbox') {
                $data[$field_name] = 0;
            }
        }
        
        return $data;
    }
    
    protected function render_form($item) {
        $template_path = $this->config['path'] . 'form-template.php';
        
        if (!file_exists($template_path)) {
            wp_die('Form template not found: ' . $template_path);
        }
        
        $account_types = [];
        if ($this->entity === 'customers') {
            global $wpdb;
            $account_types = $wpdb->get_results(
                "SELECT id, name FROM {$wpdb->prefix}saw_account_types WHERE is_active = 1 ORDER BY sort_order ASC, name ASC",
                ARRAY_A
            );
        }
        
        extract(compact('item', 'account_types'));
        
        ob_start();
        
        $style_manager = SAW_Module_Style_Manager::get_instance();
        echo $style_manager->inject_module_css($this->entity);
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        include $template_path;
        echo '</div>';
        
        echo '<script>document.dispatchEvent(new Event("sawModuleChanged"));</script>';
        
        $content = ob_get_clean();
        
        $title = $item ? 'Edit ' . $this->config['singular'] : 'New ' . $this->config['singular'];
        $this->render_with_layout($content, $title);
    }
    
    protected function render_with_layout($content, $title) {
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer = $this->get_current_customer_data();
            $layout->render($content, $title, $this->entity, $user, $customer);
        } else {
            echo $content;
        }
    }
    
    protected function enqueue_assets() {
        SAW_Asset_Manager::enqueue_global();
    }
    
    protected function verify_module_access() {
        // ✅ DEBUG
        error_log('====================================');
        error_log('🔒 VERIFY_MODULE_ACCESS START');
        
        $allowed_roles = $this->config['allowed_roles'] ?? [];
        error_log('Allowed roles: ' . json_encode($allowed_roles));
        
        if (empty($allowed_roles)) {
            error_log('✅ No allowed_roles defined - PASS');
            error_log('====================================');
            return;
        }
        
        $current_role = $this->get_current_user_role();
        error_log('Current role: ' . ($current_role ?? 'NULL'));
        
        $in_array = in_array($current_role, $allowed_roles);
        error_log('Role in allowed_roles: ' . ($in_array ? 'YES' : 'NO'));
        
        if (!$in_array) {
            error_log('❌ FAILED: Role not in allowed_roles!');
            error_log('====================================');
            wp_die('Nemáte oprávnění k tomuto modulu', 'Forbidden', ['response' => 403]);
        }
        
        error_log('✅ PASSED: Module access OK');
        error_log('====================================');
    }
    
    protected function get_current_user_role() {
        // ✅ DEBUG
        error_log('------------------------------------');
        error_log('🔍 GET_CURRENT_USER_ROLE START');
        
        $has_manage_options = current_user_can('manage_options');
        error_log('Has manage_options: ' . ($has_manage_options ? 'YES' : 'NO'));
        
        if ($has_manage_options) {
            error_log('✅ Returning: super_admin');
            error_log('------------------------------------');
            return 'super_admin';
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $session_role = $_SESSION['saw_role'] ?? null;
        error_log('Session role: ' . ($session_role ?? 'NULL'));
        
        if (!empty($session_role)) {
            error_log('✅ Returning from session: ' . $session_role);
            error_log('------------------------------------');
            return $session_role;
        }
        
        static $role = null;
        if ($role === null) {
            global $wpdb;
            error_log('Querying DB for user: ' . get_current_user_id());
            
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT role FROM {$wpdb->prefix}saw_users 
                 WHERE wp_user_id = %d AND is_active = 1",
                get_current_user_id()
            ));
            
            $role = $saw_user->role ?? null;
            error_log('DB role: ' . ($role ?? 'NULL'));
        }
        
        error_log('✅ Returning from DB: ' . ($role ?? 'NULL'));
        error_log('------------------------------------');
        return $role;
    }
    
    protected function get_filtered_customer_id() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $customer_id = $_SESSION['saw_current_customer_id'] ?? null;
        
        if (!$customer_id && is_user_logged_in()) {
            $customer_id = get_user_meta(get_current_user_id(), 'saw_current_customer_id', true);
            if ($customer_id) {
                $_SESSION['saw_current_customer_id'] = intval($customer_id);
            }
        }
        
        return $customer_id ? intval($customer_id) : null;
    }
    
    protected function get_filtered_branch_id() {
        $role = $this->get_current_user_role();
        
        if ($role === 'super_admin' || $role === 'admin') {
            return null;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['saw_branch_id'] ?? null;
    }
    
    protected function verify_capability($action) {
        // ✅ DEBUG
        error_log('====================================');
        error_log('🔐 VERIFY_CAPABILITY START');
        error_log('Action: ' . $action);
        
        $cap = $this->config['capabilities'][$action] ?? 'manage_options';
        error_log('Required capability: ' . $cap);
        
        $has_cap = current_user_can($cap);
        error_log('User has capability: ' . ($has_cap ? 'YES' : 'NO'));
        
        if (!$has_cap) {
            error_log('❌ FAILED: User does NOT have capability!');
            error_log('====================================');
            wp_die('Insufficient permissions', 'Forbidden', ['response' => 403]);
        }
        
        error_log('✅ PASSED: Capability check OK');
        error_log('====================================');
    }
    
    protected function verify_nonce() {
        if (!isset($_POST['saw_nonce']) || !wp_verify_nonce($_POST['saw_nonce'], 'saw_' . $this->entity . '_form')) {
            wp_die('Security check failed', 'Forbidden', ['response' => 403]);
        }
    }
    
    protected function before_save($data) {
        return $data;
    }
    
    protected function after_save($id) {
    }
    
    protected function before_delete($id) {
        return true;
    }
    
    protected function after_delete($id) {
    }
    
    protected function get_current_user_data() {
        if (is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            return array(
                'id' => $wp_user->ID,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'role' => 'admin',
            );
        }
        
        return array(
            'id' => 1,
            'name' => 'Demo Admin',
            'email' => 'admin@demo.cz',
            'role' => 'admin',
        );
    }
    
    protected function get_current_customer_data() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $customer_id = isset($_SESSION['saw_current_customer_id']) ? absint($_SESSION['saw_current_customer_id']) : 0;
        
        global $wpdb;
        
        if ($customer_id > 0) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
                $customer_id
            ), ARRAY_A);
            
            if ($customer) {
                return $customer;
            }
            
            unset($_SESSION['saw_current_customer_id']);
        }
        
        $customer = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        
        if ($customer) {
            $_SESSION['saw_current_customer_id'] = $customer['id'];
            return $customer;
        }
        
        if (!headers_sent()) {
            $this->set_flash_message('Nejprve vytvořte zákazníka', 'error');
            wp_redirect(home_url('/admin/settings/customers/'));
            exit;
        }
        
        return null;
    }
    
    protected function register_ajax_handlers() {
        add_action('wp_ajax_saw_search_' . $this->entity, [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_' . $this->entity, [$this, 'ajax_delete']);
        add_action('wp_ajax_saw_get_' . $this->entity . '_detail', [$this, 'ajax_get_detail']);
    }
    
    protected function set_flash_message($message, $type = 'success') {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['saw_flash_message'] = [
            'message' => $message,
            'type' => $type
        ];
    }
    
    protected function get_flash_message() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['saw_flash_message'])) {
            $flash = $_SESSION['saw_flash_message'];
            unset($_SESSION['saw_flash_message']);
            return $flash;
        }
        
        return null;
    }
    
    protected function render_flash_messages() {
        $flash = $this->get_flash_message();
        
        if (!$flash) {
            return;
        }
        
        $type_class = $flash['type'] === 'error' ? 'saw-alert-error' : 'saw-alert-success';
        $icon = $flash['type'] === 'error' ? 'dashicons-warning' : 'dashicons-yes-alt';
        
        echo '<div class="saw-alert ' . esc_attr($type_class) . '" style="margin-bottom: 20px;">';
        echo '<span class="dashicons ' . esc_attr($icon) . '"></span>';
        echo '<span>' . wp_kses_post($flash['message']) . '</span>';
        echo '</div>';
    }
}