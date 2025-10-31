<?php
/**
 * Base Controller Class
 * 
 * Univerzální CRUD operace pro všechny moduly.
 * Child controllery jen přidávají custom logiku přes hooks.
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 * @since   4.8.0
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
    
    /**
     * Index (list view)
     * 
     * Zobrazí tabulku s daty z DB. Pagination, search, filters.
     */
    public function index() {
        $this->verify_capability('list');
        $this->enqueue_assets(); // ← Tady se načtou CSS/JS
        
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
        
        foreach ($this->config['list_config']['filters'] ?? [] as $filter_key => $enabled) {
            if ($enabled && isset($_GET[$filter_key])) {
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
        include $template_path;
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer = $this->get_current_customer_data();
            $layout->render($content, $this->config['plural'], $this->entity, $user, $customer);
        } else {
            echo $content;
        }
    }
    
    /**
     * Create (form + save)
     */
    public function create() {
        $this->verify_capability('create');
        $this->enqueue_assets(); // ← Tady se načtou CSS/JS
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_save();
            return;
        }
        
        $item = null;
        $this->render_form($item);
    }
    
    /**
     * Edit (form + save)
     */
    public function edit($id) {
        $this->verify_capability('edit');
        $this->enqueue_assets(); // ← Tady se načtou CSS/JS
        
        $id = intval($id);
        if ($id <= 0) {
            wp_die('Invalid ID', 'Bad Request', ['response' => 400]);
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_die($this->config['singular'] . ' not found', 'Not Found', ['response' => 404]);
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handle_save($id);
            return;
        }
        
        $this->render_form($item);
    }
    
    /**
     * Handle save (create/update)
     */
    protected function handle_save($id = 0) {
        $this->verify_nonce();
        
        $data = $this->collect_form_data();
        
        $data = $this->before_save($data);
        
        $validation = $this->model->validate($data, $id);
        
        if (is_wp_error($validation)) {
            wp_die($validation->get_error_message());
        }
        
        if ($id > 0) {
            $result = $this->model->update($id, $data);
        } else {
            $result = $this->model->create($data);
            $id = $result;
        }
        
        if (is_wp_error($result)) {
            wp_die($result->get_error_message());
        }
        
        $this->after_save($id);
        
        $redirect_url = home_url($this->config['route'] . '/?saved=1');
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Collect form data
     */
    protected function collect_form_data() {
        $data = [];
        
        foreach ($this->config['fields'] as $field_key => $field_config) {
            if (!isset($_POST[$field_key])) {
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
    
    /**
     * Render form template
     */
    protected function render_form($item) {
        $template_path = $this->config['path'] . 'form-template.php';
        
        if (!file_exists($template_path)) {
            wp_die('Form template not found: ' . $template_path);
        }
        
        extract(compact('item'));
        
        ob_start();
        include $template_path;
        $content = ob_get_clean();
        
        if (class_exists('SAW_App_Layout')) {
            $layout = new SAW_App_Layout();
            $user = $this->get_current_user_data();
            $customer = $this->get_current_customer_data();
            $title = $item ? 'Edit ' . $this->config['singular'] : 'New ' . $this->config['singular'];
            $layout->render($content, $title, $this->entity, $user, $customer);
        } else {
            echo $content;
        }
    }
    
    /**
     * Enqueue assets
     * 
     * ← TADY JE FIX! Vždy načte globální + module CSS/JS.
     */
    protected function enqueue_assets() {
        // Enqueue global assets (base, tables, forms, components)
        SAW_Asset_Manager::enqueue_global();
        
        // Enqueue module-specific assets (customers/styles.css, scripts.js)
        SAW_Asset_Manager::enqueue_module($this->entity);
    }
    
    /**
     * Verify capability
     */
    protected function verify_capability($action) {
        $cap = $this->config['capabilities'][$action] ?? 'manage_options';
        
        if (!current_user_can($cap)) {
            wp_die('Insufficient permissions', 'Forbidden', ['response' => 403]);
        }
    }
    
    /**
     * Verify nonce
     */
    protected function verify_nonce() {
        if (!isset($_POST['saw_nonce']) || !wp_verify_nonce($_POST['saw_nonce'], 'saw_' . $this->entity . '_form')) {
            wp_die('Security check failed', 'Forbidden', ['response' => 403]);
        }
    }
    
    /**
     * Hook: Before save
     * 
     * Override v child class pro custom logiku (např. logo upload).
     */
    protected function before_save($data) {
        return $data;
    }
    
    /**
     * Hook: After save
     * 
     * Override v child class pro cache invalidation, notifikace, atd.
     */
    protected function after_save($id) {
        // Noop
    }
    
    /**
     * Hook: Before delete
     * 
     * Override v child class pro custom checks (např. foreign key constraints).
     * Return false = zastaví mazání.
     */
    protected function before_delete($id) {
        return true;
    }
    
    /**
     * Hook: After delete
     * 
     * Override v child class pro cleanup (např. smazání souborů).
     */
    protected function after_delete($id) {
        // Noop
    }
    
    /**
     * Get current user data
     */
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
    
    /**
     * Get current customer data
     */
    protected function get_current_customer_data() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $customer_id = isset($_SESSION['saw_current_customer_id']) ? absint($_SESSION['saw_current_customer_id']) : 0;
        
        if (!$customer_id) {
            global $wpdb;
            $customer = $wpdb->get_row(
                "SELECT * FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1",
                ARRAY_A
            );
            
            if ($customer) {
                $_SESSION['saw_current_customer_id'] = $customer['id'];
                return $customer;
            }
            
            return array(
                'id' => 1,
                'name' => 'Demo Firma s.r.o.',
                'ico' => '12345678',
            );
        }
        
        global $wpdb;
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
            $customer_id
        ), ARRAY_A);
        
        return $customer ?: array(
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
        );
    }
    
    /**
     * Register AJAX handlers
     */
    protected function register_ajax_handlers() {
        add_action('wp_ajax_saw_search_' . $this->entity, [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_' . $this->entity, [$this, 'ajax_delete']);
        add_action('wp_ajax_saw_get_' . $this->entity . '_detail', [$this, 'ajax_get_detail']);
    }
}