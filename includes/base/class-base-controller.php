<?php
/**
 * Base Controller Class
 * 
 * @package SAW_Visitors
 * @version 4.0.1 - OPRAVENO: Flash messages místo wp_die()
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
            $customer = $this->get_current_customer_data();
            $filters['customer_id'] = $customer['id'] ?? 0;
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
        
        // ✅ Flash messages
        $this->render_flash_messages();
        
        include $template_path;
        echo '</div>';
        
        echo '<script>document.dispatchEvent(new Event("sawModuleChanged"));</script>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    public function create() {
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
    
    /**
     * ✅ OPRAVENO: Flash messages místo wp_die()
     */
    protected function handle_save($id = 0) {
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
        
        // ✅ OPRAVA: Validační chyby jako flash message s redirect zpět
        if (is_wp_error($validation)) {
            $errors = $validation->get_error_data();
            
            if (is_array($errors)) {
                $error_messages = implode('<br>', array_values($errors));
            } else {
                $error_messages = $validation->get_error_message();
            }
            
            // Ulož error do session
            $this->set_flash_message($error_messages, 'error');
            
            // Redirect zpět na formulář
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
            $this->set_flash_message($result->get_error_message(), 'error');
            
            if ($id > 0) {
                $redirect_url = home_url($this->config['route'] . '/edit/' . $id . '/');
            } else {
                $redirect_url = home_url($this->config['route'] . '/new/');
            }
            
            wp_redirect($redirect_url);
            exit;
        }
        
        $this->after_save($id);
        
        $this->set_flash_message('Záznam byl úspěšně uložen', 'success');
        
        $redirect_url = home_url($this->config['route'] . '/');
        wp_redirect($redirect_url);
        exit;
    }
    
    protected function collect_form_data() {
        $data = [];
        
        foreach ($this->config['fields'] as $field_key => $field_config) {
            $field_type = $field_config['type'] ?? 'text';
            
            if ($field_type === 'checkbox') {
                $data[$field_key] = isset($_POST[$field_key]) ? 1 : 0;
                continue;
            }
            
            if (!isset($_POST[$field_key])) {
                if (isset($field_config['default'])) {
                    $data[$field_key] = $field_config['default'];
                }
                continue;
            }
            
            $value = $_POST[$field_key];
            
            $sanitize_fn = $field_config['sanitize'] ?? 'sanitize_text_field';
            
            if (function_exists($sanitize_fn)) {
                $value = $sanitize_fn($value);
            }
            
            $data[$field_key] = $value;
        }
        
        return $data;
    }
    
    protected function render_form($item) {
        $template_path = $this->config['path'] . 'form-template.php';
        
        if (!file_exists($template_path)) {
            wp_die('Form template not found: ' . $template_path);
        }
        
        $account_types = [];
        if (class_exists('SAW_Module_Account_Types_Model')) {
            global $wpdb;
            $account_types = $wpdb->get_results(
                "SELECT id, display_name FROM {$wpdb->prefix}saw_account_types ORDER BY sort_order ASC",
                ARRAY_A
            );
        }
        
        extract(compact('item', 'account_types'));
        
        ob_start();
        
        $style_manager = SAW_Module_Style_Manager::get_instance();
        echo $style_manager->inject_module_css($this->entity);
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        // ✅ Flash messages ve formuláři
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
    
    protected function verify_capability($action) {
        $cap = $this->config['capabilities'][$action] ?? 'manage_options';
        
        if (!current_user_can($cap)) {
            wp_die('Insufficient permissions', 'Forbidden', ['response' => 403]);
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
        
        $customer_id = isset($_SESSION['saw_current_customer_id']) ? absint($_SESSION['saw_current_customer_id']) : 0;
        
        global $wpdb;
        
        // ✅ Pokud máme ID, zkontroluj jestli zákazník existuje
        if ($customer_id > 0) {
            $customer = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
                $customer_id
            ), ARRAY_A);
            
            // ✅ Pokud existuje, vrať ho
            if ($customer) {
                return $customer;
            }
            
            // ❌ Zákazník byl smazán → vyčisti session a najdi jiného
            unset($_SESSION['saw_current_customer_id']);
        }
        
        // ✅ Najdi prvního dostupného zákazníka
        $customer = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        
        if ($customer) {
            $_SESSION['saw_current_customer_id'] = $customer['id'];
            return $customer;
        }
        
        // ❌ ŽÁDNÝ ZÁKAZNÍK NEEXISTUJE → Redirect na customers s upozorněním
        if (!headers_sent()) {
            $this->set_flash_message('Nejprve vytvořte zákazníka', 'error');
            wp_redirect(home_url('/admin/settings/customers/'));
            exit;
        }
        
        // Fallback pro AJAX requesty (nemělo by nastat)
        return null;
    }
    
    protected function register_ajax_handlers() {
        add_action('wp_ajax_saw_search_' . $this->entity, [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_' . $this->entity, [$this, 'ajax_delete']);
        add_action('wp_ajax_saw_get_' . $this->entity . '_detail', [$this, 'ajax_get_detail']);
    }
    
    /**
     * ✅ NOVÉ: Flash message system
     */
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