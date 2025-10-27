<?php
/**
 * SAW Dashboard Controller - SIMPLIFIED
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
        // Fake data for testing
        $stats = array(
            'active_visits' => 3,
            'today_visits' => 12,
            'month_visits' => 156,
            'pending_invitations' => 5,
            'compliance_rate' => 94.5,
        );
        
        // Fake customer data
        $customer = array(
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
        );
        
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/dashboard.php';
        $content = ob_get_clean();
        
        $layout = new SAW_App_Layout();
        $layout->render($content, 'Dashboard', 'dashboard');
    }
}