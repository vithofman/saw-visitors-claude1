<?php
/**
 * SAW Dashboard Controller
 * 
 * @package SAW_Visitors
 * @subpackage Controllers
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Dashboard_Controller {
    
    public static function index() {
        $user = SAW_Auth::get_current_user();
        $customer = SAW_Auth::get_current_customer();
        
        if (!$user || !$customer) {
            wp_redirect('/login');
            exit;
        }
        
        $stats = self::get_dashboard_stats($customer['id']);
        
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/dashboard.php';
        $content = ob_get_clean();
        
        $layout = new SAW_App_Layout();
        $layout->render($content, 'Dashboard', 'dashboard');
    }
    
    private static function get_dashboard_stats($customer_id) {
        global $wpdb;
        
        $today = date('Y-m-d');
        $this_month = date('Y-m-01');
        
        $active_visits = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits 
            WHERE customer_id = %d 
            AND checked_out_at IS NULL",
            $customer_id
        ));
        
        $today_visits = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits 
            WHERE customer_id = %d 
            AND DATE(checked_in_at) = %s",
            $customer_id,
            $today
        ));
        
        $month_visits = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits 
            WHERE customer_id = %d 
            AND DATE(checked_in_at) >= %s",
            $customer_id,
            $this_month
        ));
        
        $pending_invitations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_invitations 
            WHERE customer_id = %d 
            AND status = 'pending' 
            AND visit_date >= %s",
            $customer_id,
            $today
        ));
        
        $compliance_rate = self::calculate_compliance_rate($customer_id);
        
        return array(
            'active_visits' => (int) $active_visits,
            'today_visits' => (int) $today_visits,
            'month_visits' => (int) $month_visits,
            'pending_invitations' => (int) $pending_invitations,
            'compliance_rate' => $compliance_rate,
        );
    }
    
    private static function calculate_compliance_rate($customer_id) {
        global $wpdb;
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits 
            WHERE customer_id = %d 
            AND DATE(checked_in_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $customer_id
        ));
        
        if ($total === 0) {
            return 100;
        }
        
        $compliant = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits v
            INNER JOIN {$wpdb->prefix}saw_visitors vr ON v.visitor_id = vr.id
            WHERE v.customer_id = %d 
            AND DATE(v.checked_in_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND vr.training_completed = 1",
            $customer_id
        ));
        
        return round(($compliant / $total) * 100, 1);
    }
}
