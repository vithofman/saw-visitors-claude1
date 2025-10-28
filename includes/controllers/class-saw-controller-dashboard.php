<?php
/**
 * SAW Dashboard Controller
 * 
 * @package SAW_Visitors
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Controller_Dashboard {
    
    public static function index() {
        // Testing data
        $stats = array(
            'active_visits' => 3,
            'today_visits' => 12,
            'month_visits' => 156,
            'pending_invitations' => 5,
            'compliance_rate' => 94.5,
        );
        
        $customer = array(
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
        );
        
        ob_start();
        include SAW_VISITORS_PLUGIN_DIR . 'templates/pages/dashboard/index.php';
        $content = ob_get_clean();
        
        $layout = new SAW_App_Layout();
        $layout->render($content, 'Dashboard', 'dashboard');
    }
}