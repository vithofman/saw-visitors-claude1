<?php
/**
 * SAW App Layout Manager - FIXED VERSION (CHAT 7)
 * 
 * ✅ OPRAVA: Dynamic customer loading from session
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
     * Konstruktor
     */
    public function __construct() {
        // Initialize session if needed
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    /**
     * Render complete app layout
     * 
     * @param string $content       HTML content stránky
     * @param string $page_title    Titulek stránky
     * @param string $active_menu   ID aktivní menu položky
     * @param array  $user          User data (optional - will load dynamically if null)
     * @param array  $customer      Customer data (optional - will load dynamically if null)
     */
    public function render($content, $page_title = '', $active_menu = '', $user = null, $customer = null) {
        $this->page_title = $page_title;
        $this->active_menu = $active_menu;
        
        // ✅ NOVÉ: Load user dynamically if not provided
        $this->current_user = $user ?: $this->get_current_user_data();
        
        // ✅ KRITICKÁ ZMĚNA: Load customer DYNAMICALLY from session
        $this->current_customer = $customer ?: $this->get_current_customer_data();
        
        // Log for debugging
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log('SAW Layout: Rendering page "' . $page_title . '"');
            error_log('SAW Layout: User role: ' . $this->current_user['role']);
            error_log('SAW Layout: Customer ID: ' . $this->current_customer['id'] . ' (' . $this->current_customer['name'] . ')');
        }
        
        // Check if AJAX request
        if ($this->is_ajax_request()) {
            $this->render_content_only($content);
            exit;
        }
        
        // Render complete page
        $this->render_complete_page($content);
        exit;
    }
    
    /**
     * ✅ NOVÁ METODA: Get current customer data DYNAMICALLY
     * 
     * Priority:
     * 1. SuperAdmin: Load from session (selected customer)
     * 2. Admin/Manager: Load their assigned customer
     * 3. Fallback: Load first customer
     */
    private function get_current_customer_data() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'saw_customers';
        
        // 1. CHECK IF SUPERADMIN
        if ($this->is_super_admin()) {
            // Load from session
            $customer_id = $this->get_selected_customer_id_from_session();
            
            if ($customer_id) {
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$table_name} WHERE id = %d",
                    $customer_id
                ), ARRAY_A);
                
                if ($customer) {
                    if (defined('SAW_DEBUG') && SAW_DEBUG) {
                        error_log('SAW Layout: SuperAdmin loaded customer from session: ID ' . $customer_id);
                    }
                    
                    return array(
                        'id' => $customer['id'],
                        'name' => $customer['name'],
                        'ico' => $customer['ico'] ?? '',
                        'address' => $customer['address'] ?? '',
                        'logo_url' => $customer['logo_url'] ?? '',
                    );
                }
            }
            
            // Fallback: Load first customer
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log('SAW Layout: SuperAdmin session empty, loading first customer');
            }
            
            return $this->load_first_customer();
        }
        
        // 2. ADMIN/MANAGER: Load their customer
        // TODO: Implement when saw_users table is ready
        // For now, return first customer
        return $this->load_first_customer();
    }
    
    /**
     * ✅ NOVÁ METODA: Get selected customer ID from session
     * 
     * @return int|null Customer ID or null
     */
    private function get_selected_customer_id_from_session() {
        // 1. Try PHP session
        if (isset($_SESSION['saw_current_customer_id'])) {
            $session_id = intval($_SESSION['saw_current_customer_id']);
            
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log('SAW Layout: Found customer ID in PHP session: ' . $session_id);
            }
            
            return $session_id;
        }
        
        // 2. Try WP user meta
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $meta_id = get_user_meta($user_id, 'saw_current_customer_id', true);
            
            if ($meta_id) {
                $meta_id = intval($meta_id);
                
                if (defined('SAW_DEBUG') && SAW_DEBUG) {
                    error_log('SAW Layout: Found customer ID in user meta: ' . $meta_id);
                }
                
                // Sync back to session
                $_SESSION['saw_current_customer_id'] = $meta_id;
                
                return $meta_id;
            }
        }
        
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log('SAW Layout: No customer ID found in session or user meta');
        }
        
        return null;
    }
    
    /**
     * ✅ NOVÁ METODA: Load first customer (fallback)
     * 
     * @return array Customer data
     */
    private function load_first_customer() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'saw_customers';
        
        $customer = $wpdb->get_row(
            "SELECT * FROM {$table_name} ORDER BY id ASC LIMIT 1",
            ARRAY_A
        );
        
        if ($customer) {
            // Save as default in session
            $_SESSION['saw_current_customer_id'] = intval($customer['id']);
            
            if (is_user_logged_in()) {
                update_user_meta(get_current_user_id(), 'saw_current_customer_id', intval($customer['id']));
            }
            
            if (defined('SAW_DEBUG') && SAW_DEBUG) {
                error_log('SAW Layout: Loaded first customer as fallback: ID ' . $customer['id']);
            }
            
            return array(
                'id' => $customer['id'],
                'name' => $customer['name'],
                'ico' => $customer['ico'] ?? '',
                'address' => $customer['address'] ?? '',
                'logo_url' => $customer['logo_url'] ?? '',
            );
        }
        
        // Ultimate fallback
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log('SAW Layout: No customers found in database! Using demo data.');
        }
        
        return array(
            'id' => 0,
            'name' => 'Žádný zákazník',
            'ico' => '',
            'address' => '',
            'logo_url' => '',
        );
    }
    
    /**
     * ✅ NOVÁ METODA: Check if current user is SuperAdmin
     * 
     * @return bool
     */
    private function is_super_admin() {
        return is_user_logged_in() && current_user_can('manage_options');
    }
    
    /**
     * Get current user data
     * 
     * @return array User data
     */
    private function get_current_user_data() {
        if (is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            
            return array(
                'id' => $wp_user->ID,
                'name' => $wp_user->display_name,
                'email' => $wp_user->user_email,
                'role' => current_user_can('manage_options') ? 'super_admin' : 'admin',
            );
        }
        
        return array(
            'id' => 0,
            'name' => 'Guest',
            'email' => '',
            'role' => 'guest',
        );
    }
    
    /**
     * Check if AJAX request
     */
    private function is_ajax_request() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Render content only (for AJAX)
     */
    private function render_content_only($content) {
        header('Content-Type: application/json');
        
        wp_send_json_success(array(
            'content' => $content,
            'title' => $this->page_title,
            'active_menu' => $this->active_menu
        ));
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
            
            <?php
            if (function_exists('wp_head')) {
                wp_head();
            }
            ?>
        </head>
        <body class="saw-app-body">
            
            <!-- Header -->
            <?php $this->render_header(); ?>
            
            <div class="saw-app-container">
                <!-- Sidebar -->
                <?php $this->render_sidebar(); ?>
                
                <!-- Main Content -->
                <main class="saw-app-main">
                    <div class="saw-app-content">
                        <?php echo $content; ?>
                    </div>
                </main>
            </div>
            
            <!-- Footer -->
            <?php $this->render_footer(); ?>
            
            <?php
            if (function_exists('wp_print_footer_scripts')) {
                wp_print_footer_scripts();
            }
            ?>
            
            <!-- SAW App JavaScript -->
            <script src="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/js/saw-app.js?v=<?php echo SAW_VISITORS_VERSION; ?>"></script>
            <script src="<?php echo SAW_VISITORS_PLUGIN_URL; ?>assets/js/saw-app-navigation.js?v=<?php echo SAW_VISITORS_VERSION; ?>"></script>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    document.body.classList.add('loaded');
                    console.log('🚀 SAW Visitors App loaded');
                    console.log('Version: <?php echo SAW_VISITORS_VERSION; ?>');
                    console.log('Customer: <?php echo esc_js($this->current_customer['name']); ?> (ID: <?php echo intval($this->current_customer['id']); ?>)');
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
        if (class_exists('SAW_App_Header')) {
            $header = new SAW_App_Header($this->current_user, $this->current_customer);
            $header->render();
        }
    }
    
    /**
     * Render sidebar
     */
    private function render_sidebar() {
        if (class_exists('SAW_App_Sidebar')) {
            $sidebar = new SAW_App_Sidebar($this->current_user, $this->current_customer, $this->active_menu);
            $sidebar->render();
        }
    }
    
    /**
     * Render footer
     */
    private function render_footer() {
        if (class_exists('SAW_App_Footer')) {
            $footer = new SAW_App_Footer();
            $footer->render();
        }
    }
}