<?php
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
    
    private function get_logo_url() {
        if (!empty($this->customer['logo_url_full'])) {
            return $this->customer['logo_url_full'];
        }
        
        if (!empty($this->customer['logo_url'])) {
            return $this->customer['logo_url'];
        }
        
        return '';
    }
    
    public function render() {
        $logo_url = $this->get_logo_url();
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
                
                <div class="saw-logo">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($this->customer['name']); ?>" class="saw-logo-image">
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
            </div>
            
            <div class="saw-header-right">
                <div class="saw-branch-switcher-placeholder" style="display: none;">
                </div>
                
                <?php if ($this->is_super_admin()): ?>
                    <div class="saw-customer-switcher-wrapper">
                        <?php $this->render_customer_switcher(); ?>
                    </div>
                <?php endif; ?>
                
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
        
        <?php $this->enqueue_customer_switcher_script(); ?>
        <?php
    }
    
    private function render_customer_switcher() {
        if (!class_exists('SAW_Component_Selectbox')) {
            require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/selectbox/class-saw-component-selectbox.php';
        }
        
        $switcher = new SAW_Component_Selectbox('customer-switcher', array(
            'ajax_enabled' => true,
            'ajax_action' => 'saw_get_customers_for_switcher',
            'searchable' => true,
            'placeholder' => 'Přepnout zákazníka...',
            'selected' => $this->customer['id'],
            'show_icons' => true,
            'custom_class' => 'saw-customer-switcher',
            'on_change' => 'sawSwitchCustomer',
        ));
        
        $switcher->render();
    }
    
    private function enqueue_customer_switcher_script() {
        ?>
        <script type="text/javascript">
        function sawSwitchCustomer(customerId) {
            if (!customerId) return;
            
            jQuery.ajax({
                url: sawGlobal.ajaxurl,
                type: 'POST',
                data: {
                    action: 'saw_switch_customer',
                    customer_id: customerId
                },
                success: function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(response.data?.message || 'Chyba při přepínání zákazníka');
                    }
                },
                error: function() {
                    alert('Chyba serveru');
                }
            });
        }
        </script>
        <?php
    }
    
    private function is_super_admin() {
        return current_user_can('manage_options');
    }
    
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