<?php
/**
 * Calendar Module Controller
 *
 * Handles calendar view rendering and AJAX endpoints for FullCalendar.
 * Extends SAW_Base_Controller for proper layout integration.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Calendar
 * @version     1.3.0 - FIXED: AJAX handlers registration, removed branch filter
 * @since       1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load base controller
if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}

/**
 * Calendar Module Controller Class
 *
 * @since 1.0.0
 */
class SAW_Module_Calendar_Controller extends SAW_Base_Controller {
    
    /**
     * Singleton instance
     * @var SAW_Module_Calendar_Controller
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * 
     * @return SAW_Module_Calendar_Controller
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        $module_path = SAW_VISITORS_PLUGIN_DIR . 'includes/modules/calendar/';
        $this->config = require $module_path . 'config.php';
        $this->entity = $this->config['entity'];
        $this->config['path'] = $module_path;
        
        // No model for calendar - it uses visits data
        $this->model = null;
        
        // Register AJAX handlers - this runs even on AJAX requests
        $this->register_ajax_handlers();
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Register directly - this ensures handlers are always registered
        add_action('wp_ajax_saw_calendar_events', [$this, 'ajax_get_events']);
        add_action('wp_ajax_saw_calendar_event_details', [$this, 'ajax_get_event_details']);
        add_action('wp_ajax_saw_calendar_update_event', [$this, 'ajax_update_event']);
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SAW Calendar: AJAX handlers registered');
        }
    }
    
    /**
     * Main index action - render calendar page
     */
    public function index() {
        // Check permissions (uses visits permission)
        if (function_exists('saw_can') && !saw_can('list', 'visits')) {
            wp_die('Nemáte oprávnění pro zobrazení kalendáře.', 403);
        }
        
        $this->enqueue_assets();
        $this->render_calendar();
    }
    
    /**
     * Enqueue calendar assets
     */
    protected function enqueue_assets() {
        // FullCalendar from CDN
        wp_enqueue_script(
            'fullcalendar',
            'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.17/index.global.min.js',
            [],
            '6.1.17',
            true
        );
        
        // Czech locale for FullCalendar
        wp_enqueue_script(
            'fullcalendar-cs',
            'https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.17/locales/cs.global.min.js',
            ['fullcalendar'],
            '6.1.17',
            true
        );
        
        // Our calendar JS
        $js_path = SAW_VISITORS_PLUGIN_DIR . 'assets/js/modules/calendar.js';
        if (file_exists($js_path)) {
            wp_enqueue_script(
                'saw-calendar',
                SAW_VISITORS_PLUGIN_URL . 'assets/js/modules/calendar.js',
                ['fullcalendar', 'fullcalendar-cs', 'jquery'],
                filemtime($js_path),
                true
            );
            
            // Localize script
            wp_localize_script('saw-calendar', 'sawCalendar', $this->get_js_config());
        }
        
        // Our calendar CSS
        $css_path = SAW_VISITORS_PLUGIN_DIR . 'assets/css/modules/calendar.css';
        if (file_exists($css_path)) {
            wp_enqueue_style(
                'saw-calendar',
                SAW_VISITORS_PLUGIN_URL . 'assets/css/modules/calendar.css',
                [],
                filemtime($css_path)
            );
        }
    }
    
    /**
     * Get JavaScript configuration
     *
     * @return array
     */
    private function get_js_config() {
        return [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_calendar_nonce'),
            'homeUrl' => home_url(),
            'createUrl' => home_url('/admin/visits/create'),
            'detailUrl' => home_url('/admin/visits/{id}/'),
            'editUrl' => home_url('/admin/visits/{id}/edit'),
            
            // Calendar settings
            'defaultView' => $this->config['calendar']['default_view'] ?? 'dayGridMonth',
            'firstDay' => $this->config['calendar']['first_day'] ?? 1,
            'slotMinTime' => $this->config['calendar']['slot_min_time'] ?? '06:00:00',
            'slotMaxTime' => $this->config['calendar']['slot_max_time'] ?? '22:00:00',
            'slotDuration' => $this->config['calendar']['slot_duration'] ?? '00:30:00',
            
            // Colors
            'statusColors' => $this->config['calendar']['status_colors'] ?? [],
            'typeColors' => $this->config['calendar']['type_colors'] ?? [],
            
            // Translations
            'i18n' => [
                'status_draft' => 'Koncept',
                'status_pending' => 'Čekající',
                'status_confirmed' => 'Potvrzená',
                'status_in_progress' => 'Probíhá',
                'status_completed' => 'Dokončená',
                'status_cancelled' => 'Zrušená',
                'type_planned' => 'Plánovaná',
                'type_walk_in' => 'Neplánovaná',
                'loading' => 'Načítání...',
                'error_loading' => 'Chyba při načítání',
            ],
            
            // Current context - branch from switcher
            'branchId' => SAW_Context::get_branch_id(),
            'customerId' => SAW_Context::get_customer_id(),
        ];
    }
    
    /**
     * Render calendar page with layout
     */
    private function render_calendar() {
        $config = $this->config;
        
        // Start output buffering for content
        ob_start();
        
        // Add module wrapper
        echo '<div class="saw-module-' . esc_attr($this->entity) . '">';
        
        // Render flash messages
        $this->render_flash_messages();
        
        // Include template
        include $this->config['path'] . 'template.php';
        
        echo '</div>';
        
        $content = ob_get_clean();
        
        // Render with layout (header, sidebar, footer)
        $this->render_with_layout($content, $this->config['plural'] ?? 'Kalendář');
    }
    
    /**
     * AJAX: Get calendar events
     */
    public function ajax_get_events() {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SAW Calendar: ajax_get_events() called');
            error_log('SAW Calendar: GET params: ' . print_r($_GET, true));
        }
        
        // Verify nonce
        if (!check_ajax_referer('saw_calendar_nonce', 'nonce', false)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SAW Calendar: Nonce verification failed');
            }
            wp_send_json_error(['message' => 'Neplatný požadavek'], 403);
            return;
        }
        
        // Check permissions
        if (!is_user_logged_in()) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('SAW Calendar: User not logged in');
            }
            wp_send_json_error(['message' => 'Přístup zamítnut'], 403);
            return;
        }
        
        global $wpdb;
        
        // Get date range from FullCalendar
        $start = sanitize_text_field($_GET['start'] ?? '');
        $end = sanitize_text_field($_GET['end'] ?? '');
        
        // Filters from UI
        $status = sanitize_text_field($_GET['status'] ?? '');
        $type = sanitize_text_field($_GET['type'] ?? '');
        
        // Context from SAW_Context (branch switcher)
        $customer_id = SAW_Context::get_customer_id();
        $branch_id = SAW_Context::get_branch_id();
        
        // If no customer, return empty
        if (!$customer_id) {
            wp_send_json([]);
            return;
        }
        
        // Build query with proper placeholders - collect all conditions and params
        $where_parts = [];
        $where_params = [];
        
        // Customer ID (always required)
        $where_parts[] = "v.customer_id = %d";
        $where_params[] = $customer_id;
        
        // Branch from context (branch switcher) - NOT from filter
        if ($branch_id > 0) {
            $where_parts[] = "v.branch_id = %d";
            $where_params[] = $branch_id;
        }
        
        // Status filter
        if (!empty($status)) {
            $where_parts[] = "v.status = %s";
            $where_params[] = $status;
        }
        
        // Type filter
        if (!empty($type)) {
            $where_parts[] = "v.visit_type = %s";
            $where_params[] = $type;
        }
        
        // Date range - use visit_schedules if available, otherwise planned_date_from/planned_date_to
        if (!empty($start) && !empty($end)) {
            $start_date = date('Y-m-d', strtotime($start));
            $end_date = date('Y-m-d', strtotime($end));
            // Match if schedule date is in range OR if no schedule but planned_date_from/planned_date_to overlaps
            $where_parts[] = "(vs.date BETWEEN %s AND %s OR (vs.date IS NULL AND (v.planned_date_from BETWEEN %s AND %s OR v.planned_date_to BETWEEN %s AND %s OR (v.planned_date_from <= %s AND v.planned_date_to >= %s))))";
            $where_params = array_merge($where_params, [$start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
        } elseif (!empty($start)) {
            $start_date = date('Y-m-d', strtotime($start));
            $where_parts[] = "(vs.date >= %s OR (vs.date IS NULL AND v.planned_date_from >= %s))";
            $where_params = array_merge($where_params, [$start_date, $start_date]);
        } elseif (!empty($end)) {
            $end_date = date('Y-m-d', strtotime($end));
            $where_parts[] = "(vs.date <= %s OR (vs.date IS NULL AND v.planned_date_to <= %s))";
            $where_params = array_merge($where_params, [$end_date, $end_date]);
        }
        
        $where_sql = implode(' AND ', $where_parts);
        
        // Execute query - use LEFT JOIN with visit_schedules to get all schedule days
        // Also include visits without schedules (using planned_date_from/planned_date_to)
        $sql = "SELECT v.id, 
                       COALESCE(vs.date, v.planned_date_from) as visit_date,
                       vs.date as schedule_date,
                       vs.time_from,
                       vs.time_to,
                       v.planned_date_from,
                       v.planned_date_to,
                       v.status, 
                       v.visit_type, 
                       (SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors WHERE visit_id = v.id) as visitor_count,
                       v.purpose,
                       c.name as company_name,
                       b.name as branch_name,
                       d.name as department_name
                FROM {$wpdb->prefix}saw_visits v
                LEFT JOIN {$wpdb->prefix}saw_visit_schedules vs ON v.id = vs.visit_id
                LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
                LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
                LEFT JOIN {$wpdb->prefix}saw_departments d ON v.department_id = d.id
                WHERE {$where_sql}
                ORDER BY COALESCE(vs.date, v.planned_date_from) ASC, vs.time_from ASC";
        
        // Prepare and execute with all parameters
        try {
            if (!empty($where_params)) {
                $prepared_sql = $wpdb->prepare($sql, ...$where_params);
            } else {
                $prepared_sql = $sql;
            }
            
            $visits = $wpdb->get_results($prepared_sql, ARRAY_A);
            
            // Check for SQL errors
            if ($wpdb->last_error) {
                error_log('SAW Calendar SQL Error: ' . $wpdb->last_error);
                error_log('SAW Calendar SQL: ' . $prepared_sql);
                wp_send_json_error(['message' => 'Chyba při načítání návštěv: ' . $wpdb->last_error]);
                return;
            }
            
            if ($visits === null || $visits === false) {
                $visits = [];
            }
        } catch (Exception $e) {
            error_log('SAW Calendar Exception: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Chyba při načítání návštěv: ' . $e->getMessage()]);
            return;
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SAW Calendar: Found ' . count($visits) . ' visits');
            if (count($visits) > 0) {
                error_log('SAW Calendar: First visit: ' . print_r($visits[0], true));
            }
        }
        
        // Transform to FullCalendar format
        $events = [];
        foreach ($visits as $visit) {
            $event = $this->format_event($visit);
            $events[] = $event;
            
            // Debug first event
            if (defined('WP_DEBUG') && WP_DEBUG && count($events) === 1) {
                error_log('SAW Calendar: First event: ' . print_r($event, true));
            }
        }
        
        // Debug final events count
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('SAW Calendar: Returning ' . count($events) . ' events');
        }
        
        // Return as plain array (not wrapped in success/data)
        wp_send_json($events);
    }
    
    /**
     * Format visit as FullCalendar event
     *
     * @param array $visit Visit data
     * @return array Event data
     */
    private function format_event($visit) {
        // Determine start datetime
        // Priority: schedule time_from > default 09:00:00
        $visit_date = $visit['visit_date'] ?? $visit['schedule_date'] ?? $visit['planned_date_from'] ?? date('Y-m-d');
        
        if (!empty($visit['time_from'])) {
            $start = $visit_date . 'T' . $visit['time_from'];
        } else {
            $start = $visit_date . 'T09:00:00';
        }
        
        // Determine end datetime
        if (!empty($visit['time_to'])) {
            $end = $visit_date . 'T' . $visit['time_to'];
        } else {
            // Default duration: 2 hours if no time_to specified
            $start_ts = strtotime($start);
            $end = date('Y-m-d\TH:i:s', $start_ts + (2 * 60 * 60)); // 2 hours
        }
        
        // Build title
        $title_parts = [];
        if (!empty($visit['company_name'])) {
            $title_parts[] = $visit['company_name'];
        }
        $count = intval($visit['visitor_count'] ?? 0);
        if ($count > 1) {
            $title_parts[] = "({$count})";
        }
        $title = implode(' ', $title_parts) ?: 'Návštěva #' . $visit['id'];
        
        // Get colors based on status
        $status = $visit['status'] ?? 'pending';
        $status_colors = [
            'draft' => ['background' => '#94a3b8', 'border' => '#64748b', 'text' => '#ffffff'],
            'pending' => ['background' => '#f59e0b', 'border' => '#d97706', 'text' => '#ffffff'],
            'confirmed' => ['background' => '#3b82f6', 'border' => '#2563eb', 'text' => '#ffffff'],
            'in_progress' => ['background' => '#f97316', 'border' => '#ea580c', 'text' => '#ffffff'],
            'completed' => ['background' => '#6b7280', 'border' => '#4b5563', 'text' => '#ffffff'],
            'cancelled' => ['background' => '#ef4444', 'border' => '#dc2626', 'text' => '#ffffff'],
        ];
        
        $colors = $status_colors[$status] ?? $status_colors['pending'];
        
        return [
            'id' => $visit['id'],
            'title' => $title,
            'start' => $start,
            'end' => $end,
            'backgroundColor' => $colors['background'],
            'borderColor' => $colors['border'],
            'textColor' => $colors['text'],
            'extendedProps' => [
                'status' => $status,
                'type' => $visit['visit_type'] ?? 'planned',
                'company' => $visit['company_name'] ?? '',
                'branch' => $visit['branch_name'] ?? '',
                'department' => $visit['department_name'] ?? '',
                'personCount' => $count,
                'purpose' => $visit['purpose'] ?? '',
                'detailUrl' => home_url('/admin/visits/' . $visit['id'] . '/'),
                'editUrl' => home_url('/admin/visits/' . $visit['id'] . '/edit'),
            ],
        ];
    }
    
    /**
     * AJAX: Get event details for popup
     */
    public function ajax_get_event_details() {
        if (!check_ajax_referer('saw_calendar_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Neplatný požadavek'], 403);
            return;
        }
        
        $visit_id = intval($_GET['id'] ?? 0);
        
        if (!$visit_id) {
            wp_send_json_error(['message' => 'Neplatné ID'], 400);
            return;
        }
        
        global $wpdb;
        
        $visit = $wpdb->get_row($wpdb->prepare(
            "SELECT v.*, 
                    c.name as company_name,
                    b.name as branch_name,
                    d.name as department_name
             FROM {$wpdb->prefix}saw_visits v
             LEFT JOIN {$wpdb->prefix}saw_companies c ON v.company_id = c.id
             LEFT JOIN {$wpdb->prefix}saw_branches b ON v.branch_id = b.id
             LEFT JOIN {$wpdb->prefix}saw_departments d ON v.department_id = d.id
             WHERE v.id = %d",
            $visit_id
        ), ARRAY_A);
        
        if (!$visit) {
            wp_send_json_error(['message' => 'Návštěva nenalezena'], 404);
            return;
        }
        
        // Load visitors
        $visitors = $wpdb->get_results($wpdb->prepare(
            "SELECT vr.first_name, vr.last_name, vr.email, vr.company_name as visitor_company
             FROM {$wpdb->prefix}saw_visitors vr
             INNER JOIN {$wpdb->prefix}saw_visit_visitors vv ON vr.id = vv.visitor_id
             WHERE vv.visit_id = %d",
            $visit_id
        ), ARRAY_A);
        
        // Load hosts
        $hosts = $wpdb->get_results($wpdb->prepare(
            "SELECT u.display_name, u.user_email
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->prefix}saw_visit_hosts vh ON u.ID = vh.user_id
             WHERE vh.visit_id = %d",
            $visit_id
        ), ARRAY_A);
        
        // Get calendar links
        $calendar_links = [];
        if (class_exists('SAW_Calendar_Links')) {
            $calendar_links = SAW_Calendar_Links::for_visit($visit_id);
        }
        
        wp_send_json_success([
            'visit' => $visit,
            'visitors' => $visitors ?: [],
            'hosts' => $hosts ?: [],
            'calendarLinks' => $calendar_links,
        ]);
    }
    
    /**
     * AJAX: Update event (drag & drop)
     */
    public function ajax_update_event() {
        if (!check_ajax_referer('saw_calendar_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Neplatný požadavek'], 403);
            return;
        }
        
        // Check edit permission
        if (function_exists('saw_can') && !saw_can('edit', 'visits')) {
            wp_send_json_error(['message' => 'Nemáte oprávnění'], 403);
            return;
        }
        
        $visit_id = intval($_POST['id'] ?? 0);
        $new_start = sanitize_text_field($_POST['start'] ?? '');
        $new_end = sanitize_text_field($_POST['end'] ?? '');
        
        if (!$visit_id || !$new_start) {
            wp_send_json_error(['message' => 'Neplatná data'], 400);
            return;
        }
        
        global $wpdb;
        
        // Parse dates
        $start_date = date('Y-m-d', strtotime($new_start));
        
        $update_data = [
            'visit_date' => $start_date,
            'scheduled_arrival' => date('Y-m-d H:i:s', strtotime($new_start)),
        ];
        $update_format = ['%s', '%s'];
        
        if (!empty($new_end)) {
            $update_data['scheduled_departure'] = date('Y-m-d H:i:s', strtotime($new_end));
            $update_format[] = '%s';
            
            // Calculate duration
            $duration = (strtotime($new_end) - strtotime($new_start)) / 60;
            $update_data['expected_duration'] = max(15, intval($duration));
            $update_format[] = '%d';
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            $update_data,
            ['id' => $visit_id],
            $update_format,
            ['%d']
        );
        
        if ($result === false) {
            wp_send_json_error(['message' => 'Chyba při ukládání'], 500);
            return;
        }
        
        wp_send_json_success(['message' => 'Návštěva byla přesunuta']);
    }
}

// Initialize singleton to register AJAX handlers
// This runs on every request including AJAX
SAW_Module_Calendar_Controller::instance();
