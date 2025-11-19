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
        SAW_Asset_Manager::enqueue_module('visitors');
        
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
        
        return $item;
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
}