<?php
/**
 * Visits Module Controller
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     3.1.0 - FIXED: Simplified to match Departments pattern
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
        
        // Register custom AJAX
        add_action('wp_ajax_saw_get_hosts_by_branch', array($this, 'ajax_get_hosts_by_branch'));
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'), 20); // Priority 20 to run after asset loader (default 10)
    }
    
    public function index() {
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die('Nemáte oprávnění.', 403);
        }
        $this->render_list_view();
    }
    
    public function enqueue_assets() {
        SAW_Asset_Loader::enqueue_module('visits');
        
        // Pass existing hosts to JS if editing
        // Use multiple fallback methods to detect visit ID from URL path (e.g., /admin/visits/41/edit)
        $visit_id = 0;
        $detection_method = 'none';
        
        // Method 1: Try get_sidebar_context() (primary - set by router)
        $context = $this->get_sidebar_context();
        if (!empty($context['id']) && ($context['mode'] === 'edit' || $context['mode'] === 'detail')) {
            $visit_id = intval($context['id']);
            $detection_method = 'sidebar_context';
        }
        
        // Method 2: Parse URL from REQUEST_URI as fallback (if sidebar context not available yet)
        if ($visit_id === 0 && isset($_SERVER['REQUEST_URI'])) {
            $request_uri = sanitize_text_field($_SERVER['REQUEST_URI']);
            // Match patterns like /admin/visits/41/edit or /admin/visits/41/ or /admin/visits/41
            if (preg_match('#/admin/visits/(\d+)(?:/edit|/)?#', $request_uri, $matches)) {
                $visit_id = intval($matches[1]);
                $detection_method = 'url_parsing';
            }
        }
        
        // Method 3: Fallback to $_GET['id'] for backward compatibility
        if ($visit_id === 0 && isset($_GET['id'])) {
            $visit_id = intval($_GET['id']);
            $detection_method = 'get_param';
        }
        
        // Method 4: Try $_GET['saw_path'] as last resort
        if ($visit_id === 0 && isset($_GET['saw_path'])) {
            $path = sanitize_text_field($_GET['saw_path']);
            if (preg_match('/visits\/(\d+)/', $path, $matches)) {
                $visit_id = intval($matches[1]);
                $detection_method = 'saw_path';
            }
        }
        
        // Debug logging
        if ($visit_id > 0) {
            error_log(sprintf('[Visits] enqueue_assets: Detected visit_id=%d using method=%s, REQUEST_URI=%s', 
                $visit_id, 
                $detection_method,
                isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'N/A'
            ));
        }
        
        $existing_hosts = array();
        if ($visit_id > 0) {
            global $wpdb;
            $existing_hosts = $wpdb->get_col($wpdb->prepare(
                "SELECT user_id FROM {$wpdb->prefix}saw_visit_hosts WHERE visit_id = %d",
                $visit_id
            ));
            $existing_hosts = array_map('intval', $existing_hosts);
            
            error_log(sprintf('[Visits] enqueue_assets: Loaded %d existing hosts for visit_id=%d', 
                count($existing_hosts), 
                $visit_id
            ));
        }
        
        // Localize script with correct handle that matches asset loader
        // Asset loader uses 'saw-module-visits' handle and creates 'sawVisits' object
        // CRITICAL: Override asset loader's nonce (saw_visits_ajax) with saw_ajax_nonce to match AJAX handler
        // Note: JS will use sawGlobal.nonce as primary source (saw_ajax_nonce) to avoid nonce conflicts
        // This wp_localize_script runs after enqueue_module due to priority 20, so it should override asset loader's values
        $script_handle = 'saw-module-visits';
        
        // Always localize - wp_localize_script will handle if script is not enqueued yet
        // This ensures existing_hosts is available even if script loads later
        wp_localize_script($script_handle, 'sawVisits', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce'), // Must match check_ajax_referer('saw_ajax_nonce', 'nonce') in ajax_get_hosts_by_branch
            'existing_hosts' => $existing_hosts
        ));
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
        if (empty($data['customer_id']) && class_exists('SAW_Context')) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
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
                // Skip empty dates
                $date = trim($date);
                if (empty($date)) {
                    continue;
                }
                
                // Sanitize and get values
                $date = sanitize_text_field($date);
                $time_from = isset($schedule_times_from[$index]) ? sanitize_text_field(trim($schedule_times_from[$index])) : '';
                $time_to = isset($schedule_times_to[$index]) ? sanitize_text_field(trim($schedule_times_to[$index])) : '';
                $notes = isset($schedule_notes[$index]) ? sanitize_textarea_field(trim($schedule_notes[$index])) : '';
                
                // Insert schedule
                $result = $wpdb->insert(
                    $schedule_table,
                    array(
                        'visit_id' => $visit_id,
                        'date' => $date,
                        'time_from' => $time_from,
                        'time_to' => $time_to,
                        'notes' => $notes,
                        'sort_order' => intval($index)
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%d')
                );
                
                // Log error if insert failed
                if ($result === false) {
                    error_log('Failed to insert visit schedule: ' . $wpdb->last_error);
                }
            }
        }
        
        // Save visit hosts
        $hosts_table = $wpdb->prefix . 'saw_visit_hosts';
        
        // Delete existing hosts
        $wpdb->delete($hosts_table, array('visit_id' => $visit_id), array('%d'));
        
        // Insert new hosts
        $hosts = isset($_POST['hosts']) && is_array($_POST['hosts']) ? $_POST['hosts'] : array();
        
        if (!empty($hosts)) {
            foreach ($hosts as $user_id) {
                $user_id = intval($user_id);
                if ($user_id > 0) {
                    $result = $wpdb->insert(
                        $hosts_table,
                        array(
                            'visit_id' => $visit_id,
                            'user_id' => $user_id
                        ),
                        array('%d', '%d')
                    );
                    
                    // Log error if insert failed
                    if ($result === false) {
                        error_log('Failed to insert visit host: ' . $wpdb->last_error);
                    }
                }
            }
        }
    }

    protected function format_detail_data($item) {
        if (empty($item)) return $item;
        
        global $wpdb;
        
        // Load company data and name
        if (!empty($item['company_id'])) {
            $company = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}saw_companies WHERE id = %d", $item['company_id']), ARRAY_A);
            $item['company_data'] = $company;
            if ($company) {
                $item['company_name'] = $company['name'];
            }
        }
        
        // Load branch name
        if (!empty($item['branch_id'])) {
            $branch = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$wpdb->prefix}saw_branches WHERE id = %d", $item['branch_id']), ARRAY_A);
            if ($branch) $item['branch_name'] = $branch['name'];
        }
        
        // Load visitor count
        if (!empty($item['id'])) {
            $item['visitor_count'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors WHERE visit_id = %d",
                $item['id']
            ));
        }
        
        // Load first visitor name for physical persons
        if (empty($item['company_id']) && !empty($item['id'])) {
            $first_visitor = $wpdb->get_row($wpdb->prepare(
                "SELECT CONCAT(first_name, ' ', last_name) as name FROM {$wpdb->prefix}saw_visitors WHERE visit_id = %d ORDER BY id ASC LIMIT 1",
                $item['id']
            ), ARRAY_A);
            if ($first_visitor && !empty($first_visitor['name'])) {
                $item['first_visitor_name'] = $first_visitor['name'];
            }
        }
        
        // Load hosts
        if (!empty($item['id'])) {
            $hosts = $wpdb->get_results($wpdb->prepare(
                "SELECT u.id, u.first_name, u.last_name, u.email, u.role 
                 FROM {$wpdb->prefix}saw_visit_hosts vh
                 INNER JOIN {$wpdb->prefix}saw_users u ON vh.user_id = u.id
                 WHERE vh.visit_id = %d
                 ORDER BY u.last_name, u.first_name",
                $item['id']
            ), ARRAY_A);
            $item['hosts'] = $hosts;
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
     * Returns HTML for badges displayed in universal detail header.
     * Shows: visitor count, visit type, status
     * 
     * @since 7.0.0
     * @param array $item Item data
     * @return string HTML for header meta
     */
    public function get_detail_header_meta($item) {
        if (empty($item)) {
            return '';
        }
        
        $meta_parts = array();
        
        // Visitor count badge - always show
        $visitor_count = intval($item['visitor_count'] ?? 0);
        $meta_parts[] = '<span class="saw-badge-transparent">👥 ' . $visitor_count . ' ' . ($visitor_count === 1 ? 'osoba' : ($visitor_count < 5 ? 'osoby' : 'osob')) . '</span>';
        
        // Visit type badge
        if (!empty($item['visit_type'])) {
            $type_labels = array(
                'planned' => 'Plánovaná',
                'walk_in' => 'Walk-in',
            );
            $type_label = $type_labels[$item['visit_type']] ?? $item['visit_type'];
            $type_class = $item['visit_type'] === 'walk_in' ? 'saw-badge-warning' : 'saw-badge-info';
            $meta_parts[] = '<span class="saw-badge-transparent ' . esc_attr($type_class) . '">' . esc_html($type_label) . '</span>';
        }
        
        // Status badge
        if (!empty($item['status'])) {
            $status_labels = array(
                'draft' => 'Koncept',
                'pending' => 'Čekající',
                'confirmed' => 'Potvrzená',
                'in_progress' => 'Probíhající',
                'completed' => 'Dokončená',
                'cancelled' => 'Zrušená',
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
    
    public function ajax_get_hosts_by_branch() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        if (!$branch_id) {
            wp_send_json_error(array('message' => 'Neplatná pobočka'));
            return;
        }
        
        // Get customer_id from context for proper data isolation
        $customer_id = 0;
        if (class_exists('SAW_Context')) {
            $customer_id = SAW_Context::get_customer_id();
        }
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'Nelze určit zákazníka'));
            return;
        }
        
        global $wpdb;
        
        // Verify branch belongs to customer (security check)
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
        
        // Query users with customer_id, branch_id, appropriate roles, and position field
        // Filter by roles that can be hosts: admin, super_manager, manager (like terminal does)
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
        
        // Ensure position is always a string (handle NULL values)
        if (is_array($hosts)) {
            foreach ($hosts as &$host) {
                $host['position'] = isset($host['position']) ? $host['position'] : '';
            }
            unset($host); // Break reference
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
        // Return columns as defined in list-template.php
        // ID column is removed, company_person, branch_name, visit_type, visitor_count, status, created_at
        return array(
            'company_person' => array(
                'label' => 'Návštěvník',
                'type' => 'custom',
                'sortable' => false,
                'class' => 'saw-table-cell-bold',
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
            'branch_name' => array(
                'label' => 'Pobočka',
                'type' => 'text',
                'sortable' => false,
            ),
            'visit_type' => array(
                'label' => 'Typ',
                'type' => 'badge',
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
            'created_at' => array(
                'label' => 'Vytvořeno',
                'type' => 'date',
                'sortable' => true,
                'width' => '120px',
                'format' => 'd.m.Y',
            ),
        );
    }
}
