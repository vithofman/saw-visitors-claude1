<?php
/**
 * Visits Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     5.1.1 - FIXED: Risks editor routing via custom router
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SAW_Base_Controller')) require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
if (!trait_exists('SAW_AJAX_Handlers')) require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';

class SAW_Module_Visits_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/';
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Visits_Model($this->config);
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'), 999);
    }
    
    public function index() {
        // ===== EDIT-RISKS DETECTION (v5.1.1) =====
        // URL: /admin/visits/{id}/edit-risks/
        // Router parses this as: mode=detail, id={id}, tab=edit-risks
        $context = $this->get_sidebar_context();
        if (isset($context['tab']) && $context['tab'] === 'edit-risks' && !empty($context['id'])) {
            $this->handle_edit_risks(intval($context['id']));
            return;
        }
        // ===== END EDIT-RISKS DETECTION =====
        
        // Standard permission check and list view
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die('Nemáte oprávnění.', 403);
        }
        $this->render_list_view();
    }
    
    /**
     * Handle risks editing action
     * 
     * Loads and initializes the risks controller for editing visitor risk information.
     * 
     * @since 5.1.0
     * @since 5.1.1 FIXED: Now accepts visit_id as parameter from router context
     * @param int|null $visit_id Visit ID (from router context or fallback)
     */
    private function handle_edit_risks($visit_id = null) {
        // Get visit_id from parameter (primary) or context (fallback)
        if ($visit_id === null) {
            $context = $this->get_sidebar_context();
            $visit_id = !empty($context['id']) ? intval($context['id']) : 0;
        }
        
        if (!$visit_id) {
            wp_die('Neplatná návštěva.', 'Chyba', ['response' => 400]);
        }
        
        // Permission check - same roles as defined in detail-modal-template.php
        $can_edit_risks = false;
        if (current_user_can('manage_options')) {
            $can_edit_risks = true;
        } elseif (function_exists('saw_get_current_role')) {
            $user_role = saw_get_current_role();
            $can_edit_risks = in_array($user_role, ['super_admin', 'admin', 'super_manager', 'manager']);
        } elseif (current_user_can('edit_posts')) {
            $can_edit_risks = true;
        }
        
        if (!$can_edit_risks) {
            wp_die('Nemáte oprávnění upravovat informace o rizicích.', 'Přístup zamítnut', ['response' => 403]);
        }
        
        // Load risks controller
        $risks_controller_file = __DIR__ . '/risks/risks-controller.php';
        
        if (!file_exists($risks_controller_file)) {
            wp_die('Risks controller not found.', 'Error', ['response' => 500]);
        }
        
        require_once $risks_controller_file;
        
        $controller = new SAW_Visit_Risks_Controller($visit_id);
        $controller->init();
    }
    
    public function enqueue_assets() {
        wp_enqueue_style('dashicons');
        SAW_Asset_Loader::enqueue_module('visits');
        
        // Enqueue action info JS
        $action_info_js = SAW_VISITORS_PLUGIN_DIR . 'assets/js/modules/saw-visits-action-info.js';
        if (file_exists($action_info_js)) {
            wp_enqueue_script(
                'saw-visits-action-info',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/modules/saw-visits-action-info.js',
                array('jquery', 'saw-module-visits'),
                filemtime($action_info_js),
                true
            );
        }
        
        $visit_id = $this->detect_visit_id();
        
        $existing_hosts = array();
        if ($visit_id > 0) {
            global $wpdb;
            $existing_hosts = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
                $visit_id
            ));
            $existing_hosts = array_map('intval', $existing_hosts);
        }
        
        $script_handle = 'saw-module-visits';
        
        if (wp_script_is($script_handle, 'registered') || wp_script_is($script_handle, 'enqueued')) {
            wp_localize_script($script_handle, 'sawVisitsData', array(
                'existing_hosts' => $existing_hosts,
                'visit_id' => $visit_id
            ));
        }
    }
    
    /**
     * Detect visit ID from multiple sources with fallbacks
     * 
     * @since 3.2.0
     * @return int Visit ID or 0 if not found
     */
    private function detect_visit_id() {
        $visit_id = 0;
        
        // Method 1: Try get_sidebar_context() (primary - set by router)
        $context = $this->get_sidebar_context();
        if (!empty($context['id']) && ($context['mode'] === 'edit' || $context['mode'] === 'detail')) {
            return intval($context['id']);
        }
        
        // Method 2: Parse URL from REQUEST_URI as fallback
        if (isset($_SERVER['REQUEST_URI'])) {
            $request_uri = sanitize_text_field($_SERVER['REQUEST_URI']);
            if (preg_match('#/admin/visits/(\d+)(?:/edit|/)?#', $request_uri, $matches)) {
                return intval($matches[1]);
            }
        }
        
        // Method 3: Fallback to $_GET['id']
        if (isset($_GET['id'])) {
            return intval($_GET['id']);
        }
        
        // Method 4: Try $_GET['saw_path'] as last resort
        if (isset($_GET['saw_path'])) {
            $path = sanitize_text_field($_GET['saw_path']);
            if (preg_match('/visits\/(\d+)/', $path, $matches)) {
                return intval($matches[1]);
            }
        }
        
        return 0;
    }

    protected function prepare_form_data($post) {
        $data = array();
        foreach ($this->config['fields'] as $field_name => $field_config) {
            if (isset($post[$field_name])) {
                $sanitize = $field_config['sanitize'] ?? 'sanitize_text_field';
                $data[$field_name] = $sanitize($post[$field_name]);
            }
        }
        return $data;
    }
    
    protected function before_save($data) {
    // ============================================
    // DEBUG LOGGING (keep for troubleshooting)
    // ============================================
    error_log('========== VISITS BEFORE_SAVE v2.0 ==========');
    error_log('Mode: ' . (empty($data['id']) ? 'CREATE' : 'EDIT (ID: ' . $data['id'] . ')'));
    error_log('$_POST[has_company]: ' . var_export($_POST['has_company'] ?? 'NOT SET', true));
    error_log('$_POST[company_id]: ' . var_export($_POST['company_id'] ?? 'NOT SET', true));
    error_log('$data[company_id]: ' . var_export($data['company_id'] ?? 'NOT SET', true));
    
    // ============================================
    // STEP 1: Get has_company radio value
    // ============================================
    $has_company = isset($_POST['has_company']) ? $_POST['has_company'] : null;
    
    // ============================================
    // STEP 2: Handle company_id based on person type
    // ============================================
    
    if ($has_company === '0') {
        // ============================================
        // PHYSICAL PERSON - company_id MUST be NULL
        // ============================================
        $data['company_id'] = null;
        error_log('RESULT: Physical person - company_id = NULL');
        
    } elseif ($has_company === '1') {
        // ============================================
        // LEGAL PERSON - get company_id from POST or data
        // ============================================
        
        // Priority: POST > data (because hidden input may not go through prepare_form_data)
        $company_id = null;
        
        // ⭐ FIX v3.8.0: Check for 'visit_company_selection' first (neutral field name to prevent autocomplete)
        // Then fallback to 'company_id' for backward compatibility
        if (isset($_POST['visit_company_selection'])) {
            $raw_value = $_POST['visit_company_selection'];
            error_log('Found in $_POST[visit_company_selection]: ' . var_export($raw_value, true));
            
            // Normalize: empty string, "0", 0 -> null, otherwise int
            if ($raw_value === '' || $raw_value === '0' || $raw_value === 0) {
                $company_id = null;
            } else {
                $company_id = absint($raw_value);
                if ($company_id === 0) {
                    $company_id = null;
                }
            }
        }
        // Fallback to old 'company_id' for backward compatibility
        elseif (isset($_POST['company_id'])) {
            $raw_value = $_POST['company_id'];
            error_log('Found in $_POST[company_id]: ' . var_export($raw_value, true));
            
            // Normalize: empty string, "0", 0 -> null, otherwise int
            if ($raw_value === '' || $raw_value === '0' || $raw_value === 0) {
                $company_id = null;
            } else {
                $company_id = absint($raw_value);
                if ($company_id === 0) {
                    $company_id = null;
                }
            }
        }
        
        // Fallback to $data if POST was empty
        if ($company_id === null && isset($data['company_id'])) {
            $raw_value = $data['company_id'];
            error_log('Fallback to $data: ' . var_export($raw_value, true));
            
            if ($raw_value === '' || $raw_value === '0' || $raw_value === 0 || $raw_value === null) {
                $company_id = null;
            } else {
                $company_id = absint($raw_value);
                if ($company_id === 0) {
                    $company_id = null;
                }
            }
        }
        
        $data['company_id'] = $company_id;
        error_log('RESULT: Legal person - company_id = ' . var_export($company_id, true));
        
    } else {
        // ============================================
        // has_company NOT SET - shouldn't happen, but handle it
        // ============================================
        error_log('WARNING: has_company not set in POST');
        
        // For new records, default to null
        if (empty($data['id'])) {
            $data['company_id'] = null;
            error_log('RESULT: New record, no has_company - company_id = NULL');
        } else {
            // For existing records, keep existing value
            // (Don't modify $data['company_id'] - let it pass through)
            error_log('RESULT: Edit mode, no has_company - keeping existing value');
        }
    }
    
    // ============================================
    // STEP 3: Ensure customer_id is set
    // ============================================
    if (empty($data['customer_id'])) {
        if (class_exists('SAW_Context')) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        
        if (empty($data['customer_id'])) {
            return new WP_Error('missing_customer', 'Customer ID is required');
        }
    }
    
    // ============================================
    // STEP 4: Log final state
    // ============================================
    error_log('FINAL $data[company_id]: ' . var_export($data['company_id'], true));
    error_log('FINAL $data[customer_id]: ' . var_export($data['customer_id'] ?? 'NOT SET', true));
    error_log('========== END BEFORE_SAVE ==========');
    
    return $data;
}

    
    /**
     * Save visit schedules and hosts after main visit is saved
     * 
     * @since 7.0.0
     * @param int $visit_id Visit ID
     * @return void
     */
    protected function after_save($visit_id) {
        global $wpdb;
        
        if (!$visit_id || !isset($_POST)) {
            return;
        }
        
        // Get customer_id and branch_id for schedules
        $visit_data = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id, branch_id FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);

        if (!$visit_data || empty($visit_data['customer_id']) || empty($visit_data['branch_id'])) {
            return;
        }

        $customer_id = intval($visit_data['customer_id']);
        $branch_id = intval($visit_data['branch_id']);
        
        // Save visit schedules (days and times)
        $schedule_table = $wpdb->prefix . 'saw_visit_schedules';
        
        // Delete existing schedules
        $wpdb->delete($schedule_table, array('visit_id' => $visit_id), array('%d'));
        
        // Insert new schedules
        $schedule_dates = isset($_POST['schedule_dates']) && is_array($_POST['schedule_dates']) ? $_POST['schedule_dates'] : array();
        $schedule_times_from = isset($_POST['schedule_times_from']) && is_array($_POST['schedule_times_from']) ? $_POST['schedule_times_from'] : array();
        $schedule_times_to = isset($_POST['schedule_times_to']) && is_array($_POST['schedule_times_to']) ? $_POST['schedule_times_to'] : array();
        $schedule_notes = isset($_POST['schedule_notes']) && is_array($_POST['schedule_notes']) ? $_POST['schedule_notes'] : array();
        
        if (!empty($schedule_dates)) {
            foreach ($schedule_dates as $index => $date) {
                $date = trim($date);
                if (empty($date)) {
                    continue;
                }
                
                $date = sanitize_text_field($date);
                $time_from = isset($schedule_times_from[$index]) ? sanitize_text_field(trim($schedule_times_from[$index])) : '';
                $time_to = isset($schedule_times_to[$index]) ? sanitize_text_field(trim($schedule_times_to[$index])) : '';
                $notes = isset($schedule_notes[$index]) ? sanitize_textarea_field(trim($schedule_notes[$index])) : '';
                
                // ⭐ FIX: Convert empty time strings to NULL (prevents 00:00:00 in database)
                if (empty($time_from) || $time_from === '' || $time_from === '0:00' || $time_from === '00:00') {
                    $time_from = null;
                }
                if (empty($time_to) || $time_to === '' || $time_to === '0:00' || $time_to === '00:00') {
                    $time_to = null;
                }
                
                $wpdb->insert(
                    $schedule_table,
                    array(
                        'visit_id' => $visit_id,
                        'customer_id' => $customer_id,
                        'branch_id' => $branch_id,
                        'date' => $date,
                        'time_from' => $time_from,
                        'time_to' => $time_to,
                        'notes' => $notes,
                        'sort_order' => intval($index)
                    ),
                    array('%d', '%d', '%d', '%s', $time_from === null ? null : '%s', $time_to === null ? null : '%s', '%s', '%d')
                );
            }
        }
        
        // Calculate planned_date_from and planned_date_to
        $planned_date_from = null;
        $planned_date_to = null;
        
        // First, try to get from schedule_dates
        if (!empty($schedule_dates)) {
            $dates = array();
            foreach ($schedule_dates as $date) {
                $date = trim($date);
                if (!empty($date)) {
                    $dates[] = $date;
                }
            }
            
            if (!empty($dates)) {
                sort($dates);
                $planned_date_from = $dates[0];
                $planned_date_to = end($dates);
            }
        }
        
        // Fallback: If schedule_dates is empty, try to get directly from POST
        if (empty($planned_date_from) && isset($_POST['planned_date_from'])) {
            $planned_date_from = sanitize_text_field($_POST['planned_date_from']);
        }
        
        if (empty($planned_date_to) && isset($_POST['planned_date_to'])) {
            $planned_date_to = sanitize_text_field($_POST['planned_date_to']);
        }
        
        // Update main table if we have dates
        if (!empty($planned_date_from) || !empty($planned_date_to)) {
            // ========================================
            // PŘED UPDATE - získej staré datum pro porovnání
            // ========================================
            $old_visit = $wpdb->get_row($wpdb->prepare(
                "SELECT planned_date_from, planned_date_to FROM {$wpdb->prefix}saw_visits WHERE id = %d",
                $visit_id
            ), ARRAY_A);
            
            $old_date_from = $old_visit['planned_date_from'] ?? null;
            $old_date_to = $old_visit['planned_date_to'] ?? null;
            
            $update_data = array();
            $update_format = array();
            
            if (!empty($planned_date_from)) {
                $update_data['planned_date_from'] = $planned_date_from;
                $update_format[] = '%s';
            }
            
            if (!empty($planned_date_to)) {
                $update_data['planned_date_to'] = $planned_date_to;
                $update_format[] = '%s';
            }
            
            if (!empty($update_data)) {
                $wpdb->update(
                    $wpdb->prefix . 'saw_visits',
                    $update_data,
                    array('id' => $visit_id),
                    $update_format,
                    array('%d')
                );
                
                // ========================================
                // NOTIFICATION TRIGGER: visit_rescheduled
                // Detekce změny data návštěvy
                // ========================================
                $date_changed = false;
                $old_date = null;
                $new_date = null;
                
                if ($old_date_from !== $planned_date_from) {
                    $date_changed = true;
                    $old_date = $old_date_from;
                    $new_date = $planned_date_from;
                    
                    // ⭐ Recalculate risks_status when visit date changes
                    $this->model->update_risks_status($visit_id);
                } elseif ($old_date_to !== $planned_date_to) {
                    $date_changed = true;
                    $old_date = $old_date_to;
                    $new_date = $planned_date_to;
                }
                
                if ($date_changed && $old_date && $new_date && $old_date !== $new_date) {
                    do_action('saw_visit_rescheduled', $visit_id, $old_date, $new_date);
                }
            }
        }
        
        // Save visit hosts
        $hosts_table = $wpdb->prefix . 'saw_visit_hosts';
        
        // Získej stávající hostitele PŘED smazáním (pro porovnání)
        $existing_hosts_before = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$hosts_table} WHERE visit_id = %d",
            $visit_id
        ));
        $existing_hosts_before = array_map('intval', $existing_hosts_before);
        
        // Delete existing hosts
        $wpdb->delete($hosts_table, array('visit_id' => $visit_id), array('%d'));
        
        // Insert new hosts
        $hosts = isset($_POST['hosts']) && is_array($_POST['hosts']) ? $_POST['hosts'] : array();
        $hosts = array_map('intval', $hosts);
        
        if (!empty($hosts)) {
            foreach ($hosts as $user_id) {
                if ($user_id > 0) {
                    $wpdb->insert(
                        $hosts_table,
                        array(
                            'visit_id' => $visit_id,
                            'user_id' => $user_id
                        ),
                        array('%d', '%d')
                    );
                }
            }
        }
        
        // ========================================
        // NOTIFICATION TRIGGER: visit_assigned
        // Notifikace hostitelům o přiřazení k návštěvě
        // ========================================
        $new_hosts = array_diff($hosts, $existing_hosts_before);
        foreach ($new_hosts as $host_user_id) {
            if ($host_user_id > 0) {
                do_action('saw_visit_host_assigned', $visit_id, $host_user_id);
            }
        }
        
        // Update PIN expiration when dates change
        if (method_exists($this->model, 'update_pin_expiration')) {
            $this->model->update_pin_expiration($visit_id);
        }
        
        // ========================================
        // SAVE VISITORS FROM JSON
        // ========================================
        $this->save_visitors_from_json($visit_id);
        
        // ========================================
        // SAVE ACTION-SPECIFIC INFORMATION
        // ========================================
        $this->save_action_info($visit_id, $customer_id, $branch_id);
    }
    
    /**
     * Save action-specific information
     * 
     * @param int $visit_id Visit ID
     * @param int $customer_id Customer ID
     * @param int $branch_id Branch ID
     */
    protected function save_action_info($visit_id, $customer_id, $branch_id) {
        global $wpdb;
        
        $has_action_info = !empty($_POST['has_action_info']);
        
        if (!$has_action_info) {
            // Delete all action info for this visit
            $wpdb->delete($wpdb->prefix . 'saw_visit_action_info', array('visit_id' => $visit_id), array('%d'));
            $wpdb->delete($wpdb->prefix . 'saw_visit_action_documents', array('visit_id' => $visit_id), array('%d'));
            $wpdb->delete($wpdb->prefix . 'saw_visit_action_oopp', array('visit_id' => $visit_id), array('%d'));
            return;
        }
        
        $user_email = wp_get_current_user()->user_email;
        
        // ============================================
        // 1. Save/update action info text
        // ============================================
        $content_text = isset($_POST['action_content_text']) 
            ? wp_kses_post($_POST['action_content_text']) 
            : '';
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}saw_visit_action_info WHERE visit_id = %d",
            $visit_id
        ));
        
        if ($existing) {
            $wpdb->update(
                $wpdb->prefix . 'saw_visit_action_info',
                array(
                    'content_text' => $content_text,
                    'updated_by' => $user_email,
                ),
                array('visit_id' => $visit_id),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'saw_visit_action_info',
                array(
                    'visit_id' => $visit_id,
                    'customer_id' => $customer_id,
                    'branch_id' => $branch_id,
                    'content_text' => $content_text,
                    'created_by' => $user_email,
                ),
                array('%d', '%d', '%d', '%s', '%s')
            );
        }
        
        // ============================================
        // 2. Handle documents
        // ============================================
        // Keep existing documents that are still in the form
        $keep_doc_ids = isset($_POST['action_document_ids']) 
            ? array_map('intval', $_POST['action_document_ids']) 
            : array();
        
        if (!empty($keep_doc_ids)) {
            $placeholders = implode(',', array_fill(0, count($keep_doc_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->prefix}saw_visit_action_documents 
                 WHERE visit_id = %d AND id NOT IN ({$placeholders})",
                array_merge(array($visit_id), $keep_doc_ids)
            ));
        } else {
            $wpdb->delete($wpdb->prefix . 'saw_visit_action_documents', array('visit_id' => $visit_id), array('%d'));
        }
        
        // Upload new documents
        if (!empty($_FILES['action_documents']['name'][0])) {
            $this->upload_action_documents($visit_id, $customer_id);
        }
        
        // ============================================
        // 3. Handle OOPP
        // ============================================
        $wpdb->delete($wpdb->prefix . 'saw_visit_action_oopp', array('visit_id' => $visit_id), array('%d'));
        
        $oopp_ids = isset($_POST['action_oopp_ids']) 
            ? array_map('intval', $_POST['action_oopp_ids']) 
            : array();
        
        foreach ($oopp_ids as $sort => $oopp_id) {
            $is_required = isset($_POST['action_oopp_required'][$oopp_id]) ? 1 : 0;
            
            $wpdb->insert(
                $wpdb->prefix . 'saw_visit_action_oopp',
                array(
                    'visit_id' => $visit_id,
                    'oopp_id' => $oopp_id,
                    'is_required' => $is_required,
                    'sort_order' => $sort,
                    'created_by' => $user_email,
                ),
                array('%d', '%d', '%d', '%d', '%s')
            );
        }
    }
    
    /**
     * Upload action documents
     */
    private function upload_action_documents($visit_id, $customer_id) {
        global $wpdb;
        
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['basedir'] . '/saw-visitors/action-docs/' . $visit_id . '/';
        
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }
        
        $files = $_FILES['action_documents'];
        $count = count($files['name']);
        
        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            $file_name = sanitize_file_name($files['name'][$i]);
            $file_path = $target_dir . $file_name;
            
            if (move_uploaded_file($files['tmp_name'][$i], $file_path)) {
                $relative_path = 'saw-visitors/action-docs/' . $visit_id . '/' . $file_name;
                
                $wpdb->insert(
                    $wpdb->prefix . 'saw_visit_action_documents',
                    array(
                        'visit_id' => $visit_id,
                        'customer_id' => $customer_id,
                        'file_path' => $relative_path,
                        'file_name' => $files['name'][$i],
                        'file_size' => $files['size'][$i],
                        'mime_type' => $files['type'][$i],
                        'uploaded_by' => wp_get_current_user()->user_email,
                    ),
                    array('%d', '%d', '%s', '%s', '%d', '%s', '%s')
                );
            }
        }
    }

    /**
     * Format detail data for sidebar
     *
     * @param array $item Visit data
     * @return array Formatted visit data
     */
    protected function format_detail_data($item) {
        if (empty($item) || empty($item['id'])) {
            return $item;
        }
        
        global $wpdb;
        
        // Single query: Load visit, company, branch, visitor count
        $query = $wpdb->prepare(
            "SELECT 
                v.*,
                c.name as company_name,
                c.ico as company_ico,
                c.street as company_street,
                c.city as company_city,
                c.zip as company_zip,
                b.name as branch_name,
                COUNT(DISTINCT vis.id) as visitor_count,
                (
                    SELECT CONCAT(first_name, ' ', last_name)
                    FROM {$wpdb->prefix}saw_visitors
                    WHERE visit_id = v.id
                    ORDER BY id ASC
                    LIMIT 1
                ) as first_visitor_name
            FROM {$wpdb->prefix}saw_visits v
            LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
            LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
            LEFT JOIN {$wpdb->prefix}saw_visitors vis ON v.id = vis.visit_id
            WHERE v.id = %d
            GROUP BY v.id",
            $item['id']
        );
        
        $enriched = $wpdb->get_row($query, ARRAY_A);
        
        if (!$enriched) {
            return $item;
        }
        
        $item = array_merge($item, $enriched);
        
        // Load action info if exists
        if (method_exists($this->model, 'get_action_info')) {
            $action_info = $this->model->get_action_info($item['id']);
            if ($action_info) {
                $item['action_info'] = $action_info;
            }
        }
        
        // Load hosts (separate query due to many-to-many)
        $hosts_query = $wpdb->prepare(
            "SELECT 
                GROUP_CONCAT(
                    CONCAT_WS(':', u.id, u.first_name, u.last_name, u.email, u.role)
                    ORDER BY u.last_name, u.first_name
                    SEPARATOR '|'
                ) as hosts_data
             FROM {$wpdb->prefix}saw_visit_hosts vh
             INNER JOIN {$wpdb->prefix}saw_users u ON vh.user_id = u.id
             WHERE vh.visit_id = %d",
            $item['id']
        );
        
        $hosts_result = $wpdb->get_row($hosts_query, ARRAY_A);
        
        // Parse hosts data
        $hosts = array();
        if (!empty($hosts_result['hosts_data'])) {
            foreach (explode('|', $hosts_result['hosts_data']) as $host_str) {
                $parts = explode(':', $host_str);
                if (count($parts) >= 5) {
                    $hosts[] = array(
                        'id' => $parts[0],
                        'first_name' => $parts[1],
                        'last_name' => $parts[2],
                        'email' => $parts[3],
                        'role' => $parts[4],
                    );
                }
            }
        }
        
        $item['hosts'] = $hosts;
        
        // Format company data object (for backward compatibility)
        if (!empty($item['company_id'])) {
            $item['company_data'] = array(
                'id' => $item['company_id'],
                'name' => $item['company_name'] ?? '',
                'ico' => $item['company_ico'] ?? '',
                'street' => $item['company_street'] ?? '',
                'city' => $item['company_city'] ?? '',
                'zip' => $item['company_zip'] ?? '',
            );
        }
        
        // Format audit fields and dates (audit history support)
        // Handle visits table which uses created_by_email instead of created_by
        if (isset($item['created_by_email']) && !isset($item['created_by'])) {
            $item['created_by'] = $item['created_by_email'];
        }
        
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
        
        // Load change history for this visit
        if (!empty($item['id']) && class_exists('SAW_Audit')) {
            try {
                $entity_type = $this->config['entity'] ?? 'visits';
                $change_history = SAW_Audit::get_entity_history($entity_type, $item['id']);
                if (!empty($change_history)) {
                    $item['change_history'] = $change_history;
                    $item['has_audit_info'] = true;
                }
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SAW Audit] Failed to load change history for visits: ' . $e->getMessage());
                }
            }
        }
        
        return $item;
    }
    
    /**
     * Get display name for detail header
     * 
     * @since 7.0.0
     * @param array $item Item data
     * @return string Display name
     */
    public function get_display_name($item) {
        if (empty($item)) return '';
        
        $is_physical_person = empty($item['company_id']);
        
        if ($is_physical_person) {
            if (!empty($item['first_visitor_name'])) {
                return $item['first_visitor_name'];
            }
            return 'Fyzická osoba';
        } else {
            return $item['company_name'] ?? 'Firma #' . $item['company_id'];
        }
    }
    
    /**
     * Get detail header meta (badges)
     * 
     * @since 7.0.0
     * @param array $item Item data
     * @return string HTML for header meta
     */
    public function get_detail_header_meta($item = null) {
        if (empty($item)) {
            return '';
        }
        
        // Load translations
        $lang = 'cs';
        if (class_exists('SAW_Component_Language_Switcher')) {
            $lang = SAW_Component_Language_Switcher::get_user_language();
        }
        $t = function_exists('saw_get_translations') 
            ? saw_get_translations($lang, 'admin', 'visits') 
            : [];
        
        $tr = function($key, $fallback = null) use ($t) {
            return $t[$key] ?? $fallback ?? $key;
        };
        
        $meta_parts = array();
        
        // Visitor count badge
        $visitor_count = intval($item['visitor_count'] ?? 0);
        
        if ($visitor_count === 1) {
            $person_word = $tr('person_singular', 'osoba');
        } elseif ($visitor_count >= 2 && $visitor_count <= 4) {
            $person_word = $tr('person_few', 'osoby');
        } else {
            $person_word = $tr('person_many', 'osob');
        }
        
        $meta_parts[] = '<span class="saw-badge-transparent">👥 ' . $visitor_count . ' ' . esc_html($person_word) . '</span>';
        
        // Visit type badge
        if (!empty($item['visit_type'])) {
            $type_labels = array(
                'planned' => $tr('type_planned', 'Plánovaná'),
                'walk_in' => $tr('type_walk_in', 'Walk-in'),
            );
            $type_label = $type_labels[$item['visit_type']] ?? $item['visit_type'];
            $type_class = $item['visit_type'] === 'walk_in' ? 'saw-badge-warning' : 'saw-badge-info';
            $meta_parts[] = '<span class="saw-badge-transparent ' . esc_attr($type_class) . '">' . esc_html($type_label) . '</span>';
        }
        
        // Status badge
        if (!empty($item['status'])) {
            $status_labels = array(
                'draft' => $tr('status_draft', 'Koncept'),
                'pending' => $tr('status_pending', 'Čekající'),
                'confirmed' => $tr('status_confirmed', 'Potvrzená'),
                'in_progress' => $tr('status_in_progress', 'Probíhající'),
                'completed' => $tr('status_completed', 'Dokončená'),
                'cancelled' => $tr('status_cancelled', 'Zrušená'),
            );
            $status_classes = array(
                'draft' => 'saw-badge-secondary',
                'pending' => 'saw-badge-warning',
                'confirmed' => 'saw-badge-info',
                'in_progress' => 'saw-badge-primary',
                'completed' => 'saw-badge-success',
                'cancelled' => 'saw-badge-danger',
            );
            $status_label = $status_labels[$item['status']] ?? $item['status'];
            $status_class = $status_classes[$item['status']] ?? 'saw-badge-secondary';
            $meta_parts[] = '<span class="saw-badge-transparent ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
        }
        
        return implode('', $meta_parts);
    }
    
    // ============================================
    // AJAX HANDLERS
    // ============================================
    
    /**
     * AJAX: Change visit status
     * 
     * @since 3.4.0
     */
    public function ajax_change_visit_status() {
        saw_verify_ajax_unified();
        
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['new_status'] ?? '');
        
        if (!$visit_id) {
            wp_send_json_error(['message' => 'Neplatné ID návštěvy']);
        }
        
        if (!$this->can('edit')) {
            wp_send_json_error(['message' => 'Nemáte oprávnění měnit stav návštěvy']);
        }
        
        $valid_statuses = ['draft', 'pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(['message' => 'Neplatný stav: ' . $new_status]);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_visits';
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status, started_at, completed_at FROM {$table} WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            wp_send_json_error(['message' => 'Návštěva nenalezena']);
        }
        
        if ($visit['status'] === $new_status) {
            wp_send_json_error(['message' => 'Návštěva již má tento stav']);
        }
        
        $update_data = ['status' => $new_status];
        $update_format = ['%s'];
        
        $now = current_time('mysql');
        
        if ($new_status === 'in_progress' && empty($visit['started_at'])) {
            $update_data['started_at'] = $now;
            $update_format[] = '%s';
        }
        
        if ($new_status === 'completed') {
            $update_data['completed_at'] = $now;
            $update_format[] = '%s';
        }
        
        if ($visit['status'] === 'completed' && $new_status !== 'completed') {
            $update_data['completed_at'] = null;
            $update_format[] = null;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            ['id' => $visit_id],
            $update_format,
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Chyba databáze: ' . $wpdb->last_error]);
        }
        
        if (class_exists('SAW_Cache')) {
            SAW_Cache::flush('visits');
        }
        
        // ========================================
        // NOTIFICATION TRIGGER: visit_cancelled
        // Notifikace o zrušení návštěvy
        // ========================================
        if ($new_status === 'cancelled' && $visit['status'] !== 'cancelled') {
            do_action('saw_visit_cancelled', $visit_id, '');
        }
        
        $status_labels = [
            'draft' => 'Koncept',
            'pending' => 'Čeká',
            'confirmed' => 'Potvrzeno',
            'in_progress' => 'Probíhá',
            'completed' => 'Dokončeno',
            'cancelled' => 'Zrušeno',
        ];
        
        wp_send_json_success([
            'message' => 'Stav byl úspěšně změněn',
            'new_status' => $new_status,
            'new_status_label' => $status_labels[$new_status] ?? $new_status,
        ]);
    }
    
    /**
 * AJAX: Extend PIN expiration
 * 
 * Allows extending PIN validity by hours or to exact datetime.
 * 
     * @since 4.8.0
     * @updated 3.6.0 - Cleaner exact_expiry handling
     */
    public function ajax_extend_pin() {
        saw_verify_ajax_unified();
        
        if (!$this->can('edit')) {
            wp_send_json_error(['message' => 'Nemáte oprávnění']);
        }
        
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $hours = intval($_POST['hours'] ?? 0);
        $exact_expiry = sanitize_text_field($_POST['exact_expiry'] ?? '');
        
        if (!$visit_id) {
            wp_send_json_error(['message' => 'Neplatné ID návštěvy']);
        }
        
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT pin_code, pin_expires_at FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit || empty($visit['pin_code'])) {
            wp_send_json_error(['message' => 'Návštěva nemá PIN kód']);
        }
        
        // Calculate new expiry
        if (!empty($exact_expiry)) {
            // Exact datetime provided
            try {
                $tz_prague = new DateTimeZone('Europe/Prague');
                $dt = new DateTime($exact_expiry, $tz_prague);
                $new_expiry = $dt->format('Y-m-d H:i:s');
                
                $now = new DateTime('now', $tz_prague);
                if ($dt <= $now) {
                    wp_send_json_error(['message' => 'Datum musí být v budoucnosti']);
                }
            } catch (Exception $e) {
                wp_send_json_error(['message' => 'Neplatný formát data']);
            }
        } elseif ($hours > 0) {
            // Validate hours range
            if ($hours > 720) {
                wp_send_json_error(['message' => 'Maximální prodloužení je 720 hodin (30 dní)']);
            }
            
            // Relative extension - extend from current expiry if still valid
            $current = $visit['pin_expires_at'];
            $base = ($current && strtotime($current) > time()) ? $current : current_time('mysql');
            $new_expiry = date('Y-m-d H:i:s', strtotime($base . " +{$hours} hours"));
        } else {
            wp_send_json_error(['message' => 'Chybí parametr hours nebo exact_expiry']);
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            ['pin_expires_at' => $new_expiry],
            ['id' => $visit_id],
            ['%s'],
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Chyba databáze: ' . $wpdb->last_error]);
        }
        
        // Clear cache
        if (class_exists('SAW_Cache')) {
            SAW_Cache::flush('visits');
        }
        
        wp_send_json_success([
            'new_expiry' => date_i18n('d.m.Y H:i', strtotime($new_expiry)),
            'new_expiry_raw' => $new_expiry,
            'is_valid' => strtotime($new_expiry) > time(),
            'message' => 'Platnost PIN prodloužena'
        ]);
    }

    /**
     * AJAX: Extend invitation token expiration
     * 
     * Allows extending token validity by hours or to exact datetime.
     * 
     * @since 3.6.0
     */
    public function ajax_extend_token() {
        saw_verify_ajax_unified();
        
        if (!$this->can('edit')) {
            wp_send_json_error(['message' => 'Nemáte oprávnění']);
        }
        
        $visit_id = intval($_POST['visit_id'] ?? 0);
        $hours = intval($_POST['hours'] ?? 0);
        $exact_expiry = sanitize_text_field($_POST['exact_expiry'] ?? '');
        
        if (!$visit_id) {
            wp_send_json_error(['message' => 'Neplatné ID návštěvy']);
        }
        
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT invitation_token, invitation_token_expires_at 
             FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit || empty($visit['invitation_token'])) {
            wp_send_json_error(['message' => 'Návštěva nemá registrační odkaz']);
        }
        
        // Calculate new expiry
        if (!empty($exact_expiry)) {
            // Exact datetime provided
            try {
                $tz_prague = new DateTimeZone('Europe/Prague');
                $dt = new DateTime($exact_expiry, $tz_prague);
                $new_expiry = $dt->format('Y-m-d H:i:s');
                
                $now = new DateTime('now', $tz_prague);
                if ($dt <= $now) {
                    wp_send_json_error(['message' => 'Datum musí být v budoucnosti']);
                }
            } catch (Exception $e) {
                wp_send_json_error(['message' => 'Neplatný formát data']);
            }
        } elseif ($hours > 0) {
            // Relative extension
            $current = $visit['invitation_token_expires_at'];
            // Extend from current expiry if still valid, otherwise from now
            $base = ($current && strtotime($current) > time()) ? $current : current_time('mysql');
            $new_expiry = date('Y-m-d H:i:s', strtotime($base . " +{$hours} hours"));
        } else {
            wp_send_json_error(['message' => 'Chybí parametr hours nebo exact_expiry']);
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            ['invitation_token_expires_at' => $new_expiry],
            ['id' => $visit_id],
            ['%s'],
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Chyba databáze: ' . $wpdb->last_error]);
        }
        
        // Clear cache
        if (class_exists('SAW_Cache')) {
            SAW_Cache::flush('visits');
        }
        
        wp_send_json_success([
            'new_expiry' => date_i18n('d.m.Y H:i', strtotime($new_expiry)),
            'new_expiry_raw' => $new_expiry,
            'is_valid' => strtotime($new_expiry) > time(),
            'message' => 'Platnost odkazu prodloužena'
        ]);
    }

    /**
     * AJAX: Generate PIN code for visit
     * 
     * @since 4.8.0
     */
    public function ajax_generate_pin() {
        saw_verify_ajax_unified();
        
        $visit_id = intval($_POST['visit_id'] ?? 0);
        
        if (!$visit_id) {
            wp_send_json_error(['message' => 'Neplatné ID návštěvy']);
        }
        
        if (!$this->can('edit')) {
            wp_send_json_error(['message' => 'Nemáte oprávnění']);
        }
        
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT id, customer_id, pin_code, visit_type, status FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            wp_send_json_error(['message' => 'Návštěva nenalezena']);
        }
        
        if (!empty($visit['pin_code'])) {
            wp_send_json_error(['message' => 'PIN již existuje']);
        }
        
        if ($visit['visit_type'] !== 'planned') {
            wp_send_json_error(['message' => 'PIN lze vygenerovat pouze pro plánované návštěvy']);
        }
        
        $pin_code = $this->model->generate_pin($visit_id);
        
        if (!$pin_code) {
            wp_send_json_error(['message' => 'Nepodařilo se vygenerovat PIN. Zkuste to znovu.']);
        }
        
        $updated_visit = $wpdb->get_row($wpdb->prepare(
            "SELECT pin_code, pin_expires_at FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$updated_visit) {
            wp_send_json_error(['message' => 'Chyba při načítání vygenerovaných dat']);
        }
        
        $expiry_formatted = 'N/A';
        if (!empty($updated_visit['pin_expires_at'])) {
            $expiry_timestamp = strtotime($updated_visit['pin_expires_at']);
            if ($expiry_timestamp !== false) {
                $expiry_formatted = date('d.m.Y H:i', $expiry_timestamp);
            }
        }
        
        wp_send_json_success([
            'pin_code' => $pin_code,
            'pin_expires_at' => $expiry_formatted,
            'pin_expires_at_raw' => $updated_visit['pin_expires_at'],
            'message' => 'PIN byl úspěšně vygenerován'
        ]);
    }
    
    /**
 * AJAX: Send invitation email
 * 
 * Generates PIN (if needed), creates invitation token, and sends email.
 * Uses unified expiry calculation for consistency.
 * 
 * @since 1.0.0
 * @updated 3.6.0 - Use unified expiry calculation from model
 */
public function ajax_send_invitation() {
    saw_verify_ajax_unified();
    
    if (!$this->can('edit')) {
        wp_send_json_error(['message' => 'Nemáte oprávnění']);
    }
    
    $visit_id = intval($_POST['visit_id'] ?? 0);
    
    if (!$visit_id) {
        wp_send_json_error(['message' => 'Neplatné ID návštěvy']);
    }
    
    $visit = $this->model->get_by_id($visit_id);
    
    if (!$visit) {
        wp_send_json_error(['message' => 'Návštěva nenalezena']);
    }
    
    if (empty($visit['invitation_email'])) {
        wp_send_json_error(['message' => 'Email pro pozvánku není vyplněn']);
    }
    
    // Generate PIN if not exists
    if (empty($visit['pin_code'])) {
        $pin = $this->model->generate_pin($visit_id);
        if (!$pin) {
            wp_send_json_error(['message' => 'Nepodařilo se vygenerovat PIN']);
        }
        // Refresh visit data
        $visit = $this->model->get_by_id($visit_id);
    }
    
    // Generate unique token
    $token = $this->model->ensure_unique_token($visit['customer_id']);
    
    // Calculate expiry using unified method from model (v3.6.0)
    $end_date = $this->model->get_effective_end_date($visit_id);
    $expires = $this->model->calculate_expiry($end_date);
    
    global $wpdb;
    $result = $wpdb->update(
        $wpdb->prefix . 'saw_visits',
        [
            'invitation_token' => $token,
            'invitation_token_expires_at' => $expires,
        ],
        ['id' => $visit_id],
        ['%s', '%s'],
        ['%d']
    );
    
    if ($result === false) {
        wp_send_json_error(['message' => 'Chyba při ukládání tokenu']);
    }
    
    // Send email via email service
    if (!function_exists('saw_email')) {
        wp_send_json_error(['message' => 'Email služba není dostupná']);
    }
    
    $email_service = saw_email();
    
    if (!$email_service) {
        wp_send_json_error(['message' => 'Email služba se nepodařila inicializovat']);
    }
    
    $email_result = $email_service->send_invitation($visit_id);
    
    if (is_wp_error($email_result)) {
        wp_send_json_error(['message' => 'Chyba při odesílání: ' . $email_result->get_error_message()]);
    }
    
    // Update invitation_sent_at
    $wpdb->update(
        $wpdb->prefix . 'saw_visits',
        ['invitation_sent_at' => current_time('mysql')],
        ['id' => $visit_id]
    );
    
    // Clear cache
    if (class_exists('SAW_Cache')) {
        SAW_Cache::flush('visits');
    }
    
    $link = home_url('/visitor-invitation/' . $token . '/');
    
    wp_send_json_success([
        'message' => 'Pozvánka byla úspěšně odeslána',
        'email' => $visit['invitation_email'],
        'link' => $link,
        'sent_at' => current_time('d.m.Y H:i'),
        'expires_at' => date_i18n('d.m.Y H:i', strtotime($expires))
    ]);
}
    
    /**
     * AJAX: Get hosts by branch
     * 
     * @since 1.0.0
     */
    public function ajax_get_hosts_by_branch() {
        saw_verify_ajax_unified();
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        if (!$branch_id) {
            wp_send_json_error(array('message' => 'Neplatná pobočka'));
            return;
        }
        
        $customer_id = 0;
        if (class_exists('SAW_Context')) {
            $customer_id = SAW_Context::get_customer_id();
        }
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'Nelze určit zákazníka'));
            return;
        }
        
        global $wpdb;
        
        $branch_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_branches 
             WHERE id = %d AND customer_id = %d AND is_active = 1",
            $branch_id,
            $customer_id
        ));
        
        if (!$branch_exists) {
            wp_send_json_error(array('message' => 'Pobočka nenalezena nebo nemáte oprávnění'));
            return;
        }
        
        $hosts = $wpdb->get_results($wpdb->prepare(
            "SELECT id, first_name, last_name, role, position 
             FROM {$wpdb->prefix}saw_users 
             WHERE customer_id = %d 
               AND branch_id = %d 
               AND role IN ('admin', 'super_manager', 'manager')
               AND is_active = 1 
             ORDER BY last_name, first_name",
            $customer_id,
            $branch_id
        ), ARRAY_A);
        
        if (is_array($hosts)) {
            foreach ($hosts as &$host) {
                $host['position'] = isset($host['position']) ? $host['position'] : '';
            }
            unset($host);
        }
        
        wp_send_json_success(array('hosts' => $hosts ? $hosts : array()));
    }
    
    /**
     * Get table columns configuration for infinite scroll
     * 
     * @since 7.0.0
     * @return array Columns configuration
     */
    protected function get_table_columns() {
        return array(
            'company_person' => array(
                'label' => 'Návštěvník',
                'type' => 'custom',
                'sortable' => true,
                'class' => 'saw-table-cell-bold',
                'width' => '280px', // ⭐ Zúžený sloupec
                'callback' => function($value, $item) {
                    if (!empty($item['company_id'])) {
                        echo '<div style="display: flex; align-items: center; gap: 8px;">';
                        echo '<strong>' . esc_html($item['company_name'] ?? 'Firma #' . $item['company_id']) . '</strong>';
                        echo '<span class="saw-badge saw-badge-info" style="font-size: 11px;">🏢 Firma</span>';
                        echo '</div>';
                    } else {
                        echo '<div style="display: flex; align-items: center; gap: 8px;">';
                        if (!empty($item['first_visitor_name'])) {
                            echo '<strong style="color: #6366f1;">' . esc_html($item['first_visitor_name']) . '</strong>';
                        } else {
                            echo '<strong style="color: #6366f1;">Fyzická osoba</strong>';
                        }
                        echo '<span class="saw-badge" style="background: #6366f1; color: white; font-size: 11px;">👤 Fyzická</span>';
                        echo '</div>';
                    }
                },
            ),
            // ⭐ Sloupec pobočky odstraněn - zobrazují se jen data zvolené pobočky z branch switcher
            'visit_type' => array(
                'label' => 'Typ',
                'type' => 'badge',
                'sortable' => true,
                'width' => '120px',
                'map' => array(
                    'planned' => 'info',
                    'walk_in' => 'warning',
                ),
                'labels' => array(
                    'planned' => 'Plánovaná',
                    'walk_in' => 'Walk-in',
                ),
            ),
            'visitor_count' => array(
                'label' => 'Počet',
                'type' => 'custom',
                'width' => '100px',
                'align' => 'center',
                'callback' => function($value, $item) {
                    $count = intval($item['visitor_count'] ?? 0);
                    if ($count === 0) {
                        echo '<span style="color: #999;">—</span>';
                    } else {
                        echo '<strong style="color: #0066cc;">👥 ' . $count . '</strong>';
                    }
                },
            ),
            'risks_status' => array(
                'label' => 'Rizika',
                'type' => 'badge',
                'sortable' => true,
                'width' => '120px',
                'align' => 'center',
                'map' => array(
                    'pending' => 'secondary',
                    'completed' => 'success',
                    'missing' => 'danger',
                ),
                'labels' => array(
                    'pending' => 'Čeká se',
                    'completed' => 'OK',
                    'missing' => 'Chybí',
                ),
            ),
            'status' => array(
                'label' => 'Stav',
                'type' => 'badge',
                'sortable' => true,
                'width' => '140px',
                'map' => array(
                    'draft' => 'secondary',
                    'pending' => 'warning',
                    'confirmed' => 'info',
                    'in_progress' => 'primary',
                    'completed' => 'success',
                    'cancelled' => 'danger',
                ),
                'labels' => array(
                    'draft' => 'Koncept',
                    'pending' => 'Čekající',
                    'confirmed' => 'Potvrzená',
                    'in_progress' => 'Probíhající',
                    'completed' => 'Dokončená',
                    'cancelled' => 'Zrušená',
                ),
            ),
            'planned_date_from' => array(
                'label' => 'Datum návštěvy (od)',
                'type' => 'date',
                'sortable' => true,
                'width' => '140px',
                'format' => 'd.m.Y',
            ),
        );
    }
    
    /**
     * Save visitors from JSON data
     * 
     * Processes visitors_json POST data and performs INSERT/UPDATE/DELETE operations.
     * 
     * @since 7.1.0
     * @param int $visit_id Visit ID
     * @return void
     */
    private function save_visitors_from_json($visit_id) {
        global $wpdb;
        
        if (!$visit_id || !isset($_POST['visitors_json'])) {
            return;
        }
        
        $visitors_json = stripslashes($_POST['visitors_json']);
        $visitors = json_decode($visitors_json, true);
        
        if (!is_array($visitors)) {
            return;
        }
        
        // Získat customer_id a branch_id z návštěvy
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_id, branch_id FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit || empty($visit['customer_id']) || empty($visit['branch_id'])) {
            return;
        }
        
        $customer_id = intval($visit['customer_id']);
        $branch_id = intval($visit['branch_id']);
        $visitors_table = $wpdb->prefix . 'saw_visitors';
        
        foreach ($visitors as $visitor) {
            $status = sanitize_text_field($visitor['_status'] ?? '');
            $db_id = !empty($visitor['_dbId']) ? intval($visitor['_dbId']) : null;
            
            // Bezpečnostní kontrola: ověřit že _dbId patří k této návštěvě
            if ($db_id) {
                $belongs = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$visitors_table} WHERE id = %d AND visit_id = %d",
                    $db_id,
                    $visit_id
                ));
                
                if (!$belongs) {
                    continue; // Pokus o manipulaci s cizím záznamem
                }
            }
            
            switch ($status) {
                case 'new':
                    $this->insert_visitor($visit_id, $customer_id, $branch_id, $visitor);
                    break;
                    
                case 'modified':
                    if ($db_id) {
                        $this->update_visitor($db_id, $visitor);
                    }
                    break;
                    
                case 'deleted':
                    if ($db_id) {
                        $wpdb->delete($visitors_table, ['id' => $db_id], ['%d']);
                    }
                    break;
                    
                case 'existing':
                    // Beze změny - nic nedělat
                    break;
            }
        }
    }
    
    /**
     * Insert new visitor
     * 
     * @since 7.1.0
     * @param int $visit_id Visit ID
     * @param int $customer_id Customer ID
     * @param int $branch_id Branch ID
     * @param array $data Visitor data
     * @return int|false Inserted ID or false
     */
    private function insert_visitor($visit_id, $customer_id, $branch_id, $data) {
        global $wpdb;
        
        // Sanitizace
        $sanitized = $this->sanitize_visitor_data($data);
        
        // Validace povinných polí
        if (empty($sanitized['first_name']) || empty($sanitized['last_name'])) {
            return false;
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'saw_visitors',
            [
                'visit_id' => $visit_id,
                'customer_id' => $customer_id,
                'branch_id' => $branch_id,
                'first_name' => $sanitized['first_name'],
                'last_name' => $sanitized['last_name'],
                'email' => $sanitized['email'],
                'phone' => $sanitized['phone'],
                'position' => $sanitized['position'],
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update existing visitor
     * 
     * @since 7.1.0
     * @param int $visitor_id Visitor ID
     * @param array $data Visitor data
     * @return bool Success
     */
    private function update_visitor($visitor_id, $data) {
        global $wpdb;
        
        // Sanitizace
        $sanitized = $this->sanitize_visitor_data($data);
        
        // Validace povinných polí
        if (empty($sanitized['first_name']) || empty($sanitized['last_name'])) {
            return false;
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'saw_visitors',
            [
                'first_name' => $sanitized['first_name'],
                'last_name' => $sanitized['last_name'],
                'email' => $sanitized['email'],
                'phone' => $sanitized['phone'],
                'position' => $sanitized['position'],
                'updated_at' => current_time('mysql'),
            ],
            ['id' => $visitor_id],
            ['%s', '%s', '%s', '%s', '%s', '%s'],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Sanitize visitor data
     * 
     * @since 7.1.0
     * @param array $data Raw data
     * @return array Sanitized data
     */
    private function sanitize_visitor_data($data) {
        return [
            'first_name' => sanitize_text_field($data['first_name'] ?? ''),
            'last_name' => sanitize_text_field($data['last_name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'position' => sanitize_text_field($data['position'] ?? ''),
        ];
    }
    
    /**
 * AJAX: Send risks request email
 * 
 * @since 5.2.0
 */
public function ajax_send_risks_request() {
    // Error handler pro zachycení PHP chyb
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        wp_send_json_error(array(
            'message' => 'PHP Error: ' . $errstr,
            'file' => basename($errfile),
            'line' => $errline
        ));
        exit;
    });
    
    try {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
        }
        
        $visit_id = intval($_POST['visit_id'] ?? 0);
        
        if (!$visit_id) {
            wp_send_json_error(array('message' => 'Neplatné ID návštěvy'));
        }
        
        // Ověřit že návštěva existuje a má invitation_email
        global $wpdb;
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT id, customer_id, branch_id, invitation_email, invitation_token
             FROM {$wpdb->prefix}saw_visits
             WHERE id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            wp_send_json_error(array('message' => 'Návštěva nenalezena'));
        }
        
        if (empty($visit['invitation_email'])) {
            wp_send_json_error(array('message' => 'Návštěva nemá vyplněný email pro pozvánku'));
        }
        
        // Kontrola email služby
        if (!function_exists('saw_email')) {
            wp_send_json_error(array('message' => 'Funkce saw_email() neexistuje'));
        }
        
        $email_service = saw_email();
        
        if ($email_service === null) {
            wp_send_json_error(array('message' => 'saw_email() vrátilo null'));
        }
        
        if (!method_exists($email_service, 'send_risks_request')) {
            wp_send_json_error(array('message' => 'Metoda send_risks_request neexistuje'));
        }
        
        // Odeslat email
        $result = $email_service->send_risks_request($visit_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => 'WP_Error: ' . $result->get_error_message()));
        }
        
        wp_send_json_success(array(
            'message' => 'Email byl odeslán',
            'email' => $visit['invitation_email']
        ));
        
    } catch (Throwable $e) {
        wp_send_json_error(array(
            'message' => 'Exception: ' . $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ));
    }
    
    restore_error_handler();
}
}