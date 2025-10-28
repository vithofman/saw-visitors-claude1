<?php
/**
 * SAW App Layout Manager - OPRAVENÁ VERZE (BEZ FATAL ERRORS)
 * 
 * ZMĚNY:
 * - Odstraněn problematický admin_enqueue_scripts hook
 * - Bezpečné načítání WordPress assets
 * - Funguje jak ve frontendu tak v backendu
 * - Žádné závislosti na WordPress admin hooku
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
    
    /**
     * Konstruktor - BEZ HOOKŮ!
     */
    public function __construct() {
        // Žádné hooks zde! Budeme volat metody přímo.
    }
    
    /**
     * Render complete app layout
     * 
     * @param string $content       HTML content stránky
     * @param string $page_title    Titulek stránky
     * @param string $active_menu   ID aktivní menu položky
     * @param array  $user          User data (id, name, email, role)
     * @param array  $customer      Customer data (id, name, ico, address)
     */
    public function render($content, $page_title = '', $active_menu = '', $user = null, $customer = null) {
        $this->page_title = $page_title;
        $this->active_menu = $active_menu;
        
        // Použijeme data z parametrů, nebo fallback na demo data
        $this->current_user = $user ?: array(
            'id' => 1,
            'name' => 'Demo Admin',
            'email' => 'admin@demo.cz',
            'role' => 'admin',
        );
        
        $this->current_customer = $customer ?: array(
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
            'address' => 'Praha 1',
        );
        
        // Render immediately
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
                body.saw-app-body { 
                    opacity: 0; 
                    margin: 0;
                    padding: 0;
                }
                body.saw-app-body.loaded { 
                    opacity: 1; 
                    transition: opacity 0.2s; 
                }
            </style>
            
            <?php
            /**
             * BEZPEČNÉ NAČTENÍ WORDPRESS ASSETS
             * 
             * Kontrolujeme existenci funkcí PŘED voláním.
             * Toto funguje i ve frontendu!
             */
            
            // jQuery (téměř vždy dostupné)
            if (function_exists('wp_enqueue_script')) {
                wp_enqueue_script('jquery');
            }
            
            // Dashicons (ikony) - registrujeme pokud nejsou
            if (function_exists('wp_register_style') && !wp_style_is('dashicons', 'registered')) {
                wp_register_style('dashicons', includes_url('css/dashicons.min.css'), array(), null);
            }
            
            // Print head scripts a styles
            if (function_exists('wp_print_head_scripts')) {
                wp_print_head_scripts();
            }
            
            if (function_exists('wp_print_styles')) {
                wp_print_styles('dashicons');
            }
            ?>
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
            
            <?php
            /**
             * BEZPEČNÉ NAČTENÍ FOOTER SCRIPTŮ
             */
            if (function_exists('wp_print_footer_scripts')) {
                wp_print_footer_scripts();
            }
            ?>
            
            <!-- SAW App JavaScript -->
            <script src="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/js/saw-app.js?v=<?php echo SAW_VISITORS_VERSION; ?>"></script>
            
            <script>
                // Fade in po načtení
                document.addEventListener('DOMContentLoaded', function() {
                    document.body.classList.add('loaded');
                });
                
                // Console info
                console.log('🚀 SAW Visitors App loaded');
                console.log('Version: <?php echo SAW_VISITORS_VERSION; ?>');
                console.log('Page: <?php echo esc_js($this->page_title); ?>');
            </script>
        </body>
        </html>
        <?php
    }
    
    /**
     * Render header
     */
    private function render_header() {
        if (class_exists('SAW_App_Header')) {
            $header = new SAW_App_Header($this->current_user, $this->current_customer);
            $header->render();
        } else {
            // Fallback pokud header třída neexistuje
            echo '<header class="saw-app-header">';
            echo '<div class="saw-header-content">';
            echo '<h1>' . esc_html($this->page_title) . '</h1>';
            echo '</div>';
            echo '</header>';
        }
    }
    
    /**
     * Render sidebar
     */
    private function render_sidebar() {
        if (class_exists('SAW_App_Sidebar')) {
            $sidebar = new SAW_App_Sidebar($this->current_user, $this->current_customer, $this->active_menu);
            $sidebar->render();
        } else {
            // Fallback pokud sidebar třída neexistuje
            echo '<aside class="saw-app-sidebar">';
            echo '<nav><a href="/admin/">Dashboard</a></nav>';
            echo '</aside>';
        }
    }
    
    /**
     * Render footer
     */
    private function render_footer() {
        if (class_exists('SAW_App_Footer')) {
            $footer = new SAW_App_Footer();
            $footer->render();
        } else {
            // Fallback pokud footer třída neexistuje
            echo '<footer class="saw-app-footer">';
            echo '<p>SAW Visitors v' . SAW_VISITORS_VERSION . ' © ' . date('Y') . '</p>';
            echo '</footer>';
        }
    }
}