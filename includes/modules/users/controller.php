<?php
/**
 * Users Module Controller - DEBUG VERSION
 * 
 * @package SAW_Visitors
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
        error_log('SAW Users Controller: __construct() START');
        
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Users_Model($this->config);
        
        // AJAX endpoints
        add_action('wp_ajax_saw_get_users_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_users', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_users', [$this, 'ajax_delete']);
        add_action('wp_ajax_saw_get_departments_by_branch', [$this, 'ajax_get_departments_by_branch']);
        
        error_log('SAW Users Controller: __construct() END');
    }
    
    protected function before_save($data) {
        error_log('SAW Users Controller: before_save() START');
        error_log('SAW Users Controller: Role = ' . ($data['role'] ?? 'NULL'));
        error_log('SAW Users Controller: Email = ' . ($data['email'] ?? 'NULL'));
        error_log('SAW Users Controller: ID = ' . ($data['id'] ?? 'NEW'));
        
        // Start session for customer_id
        if (session_status() === PHP_SESSION_NONE) {
            error_log('SAW Users Controller: Starting session');
            session_start();
        }
        
        // ===================================
        // 1. CUSTOMER ID PODLE ROLE
        // ===================================
        error_log('SAW Users Controller: Setting customer_id');
        
        if ($data['role'] === 'super_admin') {
            $data['customer_id'] = null;
            $data['branch_id'] = null;
            error_log('SAW Users Controller: super_admin - no customer/branch');
        } else {
            if (empty($data['customer_id'])) {
                $data['customer_id'] = $_SESSION['saw_current_customer_id'] ?? null;
                error_log('SAW Users Controller: customer_id from session = ' . ($data['customer_id'] ?? 'NULL'));
            }
            
            if (empty($data['customer_id'])) {
                error_log('SAW Users Controller: ERROR - No customer_id!');
                wp_die('Chyba: Není nastaven customer_id');
            }
        }
        
        // ===================================
        // 2. BRANCH ID PODLE ROLE
        // ===================================
        error_log('SAW Users Controller: Setting branch_id');
        
        if ($data['role'] === 'admin') {
            $data['branch_id'] = null;
            error_log('SAW Users Controller: admin - no branch required');
        } elseif ($data['role'] !== 'super_admin') {
            error_log('SAW Users Controller: Checking branch_id for role: ' . $data['role']);
            
            if (empty($data['branch_id'])) {
                error_log('SAW Users Controller: ERROR - No branch_id for role: ' . $data['role']);
                wp_die('Musíte vybrat pobočku pro roli ' . $data['role']);
            }
            
            error_log('SAW Users Controller: Validating branch_id: ' . $data['branch_id']);
            $this->validate_branch_customer($data['branch_id'], $data['customer_id']);
            error_log('SAW Users Controller: Branch validation OK');
        }
        
        // ===================================
        // 3. VYTVOŘENÍ WP USERA (NOVÝ UŽIVATEL)
        // ===================================
        if (empty($data['id'])) {
            error_log('SAW Users Controller: Creating NEW user');
            
            // Kontrola zda email již není použit
            $existing_wp_user = get_user_by('email', $data['email']);
            if ($existing_wp_user) {
                error_log('SAW Users Controller: ERROR - Email already exists: ' . $data['email']);
                wp_die('Email je již používán jiným WordPress uživatelem');
            }
            
            error_log('SAW Users Controller: Creating WP user...');
            
            // Vytvoř WP user BEZ hesla
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
                error_log('SAW Users Controller: ERROR creating WP user: ' . $wp_user_id->get_error_message());
                wp_die('Chyba při vytváření WordPress uživatele: ' . $wp_user_id->get_error_message());
            }
            
            error_log('SAW Users Controller: WP user created with ID: ' . $wp_user_id);
            
            // Nastav wp_user_id do SAW data
            $data['wp_user_id'] = $wp_user_id;
            
            // Připrav setup email
            $this->pending_setup_email = [
                'email' => $data['email'],
                'role' => $data['role'],
                'first_name' => $data['first_name'],
                'saw_user_id' => null,
            ];
            
            error_log('SAW Users Controller: Setup email prepared');
        }
        
        // ===================================
        // 4. PIN PRO TERMINAL
        // ===================================
        if ($data['role'] === 'terminal' && !empty($data['pin'])) {
            error_log('SAW Users Controller: Hashing PIN for terminal');
            
            if (!preg_match('/^\d{4}$/', $data['pin'])) {
                error_log('SAW Users Controller: ERROR - Invalid PIN format');
                wp_die('PIN musí být 4 číslice');
            }
            $data['pin'] = password_hash($data['pin'], PASSWORD_BCRYPT);
        } else {
            $data['pin'] = null;
        }
        
        // ===================================
        // 5. DEPARTMENTS PRO MANAGERA
        // ===================================
        if ($data['role'] === 'manager') {
            error_log('SAW Users Controller: Checking departments for manager');
            
            $this->pending_departments = $_POST['department_ids'] ?? [];
            
            if (empty($this->pending_departments)) {
                error_log('SAW Users Controller: ERROR - No departments selected');
                wp_die('Manager musí mít přiřazeno alespoň jedno oddělení');
            }
            
            error_log('SAW Users Controller: Departments count: ' . count($this->pending_departments));
        }
        
        // Cleanup
        unset($data['department_ids']);
        unset($data['password']);
        
        error_log('SAW Users Controller: before_save() END - returning data');
        return $data;
    }
    
    protected function after_save($user_id) {
        error_log('SAW Users Controller: after_save() START - user_id: ' . $user_id);
        
        // ===================================
        // 1. ULOŽIT DEPARTMENTS (MANAGER)
        // ===================================
        if ($this->pending_departments !== null) {
            error_log('SAW Users Controller: Saving departments');
            global $wpdb;
            
            // Smaž staré přiřazení
            $wpdb->delete(
                $wpdb->prefix . 'saw_user_departments',
                ['user_id' => $user_id]
            );
            
            // Přidej nová
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
            error_log('SAW Users Controller: Departments saved');
        }
        
        // ===================================
        // 2. SETUP EMAIL (NOVÝ UŽIVATEL)
        // ===================================
        if ($this->pending_setup_email) {
            error_log('SAW Users Controller: Preparing setup email');
            
            $this->pending_setup_email['saw_user_id'] = $user_id;
            
            // Check if class exists
            if (!class_exists('SAW_Password')) {
                error_log('SAW Users Controller: Loading SAW_Password class');
                $password_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-password.php';
                
                if (!file_exists($password_file)) {
                    error_log('SAW Users Controller: ERROR - Password class file not found: ' . $password_file);
                    wp_die('Chyba: Soubor class-saw-password.php nebyl nalezen');
                }
                
                require_once $password_file;
                error_log('SAW Users Controller: SAW_Password class loaded');
            }
            
            $password_handler = new SAW_Password();
            error_log('SAW Users Controller: SAW_Password instance created');
            
            $token = $password_handler->create_setup_token($user_id);
            error_log('SAW Users Controller: Setup token created: ' . ($token ? 'YES' : 'NO'));
            
            if ($token) {
                $sent = $password_handler->send_welcome_email(
                    $this->pending_setup_email['email'],
                    $this->pending_setup_email['role'],
                    $this->pending_setup_email['first_name'],
                    $token
                );
                error_log('SAW Users Controller: Welcome email sent: ' . ($sent ? 'YES' : 'NO'));
            }
        }
        
        // ===================================
        // 3. AUDIT LOG
        // ===================================
        if (class_exists('SAW_Audit')) {
            error_log('SAW Users Controller: Creating audit log');
            SAW_Audit::log([
                'action' => empty($this->pending_setup_email) ? 'user_updated' : 'user_created',
                'entity_type' => 'user',
                'entity_id' => $user_id,
                'details' => json_encode([
                    'role' => $this->pending_setup_email['role'] ?? 'updated',
                    'email' => $this->pending_setup_email['email'] ?? '',
                ])
            ]);
        }
        
        error_log('SAW Users Controller: after_save() END');
    }
    
    protected function before_delete($id) {
        error_log('SAW Users Controller: before_delete() START - id: ' . $id);
        
        $user = $this->model->get_by_id($id);
        
        if (!$user) {
            error_log('SAW Users Controller: User not found');
            return true;
        }
        
        // Kontrola že nemazeme sami sebe
        if (!empty($user['wp_user_id'])) {
            $wp_user_id = intval($user['wp_user_id']);
            
            if ($wp_user_id === get_current_user_id()) {
                error_log('SAW Users Controller: ERROR - Cannot delete self');
                wp_die('Nemůžete smazat sami sebe');
            }
            
            // Smaž WordPress user
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            $deleted = wp_delete_user($wp_user_id);
            
            if (is_wp_error($deleted)) {
                error_log('SAW Users Controller: ERROR deleting WP user: ' . $deleted->get_error_message());
                wp_die('Chyba při mazání WordPress uživatele: ' . $deleted->get_error_message());
            }
            
            error_log('SAW Users Controller: WP user deleted');
        }
        
        // Smaž department assignments
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'saw_user_departments',
            ['user_id' => $id],
            ['%d']
        );
        
        error_log('SAW Users Controller: before_delete() END');
        return true;
    }
    
    private function map_saw_to_wp_role($saw_role) {
        $mapping = [
            'super_admin' => 'administrator',
            'admin' => 'saw_admin',
            'super_manager' => 'saw_super_manager',
            'manager' => 'saw_manager',
            'terminal' => 'saw_terminal'
        ];
        
        $wp_role = $mapping[$saw_role] ?? 'saw_admin';
        error_log('SAW Users Controller: Mapping SAW role "' . $saw_role . '" to WP role "' . $wp_role . '"');
        
        return $wp_role;
    }
    
    private function validate_branch_customer($branch_id, $customer_id) {
        global $wpdb;
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id FROM {$wpdb->prefix}saw_branches WHERE id = %d",
            $branch_id
        ));
        
        if (!$branch || $branch->customer_id != $customer_id) {
            error_log('SAW Users Controller: ERROR - Branch does not belong to customer');
            wp_die('Pobočka nepatří k vybranému zákazníkovi');
        }
    }
    
    public function ajax_get_departments_by_branch() {
        check_ajax_referer('saw_departments', 'nonce');
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        
        if (!$branch_id) {
            wp_send_json_error(['message' => 'Chybí branch_id']);
        }
        
        global $wpdb;
        $departments = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}saw_departments 
             WHERE branch_id = %d AND is_active = 1 
             ORDER BY name ASC",
            $branch_id
        ), ARRAY_A);
        
        wp_send_json_success(['departments' => $departments]);
    }
}