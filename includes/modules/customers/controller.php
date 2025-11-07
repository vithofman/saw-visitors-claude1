<?php
/**
 * Customers Module Controller - COMPLETE VERSION
 *
 * Main controller for the Customers module.
 * Handles CRUD operations, file uploads, AJAX requests, and cache management.
 *
 * Features:
 * - List view with search, filtering, sorting, pagination
 * - Create/Edit forms with logo upload and color picker
 * - AJAX detail modal
 * - AJAX search and delete
 * - Dependency validation (branches, users, visits, invitations)
 * - Comprehensive cache invalidation
 * - File upload handling (logo)
 *
 * @package SAW_Visitors
 * @version 3.1.0 - FIXED: Cache invalidation for modal + list
 * @since   4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customers Module Controller Class
 *
 * Extends base controller and uses AJAX handlers trait.
 * Manages all customer-related operations including CRUD, file uploads, and AJAX.
 *
 * @since 4.6.1
 */
class SAW_Module_Customers_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    /**
     * File uploader instance
     *
     * @since 3.1.0
     * @var SAW_File_Uploader
     */
    private $file_uploader;
    
    /**
     * Color picker instance
     *
     * @since 3.1.0
     * @var SAW_Color_Picker
     */
    private $color_picker;
    
    /**
     * Constructor
     *
     * Initializes controller, loads config, model, components,
     * and registers AJAX handlers.
     *
     * @since 4.6.1
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/customers/';
        
        // Load config
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        // Load model
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Customers_Model($this->config);
        
        // Load file uploader component
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        // Load color picker component
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/class-saw-color-picker.php';
        $this->color_picker = new SAW_Color_Picker();
        
        // Register AJAX handlers
        add_action('wp_ajax_saw_get_customers_detail', array($this, 'ajax_get_detail'));
        add_action('wp_ajax_saw_search_customers', array($this, 'ajax_search'));
        add_action('wp_ajax_saw_delete_customers', array($this, 'ajax_delete'));
        
        // Register asset enqueuing
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * AJAX: Get detail for modal
     *
     * Loads customer data and renders detail modal template.
     * Validates nonce, permissions, and item existence.
     *
     * @since 3.1.0
     * @return void
     */
    public function ajax_get_detail() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'saw_ajax_nonce')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CUSTOMERS AJAX] Nonce verification FAILED');
            }
            wp_send_json_error(array(
                'message' => 'Neplatný bezpečnostní token. Obnovte stránku a zkuste to znovu.'
            ));
            return;
        }
        
        // Check permissions
        if (!current_user_can('read')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CUSTOMERS AJAX] Permission check FAILED');
            }
            wp_send_json_error(array(
                'message' => 'Nedostatečná oprávnění k zobrazení detailu.'
            ));
            return;
        }
        
        // Validate ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CUSTOMERS AJAX] Invalid ID');
            }
            wp_send_json_error(array(
                'message' => 'Neplatné ID zákazníka.'
            ));
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[CUSTOMERS AJAX] Loading detail - ID: %d, User: %d',
                $id,
                get_current_user_id()
            ));
        }
        
        // Load customer data
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CUSTOMERS AJAX] Customer not found in DB - ID: ' . $id);
            }
            wp_send_json_error(array(
                'message' => 'Zákazník nebyl nalezen v databázi.'
            ));
            return;
        }
        
        // Format data for display
        $item = $this->format_detail_data($item);
        
        // Load template
        $template_path = $this->config['path'] . 'detail-modal-template.php';
        
        if (!file_exists($template_path)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CUSTOMERS AJAX] Template file NOT FOUND: ' . $template_path);
            }
            wp_send_json_error(array(
                'message' => 'Chyba: Template soubor nebyl nalezen. Kontaktujte administrátora.'
            ));
            return;
        }
        
        // Render template
        ob_start();
        
        try {
            require $template_path;
            $html = ob_get_clean();
            
            // Validate rendered content
            if (empty($html) || strlen($html) < 50) {
                throw new Exception('Template rendered empty or too short content');
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[CUSTOMERS AJAX] SUCCESS - ID: %d, HTML length: %d bytes',
                    $id,
                    strlen($html)
                ));
            }
            
            wp_send_json_success(array(
                'html' => $html,
                'item' => $item
            ));
            
        } catch (Exception $e) {
            ob_end_clean();
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[CUSTOMERS AJAX] Template rendering error: ' . $e->getMessage());
            }
            
            wp_send_json_error(array(
                'message' => 'Chyba při zobrazení detailu: ' . $e->getMessage()
            ));
        }
    }
    
    /**
     * Display list page
     *
     * Shows paginated, searchable, filterable list of customers.
     * Supports search, status filter, sorting, and pagination.
     *
     * @since 4.6.1
     * @return void
     */
    public function index() {
        $this->verify_module_access();
        $this->enqueue_assets();
        
        // Get and sanitize query parameters
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'id';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) : 'DESC';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Build filters
        $filters = array(
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => $page,
            'per_page' => 20,
        );
        
        if ($status !== '') {
            $filters['status'] = $status;
        }
        
        // Get data from model
        $data = $this->model->get_all($filters);
        $items = $data['items'];
        $total = $data['total'];
        $total_pages = ceil($total / 20);
        
        // Render content
        ob_start();
        
        // Inject module CSS if available
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
     * Display create form
     *
     * Shows form for creating new customer.
     * Handles POST request and saves data if valid.
     * Loads account types for dropdown selection.
     *
     * @since 4.6.1
     * @return void
     */
    public function create() {
        $this->verify_module_access();
        
        if (!$this->can('create')) {
            wp_die('Nemáte oprávnění vytvářet zákazníky');
        }
        
        $this->enqueue_assets();
        
        // Load account types for dropdown
        global $wpdb;
        $account_types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, display_name, color, price 
                 FROM %i 
                 WHERE is_active = 1 
                 ORDER BY sort_order ASC, display_name ASC",
                $wpdb->prefix . 'saw_account_types'
            ),
            ARRAY_A
        );
        
        $item = array();
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_nonce'], 'saw_customers_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            $data = $this->prepare_form_data($_POST);
            $data = $this->before_save($data);
            
            if (is_wp_error($data)) {
                $this->set_flash($data->get_error_message(), 'error');
                $item = $data;
            } else {
                $result = $this->model->create($data);
                
                if (is_wp_error($result)) {
                    $this->set_flash($result->get_error_message(), 'error');
                    $item = $data;
                } else {
                    $this->after_save($result);
                    $this->set_flash('Zákazník byl úspěšně vytvořen', 'success');
                    $this->redirect(home_url('/admin/settings/customers/'));
                }
            }
        }
        
        // Render form
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
        
        $this->render_with_layout($content, 'Nový zákazník');
    }
    
    /**
     * Display edit form
     *
     * Shows form for editing existing customer.
     * Handles POST request and updates data if valid.
     * Loads account types and performs reverse field mapping.
     *
     * @since 4.6.1
     * @param int $id Customer ID
     * @return void
     */
    public function edit($id) {
        $this->verify_module_access();
        
        if (!$this->can('edit')) {
            wp_die('Nemáte oprávnění upravovat zákazníky');
        }
        
        $this->enqueue_assets();
        
        // Load account types for dropdown
        global $wpdb;
        $account_types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, display_name, color, price 
                 FROM %i 
                 WHERE is_active = 1 
                 ORDER BY sort_order ASC, display_name ASC",
                $wpdb->prefix . 'saw_account_types'
            ),
            ARRAY_A
        );
        
        // Load customer data
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_die('Zákazník nenalezen');
        }
        
        // Reverse mapping: subscription_type → account_type_id
        // Database has 'subscription_type' column, but form expects 'account_type_id'
        if (isset($item['subscription_type'])) {
            $item['account_type_id'] = $item['subscription_type'];
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[CUSTOMERS] Reverse mapping for edit: subscription_type (%s) → account_type_id',
                    $item['subscription_type'] ?? 'NULL'
                ));
            }
        }
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saw_nonce'])) {
            if (!wp_verify_nonce($_POST['saw_nonce'], 'saw_customers_form')) {
                wp_die('Neplatný bezpečnostní token');
            }
            
            $data = $this->prepare_form_data($_POST);
            $data = $this->before_save($data);
            
            if (is_wp_error($data)) {
                $this->set_flash($data->get_error_message(), 'error');
            } else {
                $result = $this->model->update($id, $data);
                
                if (is_wp_error($result)) {
                    $this->set_flash($result->get_error_message(), 'error');
                } else {
                    $this->after_save($id);
                    $this->set_flash('Zákazník byl úspěšně aktualizován', 'success');
                    $this->redirect(home_url('/admin/settings/customers/'));
                }
            }
            
            // Reload item after save
            $item = $this->model->get_by_id($id);
        }
        
        // Render form
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
        
        $this->render_with_layout($content, 'Upravit zákazníka');
    }
    
    /**
     * Prepare form data for save
     *
     * Sanitizes and prepares POST data based on field configuration.
     * Performs field mapping: account_type_id → subscription_type
     *
     * @since 4.6.1
     * @param array $post POST data
     * @return array Prepared data
     */
    private function prepare_form_data($post) {
        $data = array();
        
        // Process each configured field
        foreach ($this->config['fields'] as $field_name => $field_config) {
            if (isset($post[$field_name])) {
                $value = $post[$field_name];
                
                // Apply sanitization if configured
                if (isset($field_config['sanitize']) && is_callable($field_config['sanitize'])) {
                    $value = call_user_func($field_config['sanitize'], $value);
                }
                
                $data[$field_name] = $value;
            } elseif ($field_config['type'] === 'checkbox') {
                $data[$field_name] = 0;
            }
        }
        
        // Add ID if present
        if (isset($post['id'])) {
            $data['id'] = intval($post['id']);
        }
        
        // Field mapping: account_type_id → subscription_type
        // Form sends 'account_type_id', but database has 'subscription_type' column
        if (isset($data['account_type_id'])) {
            $data['subscription_type'] = $data['account_type_id'];
            unset($data['account_type_id']); // Remove original key
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[CUSTOMERS] Field mapping: account_type_id (%s) → subscription_type',
                    $data['subscription_type'] ?? 'NULL'
                ));
            }
        }
        
        return $data;
    }
    
    /**
     * Enqueue assets
     *
     * Enqueues file uploader and color picker assets.
     *
     * @since 3.1.0
     * @return void
     */
    public function enqueue_assets() {
        $this->file_uploader->enqueue_assets();
        $this->color_picker->enqueue_assets();
    }
    
    /**
     * Before save hook
     *
     * Handles file uploads and removals before data is saved.
     * Deletes old logo if replaced or removed.
     *
     * @since 4.6.1
     * @param array $data Data to be saved
     * @return array|WP_Error Modified data or error
     */
    protected function before_save($data) {
        // Handle logo removal
        if ($this->file_uploader->should_remove_file('logo')) {
            if (!empty($data['id'])) {
                $existing = $this->model->get_by_id($data['id']);
                if (!empty($existing['logo_url'])) {
                    $this->file_uploader->delete($existing['logo_url']);
                }
            }
            $data['logo_url'] = '';
        }
        
        // Handle logo upload
        if (!empty($_FILES['logo']['name'])) {
            $upload = $this->file_uploader->upload($_FILES['logo'], 'customers');
            
            if (is_wp_error($upload)) {
                wp_die($upload->get_error_message());
            }
            
            $data['logo_url'] = $upload['url'];
            
            // Delete old logo if updating
            if (!empty($data['id'])) {
                $existing = $this->model->get_by_id($data['id']);
                if (!empty($existing['logo_url']) && $existing['logo_url'] !== $data['logo_url']) {
                    $this->file_uploader->delete($existing['logo_url']);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * After save hook
     *
     * Clears all caches after successful save.
     * Invalidates transients, SAW_Cache, and WordPress object cache.
     *
     * @since 3.1.0
     * @param int $id Customer ID
     * @return void
     */
    protected function after_save($id) {
        // Invalidate transient caches
        delete_transient('customers_list');
        delete_transient('customers_for_switcher');
        delete_transient(sprintf('customers_item_%d', $id));
        
        // Invalidate SAW_Cache if available
        if (class_exists('SAW_Cache')) {
            SAW_Cache::forget(sprintf('customers_item_%d', $id));
            SAW_Cache::forget_pattern('customers_*');
        }
        
        // Invalidate WordPress object cache
        wp_cache_delete($id, 'saw_customers');
        wp_cache_delete('saw_customers_all', 'saw_customers');
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[CUSTOMERS] Cache invalidated for ID: %d', $id));
        }
    }
    
    /**
     * Before delete hook
     *
     * Checks for dependencies before allowing deletion.
     * Prevents deletion if customer has branches, users, visits, or invitations.
     *
     * @since 4.6.1
     * @param int $id Customer ID
     * @return bool|WP_Error True if can delete, WP_Error if dependencies exist
     */
    protected function before_delete($id) {
        global $wpdb;
        
        // Check for branches
        $branches_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE customer_id = %d",
            $wpdb->prefix . 'saw_branches',
            $id
        ));
        
        if ($branches_count > 0) {
            return new WP_Error(
                'customer_has_branches',
                sprintf('Zákazníka nelze smazat. Má %d poboček. Nejprve je smažte.', $branches_count)
            );
        }
        
        // Check for users
        $users_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE customer_id = %d",
            $wpdb->prefix . 'saw_users',
            $id
        ));
        
        if ($users_count > 0) {
            return new WP_Error(
                'customer_has_users',
                sprintf('Zákazníka nelze smazat. Má %d uživatelů. Nejprve je smažte.', $users_count)
            );
        }
        
        // Check for visits
        $visits_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE customer_id = %d",
            $wpdb->prefix . 'saw_visits',
            $id
        ));
        
        if ($visits_count > 0) {
            return new WP_Error(
                'customer_has_visits',
                sprintf('Zákazníka nelze smazat. Má %d návštěv v historii.', $visits_count)
            );
        }
        
        // Check for invitations
        $invitations_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM %i WHERE customer_id = %d",
            $wpdb->prefix . 'saw_invitations',
            $id
        ));
        
        if ($invitations_count > 0) {
            return new WP_Error(
                'customer_has_invitations',
                sprintf('Zákazníka nelze smazat. Má %d pozvánek.', $invitations_count)
            );
        }
        
        return true;
    }
    
    /**
     * After delete hook
     *
     * Cleanup operations after successful deletion.
     * Deletes logo file and clears all caches.
     *
     * @since 4.6.1
     * @param int $id Customer ID
     * @return void
     */
    protected function after_delete($id) {
        // Delete logo file if exists
        $customer = $this->model->get_by_id($id);
        if (!empty($customer['logo_url'])) {
            $this->file_uploader->delete($customer['logo_url']);
        }
        
        // Full cache cleanup
        delete_transient('customers_list');
        delete_transient('customers_for_switcher');
        delete_transient(sprintf('customers_item_%d', $id));
        
        if (class_exists('SAW_Cache')) {
            SAW_Cache::forget(sprintf('customers_item_%d', $id));
            SAW_Cache::forget_pattern('customers_*');
        }
        
        wp_cache_delete($id, 'saw_customers');
        wp_cache_delete('saw_customers_all', 'saw_customers');
    }
    
    /**
     * Format detail data for modal
     *
     * Formats dates, URLs, status badges, and colors for display.
     *
     * @since 3.1.0
     * @param array $item Raw customer data
     * @return array Formatted customer data
     */
    protected function format_detail_data($item) {
        // Format dates
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        // Format logo URL
        if (!empty($item['logo_url'])) {
            if (strpos($item['logo_url'], 'http') === 0) {
                $item['logo_url_full'] = $item['logo_url'];
            } else {
                $upload_dir = wp_upload_dir();
                $item['logo_url_full'] = $upload_dir['baseurl'] . '/' . ltrim($item['logo_url'], '/');
            }
        } else {
            $item['logo_url_full'] = '';
        }
        
        // Format status
        $item['status_label'] = ucfirst($item['status'] ?? 'active');
        $item['status_badge_class'] = ($item['status'] ?? 'active') === 'active' 
            ? 'saw-badge-success' 
            : 'saw-badge-secondary';
        
        // Ensure colors are properly formatted
        if (!empty($item['primary_color']) && strpos($item['primary_color'], '#') !== 0) {
            $item['primary_color'] = '#' . $item['primary_color'];
        }
        
        if (!empty($item['secondary_color']) && strpos($item['secondary_color'], '#') !== 0) {
            $item['secondary_color'] = '#' . $item['secondary_color'];
        }
        
        return $item;
    }
}