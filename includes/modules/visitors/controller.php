<?php
/**
 * Visitors Module Controller
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @since       1.0.0
 * @version     3.0.0 - FINAL: Assets in module root, not assets/
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SAW_Base_Controller')) require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
if (!trait_exists('SAW_AJAX_Handlers')) require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';

class SAW_Module_Visitors_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;
    
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/';
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        require_once $module_path . 'model.php';
        $this->model = new SAW_Module_Visitors_Model($this->config);
        
        // Register AJAX handlers
        add_action('wp_ajax_saw_checkin', array($this, 'ajax_checkin'));
        add_action('wp_ajax_saw_checkout', array($this, 'ajax_checkout'));
        add_action('wp_ajax_saw_add_adhoc_visitor', array($this, 'ajax_add_adhoc_visitor'));
        
        // Enqueue assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function index() {
        if (function_exists('saw_can') && !saw_can('list', $this->entity)) {
            wp_die('Nemáte oprávnění.', 403);
        }
        $this->render_list_view();
    }
    
    /**
     * Enqueue module assets
     * CSS and JS files are DIRECTLY in module folder (not in assets/)
     */
    public function enqueue_assets() {
        SAW_Asset_Loader::enqueue_module('visitors');
        
        // Ensure dashicons font is loaded for form icons
        wp_enqueue_style('dashicons');
        
        wp_localize_script('saw-visitors', 'sawVisitorsData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce'),
        ));
    }

    protected function prepare_form_data($post) {
        $data = array();
        
        foreach ($this->config['fields'] as $field_name => $field_config) {
            if (isset($post[$field_name])) {
                $sanitize = $field_config['sanitize'] ?? 'sanitize_text_field';
                $data[$field_name] = $sanitize($post[$field_name]);
            } elseif ($field_config['type'] === 'checkbox') {
                $data[$field_name] = 0;
            }
        }
        
        return $data;
    }
    
    protected function after_save($id) {
        if (isset($_POST['certificates']) && is_array($_POST['certificates'])) {
            $this->model->save_certificates($id, $_POST['certificates']);
        }
    }

    protected function format_detail_data($item) {
        if (empty($item)) return $item;
        
        // Load visit data
        if (!empty($item['visit_id'])) {
            $item['visit_data'] = $this->model->get_visit_data($item['visit_id']);
            
            // Load hosts
            if (!empty($item['visit_data'])) {
                global $wpdb;
                $hosts = $wpdb->get_results($wpdb->prepare(
                    "SELECT u.id, u.first_name, u.last_name, u.email
                     FROM {$wpdb->prefix}saw_visit_hosts vh
                     INNER JOIN {$wpdb->prefix}saw_users u ON vh.user_id = u.id
                     WHERE vh.visit_id = %d
                     ORDER BY u.last_name, u.first_name",
                    $item['visit_id']
                ), ARRAY_A);
                
                $item['visit_data']['hosts'] = $hosts;
            }
        }
        
        // Load certificates
        if (!empty($item['id'])) {
            $item['certificates'] = $this->model->get_certificates($item['id']);
        }
        
        // Load daily logs
        if (!empty($item['id'])) {
            $item['daily_logs'] = $this->model->get_daily_logs($item['id']);
        }
        
        // Compute current status for detail view
        global $wpdb;
        $today = current_time('Y-m-d');
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs 
             WHERE visitor_id = %d AND log_date = %s
             ORDER BY checked_in_at DESC
             LIMIT 1",
            $item['id'], $today
        ), ARRAY_A);
        
        if ($item['participation_status'] === 'confirmed') {
            if ($log && $log['checked_in_at'] && !$log['checked_out_at']) {
                $item['current_status'] = 'present';
            } elseif ($log && $log['checked_out_at']) {
                $item['current_status'] = 'checked_out';
            } else {
                $item['current_status'] = 'confirmed';
            }
        } elseif ($item['participation_status'] === 'no_show') {
            $item['current_status'] = 'no_show';
        } else {
            $item['current_status'] = 'planned';
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
        if (empty($item)) {
            return '';
        }
        
        $name = trim(($item['first_name'] ?? '') . ' ' . ($item['last_name'] ?? ''));
        if (!empty($name)) {
            return $name;
        }
        
        return '#' . ($item['id'] ?? '');
    }
    
    /**
     * Get header meta badges for detail sidebar
     * Shows: participation status, position
     * 
     * @since 7.0.0
     * @param array $item Item data
     * @return string HTML with badges
     */
    public function get_detail_header_meta($item) {
        if (empty($item)) {
            return '';
        }
        
        $meta_parts = array();
        
        // Participation status badge (current_status)
        if (!empty($item['current_status'])) {
            $status_labels = array(
                'present' => '✅ Přítomen',
                'checked_out' => '🚪 Odhlášen',
                'confirmed' => '⏳ Potvrzený',
                'planned' => '📅 Plánovaný',
                'no_show' => '❌ Nedostavil se',
            );
            $status_classes = array(
                'present' => 'saw-badge-success',
                'checked_out' => 'saw-badge-secondary',
                'confirmed' => 'saw-badge-warning',
                'planned' => 'saw-badge-info',
                'no_show' => 'saw-badge-danger',
            );
            $status_label = $status_labels[$item['current_status']] ?? $item['current_status'];
            $status_class = $status_classes[$item['current_status']] ?? 'saw-badge-secondary';
            $meta_parts[] = '<span class="saw-badge-transparent ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
        }
        
        // Position badge
        if (!empty($item['position'])) {
            $meta_parts[] = '<span class="saw-badge-transparent">💼 ' . esc_html($item['position']) . '</span>';
        }
        
        return implode('', $meta_parts);
    }
    
    /**
     * Compute current_status for a visitor item
     * 
     * current_status is computed dynamically based on:
     * - participation_status
     * - today's daily log (checked_in_at, checked_out_at)
     * 
     * @since 7.1.0
     * @param array $item Visitor item data
     * @return string Computed current_status
     */
    protected function compute_current_status($item) {
        global $wpdb;
        $today = current_time('Y-m-d');
        
        // Get today's LATEST log (pro re-entry support)
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visit_daily_logs 
             WHERE visitor_id = %d AND log_date = %s
             ORDER BY checked_in_at DESC
             LIMIT 1",
            $item['id'], $today
        ), ARRAY_A);
        
        // Compute dynamic status
        if ($item['participation_status'] === 'confirmed') {
            if ($log && $log['checked_in_at'] && !$log['checked_out_at']) {
                return 'present'; // Přítomen
            } elseif ($log && $log['checked_out_at']) {
                return 'checked_out'; // Odhlášen
            } else {
                return 'confirmed'; // Potvrzený (ale dnes ještě nepřišel)
            }
        } elseif ($item['participation_status'] === 'no_show') {
            return 'no_show';
        } else {
            return 'planned';
        }
    }
    
    /**
     * Override get_list_data() to handle current_status filtering in PHP
     * 
     * current_status is NOT a database column - it's computed dynamically.
     * We must load all data, compute current_status, then filter in PHP.
     * 
     * @since 7.1.0
     * @return array List data with computed current_status
     */
    protected function get_list_data() {
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'vis.id';
        $order = isset($_GET['order']) ? strtoupper(sanitize_text_field(wp_unslash($_GET['order']))) : 'DESC';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        
        // Check if infinite scroll is enabled and use its per_page
        $infinite_scroll_enabled = !empty($this->config['infinite_scroll']['enabled']);
        $per_page = $infinite_scroll_enabled 
            ? ($this->config['infinite_scroll']['per_page'] ?? 50)
            : ($this->config['list_config']['per_page'] ?? 20);
        
        // Build filters - but EXCLUDE current_status (it's not in DB)
        $filters = array(
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
            'page' => 1, // Load ALL data for PHP filtering
            'per_page' => 9999, // Load ALL data for PHP filtering
        );
        
        // Apply list_config filters (but exclude tab_param if tabs are enabled)
        $tab_param = null;
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
        }
        
        if (!empty($this->config['list_config']['filters'])) {
            foreach ($this->config['list_config']['filters'] as $filter_key => $enabled) {
                // Skip tab_param filter (current_status) - handled in PHP below
                if ($filter_key === $tab_param) {
                    continue;
                }
                
                // Only apply if filter is enabled AND present in URL
                if ($enabled && isset($_GET[$filter_key]) && $_GET[$filter_key] !== '') {
                    $filters[$filter_key] = sanitize_text_field(wp_unslash($_GET[$filter_key]));
                }
            }
        }
        
        // Load ALL data from model (without current_status filter)
        $data = $this->model->get_all($filters);
        $all_items = $data['items'] ?? array();
        
        // Compute current_status for each item
        foreach ($all_items as &$item) {
            $item['current_status'] = $this->compute_current_status($item);
        }
        unset($item);
        
        // Handle TAB filtering - filter by current_status in PHP
        $current_tab = $this->config['tabs']['default_tab'] ?? 'all';
        $url_value = null;
        
        if (!empty($this->config['tabs']['enabled'])) {
            $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
            $url_value = isset($_GET[$tab_param]) ? sanitize_text_field(wp_unslash($_GET[$tab_param])) : null;
            
            // If URL has no parameter, it's "all" tab
            if ($url_value === null || $url_value === '') {
                $current_tab = $this->config['tabs']['default_tab'] ?? 'all';
            } else {
                // URL contains filter_value, find matching tab_key
                $tab_found = false;
                foreach ($this->config['tabs']['tabs'] as $tab_key => $tab_config) {
                    // Compare filter_value with URL value (both as strings for consistency)
                    if ($tab_config['filter_value'] !== null && 
                        (string)$tab_config['filter_value'] === (string)$url_value) {
                        $current_tab = (string)$tab_key;
                        $tab_found = true;
                        break;
                    }
                }
                
                // If no tab found matching the URL value, default to "all"
                if (!$tab_found) {
                    $current_tab = $this->config['tabs']['default_tab'] ?? 'all';
                }
            }
            
            // Filter items by current_status in PHP (if not "all" tab)
            if ($url_value !== null && $url_value !== '') {
                $filtered_items = array();
                foreach ($all_items as $item) {
                    if ($item['current_status'] === $url_value) {
                        $filtered_items[] = $item;
                    }
                }
                $all_items = $filtered_items;
            }
        }
        
        // Apply pagination in PHP
        $total_items = count($all_items);
        $total_pages = ceil($total_items / $per_page);
        $offset = ($page - 1) * $per_page;
        $items = array_slice($all_items, $offset, $per_page);
        
        $result = array(
            'items' => $items,
            'total' => $total_items,
            'page' => $page,
            'total_pages' => $total_pages,
            'search' => $search,
            'orderby' => $orderby,
            'order' => $order,
        );
        
        // Add tab data if tabs are enabled
        if (!empty($this->config['tabs']['enabled'])) {
            // Ensure current_tab is always a valid string
            if (isset($current_tab) && $current_tab !== null && $current_tab !== '') {
                $result['current_tab'] = (string)$current_tab;
            } else {
                $result['current_tab'] = (string)($this->config['tabs']['default_tab'] ?? 'all');
            }
            
            // Get tab counts - this returns array of tab_key => count
            $result['tab_counts'] = $this->get_tab_counts();
        }
        
        foreach ($_GET as $key => $value) {
            if (!in_array($key, array('s', 'orderby', 'order', 'paged'))) {
                $result[$key] = sanitize_text_field(wp_unslash($value));
            }
        }
        
        return $result;
    }
    
    /**
     * Override get_tab_counts() to handle current_status counting in PHP
     * 
     * current_status is NOT a database column - it's computed dynamically.
     * We must load all data, compute current_status, then count in PHP.
     * 
     * @since 7.1.0
     * @return array Tab key => count
     */
    protected function get_tab_counts() {
        if (empty($this->config['tabs']['enabled'])) {
            return array();
        }
        
        $tab_param = $this->config['tabs']['tab_param'] ?? 'tab';
        $tabs = $this->config['tabs']['tabs'] ?? array();
        $counts = array();
        
        // Build filters - EXCLUDE current_status (it's not in DB)
        $filters = array(
            'page' => 1,
            'per_page' => 9999, // Load ALL data for counting
        );
        
        // Apply other existing filters from GET (search, etc.)
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        if (!empty($search)) {
            $filters['search'] = $search;
        }
        
        // Apply list_config filters (but exclude the tab_param to avoid conflicts)
        if (!empty($this->config['list_config']['filters'])) {
            foreach ($this->config['list_config']['filters'] as $filter_key => $enabled) {
                // Skip tab_param filter (current_status) - handled in PHP below
                if ($filter_key === $tab_param) {
                    continue;
                }
                
                // Only apply if filter is enabled AND present in URL
                if ($enabled && isset($_GET[$filter_key]) && $_GET[$filter_key] !== '') {
                    $filters[$filter_key] = sanitize_text_field(wp_unslash($_GET[$filter_key]));
                }
            }
        }
        
        // Load ALL data from model (without current_status filter)
        $data = $this->model->get_all($filters);
        $all_items = $data['items'] ?? array();
        
        // Compute current_status for each item
        foreach ($all_items as &$item) {
            $item['current_status'] = $this->compute_current_status($item);
        }
        unset($item);
        
        // Count items for each tab based on computed current_status
        foreach ($tabs as $tab_key => $tab_config) {
            if (empty($tab_config['count_query'])) {
                $counts[$tab_key] = 0;
                continue;
            }
            
            $filter_value = $tab_config['filter_value'];
            
            // Count items matching this tab's filter_value
            $count = 0;
            foreach ($all_items as $item) {
                // "all" tab - count all items
                if ($filter_value === null || $filter_value === '') {
                    $count++;
                } 
                // Other tabs - count items matching filter_value
                elseif ($item['current_status'] === (string)$filter_value) {
                    $count++;
                }
            }
            
            $counts[$tab_key] = $count;
        }
        
        return $counts;
    }
    
    /**
     * Get table columns configuration for infinite scroll
     * 
     * @since 7.0.0
     * @return array Column configuration
     */
    public function get_table_columns() {
        return array(
            'first_name' => array(
                'label' => 'Jméno',
                'type' => 'text',
                'class' => 'saw-table-cell-bold',
                'sortable' => true,
            ),
            'last_name' => array(
                'label' => 'Příjmení',
                'type' => 'text',
                'class' => 'saw-table-cell-bold',
                'sortable' => true,
            ),
            'company_name' => array(
                'label' => 'Firma',
                'type' => 'text',
            ),
            'branch_name' => array(
                'label' => 'Pobočka',
                'type' => 'text',
            ),
            'current_status' => array(
                'label' => 'Aktuální stav',
                'type' => 'badge',
                'sortable' => false,
                'map' => array(
                    'present' => 'success',
                    'checked_out' => 'secondary',
                    'confirmed' => 'warning',
                    'planned' => 'info',
                    'no_show' => 'danger',
                ),
                'labels' => array(
                    'present' => '✅ Přítomen',
                    'checked_out' => '🚪 Odhlášen',
                    'confirmed' => '⏳ Potvrzený',
                    'planned' => '📅 Plánovaný',
                    'no_show' => '❌ Nedostavil se',
                ),
            ),
            'first_checkin_at' => array(
                'label' => 'První check-in',
                'type' => 'callback',
                'callback' => function($value) {
                    return !empty($value) ? date('d.m.Y H:i', strtotime($value)) : '—';
                },
            ),
            'last_checkout_at' => array(
                'label' => 'Poslední check-out',
                'type' => 'callback',
                'callback' => function($value) {
                    return !empty($value) ? date('d.m.Y H:i', strtotime($value)) : '—';
                },
            ),
            'training_status' => array(
                'label' => 'Školení',
                'type' => 'badge',
                'map' => array(
                    'completed' => 'success',
                    'in_progress' => 'info',
                    'skipped' => 'warning',
                    'not_started' => 'secondary',
                ),
                'labels' => array(
                    'completed' => '✅ Dokončeno',
                    'in_progress' => '🔄 Probíhá',
                    'skipped' => '⏭️ Přeskočeno',
                    'not_started' => '⚪ Nespuštěno',
                ),
            ),
        );
    }
    
    // ============================================
    // AJAX HANDLERS
    // ============================================
    
    public function ajax_checkin() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
            return;
        }
        
        $visitor_id = isset($_POST['visitor_id']) ? intval($_POST['visitor_id']) : 0;
        $log_date = isset($_POST['log_date']) ? sanitize_text_field($_POST['log_date']) : current_time('Y-m-d');
        
        if (!$visitor_id) {
            wp_send_json_error(array('message' => 'Neplatný návštěvník'));
            return;
        }
        
        $result = $this->model->daily_checkin($visitor_id, $log_date);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'Check-in úspěšný',
            'checked_in_at' => current_time('Y-m-d H:i:s'),
        ));
    }
    
    public function ajax_checkout() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
            return;
        }
        
        $visitor_id = isset($_POST['visitor_id']) ? intval($_POST['visitor_id']) : 0;
        $log_date = isset($_POST['log_date']) ? sanitize_text_field($_POST['log_date']) : current_time('Y-m-d');
        $manual = isset($_POST['manual']) ? (bool) $_POST['manual'] : false;
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : null;
        
        if (!$visitor_id) {
            wp_send_json_error(array('message' => 'Neplatný návštěvník'));
            return;
        }
        
        global $wpdb;
        $visitor_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
            $visitor_id
        ));
        
        if (!$visitor_exists) {
            wp_send_json_error(array('message' => 'Návštěvník nenalezen'));
            return;
        }
        
        $admin_id = null;
        if ($manual) {
            $admin_id = get_current_user_id();
        }
        
        $result = $this->model->daily_checkout($visitor_id, $log_date, $manual, $admin_id, $reason);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'message' => 'Check-out úspěšný',
            'checked_out_at' => current_time('Y-m-d H:i:s'),
        ));
    }
    
    public function ajax_add_adhoc_visitor() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
            return;
        }
        
        $visit_id = isset($_POST['visit_id']) ? intval($_POST['visit_id']) : 0;
        
        if (!$visit_id) {
            wp_send_json_error(array('message' => 'Neplatná návštěva'));
            return;
        }
        
        $visitor_data = array(
            'first_name' => isset($_POST['first_name']) ? sanitize_text_field($_POST['first_name']) : '',
            'last_name' => isset($_POST['last_name']) ? sanitize_text_field($_POST['last_name']) : '',
            'position' => isset($_POST['position']) ? sanitize_text_field($_POST['position']) : '',
            'email' => isset($_POST['email']) ? sanitize_email($_POST['email']) : '',
            'phone' => isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '',
            'training_skipped' => isset($_POST['training_skipped']) ? 1 : 0,
        );
        
        if (empty($visitor_data['first_name']) || empty($visitor_data['last_name'])) {
            wp_send_json_error(array('message' => 'Jméno a příjmení jsou povinné'));
            return;
        }
        
        $visitor_id = $this->model->add_adhoc_visitor($visit_id, $visitor_data);
        
        if (is_wp_error($visitor_id)) {
            wp_send_json_error(array('message' => $visitor_id->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'visitor_id' => $visitor_id,
            'message' => 'Návštěvník přidán',
        ));
    }
    
    /**
     * Override ajax_get_adjacent_id for visitors
     * Visitors table doesn't have customer_id/branch_id directly - they're in visits table
     * 
     * @since 7.0.0
     * @return void Outputs JSON
     */
    public function ajax_get_adjacent_id() {
        try {
            error_log('[Visitors] ajax_get_adjacent_id: Starting, POST data: ' . print_r($_POST, true));
            
            // Validate nonce
            check_ajax_referer('saw_ajax_nonce', 'nonce');
            
            // Validate controller state
            if (!isset($this->model) || !$this->model) {
                error_log('[Visitors] ajax_get_adjacent_id: $this->model is not set');
                wp_send_json_error(array(
                    'message' => 'Chyba: Model není inicializován'
                ));
                return;
            }
            
            if (!isset($this->config) || empty($this->config)) {
                error_log('[Visitors] ajax_get_adjacent_id: $this->config is not set');
                wp_send_json_error(array(
                    'message' => 'Chyba: Konfigurace není inicializována'
                ));
                return;
            }
            
            // Check permissions
            if (method_exists($this, 'can') && !$this->can('view')) {
                error_log('[Visitors] ajax_get_adjacent_id: Permission denied');
                wp_send_json_error(array(
                    'message' => 'Nemáte oprávnění zobrazit záznamy'
                ));
                return;
            }
            
            $current_id = intval($_POST['id'] ?? 0);
            $direction = sanitize_text_field($_POST['direction'] ?? 'next'); // 'next' or 'prev'
            
            error_log('[Visitors] ajax_get_adjacent_id: current_id=' . $current_id . ', direction=' . $direction);
            
            if (!$current_id) {
                error_log('[Visitors] ajax_get_adjacent_id: Missing ID in POST');
                wp_send_json_error(array(
                    'message' => 'Chybí ID záznamu'
                ));
                return;
            }
            
            if (!in_array($direction, array('next', 'prev'))) {
                error_log('[Visitors] ajax_get_adjacent_id: Invalid direction: ' . $direction);
                wp_send_json_error(array(
                    'message' => 'Neplatný směr navigace'
                ));
                return;
            }
            
            // Get context filters
            $customer_id = 0;
            $branch_id = 0;
            
            if (class_exists('SAW_Context')) {
                if (method_exists('SAW_Context', 'get_customer_id')) {
                    $customer_id = SAW_Context::get_customer_id();
                }
                if (method_exists('SAW_Context', 'get_branch_id')) {
                    $branch_id = SAW_Context::get_branch_id();
                }
            }
            
            error_log('[Visitors] ajax_get_adjacent_id: Context - customer_id=' . $customer_id . ', branch_id=' . $branch_id);
            
            // Get current record to determine its position
            if (!method_exists($this->model, 'get_by_id')) {
                error_log('[Visitors] ajax_get_adjacent_id: Model does not have get_by_id method');
                wp_send_json_error(array(
                    'message' => 'Chyba: Model nemá metodu get_by_id'
                ));
                return;
            }
            
            $current_item = $this->model->get_by_id($current_id);
            if (!$current_item) {
                error_log('[Visitors] ajax_get_adjacent_id: Current record not found, ID=' . $current_id);
                wp_send_json_error(array(
                    'message' => 'Záznam nenalezen'
                ));
                return;
            }
            
            // Build query to get all visitor IDs with same filters
            // Visitors don't have customer_id/branch_id directly - need to JOIN with visits
            global $wpdb;
            
            // Ensure table name is correct - model->table should already include prefix
            $visitors_table = $this->model->table;
            $visits_table = $wpdb->prefix . 'saw_visits';
            
            error_log('[Visitors] ajax_get_adjacent_id: Table names - visitors=' . $visitors_table . ', visits=' . $visits_table);
            
            // Validate tables exist
            if (empty($visitors_table) || empty($visits_table)) {
                error_log('[Visitors] ajax_get_adjacent_id: Empty table names');
                wp_send_json_error(array(
                    'message' => 'Chyba: Nelze určit tabulky databáze'
                ));
                return;
            }
            
            // Table names are from internal config, safe to use directly
            // $visitors_table already includes prefix from model
            // $visits_table is built from $wpdb->prefix
            
            $where = array('1=1');
            $where_values = array();
            
            // Filter by customer_id if set - through visits table
            if ($customer_id) {
                $where[] = "v.customer_id = %d";
                $where_values[] = $customer_id;
            }
            
            // Filter by branch_id if set - through visits table
            if ($branch_id) {
                $where[] = "v.branch_id = %d";
                $where_values[] = $branch_id;
            }
            
            $where_clause = implode(' AND ', $where);
            
            // Build query with table names directly (already escaped)
            $query = "SELECT vis.id 
                      FROM {$visitors_table} vis
                      INNER JOIN {$visits_table} v ON vis.visit_id = v.id
                      WHERE {$where_clause}
                      ORDER BY vis.id ASC";
            
            error_log('[Visitors] ajax_get_adjacent_id: Query before prepare: ' . $query);
            error_log('[Visitors] ajax_get_adjacent_id: Where values: ' . print_r($where_values, true));
            
            // Prepare query with where values
            if (!empty($where_values)) {
                $query = $wpdb->prepare($query, $where_values);
            }
            
            error_log('[Visitors] ajax_get_adjacent_id: Executing query: ' . $query);
            
            $ids = $wpdb->get_col($query);
            
            // Log error if query failed
            if ($wpdb->last_error) {
                error_log('[Visitors] ajax_get_adjacent_id: Query error: ' . $wpdb->last_error . ', Query: ' . $query);
                wp_send_json_error(array(
                    'message' => 'Chyba při načítání záznamů: ' . $wpdb->last_error
                ));
                return;
            }
            
            error_log('[Visitors] ajax_get_adjacent_id: Query returned ' . count($ids) . ' IDs');
            
            // Convert IDs to integers for consistent comparison
            $ids = array_map('intval', $ids);
            $current_id = intval($current_id);
            
            if (empty($ids)) {
                error_log('[Visitors] ajax_get_adjacent_id: No IDs found. Query: ' . $query . ', Customer ID: ' . $customer_id . ', Branch ID: ' . $branch_id);
                wp_send_json_error(array(
                    'message' => 'Žádné záznamy nenalezeny'
                ));
                return;
            }
            
            // Find current position - use strict comparison
            $current_index = array_search($current_id, $ids, true);
            
            if ($current_index === false) {
                error_log('[Visitors] ajax_get_adjacent_id: Current ID ' . $current_id . ' not found in IDs. First 10 IDs: ' . implode(', ', array_slice($ids, 0, 10)));
                wp_send_json_error(array(
                    'message' => 'Aktuální záznam není v seznamu'
                ));
                return;
            }
            
            error_log('[Visitors] ajax_get_adjacent_id: Current index: ' . $current_index . ' of ' . count($ids));
            
            // Get adjacent ID with circular navigation
            $adjacent_id = null;
            
            if ($direction === 'next') {
                // Next: if last, go to first
                $adjacent_index = ($current_index + 1) % count($ids);
                $adjacent_id = $ids[$adjacent_index];
            } else {
                // Prev: if first, go to last
                $adjacent_index = ($current_index - 1 + count($ids)) % count($ids);
                $adjacent_id = $ids[$adjacent_index];
            }
            
            error_log('[Visitors] ajax_get_adjacent_id: Adjacent index: ' . $adjacent_index . ', Adjacent ID: ' . $adjacent_id);
            
            if (!$adjacent_id) {
                error_log('[Visitors] ajax_get_adjacent_id: Failed to find adjacent ID');
                wp_send_json_error(array(
                    'message' => 'Nepodařilo se najít sousední záznam'
                ));
                return;
            }
            
            // Build detail URL
            $route = $this->config['route'] ?? $this->entity;
            $detail_url = home_url('/admin/' . $route . '/' . $adjacent_id . '/');
            
            error_log('[Visitors] ajax_get_adjacent_id: Success - adjacent_id=' . $adjacent_id . ', url=' . $detail_url);
            
            wp_send_json_success(array(
                'id' => $adjacent_id,
                'url' => $detail_url
            ));
            
        } catch (Throwable $e) {
            error_log('[Visitors] ajax_get_adjacent_id: Exception caught - ' . $e->getMessage() . ', Trace: ' . $e->getTraceAsString());
            wp_send_json_error(array(
                'message' => 'Chyba při načítání sousedního záznamu: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
        }
    }
}