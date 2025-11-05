<?php
/**
 * Users Module Controller - REFACTORED v4.0.0
 * 
 * ✅ Complete CRUD implementation (index, create, edit)
 * ✅ Uses SAW_Context instead of sessions
 * ✅ NO manual AJAX registration (SAW_Visitors does it automatically)
 * ✅ Proper error handling
 * ✅ Customer isolation
 * 
 * @package SAW_Visitors
 * @version 4.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Users_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $pending_departments = null;
    private $pending_setup_email = null;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/users/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Users_Model($this->config);
        
        // ✅ NO manual AJAX registration - SAW_Visitors::register_module_ajax_handlers() does it
        // BUT we need custom AJAX for departments (specific to users module)
        add_action('wp_ajax_saw_get_departments_by_branch', [$this, 'ajax_get_departments_by_branch']);
    }
    
    /**
     * Index - List view
     */
    public function index() {
        $this->verify_module_access();
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $is_active = isset($_GET['is_active']) ? sanitize_text_field($_GET['is_active']) : '';
        $role = isset($_GET['role']) ? sanitize_text_field($_GET['role']) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'first_name';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field($_GET['order'])) : 'ASC';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        $filters = [
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => $page,
            'per_page' => 20,
        ];
        
        if ($is_active !== '') {
            $filters['is_active'] = intval($is_active);
        }
        
        if ($role !== '') {
            $filters['role'] = $role;
        }
        
        $data = $this->model->get_all($filters);
        $items = $data['items'];
        $total = $data['total'];
        $total_pages = ceil($total / 20);
        
        ob_start();
        
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        require $this->config['path'] . 'list-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    /**
     * Create - New user form
     */
    public function create() {
        $this->verify_module_access();
        
        if (!$this->can('create')) {
            wp_die('Nemáte oprávnění vytvářet uživatele');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_users_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            $data = $this->prepare_form_data($_POST);
            $data = $this->before_save($data);
            
            if (is_wp_error($data)) {
                $this->set_flash($data->get_error_message(), 'error');
            } else {
                $id = $this->model->create($data);
                
                if (is_wp_error($id)) {
                    $this->set_flash($id->get_error_message(), 'error');
                } else {
                    $this->after_save($id);
                    $this->set_flash('Uživatel byl úspěšně vytvořen', 'success');
                    $this->redirect(home_url('/admin/users/'));
                }
            }
        }
        
        $item = [];
        
        ob_start();
        
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        require $this->config['path'] . 'form-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, 'Nový uživatel');
    }
    
    /**
     * Edit - Edit user form
     */
    public function edit($id) {
        $this->verify_module_access();
        
        if (!$this->can('edit')) {
            wp_die('Nemáte oprávnění upravovat uživatele');
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            if (class_exists('SAW_Error_Handler')) {
                SAW_Error_Handler::not_found('Uživatel');
            } else {
                wp_die('Uživatel nebyl nalezen');
            }
        }
        
        if (!$this->can_access_item($item)) {
            wp_die('Nemáte oprávnění k tomuto záznamu');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_users_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            $data = $this->prepare_form_data($_POST);
            $data['id'] = $id;
            $data = $this->before_save($data);
            
            if (is_wp_error($data)) {
                $this->set_flash($data->get_error_message(), 'error');
            } else {
                $result = $this->model->update($id, $data);
                
                if (is_wp_error($result)) {
                    $this->set_flash($result->get_error_message(), 'error');
                } else {
                    $this->after_save($id);
                    $this->set_flash('Uživatel byl úspěšně aktualizován', 'success');
                    $this->redirect(home_url('/admin/users/'));
                }
            }
            
            $item = $this->model->get_by_id($id);
        }
        
        // Get WP user email
        if (!empty($item['wp_user_id'])) {
            $wp_user = get_userdata($item['wp_user_id']);
            $item['email'] = $wp_user ? $wp_user->user_email : '';
        }
        
        ob_start();
        
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        $this->render_flash_messages();
        
        require $this->config['path'] . 'form-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        $this->render_with_layout($content, 'Upravit uživatele');
    }
    
    /**
     * AJAX: Get departments by branch
     */
    public function ajax_get_departments_by_branch() {
        check_ajax_referer('saw_departments', 'nonce');
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        
        if (!$branch_id) {
            wp_send_json_error(['message' => 'Chybí branch_id']);
        }
        
        global $wpdb;
        $departments = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM %i 
             WHERE branch_id = %d AND is_active = 1 
             ORDER BY name ASC",
            $wpdb->prefix . 'saw_departments',
            $branch_id
        ), ARRAY_A);
        
        wp_send_json_success(['departments' => $departments]);
    }
    
    /**
     * Prepare form data
     */
    private function prepare_form_data($post) {
        $data = [];
        
        foreach ($this->config['fields'] as $field_name => $field_config) {
            if (isset($post[$field_name])) {
                $value = $post[$field_name];
                
                if (isset($field_config['sanitize']) && is_callable($field_config['sanitize'])) {
                    $value = call_user_func($field_config['sanitize'], $value);
                } else {
                    $value = sanitize_text_field($value);
                }
                
                $data[$field_name] = $value;
            } elseif ($field_config['type'] === 'checkbox') {
                $data[$field_name] = 0;
            }
        }
        
        if (isset($post['id'])) {
            $data['id'] = intval($post['id']);
        }
        
        // Handle department_ids (checkbox array)
        if (isset($post['department_ids']) && is_array($post['department_ids'])) {
            $data['department_ids'] = array_map('intval', $post['department_ids']);
        }
        
        return $data;
    }
    
    /**
     * Before save hook
     */
    protected function before_save($data) {
        // ✅ Customer ID from context
        if (empty($data['id'])) {
            if (isset($data['role']) && $data['role'] === 'super_admin') {
                $data['customer_id'] = null;
            } else {
                $data['customer_id'] = SAW_Context::get_customer_id();
                if (!$data['customer_id']) {
                    wp_die('Customer ID is required');
                }
            }
        } else {
            $existing = $this->model->get_by_id($data['id']);
            if ($existing) {
                $data['customer_id'] = $existing['customer_id'];
            }
        }
        
        // Create WP user (new user)
        if (empty($data['id'])) {
            $existing_wp_user = get_user_by('email', $data['email']);
            if ($existing_wp_user) {
                wp_die('Email je již používán jiným WordPress uživatelem');
            }
            
            @set_time_limit(30);
            
            $wp_user_id = wp_insert_user([
                'user_login' => $data['email'],
                'user_email' => $data['email'],
                'user_pass' => wp_generate_password(32, true),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'display_name' => $data['first_name'] . ' ' . $data['last_name'],
                'role' => $this->map_saw_to_wp_role($data['role']),
            ]);
            
            if (is_wp_error($wp_user_id)) {
                wp_die('Chyba při vytváření WordPress uživatele: ' . $wp_user_id->get_error_message());
            }
            
            if (!$wp_user_id || !is_numeric($wp_user_id)) {
                wp_die('Chyba: Vytvoření WordPress uživatele selhalo (invalid ID)');
            }
            
            $data['wp_user_id'] = $wp_user_id;
            
            $this->pending_setup_email = [
                'email' => $data['email'],
                'role' => $data['role'],
                'first_name' => $data['first_name'],
                'saw_user_id' => null,
            ];
        }
        // Update existing user
        else {
            $existing_user = $this->model->get_by_id($data['id']);
            
            if (!$existing_user) {
                wp_die('Uživatel nenalezen');
            }
            
            // Role changed from manager - clear branch & departments
            if ($existing_user['role'] === 'manager' && $data['role'] !== 'manager') {
                $data['branch_id'] = null;
                
                global $wpdb;
                $wpdb->delete(
                    $wpdb->prefix . 'saw_user_departments',
                    ['user_id' => $data['id']],
                    ['%d']
                );
                
                $this->pending_departments = [];
            }
            
            // Role changed to admin - clear branch
            if (in_array($existing_user['role'], ['super_manager', 'terminal']) && $data['role'] === 'admin') {
                $data['branch_id'] = null;
            }
            
            // Update WP role if changed
            if (!empty($existing_user['wp_user_id']) && $existing_user['role'] !== $data['role']) {
                $wp_user = new WP_User($existing_user['wp_user_id']);
                $new_wp_role = $this->map_saw_to_wp_role($data['role']);
                
                $wp_user->set_role($new_wp_role);
                
                if ($data['role'] === 'super_admin') {
                    $wp_user->add_cap('manage_options');
                }
            }
            
            $data['wp_user_id'] = $existing_user['wp_user_id'];
        }
        
        // PIN for terminal
        if ($data['role'] === 'terminal' && !empty($data['pin'])) {
            if (!preg_match('/^\d{4}$/', $data['pin'])) {
                wp_die('PIN musí být 4 číslice');
            }
            $data['pin'] = password_hash($data['pin'], PASSWORD_BCRYPT);
        } else {
            $data['pin'] = null;
        }
        
        // Departments for manager
        if (isset($data['department_ids'])) {
            if (is_array($data['department_ids'])) {
                $this->pending_departments = array_filter($data['department_ids']);
            } else {
                $this->pending_departments = [];
            }
            
            if ($data['role'] === 'manager' && empty($this->pending_departments)) {
                wp_die('Manager musí mít přiřazeno alespoň jedno oddělení');
            }
        }
        
        unset($data['department_ids']);
        unset($data['password']);
        
        return $data;
    }
    
    /**
     * After save hook
     */
    protected function after_save($user_id) {
        // Save departments (manager)
        if ($this->pending_departments !== null) {
            global $wpdb;
            
            $wpdb->delete(
                $wpdb->prefix . 'saw_user_departments',
                ['user_id' => $user_id]
            );
            
            foreach ($this->pending_departments as $dept_id) {
                $wpdb->insert(
                    $wpdb->prefix . 'saw_user_departments',
                    [
                        'user_id' => $user_id,
                        'department_id' => (int)$dept_id
                    ],
                    ['%d', '%d']
                );
            }
        }
        
        // Setup email (new user)
        if ($this->pending_setup_email) {
            $this->pending_setup_email['saw_user_id'] = $user_id;
            
            if (!class_exists('SAW_Password')) {
                $password_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-password.php';
                
                if (file_exists($password_file)) {
                    require_once $password_file;
                }
            }
            
            if (class_exists('SAW_Password')) {
                $password_handler = new SAW_Password();
                $token = $password_handler->create_setup_token($user_id);
                
                if ($token) {
                    $password_handler->send_welcome_email(
                        $this->pending_setup_email['email'],
                        $this->pending_setup_email['role'],
                        $this->pending_setup_email['first_name'],
                        $token
                    );
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[Users] Failed to create setup token for user: ' . $user_id);
                    }
                }
            }
        }
        
        // Audit log
        if (class_exists('SAW_Audit')) {
            $user = $this->model->get_by_id($user_id);
            
            SAW_Audit::log([
                'action' => empty($this->pending_setup_email) ? 'user_updated' : 'user_created',
                'entity_type' => 'user',
                'entity_id' => $user_id,
                'customer_id' => $user['customer_id'] ?? null,
                'details' => json_encode([
                    'role' => $this->pending_setup_email['role'] ?? 'updated',
                    'email' => $this->pending_setup_email['email'] ?? '',
                ])
            ]);
        }
    }
    
    /**
     * Before delete hook
     */
    protected function before_delete($id) {
        $user = $this->model->get_by_id($id);
        
        if (!$user) {
            return true;
        }
        
        // Can't delete yourself
        if (!empty($user['wp_user_id'])) {
            $wp_user_id = intval($user['wp_user_id']);
            
            if ($wp_user_id === get_current_user_id()) {
                wp_die('Nemůžete smazat sami sebe');
            }
            
            // Delete WordPress user
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            $deleted = wp_delete_user($wp_user_id);
            
            if (is_wp_error($deleted)) {
                wp_die('Chyba při mazání WordPress uživatele: ' . $deleted->get_error_message());
            }
        }
        
        // Delete department assignments
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'saw_user_departments',
            ['user_id' => $id],
            ['%d']
        );
        
        return true;
    }
    
    /**
     * Map SAW role to WordPress role
     */
    private function map_saw_to_wp_role($saw_role) {
        $mapping = [
            'super_admin' => 'administrator',
            'admin' => 'saw_admin',
            'super_manager' => 'saw_super_manager',
            'manager' => 'saw_manager',
            'terminal' => 'saw_terminal'
        ];
        
        return $mapping[$saw_role] ?? 'saw_admin';
    }
}