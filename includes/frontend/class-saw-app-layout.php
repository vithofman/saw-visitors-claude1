<?php
/**
 * SAW App Layout Manager - FIXED VERSION
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
        // OPRAVENO: Kontrola existence SAW_Auth před voláním
        if (class_exists('SAW_Auth')) {
            $this->current_user = SAW_Auth::get_current_user();
            $this->current_customer = SAW_Auth::get_current_customer();
        } else {
            // Fallback: Fake data pro testování
            $this->current_user = array(
                'id' => 1,
                'name' => 'Demo Admin',
                'email' => 'admin@demo.cz',
                'role' => 'admin',
            );
            
            $this->current_customer = array(
                'id' => 1,
                'name' => 'Demo Firma s.r.o.',
                'ico' => '12345678',
            );
        }
    }
    
    /**
     * Render complete app layout
     */
    public function render($content, $page_title = '', $active_menu = '') {
        $this->page_title = $page_title;
        $this->active_menu = $active_menu;
        
        // Render immediately (don't wait for wp_footer)
        $this->render_complete_page($content);
        exit;
    }
    
    /**
     * Render complete HTML page
     */
    private function render_complete_page($content) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($this->page_title); ?> - SAW Visitors</title>
            
            <!-- SAW App Styles -->
            <link rel="stylesheet" href="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/css/saw-app.css?v=<?php echo SAW_VISITORS_VERSION; ?>">
            <link rel="stylesheet" href="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/css/saw-app-header.css?v=<?php echo SAW_VISITORS_VERSION; ?>">
            <link rel="stylesheet" href="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/css/saw-app-sidebar.css?v=<?php echo SAW_VISITORS_VERSION; ?>">
            <link rel="stylesheet" href="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/css/saw-app-footer.css?v=<?php echo SAW_VISITORS_VERSION; ?>">
            
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
        </head>
        <body class="saw-app-body">
            
            <?php $this->render_header(); ?>
            
            <div class="saw-app-wrapper">
                
                <?php $this->render_sidebar(); ?>
                
                <main class="saw-app-content">
                    <?php echo $content; ?>
                </main>
                
            </div>
            
            <?php $this->render_footer(); ?>
            
            <!-- SAW App Scripts -->
            <script src="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/js/saw-app.js?v=<?php echo SAW_VISITORS_VERSION; ?>"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.body.classList.add('loaded');
                });
            </script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render header
     */
    private function render_header() {
        if (!class_exists('SAW_App_Header')) {
            echo '<!-- SAW_App_Header class not loaded yet -->';
            return;
        }
        
        $header = new SAW_App_Header($this->current_user, $this->current_customer);
        $header->render();
    }
    
    /**
     * Render sidebar
     */
    private function render_sidebar() {
        if (!class_exists('SAW_App_Sidebar')) {
            echo '<!-- SAW_App_Sidebar class not loaded yet -->';
            return;
        }
        
        $sidebar = new SAW_App_Sidebar($this->current_user, $this->current_customer, $this->active_menu);
        $sidebar->render();
    }
    
    /**
     * Render footer
     */
    private function render_footer() {
        if (!class_exists('SAW_App_Footer')) {
            echo '<!-- SAW_App_Footer class not loaded yet -->';
            return;
        }
        
        $footer = new SAW_App_Footer();
        $footer->render();
    }
}