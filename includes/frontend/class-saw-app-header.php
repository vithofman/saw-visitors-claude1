<?php
/**
 * SAW App Header Component
 *
 * Renders application header with branding, user menu, customer switcher,
 * and language switcher.
 *
 * @package SAW_Visitors
 * @since   4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW App Header Class
 *
 * Main header component for the application.
 * Displays navigation, user info, customer switcher, and language selection.
 *
 * @since 4.8.0
 */
class SAW_App_Header {
    
    /**
     * Current user data
     *
     * @since 4.8.0
     * @var array
     */
    private $user;
    
    /**
     * Current customer data
     *
     * @since 4.8.0
     * @var array
     */
    private $customer;
    
    /**
     * Constructor
     *
     * Initializes user and customer data.
     * If not provided, loads from current WordPress user and SAW context.
     *
     * @since 4.8.0
     * @param array|null $user     Optional user data override
     * @param array|null $customer Optional customer data override
     */
    public function __construct($user = null, $customer = null) {
        if (!$user && is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            
            global $wpdb;
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM %i WHERE wp_user_id = %d AND is_active = 1",
                $wpdb->prefix . 'saw_users',
                $wp_user->ID
            ), ARRAY_A);
            
            if ($saw_user) {
                $this->user = array(
                    'id' => $saw_user['id'],
                    'name' => $saw_user['first_name'] . ' ' . $saw_user['last_name'],
                    'email' => $wp_user->user_email,
                    'role' => $saw_user['role'],
                    'first_name' => $saw_user['first_name'],
                    'last_name' => $saw_user['last_name'],
                );
            } else {
                $this->user = array(
                    'id' => $wp_user->ID,
                    'name' => $wp_user->display_name,
                    'email' => $wp_user->user_email,
                    'role' => 'admin',
                );
            }
        } else {
            $this->user = $user ?: array(
                'id' => 1,
                'name' => 'Demo Admin',
                'email' => 'admin@demo.cz',
                'role' => 'admin',
            );
        }
        
        // Get customer data from context
        if (!$customer) {
            $customer_id = null;
            
            if (class_exists('SAW_Context')) {
                $customer_id = SAW_Context::get_customer_id();
            }
            
            if ($customer_id) {
                global $wpdb;
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, name, ico, logo_url, primary_color FROM %i WHERE id = %d",
                    $wpdb->prefix . 'saw_customers',
                    $customer_id
                ), ARRAY_A);
            }
        }
        
        $this->customer = $customer ?: array(
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
            'logo_url' => '',
        );
    }
    
    /**
     * Render header
     *
     * Outputs complete header HTML including mobile menu toggle,
     * customer switcher, language switcher, and user menu.
     *
     * @since 4.8.0
     * @return void
     */
    public function render() {
        ?>
        <header class="saw-app-header" id="sawAppHeader">
            <div class="saw-header-left">
                <button class="saw-mobile-menu-toggle" id="sawMobileMenuToggle" aria-label="Menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                
                <?php $this->render_customer_switcher(); ?>
            </div>
            
            <div class="saw-header-right">
                <?php $this->render_language_switcher(); ?>
                
                <div class="saw-user-menu">
                    <button class="saw-user-menu-toggle" id="sawUserMenuToggle">
                        <div class="saw-user-avatar">
                            <?php echo esc_html(strtoupper(substr($this->user['name'], 0, 1))); ?>
                        </div>
                        <div class="saw-user-info">
                            <div class="saw-user-name"><?php echo esc_html($this->user['name']); ?></div>
                            <div class="saw-user-role"><?php echo esc_html($this->get_role_label()); ?></div>
                        </div>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" class="saw-user-arrow">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                    
                    <div class="saw-user-dropdown" id="sawUserDropdown">
                        <div class="saw-user-dropdown-header">
                            <div class="saw-user-dropdown-avatar">
                                <?php echo esc_html(strtoupper(substr($this->user['name'], 0, 1))); ?>
                            </div>
                            <div class="saw-user-dropdown-info">
                                <div class="saw-user-dropdown-name"><?php echo esc_html($this->user['name']); ?></div>
                                <div class="saw-user-dropdown-email"><?php echo esc_html($this->user['email']); ?></div>
                            </div>
                        </div>
                        
                        <div class="saw-user-dropdown-divider"></div>
                        
                        <a href="/admin/profile" class="saw-dropdown-item">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                            </svg>
                            <span>Můj profil</span>
                        </a>
                        
                        <a href="/admin/settings" class="saw-dropdown-item">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
                            </svg>
                            <span>Nastavení</span>
                        </a>
                        
                        <div class="saw-user-dropdown-divider"></div>
                        
                        <a href="/logout/" class="saw-dropdown-item saw-dropdown-item-danger">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1V4a1 1 0 00-1-1H3zm11 4.414l-4.293 4.293a1 1 0 01-1.414 0L4 7.414 5.414 6l3.293 3.293L13.586 5 15 6.414z" clip-rule="evenodd"/>
                            </svg>
                            <span>Odhlásit se</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>
        <?php
    }
    
    /**
     * Render customer switcher component
     *
     * Loads and renders the customer switcher component.
     *
     * @since 4.8.0
     * @return void
     */
    private function render_customer_switcher() {
        if (!class_exists('SAW_Component_Customer_Switcher')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/customer-switcher/class-saw-component-customer-switcher.php';
        }
        
        $switcher = new SAW_Component_Customer_Switcher($this->customer, $this->user);
        $switcher->render();
    }
    
    /**
     * Render language switcher component
     *
     * Loads and renders the language switcher component.
     *
     * @since 4.8.0
     * @return void
     */
    private function render_language_switcher() {
        if (!class_exists('SAW_Component_Language_Switcher')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/language-switcher/class-saw-component-language-switcher.php';
        }
        
        $current_language = $this->get_current_language();
        $switcher = new SAW_Component_Language_Switcher($current_language);
        $switcher->render();
    }
    
    /**
     * Get current language
     *
     * Returns current language from user meta.
     * Defaults to 'cs' if not set.
     *
     * @since 4.8.0
     * @return string Language code (e.g. 'cs', 'en')
     */
    private function get_current_language() {
        if (is_user_logged_in()) {
            $lang = get_user_meta(get_current_user_id(), 'saw_current_language', true);
            if ($lang) {
                return $lang;
            }
        }
        
        return 'cs';
    }
    
    /**
     * Get role label
     *
     * Translates role code to human-readable label.
     *
     * @since 4.8.0
     * @return string Translated role label
     */
    private function get_role_label() {
        $role = $this->user['role'] ?? 'admin';
        
        $labels = array(
            'super_admin' => 'Super Administrátor',
            'admin' => 'Administrátor',
            'super_manager' => 'Super Manažer',
            'manager' => 'Manažer',
            'terminal' => 'Terminál',
        );
        
        return $labels[$role] ?? 'Uživatel';
    }
}