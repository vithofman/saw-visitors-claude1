<?php
/**
 * Users Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     7.0.0 - External JS with waitFor pattern, priority 999 enqueue
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
    
    /** @var array|null Pending department assignments */
    private $pending_departments = null;
    
    /** @var array|null Pending setup email data */
    private $pending_setup_email = null;
    
    /** @var array Translation strings */
    private $translations = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/users/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Users_Model($this->config);
        
        // Initialize translations
        $this->init_translations();
        
        // ✅ Register standard AJAX handlers
        // NOTE: saw_get_departments_by_branch is registered via custom_ajax_actions in config.php
        add_action('wp_ajax_saw_get_users_detail',       array($this, 'ajax_get_detail'));
        add_action('wp_ajax_saw_search_users',           array($this, 'ajax_search'));
        add_action('wp_ajax_saw_delete_users',           array($this, 'ajax_delete'));
        add_action('wp_ajax_saw_load_sidebar_users',     array($this, 'ajax_load_sidebar'));
        add_action('wp_ajax_saw_get_adjacent_users',     array($this, 'ajax_get_adjacent_id'));
        
        // CRITICAL: Use priority 999 to ensure script is registered before wp_localize_script
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'), 999);
    }
    
    /**
     * Initialize translations
     */
    private function init_translations() {
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        
        $this->translations = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'users') 
            : array();
    }
    
    /**
     * Get translation
     * 
     * @param string $key Translation key
     * @param string $fallback Fallback text
     * @return string
     */
    private function tr($key, $fallback = null) {
        return $this->translations[$key] ?? $fallback ?? $key;
    }
    
    /**
     * Display list page with optional sidebar
     */
    public function index() {
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die($this->tr('error_no_view_permission', 'Nemáte oprávnění'), 403);
        }
        $this->render_list_view();
    }

    /**
     * Enqueue module assets
     */
    /**
     * Enqueue module assets
     * 
     * CRITICAL: Use priority 999 to ensure saw-module-users is already registered
     * before we call wp_localize_script (same pattern as visits module)
     * 
     * @since 7.0.0
     */
    public function enqueue_assets() {
        // Enqueue dashicons
        wp_enqueue_style('dashicons');
        
        // Enqueue module assets FIRST
        if (class_exists('SAW_Asset_Loader')) {
            SAW_Asset_Loader::enqueue_module('users');
        }
        
        // Detect user ID using multiple fallback methods (like visits module)
        $user_id = $this->detect_user_id();
        
        // Load existing departments from database if we have a user_id
        $existing_departments = array();
        if ($user_id > 0) {
            global $wpdb;
            $existing_departments = $wpdb->get_col($wpdb->prepare(
                "SELECT department_id FROM {$wpdb->prefix}saw_user_departments WHERE user_id = %d",
                $user_id
            ));
            $existing_departments = array_map('intval', $existing_departments);
        }
        
        // CRITICAL: Use sawUsersData to pass data to external JS (like sawVisitsData)
        $script_handle = 'saw-module-users';
        
        if (wp_script_is($script_handle, 'registered') || wp_script_is($script_handle, 'enqueued')) {
            wp_localize_script($script_handle, 'sawUsersData', array(
                'existing_departments' => $existing_departments,
                'user_id' => $user_id,
                'debug' => defined('WP_DEBUG') && WP_DEBUG
            ));
        }
    }
    
    /**
     * Detect user ID from multiple sources with fallbacks
     * 
     * @since 7.0.0
     * @return int User ID or 0 if not found
     */
    private function detect_user_id() {
        $user_id = 0;
        
        // Method 1: Try get_sidebar_context() (primary - set by router)
        $context = $this->get_sidebar_context();
        if (!empty($context['id']) && ($context['mode'] === 'edit' || $context['mode'] === 'detail')) {
            $user_id = intval($context['id']);
        }
        
        // Method 2: Parse URL from REQUEST_URI as fallback
        if ($user_id === 0 && isset($_SERVER['REQUEST_URI'])) {
            $request_uri = sanitize_text_field($_SERVER['REQUEST_URI']);
            // Match patterns like /admin/users/41/edit or /admin/users/41/
            if (preg_match('#/admin/users/(\d+)(?:/|$)#', $request_uri, $matches)) {
                $user_id = intval($matches[1]);
            }
        }
        
        return $user_id;
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
            } elseif ($field_config['type'] === 'checkbox' || $field_config['type'] === 'boolean') {
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
     * 
     * ✅ FIXED: Cannot use parent:: with trait - duplicate formatting logic
     */
    protected function format_detail_data($item) {
        if (empty($item)) {
            return $item;
        }
        
        // Format dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        if (!empty($item['last_login'])) {
            $item['last_login_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['last_login']));
        }
        
        // Add branch info
        if (!empty($item['branch_id'])) {
            global $wpdb;
            $branch = $wpdb->get_row($wpdb->prepare(
                "SELECT name, code FROM %i WHERE id = %d",
                $wpdb->prefix . 'saw_branches',
                intval($item['branch_id'])
            ), ARRAY_A);
            
            if ($branch) {
                $item['branch_name'] = $branch['name'];
                $item['branch_code'] = $branch['code'] ?? '';
            }
        }
        
        // Add email from WordPress user
        if (!empty($item['wp_user_id'])) {
            $wp_user = get_userdata($item['wp_user_id']);
            if ($wp_user) {
                $item['email'] = $wp_user->user_email;
            }
        }
        
        // Add department count
        if (!empty($item['id'])) {
            global $wpdb;
            $item['department_count'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM %i WHERE user_id = %d",
                $wpdb->prefix . 'saw_user_departments',
                intval($item['id'])
            ));
        }
        
        return $item;
    }
    
    /**
     * Get header meta badges for detail sidebar
     * 
     * @param array $item User data
     * @return string HTML badges
     */
    public function get_detail_header_meta($item) {
        if (empty($item)) {
            return '';
        }
        
        $meta = array();
        
        // Role badge
        $role_labels = array(
            'super_admin' => $this->tr('role_super_admin', 'Super Admin'),
            'admin' => $this->tr('role_admin', 'Admin'),
            'super_manager' => $this->tr('role_super_manager', 'Super Manager'),
            'manager' => $this->tr('role_manager', 'Manager'),
            'terminal' => $this->tr('role_terminal', 'Terminál'),
        );
        
        $role_label = $role_labels[$item['role']] ?? $item['role'];
        $meta[] = '<span class="saw-role-badge saw-role-' . esc_attr($item['role']) . '">' . esc_html($role_label) . '</span>';
        
        // Branch badge
        if (!empty($item['branch_name'])) {
            $meta[] = '<span class="saw-badge-transparent saw-badge-info">🏢 ' . esc_html($item['branch_name']) . '</span>';
        }
        
        // Status badge
        if (!empty($item['is_active'])) {
            $meta[] = '<span class="saw-badge-transparent saw-badge-success">✓ ' . esc_html($this->tr('status_active', 'Aktivní')) . '</span>';
        } else {
            $meta[] = '<span class="saw-badge-transparent saw-badge-secondary">' . esc_html($this->tr('status_inactive', 'Neaktivní')) . '</span>';
        }
        
        return implode(' ', $meta);
    }
    
    /**
     * AJAX: Get adjacent ID for prev/next navigation
     */
    public function ajax_get_adjacent_id() {
        saw_verify_ajax_unified();
        
        if (!$this->can('view')) {
            wp_send_json_error(array('message' => $this->tr('error_no_view_permission', 'Nemáte oprávnění')));
        }
        
        $current_id = intval($_POST['id'] ?? 0);
        $direction = sanitize_text_field($_POST['direction'] ?? 'next');
        
        if (!$current_id || !in_array($direction, array('next', 'prev'))) {
            wp_send_json_error(array('message' => $this->tr('error_missing_id', 'Chybí ID')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_users';
        
        // Build query with context filters
        $where = array('1=1');
        $params = array();
        
        $customer_id = SAW_Context::get_customer_id();
        $branch_id = SAW_Context::get_branch_id();
        
        if ($customer_id) {
            $where[] = 'customer_id = %d';
            $params[] = $customer_id;
        }
        if ($branch_id) {
            $where[] = '(branch_id = %d OR branch_id IS NULL)';
            $params[] = $branch_id;
        }
        
        $sql = "SELECT id FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY last_name ASC, first_name ASC, id ASC";
        $ids = $params ? $wpdb->get_col($wpdb->prepare($sql, $params)) : $wpdb->get_col($sql);
        
        if (empty($ids)) {
            wp_send_json_error(array('message' => $this->tr('error_no_records', 'Žádní uživatelé')));
        }
        
        $ids = array_map('intval', $ids);
        $current_index = array_search($current_id, $ids, true);
        
        if ($current_index === false) {
            wp_send_json_error(array('message' => $this->tr('error_not_in_list', 'Nenalezeno')));
        }
        
        // Circular navigation
        $adjacent_index = $direction === 'next' 
            ? ($current_index + 1) % count($ids)
            : ($current_index - 1 + count($ids)) % count($ids);
        
        $adjacent_id = $ids[$adjacent_index];
        
        wp_send_json_success(array(
            'id' => $adjacent_id,
            'url' => home_url('/admin/users/' . $adjacent_id . '/'),
        ));
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
                    return new WP_Error('no_customer', $this->tr('error_database', 'Customer ID is required'));
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
                return new WP_Error('email_exists', $this->tr('error_email_exists', 'Email je již používán'));
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
                return new WP_Error('wp_user_error', $this->tr('error_database', 'Chyba při vytváření uživatele') . ': ' . $wp_user_id->get_error_message());
            }
            
            if (!$wp_user_id || !is_numeric($wp_user_id)) {
                return new WP_Error('wp_user_invalid', $this->tr('error_database', 'Vytvoření uživatele selhalo'));
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
                return new WP_Error('user_not_found', $this->tr('error_not_found', 'Uživatel nenalezen'));
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
                return new WP_Error('invalid_pin', $this->tr('error_pin_invalid', 'PIN musí být 4 číslice'));
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
            
            // Validation: Manager MUST have at least one department
            if ($data['role'] === 'manager' && empty($this->pending_departments)) {
                return new WP_Error('departments_required', $this->tr('hint_departments_visible', 'Manager musí mít přiřazeno alespoň jedno oddělení'));
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
            
            // Insert new assignments
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
                        error_log('[Users] Failed to create setup token for user: ' . $user_id);
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
        
        // Reset pending data
        $this->pending_departments = null;
        $this->pending_setup_email = null;
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
                return new WP_Error('delete_self', $this->tr('error_cannot_delete_self', 'Nemůžete smazat sami sebe'));
            }
            
            // Check if user is in use
            if ($this->model->is_used_in_system($id)) {
                return new WP_Error('user_in_use', $this->tr('error_user_in_use', 'Uživatel má záznamy v systému a nelze smazat'));
            }
            
            // Delete WordPress user
            require_once(ABSPATH . 'wp-admin/includes/user.php');
            $deleted = wp_delete_user($wp_user_id);
            
            if (is_wp_error($deleted)) {
                return new WP_Error('wp_delete_failed', $this->tr('error_database', 'Chyba při mazání'));
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
     * 
     * @param string $saw_role SAW role name
     * @return string WordPress role name
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
     */
    public function ajax_get_departments_by_branch() {
        // ✅ SPRÁVNĚ: Unified nonce verification
        saw_verify_ajax_unified();
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        
        if (!$branch_id) {
            wp_send_json_error(array('message' => 'Chybí ID pobočky'));
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_departments';
        
        $departments = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, department_number 
             FROM {$table}
             WHERE branch_id = %d AND is_active = 1 
             ORDER BY name ASC",
            $branch_id
        ), ARRAY_A);
        
        wp_send_json_success(array('departments' => $departments ?: array()));
    }
}