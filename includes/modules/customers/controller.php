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
 * - AJAX detail modal (backward compatible)
 * - AJAX search and delete
 * - Dependency validation (branches, users, visits, invitations)
 * - Comprehensive cache invalidation
 * - File upload handling (logo)
 *
 * @package SAW_Visitors
 * @version 8.0.0 - SIDEBAR SUPPORT
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
        
        // Get sidebar context from router
        $sidebar_context = $this->get_sidebar_context();
        $sidebar_mode = $sidebar_context['mode'] ?? null;
        
        // Initialize sidebar variables
        $detail_item = null;
        $form_item = null;
        $detail_tab = $sidebar_context['tab'] ?? 'overview';
        
        // DETAIL SIDEBAR MODE
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
        
        // CREATE FORM SIDEBAR MODE
        elseif ($sidebar_mode === 'create') {
            if (!$this->can('create')) {
                $this->set_flash('Nemáte oprávnění vytvářet zákazníky', 'error');
                wp_redirect(home_url('/admin/settings/customers/'));
                exit;
            }
            
            // Handle POST for create
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->handle_create_post();
                return;
            }
            
            $form_item = array(); // Empty for new form
        }
        
        // EDIT FORM SIDEBAR MODE
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
            
            // Handle POST for edit
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->handle_edit_post($sidebar_context['id']);
                return;
            }
        }
        
        // Get list data
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
        
        // Render
        ob_start();
        
        if (class_exists('SAW_Module_Style_Manager')) {
            $style_manager = SAW_Module_Style_Manager::get_instance();
            echo $style_manager->inject_module_css($this->entity);
        }
        
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        $this->render_flash_messages();
        
        // Include list template with sidebar variables
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
        return array(
            'name' => sanitize_text_field($post['name'] ?? ''),
            'ico' => sanitize_text_field($post['ico'] ?? ''),
            'dic' => sanitize_text_field($post['dic'] ?? ''),
            'status' => sanitize_text_field($post['status'] ?? 'active'),
            'primary_color' => sanitize_hex_color($post['primary_color'] ?? '#3b82f6'),
            'address_street' => sanitize_text_field($post['address_street'] ?? ''),
            'address_city' => sanitize_text_field($post['address_city'] ?? ''),
            'address_zip' => sanitize_text_field($post['address_zip'] ?? ''),
            'email' => sanitize_email($post['email'] ?? ''),
            'phone' => sanitize_text_field($post['phone'] ?? ''),
            'website' => esc_url_raw($post['website'] ?? ''),
            'notes' => sanitize_textarea_field($post['notes'] ?? ''),
        );
    }
    
    /**
     * AJAX: Get detail for modal (BACKWARD COMPATIBLE)
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
            wp_send_json_error(array(
                'message' => 'Neplatný bezpečnostní token. Obnovte stránku a zkuste to znovu.'
            ));
            return;
        }
        
        // Check permissions
        if (!current_user_can('read')) {
            wp_send_json_error(array(
                'message' => 'Nedostatečná oprávnění k zobrazení detailu.'
            ));
            return;
        }
        
        // Validate ID
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array(
                'message' => 'Neplatné ID zákazníka.'
            ));
            return;
        }
        
        // Load customer data
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
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
            wp_send_json_error(array(
                'message' => 'Chyba: Template soubor nebyl nalezen.'
            ));
            return;
        }
        
        // Render template
        ob_start();
        
        try {
            require $template_path;
            $html = ob_get_clean();
            
            wp_send_json_success(array(
                'html' => $html,
                'item' => $item
            ));
            
        } catch (Exception $e) {
            ob_end_clean();
            
            wp_send_json_error(array(
                'message' => 'Chyba při zobrazení detailu: ' . $e->getMessage()
            ));
        }
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
                return $upload;
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
     *
     * @since 3.1.0
     * @param int $id Customer ID
     * @return void
     */
    protected function after_save($id) {
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
     * Before delete hook
     *
     * Checks for dependencies before allowing deletion.
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
                sprintf('Zákazníka nelze smazat. Má %d poboček.', $branches_count)
            );
        }
        
        return true;
    }
    
    /**
     * After delete hook
     *
     * Cleanup operations after successful deletion.
     *
     * @since 4.6.1
     * @param int $id Customer ID
     * @return void
     */
    protected function after_delete($id) {
        $customer = $this->model->get_by_id($id);
        if (!empty($customer['logo_url'])) {
            $this->file_uploader->delete($customer['logo_url']);
        }
        
        delete_transient('customers_list');
        delete_transient('customers_for_switcher');
        
        if (class_exists('SAW_Cache')) {
            SAW_Cache::forget_pattern('customers_*');
        }
        
        wp_cache_delete($id, 'saw_customers');
        wp_cache_delete('saw_customers_all', 'saw_customers');
    }
    
    /**
     * Format detail data
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
        
        // Ensure colors are properly formatted
        if (!empty($item['primary_color']) && strpos($item['primary_color'], '#') !== 0) {
            $item['primary_color'] = '#' . $item['primary_color'];
        }
        
        return $item;
    }
}