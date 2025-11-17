<?php
/**
 * Visits Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     3.0.0 - REFACTORED: Walk-in, invitations, physical/legal person support
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
        
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Enqueue module assets
     */
    public function enqueue_assets() {
        if (!is_admin()) {
            return;
        }
        
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        
        if (strpos($current_url, '/admin/visits') === false) {
            return;
        }
        
        wp_enqueue_style(
            'saw-visits-css',
            SAW_VISITORS_PLUGIN_URL . 'includes/modules/visits/visits.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-visits-scripts',
            SAW_VISITORS_PLUGIN_URL . 'includes/modules/visits/scripts.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_enqueue_script(
            'saw-visits-js',
            SAW_VISITORS_PLUGIN_URL . 'includes/modules/visits/visits.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script('saw-visits-js', 'sawVisits', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce'),
        ));
    }
    
    /**
     * Display list view with filters
     */
    public function index() {
        $branch_id = SAW_Context::get_branch_id();
        
        $filters = array();
        if ($branch_id) {
            $filters['branch_id'] = $branch_id;
        }
        
        // Search
        if (!empty($_GET['s'])) {
            $filters['search'] = sanitize_text_field($_GET['s']);
        }
        
        // Status filter
        if (!empty($_GET['status'])) {
            $filters['status'] = sanitize_text_field($_GET['status']);
        }
        
        // Sorting
        if (!empty($_GET['orderby'])) {
            $filters['orderby'] = sanitize_text_field($_GET['orderby']);
        }
        if (!empty($_GET['order'])) {
            $filters['order'] = sanitize_text_field($_GET['order']);
        }
        
        // Pagination
        if (!empty($_GET['paged'])) {
            $filters['page'] = intval($_GET['paged']);
        }
        
        // Get data from model
        $list_data = $this->model->get_all($filters);
        
        // Add formatted schedule column to each item
        foreach ($list_data['items'] as &$item) {
            $item['schedule_dates_formatted'] = $this->render_schedule_column($item);
        }
        unset($item);
        
        // Add filters back to list_data for template
        $list_data['search'] = $filters['search'] ?? '';
        $list_data['status_filter'] = $filters['status'] ?? '';
        $list_data['orderby'] = $filters['orderby'] ?? 'first_schedule_date';
        $list_data['order'] = $filters['order'] ?? 'DESC';
        
        // Render list view
        $this->render_list_view($list_data);
    }
    
    // ============================================
    // AJAX HANDLERS - NEW
    // ============================================
    
    /**
     * AJAX: Get hosts by branch
     * Used when branch changes in form
     */
    public function ajax_get_hosts_by_branch() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        
        if (!$branch_id) {
            wp_send_json_error(array('message' => 'Neplatná pobočka'));
            return;
        }
        
        global $wpdb;
        $customer_id = SAW_Context::get_customer_id();
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'Zákazník nenalezen'));
            return;
        }
        
        $hosts = $wpdb->get_results($wpdb->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.role, u.email
             FROM %i u
             WHERE u.customer_id = %d 
             AND u.branch_id = %d
             AND u.role IN ('admin', 'super_manager', 'manager')
             AND u.is_active = 1
             ORDER BY u.last_name, u.first_name",
            $wpdb->prefix . 'saw_users',
            $customer_id,
            $branch_id
        ), ARRAY_A);
        
        wp_send_json_success(array('hosts' => $hosts));
    }
    
    /**
     * AJAX: Create walk-in visit
     * Immediate check-in for spontaneous visitors
     */
    public function ajax_create_walkin() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
            return;
        }
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        $is_company = isset($_POST['is_company']) ? intval($_POST['is_company']) : 0;
        $company_name = isset($_POST['company_name']) ? sanitize_text_field($_POST['company_name']) : '';
        $purpose = isset($_POST['purpose']) ? sanitize_textarea_field($_POST['purpose']) : '';
        
        if (!$branch_id) {
            wp_send_json_error(array('message' => 'Vyberte pobočku'));
            return;
        }
        
        $company_id = null;
        
        // If company, find or create
        if ($is_company && !empty($company_name)) {
            $company_id = $this->model->find_or_create_company($branch_id, $company_name);
            
            if (is_wp_error($company_id)) {
                wp_send_json_error(array('message' => $company_id->get_error_message()));
                return;
            }
        }
        
        // Create walk-in visit
        $visit_data = array(
            'branch_id' => $branch_id,
            'company_id' => $company_id,
            'purpose' => $purpose,
        );
        
        $visit_id = $this->model->create_walkin_visit($visit_data);
        
        if (is_wp_error($visit_id)) {
            wp_send_json_error(array('message' => $visit_id->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'visit_id' => $visit_id,
            'message' => 'Walk-in návštěva vytvořena',
        ));
    }
    
    /**
     * AJAX: Send invitation email
     * Generates token and sends email with form link
     */
    public function ajax_send_invitation() {
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
        
        $visit = $this->model->get_by_id($visit_id);
        
        if (!$visit) {
            wp_send_json_error(array('message' => 'Návštěva nenalezena'));
            return;
        }
        
        if (empty($visit['invitation_email'])) {
            wp_send_json_error(array('message' => 'Email pro pozvánku není vyplněn'));
            return;
        }
        
        // Generate token if not exists
        global $wpdb;
        
        if (empty($visit['invitation_token'])) {
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $wpdb->update(
                $this->model->table,
                array(
                    'invitation_token' => $token,
                    'invitation_token_expires_at' => $expires_at,
                ),
                array('id' => $visit_id),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            $token = $visit['invitation_token'];
        }
        
        // TODO: Send email via SAW_Email class (to be implemented in Phase 5)
        // For now, just mark as sent
        $wpdb->update(
            $this->model->table,
            array('invitation_sent_at' => current_time('mysql')),
            array('id' => $visit_id),
            array('%s'),
            array('%d')
        );
        
        wp_send_json_success(array(
            'message' => 'Pozvánka odeslána',
            'invitation_url' => home_url('/visitor-training/' . $token . '/'),
        ));
    }
    
    // ============================================
    // FORM DATA HANDLING
    // ============================================
    
    /**
     * Prepare form data for save
     * Handles physical/legal person toggle
     */
    protected function prepare_form_data($post) {
        $data = array();
        
        if (isset($post['customer_id'])) {
            $data['customer_id'] = intval($post['customer_id']);
        }
        
        if (isset($post['branch_id'])) {
            $data['branch_id'] = intval($post['branch_id']);
        }
        
        // Handle physical vs legal person
        $has_company = isset($post['has_company']) ? intval($post['has_company']) : 1;
        
        if ($has_company && isset($post['company_id'])) {
            $data['company_id'] = intval($post['company_id']);
        } else {
            $data['company_id'] = null; // Physical person
        }
        
        $text_fields = array('visit_type', 'status', 'purpose', 'notes');
        foreach ($text_fields as $field) {
            if (isset($post[$field])) {
                $data[$field] = sanitize_text_field($post[$field]);
            }
        }
        
        if (isset($post['invitation_email'])) {
            $data['invitation_email'] = sanitize_email($post['invitation_email']);
        }
        
        return $data;
    }
    
    /**
     * Before save hook
     */
    protected function before_save($data) {
        if (empty($data['customer_id'])) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        
        return $data;
    }
    
    /**
 * After save hook
 * Save related data (hosts, schedules)
 */
protected function after_save($id) {
    global $wpdb;
    
    // ✅ 1. GENERATE PIN for planned visits
    $visit = $this->model->get_by_id($id);
    
    if ($visit && $visit['visit_type'] === 'planned' && empty($visit['pin_code'])) {
        $pin = $this->model->generate_pin();
        
        $wpdb->update(
            $wpdb->prefix . 'saw_visits',
            ['pin_code' => $pin],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
        
        error_log("[SAW Visits] Generated PIN {$pin} for visit #{$id}");
    }
    
    // ✅ 2. SET created_by (if not already set)
    if ($visit && empty($visit['created_by'])) {
        $current_user = wp_get_current_user();
        
        if ($current_user && $current_user->ID) {
            $saw_user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d",
                $current_user->ID
            ));
            
            if ($saw_user_id) {
                $wpdb->update(
                    $wpdb->prefix . 'saw_visits',
                    ['created_by' => $saw_user_id],
                    ['id' => $id],
                    ['%d'],
                    ['%d']
                );
            }
        }
    }
    
    // ✅ 3. SAVE HOSTS
    if (isset($_POST['hosts']) && is_array($_POST['hosts'])) {
        $this->model->save_hosts($id, array_map('intval', $_POST['hosts']));
    }
    
    // ✅ 4. SAVE SCHEDULES
    if (isset($_POST['schedule_dates']) && is_array($_POST['schedule_dates'])) {
        $result = $this->model->save_schedules($id, $_POST);
        
        if (is_wp_error($result)) {
            $_SESSION['saw_error'] = $result->get_error_message();
        }
    }
}
    
    /**
     * Format data for detail view
     */
    protected function format_detail_data($item) {
    global $wpdb;
    
    // ✅ NAČTI CELÝ ZÁZNAM ZNOVU (aby obsahoval PIN a všechna pole)
    if (!empty($item['id'])) {
        $full_item = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}saw_visits WHERE id = %d",
            $item['id']
        ), ARRAY_A);
        
        // Merge s původními daty
        if ($full_item) {
            $item = array_merge($item, $full_item);
        }
    }
    
    // Load company name
    if (!empty($item['company_id'])) {
        $company = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_companies',
            $item['company_id']
        ), ARRAY_A);
        
        if ($company) {
            $item['company_name'] = $company['name'];
        }
    }
    
    // Load branch name
    if (!empty($item['branch_id'])) {
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM %i WHERE id = %d",
            $wpdb->prefix . 'saw_branches',
            $item['branch_id']
        ), ARRAY_A);
        
        if ($branch) {
            $item['branch_name'] = $branch['name'];
        }
    }
    
    // Load hosts
    if (!empty($item['id'])) {
        $item['hosts'] = $this->model->get_hosts($item['id']);
    }
    
    // Load schedules
    if (!empty($item['id'])) {
        $item['schedules'] = $this->model->get_schedules($item['id']);
    }
    
    return $item;
}
    
    // ============================================
    // RENDERING HELPERS
    // ============================================
    
    /**
     * Render schedule column for list view
     */
    public function render_schedule_column($item) {
        if (empty($item['id'])) {
            return '<span class="saw-text-muted">—</span>';
        }
        
        $schedules = $this->model->get_schedules($item['id']);
        
        if (empty($schedules)) {
            return '<span class="saw-text-muted">—</span>';
        }
        
        $lines = array();
        foreach ($schedules as $schedule) {
            $date = date('d.m.Y', strtotime($schedule['date']));
            $day_name = $this->get_czech_day_name($schedule['date']);
            
            $time_range = '';
            if (!empty($schedule['time_from']) || !empty($schedule['time_to'])) {
                $time_from = $schedule['time_from'] ? substr($schedule['time_from'], 0, 5) : '—';
                $time_to = $schedule['time_to'] ? substr($schedule['time_to'], 0, 5) : '—';
                $time_range = " <span style=\"color: #6b7280;\">({$time_from} - {$time_to})</span>";
            }
            
            $lines[] = '<div style="margin-bottom: 4px;"><strong>' . $day_name . '</strong> ' . $date . $time_range . '</div>';
        }
        
        return '<div class="saw-schedule-list">' . implode('', $lines) . '</div>';
    }
    
    /**
     * Get Czech day name abbreviation
     */
    private function get_czech_day_name($date) {
        $day_names = array(
            'Mon' => 'Po',
            'Tue' => 'Út',
            'Wed' => 'St',
            'Thu' => 'Čt',
            'Fri' => 'Pá',
            'Sat' => 'So',
            'Sun' => 'Ne',
        );
        
        $en_day = date('D', strtotime($date));
        return $day_names[$en_day] ?? '';
    }
}