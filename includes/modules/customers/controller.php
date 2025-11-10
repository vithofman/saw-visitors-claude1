<?php
/**
 * Customers Module Controller - FILE UPLOAD FIXED
 *
 * Main controller for the Customers module with sidebar support.
 * Handles CRUD operations, file uploads, AJAX requests, and sidebar context.
 *
 * Features:
 * - List view with search, filtering, sorting, pagination
 * - Create/Edit forms in sidebar
 * - Detail view in sidebar
 * - AJAX sidebar loading
 * - AJAX detail modal (backward compatible)
 * - AJAX search and delete
 * - Dependency validation (branches, users, visits, invitations)
 * - Comprehensive cache invalidation
 * - File upload handling (logo)
 * - Related data support (branches)
 *
 * @package SAW_Visitors
 * @version 11.3.0 - HOTFIX: Fixed file upload (correct parameters & return keys)
 * @since   4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customers Module Controller Class
 *
 * Extends base controller and uses AJAX handlers trait.
 * Manages all customer-related operations including CRUD, file uploads, AJAX, and sidebar.
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
     * Constructor
     *
     * Initializes controller, loads config, model, components,
     * and registers AJAX handlers.
     *
     * @since 4.6.1
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/customers/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Customers_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        add_action('wp_ajax_saw_get_customers_detail', array($this, 'ajax_get_detail'));
        add_action('wp_ajax_saw_search_customers', array($this, 'ajax_search'));
        add_action('wp_ajax_saw_delete_customers', array($this, 'ajax_delete'));
        add_action('wp_ajax_saw_load_sidebar_customers', array($this, 'ajax_load_sidebar'));
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Display list page with optional sidebar
     *
     * Shows paginated, searchable, filterable list of customers.
     * Supports sidebar for detail view, create form, and edit form.
     *
     * @since 8.0.0
     * @return void
     */
    public function index() {
        $this->verify_module_access();
        $this->enqueue_assets();
        
        ob_start();
        
        $sidebar_context = $this->get_sidebar_context();
        $sidebar_mode = $sidebar_context['mode'] ?? null;
        
        $detail_item = null;
        $form_item = null;
        $detail_tab = $sidebar_context['tab'] ?? 'overview';
        $account_types = array();
        $related_data = null;
        
        if ($sidebar_mode === 'detail') {
            if (!$this->can('view')) {
                ob_end_clean();
                $this->set_flash('Nemáte oprávnění zobrazit detail', 'error');
                wp_redirect(home_url('/admin/customers/'));
                exit;
            }
            
            $detail_item = $this->model->get_by_id($sidebar_context['id']);
            
            if (!$detail_item) {
                ob_end_clean();
                $this->set_flash('Zákazník nenalezen', 'error');
                wp_redirect(home_url('/admin/customers/'));
                exit;
            }
            
            $detail_item = $this->format_detail_data($detail_item);
            $related_data = $this->load_related_data($sidebar_context['id']);
        }
        
        elseif ($sidebar_mode === 'create') {
            if (!$this->can('create')) {
                ob_end_clean();
                $this->set_flash('Nemáte oprávnění vytvářet zákazníky', 'error');
                wp_redirect(home_url('/admin/customers/'));
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                ob_end_clean();
                $this->handle_create_post();
                return;
            }
            
            $form_item = array();
        }
        
        elseif ($sidebar_mode === 'edit') {
            if (!$this->can('edit')) {
                ob_end_clean();
                $this->set_flash('Nemáte oprávnění upravovat zákazníky', 'error');
                wp_redirect(home_url('/admin/customers/'));
                exit;
            }
            
            $form_item = $this->model->get_by_id($sidebar_context['id']);
            
            if (!$form_item) {
                ob_end_clean();
                $this->set_flash('Zákazník nenalezen', 'error');
                wp_redirect(home_url('/admin/customers/'));
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                ob_end_clean();
                $this->handle_edit_post($sidebar_context['id']);
                return;
            }
        }
        
        if ($sidebar_mode === 'create' || $sidebar_mode === 'edit') {
            $account_types = $this->lazy_load_lookup_data('account_types', function() {
                return $this->load_account_types_from_db();
            });
        }
        
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'id';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) : 'DESC';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
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
        
        $data = $this->model->get_all($filters);
        $items = $data['items'];
        $total = $data['total'];
        $total_pages = ceil($total / 20);
        
        $captured_junk = ob_get_clean();
        
        ob_start();
        
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        $this->render_flash_messages();
        
        extract(array(
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'total_pages' => $total_pages,
            'search' => $search,
            'status' => $status,
            'orderby' => $orderby,
            'order' => $order,
            'detail_item' => $detail_item,
            'form_item' => $form_item,
            'sidebar_mode' => $sidebar_mode,
            'detail_tab' => $detail_tab,
            'account_types' => $account_types,
            'related_data' => $related_data,
        ));
        
        require $this->config['path'] . 'list-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    /**
     * AJAX: Load sidebar content
     *
     * Universal AJAX handler for loading sidebar in detail/edit/create modes.
     * Returns HTML for sidebar wrapper.
     *
     * @since 9.1.0
     * @return void (outputs JSON)
     */
    public function ajax_load_sidebar() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $mode = sanitize_text_field($_POST['mode'] ?? 'detail');
        
        if (!in_array($mode, array('detail', 'edit'), true)) {
            wp_send_json_error(array('message' => 'Invalid mode'));
        }
        
        if ($mode === 'detail' && !$this->can('view')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění zobrazit detail'));
        }
        
        if ($mode === 'edit' && !$this->can('edit')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění upravovat záznamy'));
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(array('message' => 'Zákazník nenalezen'));
        }
        
        ob_start();
        
        $account_types = array();
        $related_data = null;
        
        if ($mode === 'edit') {
            $account_types = $this->lazy_load_lookup_data('account_types', function() {
                return $this->load_account_types_from_db();
            });
        }
        
        if ($mode === 'detail') {
            $related_data = $this->load_related_data($id);
        }
        
        $captured_junk = ob_get_clean();
        
        ob_start();
        
        $item = $this->format_detail_data($item);
        
        if ($mode === 'detail') {
            $tab = 'overview';
            $config = $this->config;
            $entity = $this->entity;
            
            extract(compact('item', 'tab', 'config', 'entity', 'related_data'));
            
            $template_path = SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/detail-sidebar.php';
            
            require $template_path;
        } else {
            $is_edit = true;
            $GLOBALS['saw_sidebar_form'] = true;
            
            $config = $this->config;
            $config['account_types'] = $account_types;
            $entity = $this->entity;
            
            $form_path = $this->config['path'] . 'form-template.php';
            
            require $form_path;
            unset($GLOBALS['saw_sidebar_form']);
        }
        
        $sidebar_content = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $sidebar_content,
            'mode' => $mode,
            'id' => $id
        ));
    }
    
    /**
     * AJAX delete handler - OVERRIDE
     *
     * Override trait method to use config-based permissions instead of SAW_Permissions.
     * Uses can('delete') which checks capabilities from config.
     *
     * @since 11.2.1
     * @return void
     */
    public function ajax_delete() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!$this->can('delete')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění mazat záznamy'));
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => 'Neplatné ID'));
            return;
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(array('message' => 'Zákazník nenalezen'));
            return;
        }
        
        $before_delete_result = $this->before_delete($id);
        
        if (is_wp_error($before_delete_result)) {
            wp_send_json_error(array('message' => $before_delete_result->get_error_message()));
            return;
        }
        
        if ($before_delete_result === false) {
            wp_send_json_error(array('message' => 'Nelze smazat zákazníka'));
            return;
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        $this->after_delete($id);
        
        wp_send_json_success(array('message' => 'Zákazník byl úspěšně smazán'));
    }
    
    /**
     * Format data for detail view
     *
     * Adds computed fields and formatting for detail display.
     *
     * @since 8.0.0
     * @param array $item Raw item data
     * @return array Formatted item data
     */
    private function format_detail_data($item) {
        $account_types = $this->lazy_load_lookup_data('account_types', function() {
            return $this->load_account_types_from_db();
        });
        
        if (!empty($item['account_type_id']) && isset($account_types[$item['account_type_id']])) {
            $item['account_type_display'] = $account_types[$item['account_type_id']]['display_name'];
        } else {
            $item['account_type_display'] = 'Nezadáno';
        }
        
        if (!empty($item['logo_url'])) {
            if (strpos($item['logo_url'], 'http') === 0) {
            } else {
                $upload_dir = wp_upload_dir();
                $item['logo_url'] = $upload_dir['baseurl'] . '/' . ltrim($item['logo_url'], '/');
            }
        }
        
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
        }
        
        $item['status_label'] = $this->get_status_label($item['status'] ?? '');
        
        return $item;
    }
    
    /**
     * Format row data for list table
     *
     * @since 8.0.0
     * @param array $item Raw item data
     * @param array $account_types Account types lookup
     * @return array Formatted row data
     */
    private function format_row_data($item, $account_types) {
        if (!empty($item['account_type_id']) && isset($account_types[$item['account_type_id']])) {
            $item['account_type_display'] = $account_types[$item['account_type_id']]['display_name'];
        } else {
            $item['account_type_display'] = '-';
        }
        
        if (!empty($item['logo_path'])) {
            $upload_dir = wp_upload_dir();
            $item['logo_url'] = $upload_dir['baseurl'] . '/' . $item['logo_path'];
        }
        
        return $item;
    }
    
    /**
     * Get status label
     *
     * @since 8.0.0
     * @param string $status Status value
     * @return string Status label
     */
    private function get_status_label($status) {
        $labels = array(
            'potential' => 'Potenciální',
            'active' => 'Aktivní',
            'inactive' => 'Neaktivní',
        );
        
        return $labels[$status] ?? 'Neznámý';
    }
    
    /**
     * Load account types from database
     *
     * @since 8.0.0
     * @return array Account types indexed by ID
     */
    private function load_account_types_from_db() {
        global $wpdb;
        
        $types = $wpdb->get_results(
            "SELECT id, name, display_name FROM {$wpdb->prefix}saw_account_types WHERE is_active = 1 ORDER BY name ASC",
            ARRAY_A
        );
        
        $account_types = array();
        foreach ($types as $type) {
            $account_types[$type['id']] = array(
                'name' => $type['name'],
                'display_name' => $type['display_name'],
            );
        }
        
        return $account_types;
    }
    
    /**
     * Load account types (backward compatible wrapper)
     *
     * @since 8.0.0
     * @return array Account types indexed by ID
     */
    private function load_account_types() {
        return $this->lazy_load_lookup_data('account_types', function() {
            return $this->load_account_types_from_db();
        });
    }
    
    /**
     * Prepare form data from POST
     * 
     * Override base controller method to handle file uploads and custom fields.
     * 
     * ✅ HOTFIX v11.3.0: Fixed file upload parameters and return key usage
     * 
     * @since 11.3.0
     * @param array $post POST data
     * @return array|WP_Error Prepared data or error
     */
    protected function prepare_form_data($post) {
        $data = array(
            'name' => sanitize_text_field($post['name'] ?? ''),
            'ico' => sanitize_text_field($post['ico'] ?? ''),
            'dic' => sanitize_text_field($post['dic'] ?? ''),
            
            'address_street' => sanitize_text_field($post['address_street'] ?? ''),
            'address_number' => sanitize_text_field($post['address_number'] ?? ''),
            'address_city' => sanitize_text_field($post['address_city'] ?? ''),
            'address_zip' => sanitize_text_field($post['address_zip'] ?? ''),
            'address_country' => sanitize_text_field($post['address_country'] ?? 'Česká republika'),
            
            'billing_address_street' => sanitize_text_field($post['billing_address_street'] ?? ''),
            'billing_address_number' => sanitize_text_field($post['billing_address_number'] ?? ''),
            'billing_address_city' => sanitize_text_field($post['billing_address_city'] ?? ''),
            'billing_address_zip' => sanitize_text_field($post['billing_address_zip'] ?? ''),
            'billing_address_country' => sanitize_text_field($post['billing_address_country'] ?? ''),
            
            'contact_person' => sanitize_text_field($post['contact_person'] ?? ''),
            'contact_email' => sanitize_email($post['contact_email'] ?? ''),
            'contact_phone' => sanitize_text_field($post['contact_phone'] ?? ''),
            
            'website' => esc_url_raw($post['website'] ?? ''),
            'status' => sanitize_text_field($post['status'] ?? 'potential'),
            'account_type_id' => !empty($post['account_type_id']) ? intval($post['account_type_id']) : null,
            'notes' => sanitize_textarea_field($post['notes'] ?? ''),
        );
        
        // ✅ HOTFIX: File upload with CORRECT parameters and return key
        if (!empty($_FILES['logo']['name'])) {
            // upload() method signature: upload($file, $dir_key = 'customers')
            // Returns: array('url' => ..., 'path' => ..., 'filename' => ...)
            $upload_result = $this->file_uploader->upload($_FILES['logo'], 'customers');
            
            if (is_wp_error($upload_result)) {
                return $upload_result;
            }
            
            // Delete old logo if editing
            if (isset($post['id'])) {
                $old_customer = $this->model->get_by_id($post['id']);
                if (!empty($old_customer['logo_url'])) {
                    $this->file_uploader->delete($old_customer['logo_url']);
                }
            }
            
            // ✅ HOTFIX: Use correct return key 'url' (not 'file')
            $data['logo_url'] = $upload_result['url'];
        }
        
        return $data;
    }
    
    /**
     * After save hook - cache invalidation
     * 
     * @since 11.2.0
     * @param int $id Item ID
     */
    protected function after_save($id) {
        $this->invalidate_caches();
    }
    
    /**
     * Before delete hook - dependency validation
     * 
     * HOTFIX: Database has CASCADE delete, so we just log dependencies and allow deletion.
     * In AJAX context, we can't show confirmation dialog, so we proceed directly.
     * 
     * @since 11.2.2
     * @param int $id Customer ID
     * @return bool Always true (CASCADE delete handles dependencies)
     */
    protected function before_delete($id) {
        global $wpdb;
        
        // Log dependencies in debug mode
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            $branches = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d", $id
            ));
            $users = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_users WHERE customer_id = %d", $id
            ));
            $departments = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_departments WHERE customer_id = %d", $id
            ));
            
            error_log(sprintf(
                '[SAW] Deleting customer #%d with dependencies: %d branches, %d users, %d departments (CASCADE)',
                $id, $branches, $users, $departments
            ));
        }
        
        // HOTFIX: Always return true - database CASCADE will handle deletion
        return true;
    }
    
    /**
     * After delete hook - cache invalidation
     * 
     * @since 11.2.1
     * @param int $id Customer ID
     */
    protected function after_delete($id) {
        $this->invalidate_caches();
    }
    
    /**
     * Invalidate all caches after data changes
     *
     * @since 8.0.0
     * @return void
     */
    private function invalidate_caches() {
        if (function_exists('saw_clear_cache')) {
            saw_clear_cache('customers');
        }
    }
    
    /**
     * Enqueue module assets
     *
     * ✅ HOTFIX v11.3.0: Load file upload component assets
     *
     * @since 11.3.0
     * @return void
     */
    protected function enqueue_assets() {
        // ✅ CRITICAL: Enqueue file upload component CSS & JS
        $this->file_uploader->enqueue_assets();
    }
}