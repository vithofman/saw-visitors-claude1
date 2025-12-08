<?php
/**
 * Customers Module Controller
 *
 * Main controller for the Customers module with sidebar support.
 * Handles CRUD operations, file uploads, AJAX requests, and sidebar context.
 *
 * @package SAW_Visitors
 * @version 2.1.0 - ADDED: Translations, get_detail_header_meta, get_display_name
 * @since   4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure base classes are loaded
if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}

if (!trait_exists('SAW_AJAX_Handlers')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
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
     * Translations array
     *
     * @since 2.1.0
     * @var array
     */
    private $translations = array();
    
    /**
     * Constructor
     *
     * Initializes controller, loads config, model, components,
     * translations, and registers AJAX handlers.
     *
     * @since 4.6.1
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/customers/';
        
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        // Load translations
        $this->load_translations();
        
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Customers_Model($this->config);
        
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/class-saw-file-uploader.php';
        $this->file_uploader = new SAW_File_Uploader();
        
        add_action('wp_ajax_saw_get_customers_detail', array($this, 'ajax_get_detail'));
        add_action('wp_ajax_saw_search_customers', array($this, 'ajax_search'));
        add_action('wp_ajax_saw_delete_customers', array($this, 'ajax_delete'));
        add_action('wp_ajax_saw_load_sidebar_customers', array($this, 'ajax_load_sidebar'));
        add_action('wp_ajax_saw_get_adjacent_customers', array($this, 'ajax_get_adjacent_id'));
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Load translations for this module
     *
     * @since 2.1.0
     * @return void
     */
    private function load_translations() {
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        
        $this->translations = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'customers') 
            : array();
    }
    
    /**
     * Get translation for key
     *
     * @since 2.1.0
     * @param string $key Translation key
     * @param string $fallback Fallback text
     * @return string Translated text
     */
    protected function tr($key, $fallback = null) {
        return $this->translations[$key] ?? $fallback ?? $key;
    }
    
    /**
     * Display list page with optional sidebar
     *
     * Now uses universal render_list_view() from Base Controller.
     *
     * @since 12.2.0 - FÁZE 3
     * @return void
     */
    public function index() {
        $this->render_list_view();
    }
    
    /**
     * Get display name for detail header
     * 
     * @since 2.1.0
     * @param array $item Customer data
     * @return string Display name (customer name)
     */
    public function get_display_name($item) {
        return $item['name'] ?? $this->config['singular'] ?? $this->tr('singular', 'Zákazník');
    }
    
    /**
     * Get header meta for detail sidebar (blue header)
     * 
     * Shows: Status badge + Account Type badge
     * NO ID displayed - as per requirement.
     * 
     * @since 2.1.0
     * @param array $item Customer data
     * @return string HTML for header meta badges
     */
    protected function get_detail_header_meta($item) {
        if (empty($item)) {
            return '';
        }
        
        $meta_parts = array();
        
        // Status badge
        $status = $item['status'] ?? 'potential';
        $status_labels = array(
            'potential' => $this->tr('status_potential', 'Potenciální'),
            'active' => $this->tr('status_active', 'Aktivní'),
            'inactive' => $this->tr('status_inactive', 'Neaktivní'),
        );
        $status_icons = array(
            'potential' => '⏳',
            'active' => '✓',
            'inactive' => '✕',
        );
        $status_classes = array(
            'potential' => 'saw-badge-warning',
            'active' => 'saw-badge-success',
            'inactive' => 'saw-badge-secondary',
        );
        
        $meta_parts[] = sprintf(
            '<span class="saw-badge-transparent %s">%s %s</span>',
            esc_attr($status_classes[$status] ?? 'saw-badge-secondary'),
            $status_icons[$status] ?? '',
            esc_html($status_labels[$status] ?? $status)
        );
        
        // Account Type badge (if set)
        $not_set = $this->tr('not_set', 'Nezadáno');
        if (!empty($item['account_type_display']) && $item['account_type_display'] !== $not_set) {
            $meta_parts[] = sprintf(
                '<span class="saw-badge-transparent saw-badge-info">%s</span>',
                esc_html($item['account_type_display'])
            );
        }
        
        return implode(' ', $meta_parts);
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
        saw_verify_ajax_unified();
        
        if (!$this->can('delete')) {
            wp_send_json_error(array('message' => $this->tr('error_no_permission_delete', 'Nemáte oprávnění mazat záznamy')));
            return;
        }
        
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        
        if (!$id) {
            wp_send_json_error(array('message' => $this->tr('error_invalid_id', 'Neplatné ID')));
            return;
        }
        
        $item = $this->model->get_by_id($id);
        
        if (!$item) {
            wp_send_json_error(array('message' => $this->tr('error_not_found', 'Zákazník nenalezen')));
            return;
        }
        
        $before_delete_result = $this->before_delete($id);
        
        if (is_wp_error($before_delete_result)) {
            wp_send_json_error(array('message' => $before_delete_result->get_error_message()));
            return;
        }
        
        if ($before_delete_result === false) {
            wp_send_json_error(array('message' => $this->tr('error_cannot_delete', 'Nelze smazat zákazníka')));
            return;
        }
        
        $result = $this->model->delete($id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        $this->after_delete($id);
        
        wp_send_json_success(array('message' => $this->tr('success_deleted', 'Zákazník byl úspěšně smazán')));
    }
    
    /**
     * Format data for detail view
     *
     * Adds computed fields and formatting for detail display.
     *
     * @since 12.0.0 - Config-driven lookups
     * @param array $item Raw item data
     * @return array Formatted item data
     */
    protected function format_detail_data($item) {
        $account_types = $this->load_lookup_from_config('account_types');
        
        if (!empty($item['account_type_id']) && isset($account_types[$item['account_type_id']])) {
            $item['account_type_display'] = $account_types[$item['account_type_id']]['display_name'];
        } else {
            $item['account_type_display'] = $this->tr('not_set', 'Nezadáno');
        }
        
        if (!empty($item['logo_url'])) {
            if (strpos($item['logo_url'], 'http') !== 0) {
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
            'potential' => $this->tr('status_potential', 'Potenciální'),
            'active' => $this->tr('status_active', 'Aktivní'),
            'inactive' => $this->tr('status_inactive', 'Neaktivní'),
        );
        
        return $labels[$status] ?? $this->tr('status_unknown', 'Neznámý');
    }
    
    /**
     * Prepare form data from POST
     * 
     * Override base controller method to handle file uploads and custom fields.
     * 
     * @since 11.3.0
     * @param array $post POST data
     * @return array|WP_Error Prepared data or error
     */
    protected function prepare_form_data($post) {
        $default_country = $this->tr('default_country', 'Česká republika');
        
        $data = array(
            'name' => sanitize_text_field($post['name'] ?? ''),
            'ico' => sanitize_text_field($post['ico'] ?? ''),
            'dic' => sanitize_text_field($post['dic'] ?? ''),
            
            'address_street' => sanitize_text_field($post['address_street'] ?? ''),
            'address_number' => sanitize_text_field($post['address_number'] ?? ''),
            'address_city' => sanitize_text_field($post['address_city'] ?? ''),
            'address_zip' => sanitize_text_field($post['address_zip'] ?? ''),
            'address_country' => sanitize_text_field($post['address_country'] ?? $default_country),
            
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
        
        if (!empty($_FILES['logo']['name'])) {
            $upload_result = $this->file_uploader->upload($_FILES['logo'], 'customers');
            
            if (is_wp_error($upload_result)) {
                return $upload_result;
            }
            
            if (isset($post['id'])) {
                $old_customer = $this->model->get_by_id($post['id']);
                if (!empty($old_customer['logo_url'])) {
                    $this->file_uploader->delete($old_customer['logo_url']);
                }
            }
            
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
     * Database has CASCADE delete, so we just log dependencies and allow deletion.
     * 
     * @since 11.2.2
     * @param int $id Customer ID
     * @return bool Always true (CASCADE delete handles dependencies)
     */
    protected function before_delete($id) {
        global $wpdb;
        
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
        }
        
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
     * Get tab counts for dynamic account_type tabs
     * 
     * Override base method to handle dynamic tabs from saw_account_types table.
     * Counts customers per account_type_id.
     * 
     * @since 3.0.0
     * @return array Tab key => count
     */
    protected function get_tab_counts() {
        global $wpdb;
        
        if (empty($this->config['tabs']['enabled'])) {
            return array();
        }
        
        $counts = array();
        
        // Get total count for "all" tab
        $total = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_customers"
        );
        $counts['all'] = (int) $total;
        
        // Get counts per account_type_id
        $type_counts = $wpdb->get_results(
            "SELECT 
                account_type_id,
                COUNT(*) as count
             FROM {$wpdb->prefix}saw_customers
             WHERE account_type_id IS NOT NULL
             GROUP BY account_type_id",
            ARRAY_A
        );
        
        // Build counts array with dynamic tab keys (type_X)
        if ($type_counts) {
            foreach ($type_counts as $row) {
                $tab_key = 'type_' . $row['account_type_id'];
                $counts[$tab_key] = (int) $row['count'];
            }
        }
        
        // Get all account types to ensure all tabs have a count (even 0)
        $account_types = $wpdb->get_col(
            "SELECT id FROM {$wpdb->prefix}saw_account_types WHERE is_active = 1"
        );
        
        if ($account_types) {
            foreach ($account_types as $type_id) {
                $tab_key = 'type_' . $type_id;
                if (!isset($counts[$tab_key])) {
                    $counts[$tab_key] = 0;
                }
            }
        }
        
        return $counts;
    }
    
    /**
     * AJAX: Get adjacent ID for prev/next navigation
     * 
     * Customers are top-level entities, no customer_id/branch_id filtering needed.
     * 
     * @since 3.0.0
     * @return void Outputs JSON
     */
    public function ajax_get_adjacent_id() {
        saw_verify_ajax_unified();
        
        if (!$this->can('view')) {
            wp_send_json_error(array('message' => $this->tr('error_no_permission', 'Nemáte oprávnění')));
        }
        
        $current_id = intval($_POST['id'] ?? 0);
        $direction = sanitize_text_field($_POST['direction'] ?? 'next');
        
        if (!$current_id || !in_array($direction, array('next', 'prev'))) {
            wp_send_json_error(array('message' => $this->tr('error_invalid_id', 'Neplatné ID nebo směr')));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_customers';
        
        // Get all IDs ordered by name
        $sql = "SELECT id FROM {$table} ORDER BY name ASC, id ASC";
        $ids = $wpdb->get_col($sql);
        
        if (empty($ids)) {
            wp_send_json_error(array('message' => $this->tr('error_no_records', 'Žádní zákazníci')));
        }
        
        $ids = array_map('intval', $ids);
        $current_index = array_search($current_id, $ids, true);
        
        if ($current_index === false) {
            wp_send_json_error(array('message' => $this->tr('error_not_in_list', 'Záznam nenalezen v seznamu')));
        }
        
        // Circular navigation
        $adjacent_index = $direction === 'next' 
            ? ($current_index + 1) % count($ids)
            : ($current_index - 1 + count($ids)) % count($ids);
        
        $adjacent_id = $ids[$adjacent_index];
        
        $route = $this->config['route'] ?? $this->entity;
        $detail_url = home_url('/admin/' . $route . '/' . $adjacent_id . '/');
        
        wp_send_json_success(array(
            'id' => $adjacent_id,
            'url' => $detail_url,
        ));
    }
    
    /**
     * Enqueue module assets
     *
     * @since 11.3.0
     * @return void
     */
    protected function enqueue_assets() {
        $this->file_uploader->enqueue_assets();
        SAW_Asset_Loader::enqueue_module('customers');
    }
}