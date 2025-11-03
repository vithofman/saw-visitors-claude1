<?php
/**
 * Users Module Controller - FINAL FIX
 * 
 * Based on original working controller.
 * ONLY CHANGE: Added customer_id handling in before_save().
 * 
 * @package SAW_Visitors
 * @version 1.0.4
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
        $this->config = require __DIR__ . '/config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = __DIR__ . '/';
        
        require_once __DIR__ . '/model.php';
        $this->model = new SAW_Module_Users_Model($this->config);
        
        add_action('wp_ajax_saw_get_users_detail', [$this, 'ajax_get_detail']);
        add_action('wp_ajax_saw_search_users', [$this, 'ajax_search']);
        add_action('wp_ajax_saw_delete_users', [$this, 'ajax_delete']);
        add_action('wp_ajax_saw_get_departments_by_branch', [$this, 'ajax_get_departments_by_branch']);
    }
    
    protected function before_save($data) {
        error_log('=== SAW USERS BEFORE_SAVE START ===');
        error_log('Data keys: ' . implode(', ', array_keys($data)));
        
        // ===================================
        // 0. ✅ NEW: CUSTOMER_ID HANDLING
        // ===================================
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // NEW USER - set customer_id
        if (empty($data['id'])) {
            if (isset($data['role']) && $data['role'] === 'super_admin') {
                $data['customer_id'] = null;
            } else {
                $data['customer_id'] = $_SESSION['saw_current_customer_id'] ?? null;
                if (!$data['customer_id']) {
                    wp_die('Customer ID is required');
                }
            }
        }
        // EXISTING USER - preserve customer_id
        else {
            $existing = $this->model->get_by_id($data['id']);
            if ($existing) {
                $data['customer_id'] = $existing['customer_id'];
            }
        }
        
        // ===================================
        // 1. VYTVOŘENÍ WP USERA (NOVÝ UŽIVATEL)
        // ===================================
        if (empty($data['id'])) {
            // Kontrola zda email již není použit
            $existing_wp_user = get_user_by('email', $data['email']);
            if ($existing_wp_user) {
                wp_die('Email je již používán jiným WordPress uživatelem');
            }
            
            // ✅ Set max execution time for WP user creation
            @set_time_limit(30);
            
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
                error_log('SAW Users: wp_insert_user failed - ' . $wp_user_id->get_error_message());
                wp_die('Chyba při vytváření WordPress uživatele: ' . $wp_user_id->get_error_message());
            }
            
            if (!$wp_user_id || !is_numeric($wp_user_id)) {
                error_log('SAW Users: wp_insert_user returned invalid ID');
                wp_die('Chyba: Vytvoření WordPress uživatele selhalo (invalid ID)');
            }
            
            $data['wp_user_id'] = $wp_user_id;
            
            // Připrav setup email
            $this->pending_setup_email = [
                'email' => $data['email'],
                'role' => $data['role'],
                'first_name' => $data['first_name'],
                'saw_user_id' => null,
            ];
        }
        // ===================================
        // 2. AKTUALIZACE EXISTUJÍCÍHO UŽIVATELE
        // ===================================
        else {
            $existing_user = $this->model->get_by_id($data['id']);
            
            if (!$existing_user) {
                wp_die('Uživatel nenalezen');
            }
            
            // Pokud se změnila role, aktualizuj WP roli
            if (!empty($existing_user['wp_user_id']) && $existing_user['role'] !== $data['role']) {
                $wp_user = new WP_User($existing_user['wp_user_id']);
                $new_wp_role = $this->map_saw_to_wp_role($data['role']);
                
                $wp_user->set_role($new_wp_role);
                
                // Pro super_admina přidej i administrator capability
                if ($data['role'] === 'super_admin') {
                    $wp_user->add_cap('manage_options');
                }
            }
            
            // Zachovej wp_user_id
            $data['wp_user_id'] = $existing_user['wp_user_id'];
        }
        
        // ===================================
        // 3. PIN PRO TERMINAL
        // ===================================
        if ($data['role'] === 'terminal' && !empty($data['pin'])) {
            if (!preg_match('/^\d{4}$/', $data['pin'])) {
                wp_die('PIN musí být 4 číslice');
            }
            $data['pin'] = password_hash($data['pin'], PASSWORD_BCRYPT);
        } else {
            $data['pin'] = null;
        }
        
        // ===================================
        // 4. DEPARTMENTS PRO MANAGER
        // ===================================
        if (isset($data['department_ids'])) {
            // ✅ Ensure it's an array before filtering
            if (is_array($data['department_ids'])) {
                $this->pending_departments = array_filter($data['department_ids']);
            } else {
                $this->pending_departments = [];
            }
            
            if ($data['role'] === 'manager' && empty($this->pending_departments)) {
                wp_die('Manager musí mít přiřazeno alespoň jedno oddělení');
            }
        }
        
        // Cleanup
        unset($data['department_ids']);
        unset($data['password']);
        
        return $data;
    }
    
    protected function after_save($user_id) {
        // ===================================
        // 1. ULOŽIT DEPARTMENTS (MANAGER)
        // ===================================
        if ($this->pending_departments !== null) {
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
        }
        
        // ===================================
        // 2. SETUP EMAIL (NOVÝ UŽIVATEL)
        // ===================================
        if ($this->pending_setup_email) {
            $this->pending_setup_email['saw_user_id'] = $user_id;
            
            if (!class_exists('SAW_Password')) {
                $password_file = SAW_VISITORS_PLUGIN_DIR . 'includes/auth/class-saw-password.php';
                
                if (!file_exists($password_file)) {
                    wp_die('Chyba: Soubor class-saw-password.php nebyl nalezen');
                }
                
                require_once $password_file;
            }
            
            $password_handler = new SAW_Password();
            $token = $password_handler->create_setup_token($user_id);
            
            if ($token) {
                $password_handler->send_welcome_email(
                    $this->pending_setup_email['email'],
                    $this->pending_setup_email['role'],
                    $this->pending_setup_email['first_name'],
                    $token
                );
            }
        }
        
        // ===================================
        // 3. AUDIT LOG
        // ===================================
        if (class_exists('SAW_Audit')) {
            // Get customer_id from newly created user
            $user = $this->model->get_by_id($user_id);
            
            SAW_Audit::log([
                'action' => empty($this->pending_setup_email) ? 'user_updated' : 'user_created',
                'entity_type' => 'user',
                'entity_id' => $user_id,
                'customer_id' => $user['customer_id'] ?? null, // ✅ FIXED
                'details' => json_encode([
                    'role' => $this->pending_setup_email['role'] ?? 'updated',
                    'email' => $this->pending_setup_email['email'] ?? '',
                ])
            ]);
        }
    }
    
    protected function before_delete($id) {
        $user = $this->model->get_by_id($id);
        
        if (!$user) {
            return true;
        }
        
        // Kontrola že nemazeme sami sebe
        if (!empty($user['wp_user_id'])) {
            $wp_user_id = intval($user['wp_user_id']);
            
            if ($wp_user_id === get_current_user_id()) {
                wp_die('Nemůžete smazat sami sebe');
            }
            
            // Smaž WordPress user
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            $deleted = wp_delete_user($wp_user_id);
            
            if (is_wp_error($deleted)) {
                wp_die('Chyba při mazání WordPress uživatele: ' . $deleted->get_error_message());
            }
        }
        
        // Smaž department assignments
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'saw_user_departments',
            ['user_id' => $id],
            ['%d']
        );
        
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
        
        return $mapping[$saw_role] ?? 'saw_admin';
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