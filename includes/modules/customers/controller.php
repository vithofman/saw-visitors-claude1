<?php
/**
 * Customers Module Controller - SIDEBAR VERSION
 *
 * Main controller for the Customers module with sidebar support.
 * Handles CRUD operations, file uploads, AJAX requests, and sidebar context.
 *
 * Features:
 * - List view with search, filtering, sorting, pagination
 * - Create/Edit forms in sidebar
 * - Detail view in sidebar
 * - AJAX sidebar loading (NEW)
 * - AJAX detail modal (backward compatible)
 * - AJAX search and delete
 * - Dependency validation (branches, users, visits, invitations)
 * - Comprehensive cache invalidation
 * - File upload handling (logo)
 *
 * @package SAW_Visitors
 * @version 9.0.0 - AJAX SIDEBAR LOADING
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
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Customers_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/class-saw-color-picker.php';
        $this->color_picker = new SAW_Color_Picker();
        
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
        
        $sidebar_context = $this->get_sidebar_context();
        $sidebar_mode = $sidebar_context['mode'] ?? null;
        
        $detail_item = null;
        $form_item = null;
        $detail_tab = $sidebar_context['tab'] ?? 'overview';
        $account_types = array();
        
        if ($sidebar_mode === 'detail') {
            if (!$this->can('view')) {
                $this->set_flash('Nemáte oprávnění zobrazit detail', 'error');
                wp_redirect(home_url('/admin/settings/customers/'));
                exit;
            }
            
            $detail_item = $this->model->get_by_id($sidebar_context['id']);
            
            if (!$detail_item) {
                $this->set_flash('Zákazník nenalezen', 'error');
                wp_redirect(home_url('/admin/settings/customers/'));
                exit;
            }
            
            $detail_item = $this->format_detail_data($detail_item);
        }
        
        elseif ($sidebar_mode === 'create') {
            if (!$this->can('create')) {
                $this->set_flash('Nemáte oprávnění vytvářet zákazníky', 'error');
                wp_redirect(home_url('/admin/settings/customers/'));
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->handle_create_post();
                return;
            }
            
            $form_item = array();
        }
        
        elseif ($sidebar_mode === 'edit') {
            if (!$this->can('edit')) {
                $this->set_flash('Nemáte oprávnění upravovat zákazníky', 'error');
                wp_redirect(home_url('/admin/settings/customers/'));
                exit;
            }
            
            $form_item = $this->model->get_by_id($sidebar_context['id']);
            
            if (!$form_item) {
                $this->set_flash('Zákazník nenalezen', 'error');
                wp_redirect(home_url('/admin/settings/customers/'));
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->handle_edit_post($sidebar_context['id']);
                return;
            }
        }
        
        if ($sidebar_mode === 'create' || $sidebar_mode === 'edit') {
            $account_types = $this->load_account_types();
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
        
        ob_start();
        
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        $this->render_flash_messages();
        
        extract(array(
            'detail_item' => $detail_item,
            'form_item' => $form_item,
            'sidebar_mode' => $sidebar_mode,
            'detail_tab' => $detail_tab,
            'account_types' => $account_types,
        ));
        
        require $this->config['path'] . 'list-template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        $this->render_with_layout($content, $this->config['plural']);
    }
    
    /**
     * Handle create POST request
     *
     * @since 8.0.0
     * @return void (redirects)
     */
    protected function handle_create_post() {
        if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_customers_form')) {
            wp_die('Neplatný bezpečnostní token');
        }
        
        $data = $this->prepare_form_data($_POST);
        
        $data = $this->before_save($data);
        if (is_wp_error($data)) {
            $this->set_flash($data->get_error_message(), 'error');
            wp_redirect(home_url('/admin/settings/customers/create'));
            exit;
        }
        
        $validation = $this->model->validate($data);
        if (is_wp_error($validation)) {
            $errors = $validation->get_error_data();
            $this->set_flash(implode('<br>', $errors), 'error');
            wp_redirect(home_url('/admin/settings/customers/create'));
            exit;
        }
        
        $result = $this->model->create($data);
        
        if (is_wp_error($result)) {
            $this->set_flash($result->get_error_message(), 'error');
            wp_redirect(home_url('/admin/settings/customers/create'));
            exit;
        }
        
        $this->after_save($result);
        
        $this->set_flash('Zákazník byl úspěšně vytvořen', 'success');
        wp_redirect(home_url('/admin/settings/customers/' . $result . '/'));
        exit;
    }
    
    /**
     * Handle edit POST request
     *
     * @since 8.0.0
     * @param int $id Customer ID
     * @return void (redirects)
     */
    protected function handle_edit_post($id) {
        if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_customers_form')) {
            wp_die('Neplatný bezpečnostní token');
        }
        
        $data = $this->prepare_form_data($_POST);
        $data['id'] = $id;
        
        $data = $this->before_save($data);
        if (is_wp_error($data)) {
            $this->set_flash($data->get_error_message(), 'error');
            wp_redirect(home_url('/admin/settings/customers/' . $id . '/edit'));
            exit;
        }
        
        $validation = $this->model->validate($data, $id);
        if (is_wp_error($validation)) {
            $errors = $validation->get_error_data();
            $this->set_flash(implode('<br>', $errors), 'error');
            wp_redirect(home_url('/admin/settings/customers/' . $id . '/edit'));
            exit;
        }
        
        $result = $this->model->update($id, $data);
        
        if (is_wp_error($result)) {
            $this->set_flash($result->get_error_message(), 'error');
            wp_redirect(home_url('/admin/settings/customers/' . $id . '/edit'));
            exit;
        }
        
        $this->after_save($id);
        
        $this->set_flash('Zákazník byl úspěšně aktualizován', 'success');
        wp_redirect(home_url('/admin/settings/customers/' . $id . '/'));
        exit;
    }
    
    /**
     * Prepare form data from POST
     *
     * @since 8.0.0
     * @param array $post POST data
     * @return array Prepared data
     */
    protected function prepare_form_data($post) {
        $data = array(
            'name' => sanitize_text_field($post['name'] ?? ''),
            'ico' => sanitize_text_field($post['ico'] ?? ''),
            'dic' => sanitize_text_field($post['dic'] ?? ''),
            'status' => sanitize_text_field($post['status'] ?? 'active'),
            'primary_color' => sanitize_hex_color($post['primary_color'] ?? '#3b82f6'),
            'address_street' => sanitize_text_field($post['address_street'] ?? ''),
            'address_city' => sanitize_text_field($post['address_city'] ?? ''),
            'address_zip' => sanitize_text_field($post['address_zip'] ?? ''),
            'contact_email' => sanitize_email($post['contact_email'] ?? ''),
            'contact_phone' => sanitize_text_field($post['contact_phone'] ?? ''),
            'website' => esc_url_raw($post['website'] ?? ''),
            'notes' => sanitize_textarea_field($post['notes'] ?? ''),
        );
        
        if (isset($post['account_type_id']) && !empty($post['account_type_id'])) {
            $data['account_type_id'] = intval($post['account_type_id']);
        }
        
        return $data;
    }
    
    /**
     * Load account types for dropdown
     *
     * @since 9.0.0
     * @return array Account types
     */
    protected function load_account_types() {
        global $wpdb;
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, display_name, price FROM %i WHERE is_active = 1 ORDER BY sort_order ASC, display_name ASC",
                $wpdb->prefix . 'saw_account_types'
            ),
            ARRAY_A
        );
        
        return is_array($results) ? $results : array();
    }
    
    /**
     * Get sidebar context from router
     *
     * @since 8.0.0
     * @return array Sidebar context
     */
    protected function get_sidebar_context() {
        $context = get_query_var('saw_sidebar_context');
        
        if (empty($context) || !is_array($context)) {
            return array(
                'mode' => null,
                'id' => 0,
                'tab' => 'overview',
            );
        }
        
        return $context;
    }
    
    /**
     * Format detail data
     *
     * @since 8.0.0
     * @param array $item Raw item data
     * @return array Formatted item
     */
    protected function format_detail_data($item) {
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = mysql2date(get_option('date_format'), $item['created_at']);
        }
        
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = mysql2date(get_option('date_format'), $item['updated_at']);
        }
        
        return $item;
    }
    
    /**
     * Enqueue module assets
     *
     * @since 8.0.0
     * @return void
     */
    public function enqueue_assets() {
        $this->color_picker->enqueue_assets();
        $this->file_uploader->enqueue_assets();
    }
    
    /**
     * AJAX: Load sidebar content
     *
     * Loads customer data and renders sidebar template (detail or edit).
     * Validates nonce, permissions, and item existence.
     *
     * @since 9.0.0
     * @return void (outputs JSON)
     */
    public function ajax_load_sidebar() {
        error_log('=== AJAX LOAD SIDEBAR START ===');
        
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        $mode = sanitize_text_field($_POST['mode'] ?? 'detail');
        
        error_log('ID: ' . $id);
        error_log('Mode: ' . $mode);
        error_log('Entity: ' . $this->entity);
        
        if (!in_array($mode, array('detail', 'edit'), true)) {
            error_log('ERROR: Invalid mode');
            wp_send_json_error(array('message' => 'Invalid mode'));
        }
        
        if ($mode === 'detail' && !$this->can('view')) {
            error_log('ERROR: No view permission');
            wp_send_json_error(array('message' => 'Nemáte oprávnění zobrazit detail'));
        }
        
        if ($mode === 'edit' && !$this->can('edit')) {
            error_log('ERROR: No edit permission');
            wp_send_json_error(array('message' => 'Nemáte oprávnění upravovat záznamy'));
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            error_log('ERROR: Item not found');
            wp_send_json_error(array('message' => 'Zákazník nenalezen'));
        }
        
        error_log('Item loaded: ' . print_r($item, true));
        
        $account_types = array();
        
        if ($mode === 'edit') {
            $account_types = $this->load_account_types();
            error_log('Account types loaded: ' . count($account_types));
        }
        
        $item = $this->format_detail_data($item);
        
        ob_start();
        
        if ($mode === 'detail') {
            $tab = 'overview';
            $config = $this->config;
            $entity = $this->entity;
            
            $template_path = SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/detail-sidebar.php';
            error_log('Template path: ' . $template_path);
            error_log('Template exists: ' . (file_exists($template_path) ? 'YES' : 'NO'));
            
            require $template_path;
        } else {
            $is_edit = true;
            $GLOBALS['saw_sidebar_form'] = true;
            
            $form_path = $this->config['path'] . 'form-template.php';
            error_log('Form path: ' . $form_path);
            error_log('Form exists: ' . (file_exists($form_path) ? 'YES' : 'NO'));
            
            require $form_path;
            unset($GLOBALS['saw_sidebar_form']);
        }
        
        $sidebar_content = ob_get_clean();
        
        error_log('Sidebar content length: ' . strlen($sidebar_content));
        error_log('First 200 chars: ' . substr($sidebar_content, 0, 200));
        
        // Wrap in sidebar wrapper for proper CSS styling
        $html = '<div class="saw-sidebar-wrapper active">' . $sidebar_content . '</div>';
        
        error_log('Final HTML length: ' . strlen($html));
        error_log('=== AJAX LOAD SIDEBAR END ===');
        
        wp_send_json_success(array(
            'html' => $html,
            'mode' => $mode,
            'id' => $id
        ));
    }
    
    /**
     * AJAX: Get detail for modal (BACKWARD COMPATIBLE)
     *
     * Loads customer data and renders detail modal template.
     * Validates nonce, permissions, and item existence.
     *
     * @since 2.0.0
     * @return void (outputs JSON)
     */
    public function ajax_get_detail() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        
        if (!$this->can('view')) {
            wp_send_json_error(array('message' => 'Nedostatečná oprávnění'));
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(array('message' => 'Záznam nenalezen'));
        }
        
        $item = $this->format_detail_data($item);
        
        ob_start();
        require $this->config['path'] . 'detail-modal-template.php';
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
    
    /**
     * AJAX: Search customers
     *
     * @since 2.0.0
     * @return void (outputs JSON)
     */
    public function ajax_search() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!$this->can('list')) {
            wp_send_json_error(array('message' => 'Nedostatečná oprávnění'));
        }
        
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        $filters = array(
            'search' => $search,
            'per_page' => 10,
        );
        
        $data = $this->model->get_all($filters);
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Delete customer
     *
     * @since 2.0.0
     * @return void (outputs JSON)
     */
    public function ajax_delete() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $id = intval($_POST['id'] ?? 0);
        
        if (!$this->can('delete')) {
            wp_send_json_error(array('message' => 'Nedostatečná oprávnění'));
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        $this->invalidate_caches();
        
        wp_send_json_success(array('message' => 'Zákazník byl úspěšně smazán'));
    }
    
    /**
     * Before save hook
     *
     * @since 8.0.0
     * @param array $data Data to save
     * @return array|WP_Error
     */
    protected function before_save($data) {
        return $data;
    }
    
    /**
     * After save hook
     *
     * @since 8.0.0
     * @param int $id Saved ID
     * @return void
     */
    protected function after_save($id) {
        $this->invalidate_caches();
    }
    
    /**
     * Invalidate caches
     *
     * @since 8.0.0
     * @return void
     */
    protected function invalidate_caches() {
        delete_transient('customers_list');
    }
}