<?php
/**
 * SAW App Header Component
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
    
    public function __construct($user, $customer) {
        $this->user = $user;
        $this->customer = $customer;
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
                
                <?php if ($this->customer): ?>
                <div class="saw-customer-info">
                    <div class="saw-customer-name"><?php echo esc_html($this->customer['name']); ?></div>
                    <?php if (!empty($this->customer['ico'])): ?>
                    <div class="saw-customer-ico">IČO: <?php echo esc_html($this->customer['ico']); ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="saw-header-right">
                <?php if ($this->is_super_admin()): ?>
                    <?php $this->render_customer_switcher(); ?>
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
    
    private function render_customer_switcher() {
        global $wpdb;
        $customers = $wpdb->get_results(
            "SELECT id, name, ico FROM {$wpdb->prefix}saw_customers ORDER BY name ASC"
        );
        
        if (empty($customers)) {
            return;
        }
        ?>
        <div class="saw-customer-switcher">
            <button class="saw-customer-switcher-button" id="sawCustomerSwitcherToggle">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 14a6 6 0 110-12 6 6 0 010 12z"/>
                </svg>
                <span>Přepnout zákazníka</span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M8 10.5l-4-4h8l-4 4z"/>
                </svg>
            </button>
            
            <div class="saw-customer-switcher-dropdown" id="sawCustomerSwitcherDropdown">
                <?php foreach ($customers as $customer): ?>
                    <button 
                        class="saw-customer-item <?php echo ($customer->id === $this->customer['id']) ? 'active' : ''; ?>"
                        data-customer-id="<?php echo esc_attr($customer->id); ?>"
                    >
                        <?php if ($customer->id === $this->customer['id']): ?>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                <path d="M6 11L2 7l1.5-1.5L6 8l6.5-6.5L14 3l-8 8z"/>
                            </svg>
                        <?php endif; ?>
                        <div class="saw-customer-item-info">
                            <div class="saw-customer-item-name"><?php echo esc_html($customer->name); ?></div>
                            <?php if (!empty($customer->ico)): ?>
                            <div class="saw-customer-item-ico">IČO: <?php echo esc_html($customer->ico); ?></div>
                            <?php endif; ?>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
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
