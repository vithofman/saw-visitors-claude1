<?php
/**
 * SAW App Layout Manager
 * 
 * @package SAW_Visitors
 * @subpackage Frontend
 * @since 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_App_Layout {
    
    private $current_user;
    private $current_customer;
    private $page_title;
    private $active_menu;
    
    public function __construct() {
        $this->current_user = SAW_Auth::get_current_user();
        $this->current_customer = SAW_Auth::get_current_customer();
    }
    
    /**
     * Render complete app layout
     */
    public function render($content, $page_title = '', $active_menu = '') {
        $this->page_title = $page_title;
        $this->active_menu = $active_menu;
        
        $this->render_header();
        
        echo '<div class="saw-app-wrapper">';
        
        $this->render_sidebar();
        
        echo '<main class="saw-app-content">';
        echo $content;
        echo '</main>';
        
        echo '</div>'; // .saw-app-wrapper
        
        $this->render_footer();
    }
    
    /**
     * Render header
     */
    private function render_header() {
        $header = new SAW_App_Header($this->current_user, $this->current_customer);
        $header->render();
    }
    
    /**
     * Render sidebar
     */
    private function render_sidebar() {
        $sidebar = new SAW_App_Sidebar($this->current_user, $this->current_customer, $this->active_menu);
        $sidebar->render();
    }
    
    /**
     * Render footer
     */
    private function render_footer() {
        $footer = new SAW_App_Footer();
        $footer->render();
    }
    
    /**
     * Enqueue app styles & scripts
     */
    public static function enqueue_assets() {
        wp_enqueue_style(
            'saw-app',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-app-header',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-header.css',
            array('saw-app'),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-app-sidebar',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-sidebar.css',
            array('saw-app'),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_style(
            'saw-app-responsive',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-app-responsive.css',
            array('saw-app'),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-app',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-app.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script('saw-app', 'sawApp', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_app_nonce'),
            'userId' => $this->current_user['id'] ?? 0,
            'customerId' => $this->current_customer['id'] ?? 0,
        ));
    }
}
