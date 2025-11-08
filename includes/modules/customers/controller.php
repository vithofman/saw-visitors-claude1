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
 * - AJAX sidebar loading (FIXED v9.1.0)
 * - AJAX detail modal (backward compatible)
 * - AJAX search and delete
 * - Dependency validation (branches, users, visits, invitations)
 * - Comprehensive cache invalidation
 * - File upload handling (logo)
 *
 * @package SAW_Visitors
 * @version 10.0.0 - REMOVED color field, FIXED account_types with display_name
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
     * CRITICAL FIX v9.1.4: Start output buffering BEFORE load_account_types()
     *
     * @since 8.0.0
     * @return void
     */
    public function index() {
        $this->verify_module_access();
        $this->enqueue_assets();
        
        // CRITICAL FIX: Start output buffering at the very beginning
        // to catch any SQL output from load_account_types()
        ob_start();
        
        $sidebar_context = $this->get_sidebar_context();
        $sidebar_mode = $sidebar_context['mode'] ?? null;
        
        $detail_item = null;
        $form_item = null;
        $detail_tab = $sidebar_context['tab'] ?? 'overview';
        $account_types = array();
        
        if ($sidebar_mode === 'detail') {
            if (!$this->can('view')) {
                ob_end_clean();
                $this->set_flash('Nemáte oprávnění zobrazit detail', 'error');
                wp_redirect(home_url('/admin/settings/customers/'));
                exit;
            }
            
            $detail_item = $this->model->get_by_id($sidebar_context['id']);
            
            if (!$detail_item) {
                ob_end_clean();
                $this->set_flash('Zákazník nenalezen', 'error');
                wp_redirect(home_url('/admin/settings/customers/'));
                exit;
            }
            
            $detail_item = $this->format_detail_data($detail_item);
        }
        
        elseif ($sidebar_mode === 'create') {
            if (!$this->can('create')) {
                ob_end_clean();
                $this->set_flash('Nemáte oprávnění vytvářet zákazníky', 'error');
                wp_redirect(home_url('/admin/settings/customers/'));
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
                wp_redirect(home_url('/admin/settings/customers/'));
                exit;
            }
            
            $form_item = $this->model->get_by_id($sidebar_context['id']);
            
            if (!$form_item) {
                ob_end_clean();
                $this->set_flash('Zákazník nenalezen', 'error');
                wp_redirect(home_url('/admin/settings/customers/'));
                exit;
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                ob_end_clean();
                $this->handle_edit_post($sidebar_context['id']);
                return;
            }
        }
        
        // Load account types AFTER ob_start() - any SQL output will be caught
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
        
        // Clear any captured SQL output before starting real output
        $captured_junk = ob_get_clean();
        
        // Start fresh output buffer for actual content
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
        
        $this->set_flash('Zákazník byl úspěšně upraven', 'success');
        wp_redirect(home_url('/admin/settings/customers/' . $id . '/'));
        exit;
    }
    
    /**
     * Prepare form data from POST
     *
     * REMOVED: primary_color field (no longer in DB schema)
     *
     * @since 8.0.0
     * @param array $post POST data
     * @return array Prepared data
     */
    protected function prepare_form_data($post) {
        $data = array(
            'name' => sanitize_text_field($post['name'] ?? ''),
            'ico' => sanitize_text_field($post['ico'] ?? ''),
            'status' => sanitize_text_field($post['status'] ?? 'potential'),
            'account_type_id' => !empty($post['account_type_id']) ? intval($post['account_type_id']) : null,
        );
        
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload_result = $this->file_uploader->upload($_FILES['logo'], 'customers');
            
            if (!is_wp_error($upload_result)) {
                $data['logo_url'] = $upload_result['url'];
            }
        }
        
        return $data;
    }
    
    /**
     * Load account types from database
     *
     * FIXED v10.0.0: Added display_name and price to SELECT
     *
     * @since 8.0.0
     * @return array Account types with id, name, display_name, price
     */
    protected function load_account_types() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'saw_account_types';
        
        $results = @$wpdb->get_results(
    "SELECT id, name, display_name, price 
     FROM {$table_name}
     WHERE is_active = 1 
     ORDER BY sort_order ASC, name ASC",
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
        $this->file_uploader->enqueue_assets();
    }
    
    /**
     * AJAX: Load sidebar content
     *
     * Loads customer data and renders sidebar template (detail or edit).
     * Validates nonce, permissions, and item existence.
     *
     * CRITICAL FIX v9.1.4: Output buffering starts BEFORE load_account_types()
     *
     * @since 9.0.0
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
        
        // CRITICAL FIX: Start output buffering BEFORE load_account_types()
        ob_start();
        
        $account_types = array();
        
        if ($mode === 'edit') {
            $account_types = $this->load_account_types();
        }
        
        // Clear any captured SQL output
        $captured_junk = ob_get_clean();
        
        // Start fresh buffer for actual content
        ob_start();
        
        $item = $this->format_detail_data($item);
        
        if ($mode === 'detail') {
            $tab = 'overview';
            $config = $this->config;
            $entity = $this->entity;
            
            $template_path = SAW_VISITORS_PLUGIN_DIR . 'includes/components/admin-table/detail-sidebar.php';
            
            require $template_path;
        } else {
            $is_edit = true;
            $GLOBALS['saw_sidebar_form'] = true;
            
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
     * CRITICAL: Signatura musí zůstat (array $data) bez druhého parametru
     * pro kompatibilitu s existing code.
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