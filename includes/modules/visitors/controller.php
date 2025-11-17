<?php
/**
 * Visitors Module Controller
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visitors
 * @version     2.0.0 - REFACTORED: Check-in/out AJAX handlers, ad-hoc visitors
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('SAW_Base_Controller')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/class-base-controller.php';
}

if (!trait_exists('SAW_AJAX_Handlers')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/base/trait-ajax-handlers.php';
}

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
    }
    
    /**
     * Display list view
     */
    public function index() {
        $this->render_list_view();
    }
    
    // ============================================
    // NEW AJAX HANDLERS - CHECK-IN/OUT
    // ============================================
    
    /**
     * AJAX: Check-in visitor for specific day
     * Called from terminal or dashboard
     */
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
    
    /**
 * AJAX: Check-out visitor for specific day
 * Called from terminal or dashboard
 * Supports manual checkout by admin
 */
public function ajax_checkout() {
    check_ajax_referer('saw_ajax_nonce', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(array('message' => 'Nemáte oprávnění'));
        return;
    }
    
    // ✅ DEBUG
    error_log('=== AJAX CHECKOUT DEBUG ===');
    error_log('POST data: ' . print_r($_POST, true));
    
    $visitor_id = isset($_POST['visitor_id']) ? intval($_POST['visitor_id']) : 0;
    $log_date = isset($_POST['log_date']) ? sanitize_text_field($_POST['log_date']) : current_time('Y-m-d');
    $manual = isset($_POST['manual']) ? (bool) $_POST['manual'] : false;
    $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : null;
    
    error_log('Parsed visitor_id: ' . $visitor_id);
    error_log('Parsed log_date: ' . $log_date);
    error_log('Parsed manual: ' . ($manual ? 'yes' : 'no'));
    
    if (!$visitor_id) {
        error_log('ERROR: visitor_id is 0 or empty!');
        wp_send_json_error(array('message' => 'Neplatný návštěvník (ID: 0)'));
        return;
    }
    
    // ✅ Verify visitor exists BEFORE calling model
    global $wpdb;
    $visitor_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors WHERE id = %d",
        $visitor_id
    ));
    
    if (!$visitor_exists) {
        error_log('ERROR: Visitor ID ' . $visitor_id . ' not found in database!');
        wp_send_json_error(array('message' => 'Návštěvník nenalezen (ID: ' . $visitor_id . ')'));
        return;
    }
    
    error_log('Visitor exists, proceeding with checkout...');
    
    $admin_id = null;
    if ($manual) {
        $admin_id = get_current_user_id();
        error_log('Manual checkout by admin ID: ' . $admin_id);
    }
    
    $result = $this->model->daily_checkout($visitor_id, $log_date, $manual, $admin_id, $reason);
    
    if (is_wp_error($result)) {
        error_log('ERROR from model: ' . $result->get_error_message());
        wp_send_json_error(array('message' => $result->get_error_message()));
        return;
    }
    
    error_log('SUCCESS: Checkout completed for visitor ID ' . $visitor_id);
    
    wp_send_json_success(array(
        'message' => 'Check-out úspěšný',
        'checked_out_at' => current_time('Y-m-d H:i:s'),
    ));
}
    
    /**
     * AJAX: Add ad-hoc visitor
     * For unplanned visitors that show up
     */
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
    
    // ============================================
    // FORM DATA HANDLING
    // ============================================
    
    /**
     * Prepare form data for save
     */
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
    
    /**
     * After save hook
     * Save certificates
     */
    protected function after_save($id) {
        if (isset($_POST['certificates']) && is_array($_POST['certificates'])) {
            $this->model->save_certificates($id, $_POST['certificates']);
        }
    }
    
    /**
     * Format data for detail view
     * Load related data (visit, hosts, certificates, daily logs)
     */
    protected function format_detail_data($item) {
        if (empty($item)) {
            return $item;
        }
        
        // Load visit data
        if (!empty($item['visit_id'])) {
            $item['visit_data'] = $this->model->get_visit_data($item['visit_id']);
            
            // Load hosts for this visit
            if (!empty($item['visit_data'])) {
                global $wpdb;
                $hosts = $wpdb->get_results($wpdb->prepare(
                    "SELECT u.id, u.first_name, u.last_name, u.email
                     FROM %i vh
                     INNER JOIN %i u ON vh.user_id = u.id
                     WHERE vh.visit_id = %d
                     ORDER BY u.last_name, u.first_name",
                    $wpdb->prefix . 'saw_visit_hosts',
                    $wpdb->prefix . 'saw_users',
                    $item['visit_id']
                ), ARRAY_A);
                
                $item['visit_data']['hosts'] = $hosts;
            }
        }
        
        // Load certificates
        if (!empty($item['id'])) {
            $item['certificates'] = $this->model->get_certificates($item['id']);
        }
        
        // Load daily logs (check-in/out history)
        if (!empty($item['id'])) {
            $item['daily_logs'] = $this->model->get_daily_logs($item['id']);
        }
        
        return $item;
    }
}