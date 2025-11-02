<?php
/**
 * SAW App Header Component - OPRAVENÁ VERZE
 * 
 * ✅ Dynamicky načítá přihlášeného uživatele z WP + SAW
 * ✅ Zobrazuje skutečné jméno, email, roli
 * ✅ Customer switcher pro SuperAdminy
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_App_Header {
    
    private $user;
    private $customer;
    
    public function __construct($user = null, $customer = null) {
        // ✅ OPRAVENO: Načti skutečného přihlášeného uživatele
        if (!$user && is_user_logged_in()) {
            $wp_user = wp_get_current_user();
            
            // 🐛 DEBUG
            error_log('HEADER CONSTRUCT: WP User ID = ' . $wp_user->ID);
            error_log('HEADER CONSTRUCT: WP Email = ' . $wp_user->user_email);
            
            // Načti SAW user data
            global $wpdb;
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                $wp_user->ID
            ), ARRAY_A);
            
            // 🐛 DEBUG
            error_log('HEADER CONSTRUCT: SAW User = ' . print_r($saw_user, true));
            error_log('HEADER CONSTRUCT: SAW Role = ' . ($saw_user['role'] ?? 'NULL'));
            
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
                // Fallback - jen WP data
                error_log('HEADER CONSTRUCT: ⚠️ SAW User NOT FOUND - using fallback!');
                $this->user = [
                    'id' => $wp_user->ID,
                    'name' => $wp_user->display_name,
                    'email' => $wp_user->user_email,
                    'role' => 'admin',  // ❌ FALLBACK = 'admin'
                ];
            }
            
            // 🐛 DEBUG
            error_log('HEADER CONSTRUCT: Final user role = ' . $this->user['role']);
        } else {
            $this->user = $user ?: [
                'id' => 1,
                'name' => 'Demo Admin',
                'email' => 'admin@demo.cz',
                'role' => 'admin',
            ];
        }
        
        // ✅ OPRAVENO: Načti skutečného zákazníka
        if (!$customer) {
            if ($this->is_super_admin()) {
                // Super admin - načti vybraného zákazníka z user meta
                $customer_id = get_user_meta(get_current_user_id(), 'saw_selected_customer_id', true);
                
                if ($customer_id) {
                    global $wpdb;
                    $customer = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
                        $customer_id
                    ), ARRAY_A);
                }
            } else {
                // Admin/Manager - načti jejich zákazníka
                if (isset($this->user['role']) && $this->user['role'] !== 'super_admin') {
                    global $wpdb;
                    $saw_user = $wpdb->get_row($wpdb->prepare(
                        "SELECT customer_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d",
                        get_current_user_id()
                    ), ARRAY_A);
                    
                    if ($saw_user && $saw_user['customer_id']) {
                        $customer = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
                            $saw_user['customer_id']
                        ), ARRAY_A);
                    }
                }
            }
            
            // Fallback pokud zákazník nebyl nalezen
            if (!$customer) {
                global $wpdb;
                $customer = $wpdb->get_row(
                    "SELECT * FROM {$wpdb->prefix}saw_customers ORDER BY id ASC LIMIT 1",
                    ARRAY_A
                );
            }
        }
        
        $this->customer = $customer ?: [
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
        ];
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
                        
                        <a href="<?php echo wp_logout_url(home_url('/login/')); ?>" class="saw-user-menu-item saw-user-logout">
                            <span class="dashicons dashicons-exit"></span>
                            <span>Odhlásit se</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <style>
            /* ✅ PŘIDÁNO: Základní styly pro user menu (pokud chybí) */
            .saw-user-menu {
                position: relative;
            }
            
            .saw-user-button {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                background: transparent;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                cursor: pointer;
                transition: all 0.2s;
            }
            
            .saw-user-button:hover {
                background: #f9fafb;
                border-color: #d1d5db;
            }
            
            .saw-user-icon {
                font-size: 20px;
            }
            
            .saw-user-name {
                font-size: 14px;
                font-weight: 500;
                color: #374151;
            }
            
            .saw-user-dropdown-arrow {
                transition: transform 0.2s;
            }
            
            .saw-user-button[aria-expanded="true"] .saw-user-dropdown-arrow {
                transform: rotate(180deg);
            }
            
            .saw-user-dropdown {
                position: absolute;
                top: calc(100% + 8px);
                right: 0;
                min-width: 250px;
                background: white;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                padding: 8px;
                display: none;
                z-index: 1000;
            }
            
            .saw-user-dropdown.show {
                display: block;
            }
            
            .saw-user-info {
                padding: 12px;
                text-align: center;
            }
            
            .saw-user-name-full {
                font-size: 16px;
                font-weight: 600;
                color: #111827;
                margin-bottom: 4px;
            }
            
            .saw-user-email {
                font-size: 13px;
                color: #6b7280;
                margin-bottom: 4px;
            }
            
            .saw-user-role {
                display: inline-block;
                font-size: 12px;
                padding: 4px 12px;
                background: #eff6ff;
                color: #1e40af;
                border-radius: 6px;
                font-weight: 500;
            }
            
            .saw-user-divider {
                height: 1px;
                background: #e5e7eb;
                margin: 8px 0;
            }
            
            .saw-user-menu-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px 12px;
                color: #374151;
                text-decoration: none;
                border-radius: 8px;
                transition: all 0.2s;
            }
            
            .saw-user-menu-item:hover {
                background: #f9fafb;
                color: #111827;
            }
            
            .saw-user-menu-item .dashicons {
                font-size: 18px;
                width: 18px;
                height: 18px;
            }
            
            .saw-user-logout {
                color: #dc2626;
            }
            
            .saw-user-logout:hover {
                background: #fef2f2;
                color: #991b1b;
            }
        </style>
        
        <script>
        // ✅ PŘIDÁNO: JavaScript pro dropdown menu
        document.addEventListener('DOMContentLoaded', function() {
            const userButton = document.getElementById('sawUserMenuToggle');
            const userDropdown = document.getElementById('sawUserDropdown');
            
            if (userButton && userDropdown) {
                userButton.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const isOpen = userDropdown.classList.contains('show');
                    userDropdown.classList.toggle('show', !isOpen);
                    userButton.setAttribute('aria-expanded', !isOpen);
                });
                
                // Close when clicking outside
                document.addEventListener('click', function(e) {
                    if (!userButton.contains(e.target) && !userDropdown.contains(e.target)) {
                        userDropdown.classList.remove('show');
                        userButton.setAttribute('aria-expanded', 'false');
                    }
                });
            }
        });
        </script>
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
        // ✅ OPRAVENO: Prioritně používej SAW roli, ne WP capabilities
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