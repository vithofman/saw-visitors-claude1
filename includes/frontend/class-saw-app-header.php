<?php
/**
 * SAW App Header Component
 * 
 * Updated version with customer switcher integrated into logo/name area
 * Customer switcher available only for SuperAdmins
 * 
 * @package SAW_Visitors
 * @since 4.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_App_Header {
    
    private $user;
    private $customer;
    
    public function __construct($user = null, $customer = null) {
        $this->user = $user ?: array(
            'id' => 1,
            'name' => 'Demo Admin',
            'email' => 'admin@demo.cz',
            'role' => 'admin',
        );
        
        $this->customer = $customer ?: array(
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
        );
    }
    
    /**
     * Get logo URL with fallback logic
     */
    private function get_logo_url() {
        if (!empty($this->customer['logo_url_full'])) {
            return $this->customer['logo_url_full'];
        }
        
        if (!empty($this->customer['logo_url'])) {
            return $this->customer['logo_url'];
        }
        
        return '';
    }
    
    /**
     * Check if current user is Super Admin
     */
    private function is_super_admin() {
        return current_user_can('manage_options');
    }
    
    /**
     * Render header
     */
    public function render() {
        $logo_url = $this->get_logo_url();
        $is_super_admin = $this->is_super_admin();
        
        if ($is_super_admin) {
            $this->enqueue_customer_switcher_assets();
        }
        ?>
        <header class="saw-app-header">
            <div class="saw-header-left">
                <button class="saw-hamburger-menu" id="sawHamburgerMenu" aria-label="Toggle menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                
                <?php if ($is_super_admin): ?>
                    <div class="saw-customer-switcher" id="sawCustomerSwitcher">
                        <button class="saw-customer-switcher-button" id="sawCustomerSwitcherButton"
                                data-current-customer-id="<?php echo esc_attr($this->customer['id']); ?>"
                                data-current-customer-name="<?php echo esc_attr($this->customer['name']); ?>">
                            
                            <div class="saw-logo">
                                <?php if ($logo_url): ?>
                                    <img src="<?php echo esc_url($logo_url); ?>" 
                                         alt="<?php echo esc_attr($this->customer['name']); ?>" 
                                         class="saw-logo-image">
                                <?php else: ?>
                                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none" class="saw-logo-fallback">
                                        <rect width="40" height="40" rx="8" fill="#2563eb"/>
                                        <text x="20" y="28" font-size="20" font-weight="bold" fill="white" text-anchor="middle">SAW</text>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            
                            <div class="saw-customer-info">
                                <div class="saw-customer-name"><?php echo esc_html($this->customer['name']); ?></div>
                                <?php if (!empty($this->customer['ico'])): ?>
                                <div class="saw-customer-ico">IČO: <?php echo esc_html($this->customer['ico']); ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="saw-customer-dropdown-arrow">
                                <path d="M8 10.5l-4-4h8l-4 4z"/>
                            </svg>
                        </button>
                        
                        <div class="saw-customer-switcher-dropdown" id="sawCustomerSwitcherDropdown">
                            <div class="saw-switcher-search">
                                <input type="text" 
                                       class="saw-switcher-search-input" 
                                       id="sawCustomerSwitcherSearch" 
                                       placeholder="Hledat zákazníka...">
                            </div>
                            <div class="saw-switcher-list" id="sawCustomerSwitcherList">
                                <div class="saw-switcher-loading">
                                    <div class="saw-spinner"></div>
                                    <span>Načítání zákazníků...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php else: ?>
                    <div class="saw-logo">
                        <?php if ($logo_url): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" 
                                 alt="<?php echo esc_attr($this->customer['name']); ?>" 
                                 class="saw-logo-image">
                        <?php else: ?>
                            <svg width="40" height="40" viewBox="0 0 40 40" fill="none" class="saw-logo-fallback">
                                <rect width="40" height="40" rx="8" fill="#2563eb"/>
                                <text x="20" y="28" font-size="20" font-weight="bold" fill="white" text-anchor="middle">SAW</text>
                            </svg>
                        <?php endif; ?>
                    </div>
                    
                    <div class="saw-customer-info">
                        <div class="saw-customer-name"><?php echo esc_html($this->customer['name']); ?></div>
                        <?php if (!empty($this->customer['ico'])): ?>
                        <div class="saw-customer-ico">IČO: <?php echo esc_html($this->customer['ico']); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="saw-header-right">
                <?php $this->render_language_switcher(); ?>
                
                <div class="saw-user-menu">
                    <button class="saw-user-button" id="sawUserMenuToggle">
                        <span class="saw-user-icon">👤</span>
                        <span class="saw-user-name"><?php echo esc_html($this->user['name']); ?></span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="saw-user-dropdown-arrow">
                            <path d="M8 10.5l-4-4h8l-4 4z"/>
                        </svg>
                    </button>
                    
                    <div class="saw-user-dropdown" id="sawUserDropdown">
                        <div class="saw-user-info">
                            <div class="saw-user-name-full"><?php echo esc_html($this->user['name']); ?></div>
                            <div class="saw-user-email"><?php echo esc_html($this->user['email']); ?></div>
                            <div class="saw-user-role"><?php echo esc_html($this->get_role_label()); ?></div>
                        </div>
                        
                        <div class="saw-user-divider"></div>
                        
                        <a href="<?php echo home_url('/admin/profile/'); ?>" class="saw-user-menu-item">
                            <span class="dashicons dashicons-admin-users"></span>
                            <span>Můj profil</span>
                        </a>
                        
                        <a href="<?php echo home_url('/admin/settings/'); ?>" class="saw-user-menu-item">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <span>Nastavení</span>
                        </a>
                        
                        <div class="saw-user-divider"></div>
                        
                        <a href="<?php echo wp_logout_url(home_url('/')); ?>" class="saw-user-menu-item saw-user-logout">
                            <span class="dashicons dashicons-exit"></span>
                            <span>Odhlásit se</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>
        <?php
    }
    
    /**
     * Enqueue customer switcher assets
     */
    private function enqueue_customer_switcher_assets() {
        wp_enqueue_style(
            'saw-customer-switcher',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/customer-switcher/customer-switcher.css',
            [],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-customer-switcher',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/customer-switcher/customer-switcher.js',
            ['jquery'],
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script('saw-customer-switcher', 'sawCustomerSwitcher', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_customer_switcher'),
            'currentCustomerId' => $this->customer['id'],
            'currentCustomerName' => $this->customer['name'],
        ]);
    }
    
    /**
     * Render Language Switcher
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
     */
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
    
    /**
     * Get user role label in Czech
     */
    private function get_role_label() {
        if ($this->is_super_admin()) {
            return 'Super Administrátor';
        }
        
        $role = $this->user['role'] ?? 'admin';
        $labels = array(
            'admin' => 'Administrátor',
            'manager' => 'Manažer',
            'terminal' => 'Terminál',
        );
        
        return $labels[$role] ?? 'Uživatel';
    }
}