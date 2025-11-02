<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Module_Users_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    private $pending_departments = null;
    private $pending_welcome_email = null;
    
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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if ($data['role'] === 'super_admin') {
            $data['customer_id'] = null;
            $data['branch_id'] = null;
        } else {
            if (empty($data['customer_id'])) {
                $data['customer_id'] = $_SESSION['saw_current_customer_id'] ?? null;
            }
        }
        
        if ($data['role'] === 'admin') {
            $data['branch_id'] = null;
        } elseif ($data['role'] !== 'super_admin') {
            if (empty($data['branch_id'])) {
                wp_die('Musíte vybrat pobočku pro roli ' . $data['role']);
            }
            $this->validate_branch_customer($data['branch_id'], $data['customer_id']);
        }
        
        if (empty($data['id'])) {
            $existing_wp_user = get_user_by('email', $data['email']);
            if ($existing_wp_user) {
                wp_die('Email je již používán jiným WordPress uživatelem');
            }
            
            $password = wp_generate_password(12, true);
            $wp_user_id = wp_create_user($data['email'], $password, $data['email']);
            
            if (is_wp_error($wp_user_id)) {
                wp_die($wp_user_id->get_error_message());
            }
            
            $wp_role = $this->map_saw_to_wp_role($data['role']);
            wp_update_user([
                'ID' => $wp_user_id,
                'role' => $wp_role,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'display_name' => $data['first_name'] . ' ' . $data['last_name']
            ]);
            
            $data['wp_user_id'] = $wp_user_id;
            
            $this->pending_welcome_email = [
                'email' => $data['email'],
                'role' => $data['role'],
                'password' => $password,
                'first_name' => $data['first_name']
            ];
        }
        
        if ($data['role'] === 'terminal' && !empty($data['pin'])) {
            $data['pin'] = password_hash($data['pin'], PASSWORD_BCRYPT);
        } else {
            $data['pin'] = null;
        }
        
        if ($data['role'] === 'manager') {
            $this->pending_departments = $_POST['department_ids'] ?? [];
            
            if (empty($this->pending_departments)) {
                wp_die('Manager musí mít přiřazeno alespoň jedno oddělení');
            }
        }
        
        unset($data['department_ids']);
        unset($data['password']);
        
        return $data;
    }
    
    protected function after_save($user_id) {
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
                    ]
                );
            }
        }
        
        if ($this->pending_welcome_email) {
            $this->send_welcome_email(
                $this->pending_welcome_email['email'],
                $this->pending_welcome_email['role'],
                $this->pending_welcome_email['password'],
                $this->pending_welcome_email['first_name']
            );
        }
    }
    
    private function map_saw_to_wp_role($saw_role) {
        return [
            'super_admin' => 'administrator',
            'admin' => 'saw_admin',
            'super_manager' => 'saw_super_manager',
            'manager' => 'saw_manager',
            'terminal' => 'saw_terminal'
        ][$saw_role] ?? 'saw_admin';
    }
    
    private function validate_branch_customer($branch_id, $customer_id) {
        global $wpdb;
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id FROM {$wpdb->prefix}saw_branches WHERE id = %d",
            $branch_id
        ));
        
        if (!$branch || $branch->customer_id != $customer_id) {
            wp_die('Pobočka nepatří k vybranému zákazníkovi');
        }
    }
    
    private function send_welcome_email($email, $role, $password, $first_name) {
        $subject = 'Vítejte v SAW Visitors';
        
        $message = "Dobrý den {$first_name},\n\n";
        $message .= "Váš účet byl vytvořen v systému SAW Visitors.\n\n";
        $message .= "Přihlašovací údaje:\n";
        $message .= "Email: {$email}\n";
        $message .= "Heslo: {$password}\n\n";
        $message .= "Přihlásit se můžete zde: " . home_url('/login/') . "\n\n";
        $message .= "Doporučujeme si heslo změnit po prvním přihlášení.";
        
        wp_mail($email, $subject, $message);
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