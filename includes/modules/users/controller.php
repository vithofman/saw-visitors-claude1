<?php
/**
 * Users Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     5.2.0 - FIXED: AJAX handler registration (uses universal handler)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}

if (!trait_exists('SAW_AJAX_Handlers')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
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
        
        // ✅ REMOVED: AJAX handler registration from constructor
        // Universal handler in saw-visitors.php will handle it:
        // saw_get_departments_by_branch -> ajax_get_departments_by_branch()
    }
    
    /**
     * Display list page with optional sidebar
     */
    public function index() {
        $this->render_list_view();
    }

    protected function enqueue_assets() {
        if (class_exists('SAW_Asset_Loader')) {
            SAW_Asset_Loader::enqueue_module('users');
            
            // Pass existing department IDs for edit mode
            if (isset($_GET['id']) && isset($_GET['mode']) && $_GET['mode'] === 'edit') {
                $id = intval($_GET['id']);
                $item = $this->model->get_by_id($id);
                
                $existing_ids = [];
                if ($item && isset($item['department_ids'])) {
                    $existing_ids = array_map('intval', $item['department_ids']);
                } else {
                    // Fallback direct query
                    global $wpdb;
                    $existing_ids = $wpdb->get_col($wpdb->prepare(
                        "SELECT department_id FROM %i WHERE user_id = %d",
                        $wpdb->prefix . 'saw_user_departments',
                        $id
                    ));
                    $existing_ids = array_map('intval', $existing_ids);
                }
                
                wp_localize_script('saw-users', 'sawUsers', array(
                    'existingIds' => $existing_ids
                ));
            }
        }
    }
    
    /**
     * Prepare form data
     */
    protected function prepare_form_data($post) {
        $data = array();
        
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
        
        // Handle department_ids array (multiselect)
        if (isset($post['department_ids']) && is_array($post['department_ids'])) {
            $data['department_ids'] = array_map('intval', $post['department_ids']);
        } else {
            $data['department_ids'] = array();
        }
        
        return $data;
    }
    
    /**
     * Format data for detail view
     */
    protected function format_detail_data($item) {
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
        }
        
        if (!empty($item['last_login'])) {
            $item['last_login_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['last_login']));
        }
        
        return $item;
    }
    
    /**
     * Before save hook - handles WP user creation/update, role mapping, PIN hashing
     */
    protected function before_save($data) {
        // Auto-set customer_id for non-edit operations
        if (empty($data['id'])) {
            if (isset($data['role']) && $data['role'] === 'super_admin') {
                $data['customer_id'] = null;
            } else {
                $data['customer_id'] = SAW_Context::get_customer_id();
                if (!$data['customer_id']) {
                    return new WP_Error('no_customer', 'Customer ID is required');
                }
            }
        } else {
            // For edits, preserve existing customer_id
            $existing = $this->model->get_by_id($data['id']);
            if ($existing) {
                $data['customer_id'] = $existing['customer_id'];
            }
        }
        
        // CREATE: Create WordPress user
        if (empty($data['id'])) {
            $existing_wp_user = get_user_by('email', $data['email']);
            if ($existing_wp_user) {
                return new WP_Error('email_exists', 'Email je již používán jiným WordPress uživatelem');
            }
            
            @set_time_limit(30);
            
            $wp_user_id = wp_insert_user(array(
                'user_login' => $data['email'],
                'user_email' => $data['email'],
                'user_pass' => wp_generate_password(32, true),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'display_name' => $data['first_name'] . ' ' . $data['last_name'],
                'role' => $this->map_saw_to_wp_role($data['role']),
            ));
            
            if (is_wp_error($wp_user_id)) {
                return new WP_Error('wp_user_error', 'Chyba při vytváření WordPress uživatele: ' . $wp_user_id->get_error_message());
            }
            
            if (!$wp_user_id || !is_numeric($wp_user_id)) {
                return new WP_Error('wp_user_invalid', 'Chyba: Vytvoření WordPress uživatele selhalo (invalid ID)');
            }
            
            $data['wp_user_id'] = $wp_user_id;
            
            // Queue welcome email
            $this->pending_setup_email = array(
                'email' => $data['email'],
                'role' => $data['role'],
                'first_name' => $data['first_name'],
                'saw_user_id' => null,
            );
        } else {
            // EDIT: Update existing user
            $existing_user = $this->model->get_by_id($data['id']);
            
            if (!$existing_user) {
                return new WP_Error('user_not_found', 'Uživatel nenalezen');
            }
            
            // Clear departments if role changes from manager
            if ($existing_user['role'] === 'manager' && $data['role'] !== 'manager') {
                $data['branch_id'] = null;
                
                global $wpdb;
                $wpdb->delete(
                    $wpdb->prefix . 'saw_user_departments',
                    array('user_id' => $data['id']),
                    array('%d')
                );
                
                $this->pending_departments = array();
            }
            
            // Clear branch if role changes to admin
            if (in_array($existing_user['role'], array('super_manager', 'terminal')) && $data['role'] === 'admin') {
                $data['branch_id'] = null;
            }
            
            // Update WordPress role if changed
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
        
        // Hash PIN for terminal users
        if ($data['role'] === 'terminal' && !empty($data['pin'])) {
            if (!preg_match('/^\d{4}$/', $data['pin'])) {
                return new WP_Error('invalid_pin', 'PIN musí být 4 číslice');
            }
            $data['pin'] = password_hash($data['pin'], PASSWORD_BCRYPT);
        } else {
            $data['pin'] = null;
        }
        
        // Store departments for later processing
        if (isset($data['department_ids'])) {
            if (is_array($data['department_ids'])) {
                $this->pending_departments = array_filter($data['department_ids']);
            } else {
                $this->pending_departments = array();
            }
            
            // Validace: Manager MUSÍ mít alespoň jedno oddělení
            if ($data['role'] === 'manager' && empty($this->pending_departments)) {
                return new WP_Error('departments_required', 'Manager musí mít přiřazeno alespoň jedno oddělení');
            }
        }
        
        // Remove fields that shouldn't be saved directly
        unset($data['department_ids']);
        unset($data['password']);
        
        return $data;
    }
    
    /**
     * After save hook - handles department assignments and welcome email
     */
    protected function after_save($user_id) {
        // Handle department assignments (multiselect)
        if ($this->pending_departments !== null) {
            global $wpdb;
            
            // Delete old assignments
            $wpdb->delete(
                $wpdb->prefix . 'saw_user_departments',
                array('user_id' => $user_id),
                array('%d')
            );
            
            // Insert new assignments (pokud jsou nějaká vybraná oddělení)
            if (!empty($this->pending_departments)) {
                foreach ($this->pending_departments as $dept_id) {
                    $wpdb->insert(
                        $wpdb->prefix . 'saw_user_departments',
                        array(
                            'user_id' => $user_id,
                            'department_id' => (int)$dept_id
                        ),
                        array('%d', '%d')
                    );
                }
            }
        }
        
        // Send welcome email with setup link
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
                        SAW_Logger::error('[Users] Failed to create setup token for user: ' . $user_id);
                    }
                }
            }
        }
        
        // Audit log
        if (class_exists('SAW_Audit')) {
            $user = $this->model->get_by_id($user_id);
            
            SAW_Audit::log(array(
                'action' => empty($this->pending_setup_email) ? 'user_updated' : 'user_created',
                'entity_type' => 'user',
                'entity_id' => $user_id,
                'customer_id' => $user['customer_id'] ?? null,
                'details' => json_encode(array(
                    'role' => $this->pending_setup_email['role'] ?? 'updated',
                    'email' => $this->pending_setup_email['email'] ?? '',
                ))
            ));
        }
    }
    
    /**
     * Before delete hook - deletes WP user and department assignments
     */
    protected function before_delete($id) {
        $user = $this->model->get_by_id($id);
        
        if (!$user) {
            return true;
        }
        
        // Prevent self-deletion
        if (!empty($user['wp_user_id'])) {
            $wp_user_id = intval($user['wp_user_id']);
            
            if ($wp_user_id === get_current_user_id()) {
                return new WP_Error('delete_self', 'Nemůžete smazat sami sebe');
            }
            
            // Delete WordPress user
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            $deleted = wp_delete_user($wp_user_id);
            
            if (is_wp_error($deleted)) {
                return new WP_Error('wp_delete_failed', 'Chyba při mazání WordPress uživatele: ' . $deleted->get_error_message());
            }
        }
        
        // Delete department assignments
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'saw_user_departments',
            array('user_id' => $id),
            array('%d')
        );
        
        return true;
    }
    
    /**
     * Map SAW role to WordPress role
     */
    private function map_saw_to_wp_role($saw_role) {
        $mapping = array(
            'super_admin' => 'administrator',
            'admin' => 'saw_admin',
            'super_manager' => 'saw_super_manager',
            'manager' => 'saw_manager',
            'terminal' => 'saw_terminal'
        );
        
        return $mapping[$saw_role] ?? 'saw_admin';
    }
    
    /**
     * AJAX: Get departments by branch
     * 
     * Called by universal AJAX handler: saw_get_departments_by_branch
     * Pattern: saw_{method}_{module} -> ajax_{method}()
     */
    public function ajax_get_departments_by_branch() {
        // Verify nonce
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        
        if (!$branch_id) {
            wp_send_json_error(array('message' => 'Chybí ID pobočky'));
            return;
        }
        
        // Security: Ověření, že branch patří k customer_id z kontextu
        $customer_id = SAW_Context::get_customer_id();
        
        global $wpdb;
        
        // Nejdřív ověříme, že branch patří k customer_id
        $branch_check = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE id = %d AND customer_id = %d AND is_active = 1",
            $wpdb->prefix . 'saw_branches',
            $branch_id,
            $customer_id
        ));
        
        if (!$branch_check) {
            wp_send_json_error(array('message' => 'Nemáte přístup k této pobočce'));
            return;
        }
        
        // Načteme departments pro danou pobočku
        $departments = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, department_number 
             FROM %i 
             WHERE branch_id = %d AND is_active = 1 
             ORDER BY name ASC",
            $wpdb->prefix . 'saw_departments',
            $branch_id
        ), ARRAY_A);
        
        wp_send_json_success(array('departments' => $departments));
    }
}