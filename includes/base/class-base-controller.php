<?php
/**
 * Base Controller Class - UPDATED with Permissions System
 * 
 * @package SAW_Visitors
 * @version 4.10.0
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
            $data['customer_id'] = $customer['id'] ?? null;
        }
        
        $data = $this->before_save($data);
        
        if ($id > 0) {
            $result = $this->model->update($id, $data);
        } else {
            $result = $this->model->create($data);
        }
        
        if (is_wp_error($result)) {
            $this->set_flash_message('error', $result->get_error_message());
            if ($id > 0) {
                wp_redirect($this->get_edit_url($id));
            } else {
                wp_redirect($this->get_create_url());
            }
            exit;
        }
        
        $saved_id = $id > 0 ? $id : $result;
        $this->after_save($saved_id);
        
        $action = $id > 0 ? 'updated' : 'created';
        $this->set_flash_message('success', $this->config['singular'] . ' ' . ($action === 'updated' ? 'upraven' : 'vytvořen'));
        
        wp_redirect($this->get_list_url());
        exit;
    }
    
    public function delete($id) {
        $this->verify_module_access();
        $this->verify_capability('delete');
        
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
                wp_die('Nemáte oprávnění smazat tento záznam', 'Forbidden', ['response' => 403]);
            }
        }
        
        if (!$this->before_delete($id)) {
            $this->set_flash_message('error', 'Nelze smazat tento záznam');
            wp_redirect($this->get_list_url());
            exit;
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            $this->set_flash_message('error', $result->get_error_message());
        } else {
            $this->after_delete($id);
            $this->set_flash_message('success', $this->config['singular'] . ' smazán');
        }
        
        wp_redirect($this->get_list_url());
        exit;
    }
    
    protected function verify_module_access() {
        if (!is_user_logged_in()) {
            wp_redirect(home_url('/login/'));
            exit;
        }
    }
    
    protected function get_current_user_role() {
        if (current_user_can('manage_options')) {
            return 'super_admin';
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $session_role = $_SESSION['saw_role'] ?? null;
        
        if (!empty($session_role)) {
            return $session_role;
        }
        
        static $role = null;
        if ($role === null) {
            global $wpdb;
            
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT role FROM {$wpdb->prefix}saw_users 
                 WHERE wp_user_id = %d AND is_active = 1",
                get_current_user_id()
            ));
            
            $role = $saw_user->role ?? null;
        }
        
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
        if (current_user_can('manage_options')) {
            return;
        }
        
        $saw_role = $this->get_current_user_role();
        
        if (!$saw_role) {
            wp_die('Insufficient permissions - no SAW role', 'Forbidden', ['response' => 403]);
        }
        
        if (!class_exists('SAW_Permissions')) {
            $permissions_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-permissions.php';
            if (file_exists($permissions_file)) {
                require_once $permissions_file;
            }
        }
        
        if (!class_exists('SAW_Permissions')) {
            error_log('[Base Controller] ERROR: SAW_Permissions class not found');
            wp_die('Permissions system not available', 'Error', ['response' => 500]);
        }
        
        $allowed = SAW_Permissions::check($saw_role, $this->entity, $action);
        
        if (!$allowed) {
            wp_die('Insufficient permissions for ' . $action . ' on ' . $this->entity, 'Forbidden', ['response' => 403]);
        }
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
        
        $customer_id = isset($_SESSION['saw_current_customer_id']) ? intval($_SESSION['saw_current_customer_id']) : null;
        
        if (!$customer_id && is_user_logged_in()) {
            $customer_id = get_user_meta(get_current_user_id(), 'saw_current_customer_id', true);
            if ($customer_id) {
                $_SESSION['saw_current_customer_id'] = intval($customer_id);
            }
        }
        
        if (!$customer_id) {
            return array(
                'id' => 0,
                'name' => 'Demo zákazník',
            );
        }
        
        global $wpdb;
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
            $customer_id
        ), ARRAY_A);
        
        return $customer ?: array(
            'id' => 0,
            'name' => 'Demo zákazník',
        );
    }
    
    protected function collect_form_data() {
        $data = array();
        
        foreach ($this->config['fields'] as $field_key => $field_config) {
            if ($field_config['type'] === 'checkbox') {
                $data[$field_key] = isset($_POST[$field_key]) ? 1 : 0;
            } else {
                $value = $_POST[$field_key] ?? '';
                
                if (!empty($field_config['sanitize'])) {
                    $sanitize_func = $field_config['sanitize'];
                    if (function_exists($sanitize_func)) {
                        $data[$field_key] = $sanitize_func($value);
                    } else {
                        $data[$field_key] = sanitize_text_field($value);
                    }
                } else {
                    $data[$field_key] = sanitize_text_field($value);
                }
            }
        }
        
        return $data;
    }
    
    protected function render_form($item) {
        $form_template = $this->config['path'] . 'form-template.php';
        
        if (!file_exists($form_template)) {
            wp_die('Form template not found: ' . $form_template);
        }
        
        $is_edit = !empty($item);
        $page_title = $is_edit ? 'Upravit ' . $this->config['singular'] : 'Nový ' . $this->config['singular'];
        
        ob_start();
        
        $style_manager = SAW_Module_Style_Manager::get_instance();
        echo $style_manager->inject_module_css($this->entity);
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        include $form_template;
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, $page_title);
    }
    
    protected function render_with_layout($content, $page_title) {
        if (!class_exists('SAW_App_Layout')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/class-saw-app-layout.php';
        }
        
        $layout = new SAW_App_Layout();
        $layout->render($content, $page_title, $this->entity);
    }
    
    protected function enqueue_assets() {
        $module_css = $this->config['path'] . 'styles.css';
        if (file_exists($module_css)) {
            $version = filemtime($module_css);
            $css_url = str_replace(SAW_VISITORS_PLUGIN_DIR, SAW_VISITORS_PLUGIN_URL, $module_css);
            wp_enqueue_style('saw-module-' . $this->entity, $css_url, [], $version);
        }
        
        $module_js = $this->config['path'] . 'scripts.js';
        if (file_exists($module_js)) {
            $version = filemtime($module_js);
            $js_url = str_replace(SAW_VISITORS_PLUGIN_DIR, SAW_VISITORS_PLUGIN_URL, $module_js);
            wp_enqueue_script('saw-module-' . $this->entity, $js_url, ['jquery'], $version, true);
            
            wp_localize_script('saw-module-' . $this->entity, 'sawModuleConfig', [
                'entity' => $this->entity,
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('saw_ajax_nonce'),
            ]);
        }
    }
    
    protected function get_list_url() {
        return home_url('/' . $this->config['route'] . '/');
    }
    
    protected function get_create_url() {
        return home_url('/' . $this->config['route'] . '/new/');
    }
    
    protected function get_edit_url($id) {
        return home_url('/' . $this->config['route'] . '/edit/' . $id . '/');
    }
    
    protected function get_delete_url($id) {
        return home_url('/' . $this->config['route'] . '/delete/' . $id . '/');
    }
    
    protected function set_flash_message($type, $message) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['saw_flash_message'] = array(
            'type' => $type,
            'message' => $message
        );
    }
    
    protected function render_flash_messages() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['saw_flash_message'])) {
            $flash = $_SESSION['saw_flash_message'];
            unset($_SESSION['saw_flash_message']);
            
            $class = $flash['type'] === 'success' ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible">';
            echo '<p>' . esc_html($flash['message']) . '</p>';
            echo '</div>';
        }
    }
}