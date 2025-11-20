<?php
/**
 * AJAX Registry - Centralized AJAX Management
 *
 * Centralizes registration of all AJAX handlers for the plugin.
 * Handles module AJAX, component AJAX, and custom AJAX endpoints.
 *
 * @package    SAW_Visitors
 * @subpackage Core
 * @since      5.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * AJAX Registry Class
 *
 * Manages all AJAX handler registration for modules, components, and custom endpoints.
 *
 * @since 5.0.0
 */
class SAW_AJAX_Registry {
    
    /**
     * Initialize AJAX registry
     *
     * Registers all AJAX handlers for components, modules, and custom endpoints.
     *
     * @since 5.0.0
     * @return void
     */
    public function init() {
        $this->register_component_ajax();
        $this->register_module_ajax();
        $this->register_custom_ajax();
    }
    
    /**
     * Register component AJAX handlers
     *
     * Registers AJAX handlers for global components that need to work
     * across all modules (customer switcher, branch switcher, etc.).
     *
     * @since 5.0.0
     * @return void
     */
    private function register_component_ajax() {
        $components = [
            'SAW_Component_Customer_Switcher',
            'SAW_Component_Branch_Switcher',
            'SAW_Component_Language_Switcher',
            'SAW_Component_Select_Create',
            'SAW_Component_File_Upload',
        ];
        
        foreach ($components as $class_name) {
            if (class_exists($class_name) && method_exists($class_name, 'register_ajax_handlers')) {
                call_user_func([$class_name, 'register_ajax_handlers']);
            }
        }
    }
    
    /**
     * Register module AJAX handlers
     *
     * Registers universal AJAX handlers for all modules:
     * - saw_load_sidebar_{module}
     * - saw_delete_{module}
     * - saw_create_{module}
     * - saw_edit_{module}
     * - Custom actions from module config
     *
     * @since 5.0.0
     * @return void
     */
    private function register_module_ajax() {
        // Get all modules
        if (!class_exists('SAW_Module_Loader')) {
            return;
        }
        
        $modules = SAW_Module_Loader::get_all();
        
        foreach ($modules as $slug => $config) {
            $entity = $config['entity'] ?? $slug;
            
            // Universal CRUD handlers
            add_action("wp_ajax_saw_load_sidebar_{$slug}", function() use ($slug) {
                $this->dispatch($slug, 'ajax_load_sidebar');
            });
            
            add_action("wp_ajax_saw_delete_{$slug}", function() use ($slug) {
                $this->dispatch($slug, 'ajax_delete');
            });
            
            add_action("wp_ajax_saw_create_{$slug}", function() use ($slug) {
                $this->dispatch($slug, 'ajax_create');
            });
            
            add_action("wp_ajax_saw_edit_{$slug}", function() use ($slug) {
                $this->dispatch($slug, 'ajax_edit');
            });
            
            // Legacy handlers using entity name
            add_action("wp_ajax_saw_get_{$entity}_detail", function() use ($slug) {
                $this->dispatch($slug, 'ajax_get_detail');
            });
            
            add_action("wp_ajax_saw_search_{$entity}", function() use ($slug) {
                $this->dispatch($slug, 'ajax_search');
            });
            
            add_action("wp_ajax_saw_delete_{$entity}", function() use ($slug) {
                $this->dispatch($slug, 'ajax_delete');
            });
            
            // Custom AJAX actions from module config
            if (!empty($config['custom_ajax_actions']) && is_array($config['custom_ajax_actions'])) {
                foreach ($config['custom_ajax_actions'] as $ajax_action => $controller_method) {
                    add_action("wp_ajax_{$ajax_action}", function() use ($slug, $controller_method) {
                        $this->dispatch($slug, $controller_method);
                    });
                }
            }
        }
        
        // Permissions module handlers (backward compatibility)
        add_action('wp_ajax_saw_update_permission', [$this, 'handle_permissions_update']);
        add_action('wp_ajax_saw_get_permissions_for_role', [$this, 'handle_permissions_get_for_role']);
        add_action('wp_ajax_saw_reset_permissions', [$this, 'handle_permissions_reset']);
    }
    
    /**
     * Register custom AJAX handlers
     *
     * Registers custom AJAX endpoints that don't fit the standard module pattern:
     * - Nested sidebar (for select-create component)
     * - Terminal search
     * - Companies merge
     * - Companies inline create
     *
     * @since 5.0.0
     * @return void
     */
    private function register_custom_ajax() {
        // Nested sidebar handler (for select-create component)
        add_action('wp_ajax_saw_load_nested_sidebar', [$this, 'handle_nested_sidebar']);
        
        // Terminal search handler
        add_action('wp_ajax_saw_terminal_search_by_name', [$this, 'handle_terminal_search']);
        add_action('wp_ajax_nopriv_saw_terminal_search_by_name', [$this, 'handle_terminal_search']);
        
        // Companies module custom handlers
        add_action('wp_ajax_saw_inline_create_companies', function() {
            $this->dispatch('companies', 'ajax_inline_create');
        });
        
        add_action('wp_ajax_saw_show_merge_modal', function() {
            $this->dispatch('companies', 'ajax_show_merge_modal');
        });
        
        add_action('wp_ajax_saw_merge_companies', function() {
            $this->dispatch('companies', 'ajax_merge_companies');
        });
    }
    
    /**
     * Dispatch AJAX request to module controller
     *
     * Universal dispatcher that loads the appropriate module controller
     * and calls the requested method.
     *
     * @since 5.0.0
     * @param string $slug   Module slug
     * @param string $method Controller method to call
     * @return void
     */
    private function dispatch($slug, $method) {
        // Load Base Controller if needed
        if (!class_exists('SAW_Base_Controller')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
        }
        
        // Get module config
        if (!class_exists('SAW_Module_Loader')) {
            wp_send_json_error(['message' => 'Module Loader not available']);
            return;
        }
        
        $config = SAW_Module_Loader::load($slug);
        
        if (!$config) {
            wp_send_json_error(['message' => "Module not found: {$slug}"]);
            return;
        }
        
        // Build controller class name
        $parts = explode('-', $slug);
        $parts = array_map('ucfirst', $parts);
        $class_name = implode('_', $parts);
        $controller_class = 'SAW_Module_' . $class_name . '_Controller';
        
        // Load controller file
        $controller_file = SAW_VISITORS_PLUGIN_DIR . "includes/modules/{$slug}/controller.php";
        
        if (!file_exists($controller_file)) {
            wp_send_json_error(['message' => "Controller file not found: {$slug}"]);
            return;
        }
        
        require_once $controller_file;
        
        if (!class_exists($controller_class)) {
            wp_send_json_error(['message' => "Controller class not found: {$controller_class}"]);
            return;
        }
        
        // Instantiate and call method
        try {
            $controller = new $controller_class();
            
            if (!method_exists($controller, $method)) {
                wp_send_json_error(['message' => "Method not found: {$method}"]);
                return;
            }
            
            $controller->$method();
        } catch (Throwable $e) {
            wp_send_json_error([
                'message' => 'Controller error',
                'error'   => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Handle nested sidebar AJAX request
     *
     * Loads a sidebar for a target module within another module's sidebar
     * (used by select-create component).
     *
     * @since 5.0.0
     * @return void
     */
    public function handle_nested_sidebar() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Nemáte oprávnění']);
            return;
        }
        
        $target_module = sanitize_key($_POST['target_module'] ?? '');
        
        if (empty($target_module)) {
            wp_send_json_error(['message' => 'Chybí cílový modul']);
            return;
        }
        
        $this->dispatch($target_module, 'ajax_load_nested_sidebar');
    }
    
    /**
     * Handle terminal search AJAX request
     *
     * Searches for visitors by name in terminal checkout.
     *
     * @since 5.0.0
     * @return void
     */
    public function handle_terminal_search() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_terminal_search')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }
        
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        $customer_id = intval($_POST['customer_id'] ?? 0);
        $branch_id = intval($_POST['branch_id'] ?? 0);
        
        if (empty($first_name) || empty($last_name) || !$customer_id || !$branch_id) {
            wp_send_json_error(['message' => 'Invalid parameters']);
            return;
        }
        
        global $wpdb;
        
        $visitors = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT 
                vis.id,
                vis.first_name,
                vis.last_name,
                v.company_id,
                c.name as company_name,
                dl.checked_in_at,
                dl.log_date,
                TIMESTAMPDIFF(MINUTE, dl.checked_in_at, NOW()) as minutes_inside
             FROM {$wpdb->prefix}saw_visitors vis
             INNER JOIN {$wpdb->prefix}saw_visits v ON vis.visit_id = v.id
             LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
             INNER JOIN {$wpdb->prefix}saw_visit_daily_logs dl ON vis.id = dl.visitor_id
             WHERE v.customer_id = %d
               AND v.branch_id = %d
               AND LOWER(vis.first_name) = LOWER(%s)
               AND LOWER(vis.last_name) = LOWER(%s)
               AND dl.checked_in_at IS NOT NULL
               AND dl.checked_out_at IS NULL
             ORDER BY dl.checked_in_at DESC",
            $customer_id,
            $branch_id,
            $first_name,
            $last_name
        ), ARRAY_A);
        
        if ($wpdb->last_error) {
            wp_send_json_error(['message' => 'Database error: ' . $wpdb->last_error]);
            return;
        }
        
        if (empty($visitors)) {
            wp_send_json_error(['message' => 'No visitors found']);
            return;
        }
        
        // Format results
        foreach ($visitors as &$visitor) {
            $visitor['checkin_time'] = date('H:i', strtotime($visitor['checked_in_at']));
            
            $log_date = date('Y-m-d', strtotime($visitor['log_date']));
            $today = current_time('Y-m-d');
            
            if ($log_date !== $today) {
                $visitor['checkin_date'] = date('d.m.Y', strtotime($log_date));
            }
        }
        
        wp_send_json_success([
            'visitors' => $visitors,
            'count'    => count($visitors)
        ]);
    }
    
    /**
     * Handle permissions update AJAX
     *
     * Updates a single permission for a role.
     *
     * @since 5.0.0
     * @return void
     */
    public function handle_permissions_update() {
        $controller = $this->get_module_controller('permissions');
        
        if (!$controller) {
            wp_send_json_error(['message' => __('Permissions controller not found', 'saw-visitors')]);
            return;
        }
        
        if (method_exists($controller, 'ajax_update_permission')) {
            $controller->ajax_update_permission();
        } else {
            wp_send_json_error(['message' => __('Method ajax_update_permission not found', 'saw-visitors')]);
        }
    }
    
    /**
     * Handle get permissions for role AJAX
     *
     * Retrieves all permissions for a specific role.
     *
     * @since 5.0.0
     * @return void
     */
    public function handle_permissions_get_for_role() {
        $controller = $this->get_module_controller('permissions');
        
        if (!$controller) {
            wp_send_json_error(['message' => __('Permissions controller not found', 'saw-visitors')]);
            return;
        }
        
        if (method_exists($controller, 'ajax_get_permissions_for_role')) {
            $controller->ajax_get_permissions_for_role();
        } else {
            wp_send_json_error(['message' => __('Method ajax_get_permissions_for_role not found', 'saw-visitors')]);
        }
    }
    
    /**
     * Handle reset permissions AJAX
     *
     * Resets permissions to default state.
     *
     * @since 5.0.0
     * @return void
     */
    public function handle_permissions_reset() {
        $controller = $this->get_module_controller('permissions');
        
        if (!$controller) {
            wp_send_json_error(['message' => __('Permissions controller not found', 'saw-visitors')]);
            return;
        }
        
        if (method_exists($controller, 'ajax_reset_permissions')) {
            $controller->ajax_reset_permissions();
        } else {
            wp_send_json_error(['message' => __('Method ajax_reset_permissions not found', 'saw-visitors')]);
        }
    }
    
    /**
     * Get module controller instance
     *
     * Helper method to instantiate a module controller.
     *
     * @since 5.0.0
     * @param string $slug Module slug
     * @return object|null Controller instance or null if not found
     */
    private function get_module_controller($slug) {
        $config = SAW_Module_Loader::load($slug);
        
        if (!$config) {
            return null;
        }
        
        $parts = explode('-', $slug);
        $parts = array_map('ucfirst', $parts);
        $class_name = implode('_', $parts);
        $controller_class = 'SAW_Module_' . $class_name . '_Controller';
        
        if (!class_exists($controller_class)) {
            return null;
        }
        
        return new $controller_class();
    }
}

