<?php
/**
 * Visits Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits
 * @version     2.1.0 - Added search, filters, and schedule-based sorting
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
    }
    
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
    
    // Zavolej model PŘÍMO
    $list_data = $this->model->get_all($filters);
    
    // PŘIDEJ SCHEDULES DO KAŽDÉHO ŘÁDKU
    foreach ($list_data['items'] as &$item) {
        $item['schedule_dates_formatted'] = $this->render_schedule_column($item);
    }
    unset($item);
    
    // Přidej filtry zpátky do list_data
    $list_data['search'] = $filters['search'] ?? '';
    $list_data['status_filter'] = $filters['status'] ?? '';
    $list_data['orderby'] = $filters['orderby'] ?? 'first_schedule_date';
    $list_data['order'] = $filters['order'] ?? 'DESC';
    
    // ✅ POUŽIJ render_list_view() - má správný layout!
    $this->render_list_view($list_data);
}
    
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
    
    protected function prepare_form_data($post) {
        $data = array();
        
        if (isset($post['customer_id'])) {
            $data['customer_id'] = intval($post['customer_id']);
        }
        
        if (isset($post['branch_id'])) {
            $data['branch_id'] = intval($post['branch_id']);
        }
        
        if (isset($post['company_id'])) {
            $data['company_id'] = intval($post['company_id']);
        }
        
        $text_fields = array('visit_type', 'status', 'purpose');
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
    
    protected function before_save($data) {
        if (empty($data['customer_id'])) {
            $data['customer_id'] = SAW_Context::get_customer_id();
        }
        
        return $data;
    }
    
    protected function after_save($id) {
        if (isset($_POST['hosts']) && is_array($_POST['hosts'])) {
            $this->model->save_hosts($id, array_map('intval', $_POST['hosts']));
        }
        
        if (isset($_POST['schedule_dates']) && is_array($_POST['schedule_dates'])) {
            $this->model->save_schedules($id, $_POST);
        }
    }
    
    protected function format_detail_data($item) {
        global $wpdb;
        
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
        
        if (!empty($item['id'])) {
            $item['hosts'] = $this->model->get_hosts($item['id']);
        }
        
        return $item;
    }
    
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