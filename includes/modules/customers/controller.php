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
 * - Related data support (branches)
 *
 * @package SAW_Visitors
 * @version 11.0.0 - RELATED DATA SUPPORT ADDED
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
        
        // ✅ Načti related data
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
            $this->handle_create();
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
            $this->handle_edit($sidebar_context['id']);
            return;
        }
    }
    
    // Load account types AFTER ob_start()
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
    
    // Clear any captured SQL output
    $captured_junk = ob_get_clean();
    
    // Start fresh output buffer
    ob_start();
    
    if (class_exists('SAW_Module_Style_Manager')) {
        $style_manager = SAW_Module_Style_Manager::get_instance();
        echo $style_manager->inject_module_css($this->entity);
    }
    
    echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
    $this->render_flash_messages();
    
    // ✅ CRITICAL: Extract ALL variables needed by template
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
    error_log('========== AJAX SIDEBAR CALLED ==========');
    error_log('POST data: ' . print_r($_POST, true));
    
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
    
    // Start output buffering BEFORE load_account_types()
    ob_start();
    
    $account_types = array();
    $related_data = null;
    
    if ($mode === 'edit') {
        $account_types = $this->load_account_types();
    }
    
    if ($mode === 'detail') {
        // ✅ Načti related data pro detail mode
        $related_data = $this->load_related_data($id);
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
        
        // CRITICAL: Explicitly extract variables for template scope
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
     * Format data for detail view
     *
     * Adds computed fields and formatting for detail display.
     *
     * @since 8.0.0
     * @param array $item Raw item data
     * @return array Formatted item data
     */
    private function format_detail_data($item) {
    $account_types = $this->load_account_types();
    
    // Format account type with display name
    if (!empty($item['account_type_id']) && isset($account_types[$item['account_type_id']])) {
        $item['account_type_display'] = $account_types[$item['account_type_id']]['display_name'];
    } else {
        $item['account_type_display'] = 'Nezadáno';
    }
    
    // ✅ OPRAVENO: logo_url je už v DB, jen zkontroluj jestli je relativní cesta
    if (!empty($item['logo_url'])) {
        // Pokud logo_url začíná na http, je to už full URL
        if (strpos($item['logo_url'], 'http') === 0) {
            // Už je to full URL, nech to být
        } else {
            // Je to relativní cesta, přidej base URL
            $upload_dir = wp_upload_dir();
            $item['logo_url'] = $upload_dir['baseurl'] . '/' . ltrim($item['logo_url'], '/');
        }
    }
    
    // Format dates
    if (!empty($item['created_at'])) {
        $item['created_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['created_at']));
    }
    
    if (!empty($item['updated_at'])) {
        $item['updated_at_formatted'] = date_i18n('d.m.Y H:i', strtotime($item['updated_at']));
    }
    
    // Format status
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
        // Format account type
        if (!empty($item['account_type_id']) && isset($account_types[$item['account_type_id']])) {
            $item['account_type_display'] = $account_types[$item['account_type_id']]['display_name'];
        } else {
            $item['account_type_display'] = '-';
        }
        
        // Add logo URL
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
     * Load account types
     *
     * FIXED v10.0.0: Returns array with both 'name' and 'display_name' for compatibility.
     *
     * @since 8.0.0
     * @return array Account types indexed by ID
     */
    private function load_account_types() {
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
     * Handle create POST request
     *
     * @since 8.0.0
     * @return void (redirects)
     */
    private function handle_create() {
    if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_customers_form')) {
        wp_die('Neplatný bezpečnostní token');
    }
    
    if (!$this->can('create')) {
        $this->set_flash('Nemáte oprávnění vytvářet záznamy', 'error');
        wp_redirect(home_url('/admin/customers/create'));
        exit;
    }
    
    // ✅ OPRAVENÉ FIELDY podle DB struktury
    $data = array(
        'name' => sanitize_text_field($_POST['name'] ?? ''),
        'ico' => sanitize_text_field($_POST['ico'] ?? ''),  // ✅ ico (ne ic)
        'dic' => sanitize_text_field($_POST['dic'] ?? ''),
        
        // ✅ Address fields (rozdělit)
        'address_street' => sanitize_text_field($_POST['address_street'] ?? ''),
        'address_number' => sanitize_text_field($_POST['address_number'] ?? ''),
        'address_city' => sanitize_text_field($_POST['address_city'] ?? ''),
        'address_zip' => sanitize_text_field($_POST['address_zip'] ?? ''),
        'address_country' => sanitize_text_field($_POST['address_country'] ?? 'Česká republika'),
        
        // ✅ Billing address fields (rozdělit)
        'billing_address_street' => sanitize_text_field($_POST['billing_address_street'] ?? ''),
        'billing_address_number' => sanitize_text_field($_POST['billing_address_number'] ?? ''),
        'billing_address_city' => sanitize_text_field($_POST['billing_address_city'] ?? ''),
        'billing_address_zip' => sanitize_text_field($_POST['billing_address_zip'] ?? ''),
        'billing_address_country' => sanitize_text_field($_POST['billing_address_country'] ?? ''),
        
        // ✅ Contact fields
        'contact_person' => sanitize_text_field($_POST['contact_person'] ?? ''),
        'contact_email' => sanitize_email($_POST['contact_email'] ?? ''),  // ✅ contact_email (ne email)
        'contact_phone' => sanitize_text_field($_POST['contact_phone'] ?? ''),  // ✅ contact_phone (ne phone)
        
        'website' => esc_url_raw($_POST['website'] ?? ''),  // ✅ website (ne web)
        'status' => sanitize_text_field($_POST['status'] ?? 'potential'),
        'account_type_id' => !empty($_POST['account_type_id']) ? intval($_POST['account_type_id']) : null,
        'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
    );
    
    // Handle logo upload
    if (!empty($_FILES['logo']['name'])) {
        $upload_result = $this->file_uploader->upload(
            $_FILES['logo'],
            array('jpg', 'jpeg', 'png', 'gif', 'svg'),
            2 * 1024 * 1024,
            'customers'
        );
        
        if (is_wp_error($upload_result)) {
            $this->set_flash($upload_result->get_error_message(), 'error');
            wp_redirect(home_url('/admin/customers/create'));
            exit;
        }
        
        $data['logo_url'] = $upload_result['file'];  // ✅ logo_url (ne logo_path)
    }
    
    $validation = $this->model->validate($data);
    if (is_wp_error($validation)) {
        $errors = $validation->get_error_data();
        $this->set_flash(implode('<br>', $errors), 'error');
        wp_redirect(home_url('/admin/customers/create'));
        exit;
    }
    
    $result = $this->model->create($data);
    
    if (is_wp_error($result)) {
        $this->set_flash($result->get_error_message(), 'error');
        wp_redirect(home_url('/admin/customers/create'));
        exit;
    }
    
    $this->invalidate_caches();
    
    $this->set_flash('Zákazník byl úspěšně vytvořen', 'success');
    wp_redirect(home_url('/admin/customers/' . $result . '/'));
    exit;
}
    
    /**
     * Handle edit POST request
     *
     * @since 8.0.0
     * @param int $id Customer ID
     * @return void (redirects)
     */
    private function handle_edit($id) {
    if (!wp_verify_nonce($_POST['saw_nonce'] ?? '', 'saw_customers_form')) {
        wp_die('Neplatný bezpečnostní token');
    }
    
    if (!$this->can('edit')) {
        $this->set_flash('Nemáte oprávnění upravovat záznamy', 'error');
        wp_redirect(home_url('/admin/customers/' . $id . '/edit'));
        exit;
    }
    
    // ✅ OPRAVENÉ FIELDY podle DB struktury
    $data = array(
        'name' => sanitize_text_field($_POST['name'] ?? ''),
        'ico' => sanitize_text_field($_POST['ico'] ?? ''),  // ✅ ico (ne ic)
        'dic' => sanitize_text_field($_POST['dic'] ?? ''),
        
        // ✅ Address fields (rozdělit)
        'address_street' => sanitize_text_field($_POST['address_street'] ?? ''),
        'address_number' => sanitize_text_field($_POST['address_number'] ?? ''),
        'address_city' => sanitize_text_field($_POST['address_city'] ?? ''),
        'address_zip' => sanitize_text_field($_POST['address_zip'] ?? ''),
        'address_country' => sanitize_text_field($_POST['address_country'] ?? 'Česká republika'),
        
        // ✅ Billing address fields (rozdělit)
        'billing_address_street' => sanitize_text_field($_POST['billing_address_street'] ?? ''),
        'billing_address_number' => sanitize_text_field($_POST['billing_address_number'] ?? ''),
        'billing_address_city' => sanitize_text_field($_POST['billing_address_city'] ?? ''),
        'billing_address_zip' => sanitize_text_field($_POST['billing_address_zip'] ?? ''),
        'billing_address_country' => sanitize_text_field($_POST['billing_address_country'] ?? ''),
        
        // ✅ Contact fields
        'contact_person' => sanitize_text_field($_POST['contact_person'] ?? ''),
        'contact_email' => sanitize_email($_POST['contact_email'] ?? ''),  // ✅ contact_email (ne email)
        'contact_phone' => sanitize_text_field($_POST['contact_phone'] ?? ''),  // ✅ contact_phone (ne phone)
        
        'website' => esc_url_raw($_POST['website'] ?? ''),  // ✅ website (ne web)
        'status' => sanitize_text_field($_POST['status'] ?? 'potential'),
        'account_type_id' => !empty($_POST['account_type_id']) ? intval($_POST['account_type_id']) : null,
        'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
    );
    
    // Handle logo upload
    if (!empty($_FILES['logo']['name'])) {
        $upload_result = $this->file_uploader->upload(
            $_FILES['logo'],
            array('jpg', 'jpeg', 'png', 'gif', 'svg'),
            2 * 1024 * 1024,
            'customers'
        );
        
        if (is_wp_error($upload_result)) {
            $this->set_flash($upload_result->get_error_message(), 'error');
            wp_redirect(home_url('/admin/customers/' . $id . '/edit'));
            exit;
        }
        
        // Delete old logo
        $old_customer = $this->model->get_by_id($id);
        if (!empty($old_customer['logo_url'])) {  // ✅ logo_url (ne logo_path)
            $this->file_uploader->delete($old_customer['logo_url']);
        }
        
        $data['logo_url'] = $upload_result['file'];  // ✅ logo_url (ne logo_path)
    }
    
    $validation = $this->model->validate($data, $id);
    if (is_wp_error($validation)) {
        $errors = $validation->get_error_data();
        $this->set_flash(implode('<br>', $errors), 'error');
        wp_redirect(home_url('/admin/customers/' . $id . '/edit'));
        exit;
    }
    
    $result = $this->model->update($id, $data);
    
    if (is_wp_error($result)) {
        $this->set_flash($result->get_error_message(), 'error');
        wp_redirect(home_url('/admin/customers/' . $id . '/edit'));
        exit;
    }
    
    $this->invalidate_caches();
    
    $this->set_flash('Zákazník byl úspěšně aktualizován', 'success');
    wp_redirect(home_url('/admin/customers/' . $id . '/'));
    exit;
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
     * @since 8.0.0
     * @return void
     */
    protected function enqueue_assets() {
        // Assets are loaded globally by Asset Manager
    }
}