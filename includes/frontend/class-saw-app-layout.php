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
        
        // Add to wp_head
        add_action('wp_head', array($this, 'add_head_content'));
        
        // Add to wp_footer (render before </body>)
        add_action('wp_footer', array($this, 'render_layout_content'), 1);
        
        // Store content for later rendering
        $GLOBALS['saw_page_content'] = $content;
    }
    
    public function add_head_content() {
        ?>
        <style>
            /* Prevent FOUC */
            body.saw-app-body { 
                opacity: 0; 
            }
            body.saw-app-body.loaded {
                opacity: 1;
                transition: opacity 0.2s;
            }
        </style>
        <?php
    }
    
    public function render_layout_content() {
        $content = isset($GLOBALS['saw_page_content']) ? $GLOBALS['saw_page_content'] : '';
        
        $this->render_header();
        
        echo '<div class="saw-app-wrapper">';
        
        $this->render_sidebar();
        
        echo '<main class="saw-app-content">';
        echo $content;
        echo '</main>';
        
        echo '</div>'; // .saw-app-wrapper
        
        $this->render_footer();
        
        ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                document.body.classList.add('loaded');
            });
        </script>
        <?php
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
}