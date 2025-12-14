<?php
/**
 * Departments Module Controller
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @version     4.0.2 - FIXED: Removed invalid parent:: call
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

class SAW_Module_Departments_Controller extends SAW_Base_Controller
{
    use SAW_AJAX_Handlers;

    /** @var array Translation strings */
    private $translations = array();

    /**
     * Constructor
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/departments/';

        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;

        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Departments_Model($this->config);
        
        $this->init_translations();

        // Register ALL AJAX handlers
        add_action('wp_ajax_saw_get_departments_detail',   array($this, 'ajax_get_detail'));
        add_action('wp_ajax_saw_search_departments',       array($this, 'ajax_search'));
        add_action('wp_ajax_saw_delete_departments',       array($this, 'ajax_delete'));
        add_action('wp_ajax_saw_load_sidebar_departments', array($this, 'ajax_load_sidebar'));
        add_action('wp_ajax_saw_get_adjacent_departments', array($this, 'ajax_get_adjacent_id'));

        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
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
            ? saw_get_translations($lang, 'admin', 'departments') 
            : array();
    }
    
    /**
     * Get translation
     */
    private function tr($key, $fallback = null) {
        return $this->translations[$key] ?? $fallback ?? $key;
    }

    /**
     * Index - list view
     */
    public function index() {
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die($this->tr('error_no_view_permission', 'Nemáte oprávnění'), 403);
        }
        $this->render_list_view();
    }

    /**
     * Prepare form data for save
     */
    protected function prepare_form_data($post) {
        $data = array();
        
        if (isset($post['customer_id'])) {
            $data['customer_id'] = intval($post['customer_id']);
        }
        if (isset($post['branch_id'])) {
            $data['branch_id'] = intval($post['branch_id']);
        }
        
        $text_fields = array('department_number', 'name', 'description');
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        $data['is_active'] = isset($post['is_active']) ? 1 : 0;
        
        return $data;
    }
    
    /**
     * Before save hook - auto-fill context
     */
    protected function before_save($data) {
        if (empty($data['customer_id']) && class_exists('SAW_Context')) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        if (empty($data['branch_id']) && class_exists('SAW_Context')) {
            $data['branch_id'] = SAW_Context::get_branch_id();
        }
        return $data;
    }
    
    /**
     * Before delete hook - check relations
     */
    protected function before_delete($id) {
        global $wpdb;
        
        $user_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_user_departments WHERE department_id = %d",
            intval($id)
        ));
        
        return $user_count === 0;
    }

    /**
     * Format detail data - add branch info and dates
     * 
     * ✅ FIXED: No parent:: call - trait method is overridden completely
     */
    protected function format_detail_data($item) {
        if (empty($item)) {
            return $item;
        }
        
        // Format audit fields and dates (audit history support)
        if (!empty($item['created_at'])) {
            $item['created_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['created_at']));
            $item['created_at_relative'] = human_time_diff(strtotime($item['created_at']), current_time('timestamp')) . ' ' . __('před', 'saw-visitors');
        }
        if (!empty($item['updated_at'])) {
            $item['updated_at_formatted'] = date_i18n('j. n. Y H:i', strtotime($item['updated_at']));
            $item['updated_at_relative'] = human_time_diff(strtotime($item['updated_at']), current_time('timestamp')) . ' ' . __('před', 'saw-visitors');
        }
        
        // Set flag for audit info availability
        $item['has_audit_info'] = !empty($item['created_by']) || !empty($item['updated_by']) || 
                                  !empty($item['created_at']) || !empty($item['updated_at']);
        
        // Load change history for this department
        if (!empty($item['id']) && class_exists('SAW_Audit')) {
            try {
                $entity_type = $this->config['entity'] ?? 'departments';
                $change_history = SAW_Audit::get_entity_history($entity_type, $item['id']);
                if (!empty($change_history)) {
                    $item['change_history'] = $change_history;
                    $item['has_audit_info'] = true;
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SAW Audit] Failed to load change history for departments: ' . $e->getMessage());
                }
            }
        }
        
        // Add branch info
        if (!empty($item['branch_id'])) {
            global $wpdb;
            $branch = $wpdb->get_row($wpdb->prepare(
                "SELECT name, code FROM {$wpdb->prefix}saw_branches WHERE id = %d",
                intval($item['branch_id'])
            ), ARRAY_A);
            
            if ($branch) {
                $item['branch_name'] = $branch['name'];
                $item['branch_code'] = $branch['code'] ?? '';
            }
        }
        
        return $item;
    }
    
    /**
     * Get header meta badges for detail sidebar
     */
    public function get_detail_header_meta($item) {
        if (empty($item)) {
            return '';
        }
        
        $meta = array();
        
        if (!empty($item['department_number'])) {
            $meta[] = '<span class="saw-badge-transparent">' . esc_html($item['department_number']) . '</span>';
        }
        
        if (!empty($item['branch_name'])) {
            $meta[] = '<span class="saw-badge-transparent saw-badge-info">🏢 ' . esc_html($item['branch_name']) . '</span>';
        }
        
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
        $table = $wpdb->prefix . 'saw_departments';
        
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
            $where[] = 'branch_id = %d';
            $params[] = $branch_id;
        }
        
        $sql = "SELECT id FROM {$table} WHERE " . implode(' AND ', $where) . " ORDER BY name ASC, id ASC";
        $ids = $params ? $wpdb->get_col($wpdb->prepare($sql, $params)) : $wpdb->get_col($sql);
        
        if (empty($ids)) {
            wp_send_json_error(array('message' => $this->tr('error_no_records', 'Žádná oddělení')));
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
            'url' => home_url('/admin/departments/' . $adjacent_id . '/'),
        ));
    }
    
    /**
     * Enqueue module assets
     */
    protected function enqueue_assets() {
        SAW_Asset_Loader::enqueue_module('departments');
    }
}