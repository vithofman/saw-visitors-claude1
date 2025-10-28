<?php
/**
 * SAW App Header Component - WITH WORKING CUSTOMER SWITCHER
 * 
 * @package SAW_Visitors
 * @subpackage Frontend
 * @since 4.6.1
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
        
        // Enqueue assets IMMEDIATELY if SuperAdmin
        if ($this->is_super_admin()) {
            $this->enqueue_customer_switcher_assets();
        }
    }
    
    /**
     * Enqueue customer switcher assets (only for SuperAdmin)
     */
    public function enqueue_customer_switcher_assets() {
        // CSS - inline protože jsme už v DOM
        $css_file = SAW_VISITORS_PLUGIN_DIR . 'assets/css/saw-customer-switcher.css';
        if (file_exists($css_file)) {
            echo '<style id="saw-customer-switcher-css">' . file_get_contents($css_file) . '</style>';
        }
        
        // JS - inline s localized data
        $js_file = SAW_VISITORS_PLUGIN_DIR . 'assets/js/saw-customer-switcher.js';
        if (file_exists($js_file)) {
            ?>
            <script type="text/javascript">
            var sawCustomerSwitcher = {
                ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
                nonce: '<?php echo wp_create_nonce('saw_customer_switcher_nonce'); ?>'
            };
            </script>
            <script type="text/javascript" id="saw-customer-switcher-js">
            <?php echo file_get_contents($js_file); ?>
            </script>
            <?php
        }
    }
    
    public function render() {
        ?>
        <header class="saw-app-header">
            <div class="saw-header-left">
                <div class="saw-logo">
                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                        <rect width="40" height="40" rx="8" fill="#2563eb"/>
                        <text x="20" y="28" font-size="20" font-weight="bold" fill="white" text-anchor="middle">SAW</text>
                    </svg>
                </div>
                
                <div class="saw-customer-info">
                    <div class="saw-customer-name"><?php echo esc_html($this->customer['name']); ?></div>
                    <?php if (!empty($this->customer['ico'])): ?>
                    <div class="saw-customer-ico">IČO: <?php echo esc_html($this->customer['ico']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="saw-header-right">
                <?php if ($this->is_super_admin()): ?>
                    <div class="saw-customer-switcher">
                        <button class="saw-customer-switcher-button" id="sawCustomerSwitcherButton">
                            🏢 Přepnout zákazníka
                        </button>
                    </div>
                <?php endif; ?>
                
                <div class="saw-user-menu">
                    <button class="saw-user-button" id="sawUserMenuToggle">
                        <span class="saw-user-icon">👤</span>
                        <span class="saw-user-name"><?php echo esc_html($this->user['name']); ?></span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M8 10.5l-4-4h8l-4 4z"/>
                        </svg>
                    </button>
                    
                    <div class="saw-user-dropdown" id="sawUserDropdown">
                        <div class="saw-user-info">
                            <div class="saw-user-name-full"><?php echo esc_html($this->user['name']); ?></div>
                            <div class="saw-user-email"><?php echo esc_html($this->user['email']); ?></div>
                            <div class="saw-user-role"><?php echo esc_html($this->get_role_label()); ?></div>
                        </div>
                        
                        <div class="saw-user-actions">
                            <a href="/admin/settings/profile" class="saw-dropdown-item">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 8a3 3 0 100-6 3 3 0 000 6zM8 9c-3 0-6 1.5-6 3.5V14h12v-1.5C14 10.5 11 9 8 9z"/>
                                </svg>
                                Můj profil
                            </a>
                            
                            <a href="/logout" class="saw-dropdown-item saw-dropdown-item-danger">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M6 2v2H4v10h8V4h-2V2H6zM8 11H6V5h2v6z"/>
                                </svg>
                                Odhlásit se
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        <?php
    }
    
    private function is_super_admin() {
        return current_user_can('manage_options');
    }
    
    private function get_role_label() {
        if ($this->is_super_admin()) {
            return 'Super Administrator';
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