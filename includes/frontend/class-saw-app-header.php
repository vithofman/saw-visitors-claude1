<?php
/**
 * SAW App Header Component
 * 
 * @package SAW_Visitors
 * @since 4.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_App_Header {
    
    private $user;
    private $customer;
    
    public function __construct($user = null, $customer = null) {
        if (!$user && is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            
            global $wpdb;
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                $wp_user->ID
            ), ARRAY_A);
            
            if ($saw_user) {
                $this->user = [
                    'id' => $saw_user['id'],
                    'name' => $saw_user['first_name'] . ' ' . $saw_user['last_name'],
                    'email' => $wp_user->user_email,
                    'role' => $saw_user['role'],
                    'first_name' => $saw_user['first_name'],
                    'last_name' => $saw_user['last_name'],
                ];
            } else {
                $this->user = [
                    'id' => $wp_user->ID,
                    'name' => $wp_user->display_name,
                    'email' => $wp_user->user_email,
                    'role' => 'admin',
                ];
            }
        } else {
            $this->user = $user ?: [
                'id' => 1,
                'name' => 'Demo Admin',
                'email' => 'admin@demo.cz',
                'role' => 'admin',
            ];
        }
        
        if (!$customer) {
            $customer = SAW_Context::get_customer_data();
        }
        
        $this->customer = $customer ?: [
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
        ];
    }
    
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
                            <?php echo esc_html(substr($this->user['name'], 0, 1)); ?>
                        </div>
                        <div class="saw-user-info">
                            <div class="saw-user-name"><?php echo esc_html($this->user['name']); ?></div>
                            <div class="saw-user-role"><?php echo esc_html($this->get_role_label()); ?></div>
                        </div>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="saw-user-arrow">
                            <path d="M8 10.5l-4-4h8l-4 4z"/>
                        </svg>
                    </button>
                    
                    <div class="saw-user-dropdown" id="sawUserDropdown">
                        <a href="/admin/profile" class="saw-dropdown-item">
                            <span class="saw-dropdown-icon">👤</span>
                            <span>Můj profil</span>
                        </a>
                        <a href="/admin/settings" class="saw-dropdown-item">
                            <span class="saw-dropdown-icon">⚙️</span>
                            <span>Nastavení</span>
                        </a>
                        <div class="saw-dropdown-divider"></div>
                        <a href="/logout/" class="saw-dropdown-item">
                            <span class="saw-dropdown-icon">🚪</span>
                            <span>Odhlásit se</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>
        <?php
    }
    
    private function render_customer_switcher() {
        if (!class_exists('SAW_Component_Customer_Switcher')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/customer-switcher/class-saw-component-customer-switcher.php';
        }
        
        $switcher = new SAW_Component_Customer_Switcher($this->customer, $this->user);
        $switcher->render();
    }
    
    private function render_language_switcher() {
        if (!class_exists('SAW_Component_Language_Switcher')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/language-switcher/class-saw-component-language-switcher.php';
        }
        $current_language = $this->get_current_language();
        $switcher = new SAW_Component_Language_Switcher($current_language);
        $switcher->render();
    }
    
    private function get_current_language() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['saw_current_language'])) {
            return $_SESSION['saw_current_language'];
        }
        if (is_user_logged_in()) {
            $lang = get_user_meta(get_current_user_id(), 'saw_current_language', true);
            if ($lang) {
                $_SESSION['saw_current_language'] = $lang;
                return $lang;
            }
        }
        return 'cs';
    }
    
    private function get_role_label() {
        $role = $this->user['role'] ?? 'admin';
        $labels = [
            'super_admin' => 'Super Administrátor',
            'admin' => 'Administrátor',
            'super_manager' => 'Super Manažer',
            'manager' => 'Manažer',
            'terminal' => 'Terminál',
        ];
        return $labels[$role] ?? 'Uživatel';
    }
}