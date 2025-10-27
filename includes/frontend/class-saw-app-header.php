<?php
/**
 * SAW App Header Component - SIMPLIFIED
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
        // Fake data for testing
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
                        <button class="saw-customer-switcher-button" disabled>
                            🏢 Přepnutí zákazníka (TODO)
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