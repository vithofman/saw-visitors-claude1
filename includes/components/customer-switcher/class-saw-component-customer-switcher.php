<?php
/**
 * Customer Switcher Component
 * 
 * Globální komponenta pro přepínání zákazníků (pouze pro superadminy)
 * Zobrazuje se místo loga+názvu zákazníka v headeru
 * 
 * @package SAW_Visitors
 * @since 4.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Customer_Switcher {
    
    private $current_customer;
    private $current_user;
    
    public function __construct($customer = null, $user = null) {
        $this->current_customer = $customer ?: array(
            'id' => 1,
            'name' => 'Demo Firma s.r.o.',
            'ico' => '12345678',
            'logo_url' => '',
        );
        
        $this->current_user = $user ?: array(
            'id' => 1,
            'role' => 'admin',
        );
    }
    
    /**
     * Render customer switcher
     */
    public function render() {
        // Kontrola oprávnění - pouze superadmin
        if (!$this->is_super_admin()) {
            $this->render_static_info();
            return;
        }
        
        $this->enqueue_assets();
        $logo_url = $this->get_logo_url();
        ?>
        <div class="saw-customer-switcher" id="sawCustomerSwitcher">
            <button class="saw-customer-switcher-button" id="sawCustomerSwitcherButton" 
                    data-current-customer-id="<?php echo esc_attr($this->current_customer['id']); ?>">
                <div class="saw-switcher-logo">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" 
                             alt="<?php echo esc_attr($this->current_customer['name']); ?>" 
                             class="saw-switcher-logo-image">
                    <?php else: ?>
                        <svg width="32" height="32" viewBox="0 0 40 40" fill="none" class="saw-switcher-logo-fallback">
                            <rect width="40" height="40" rx="8" fill="#2563eb"/>
                            <text x="20" y="28" font-size="20" font-weight="bold" fill="white" text-anchor="middle">SAW</text>
                        </svg>
                    <?php endif; ?>
                </div>
                <div class="saw-switcher-info">
                    <div class="saw-switcher-name"><?php echo esc_html($this->current_customer['name']); ?></div>
                    <?php if (!empty($this->current_customer['ico'])): ?>
                        <div class="saw-switcher-ico">IČO: <?php echo esc_html($this->current_customer['ico']); ?></div>
                    <?php endif; ?>
                </div>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="saw-switcher-arrow">
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
        <?php
    }
    
    /**
     * Render static info (pro non-superadminy)
     */
    private function render_static_info() {
        $logo_url = $this->get_logo_url();
        ?>
        <div class="saw-customer-info-static">
            <div class="saw-logo">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" 
                         alt="<?php echo esc_attr($this->current_customer['name']); ?>" 
                         class="saw-logo-image">
                <?php else: ?>
                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none" class="saw-logo-fallback">
                        <rect width="40" height="40" rx="8" fill="#2563eb"/>
                        <text x="20" y="28" font-size="20" font-weight="bold" fill="white" text-anchor="middle">SAW</text>
                    </svg>
                <?php endif; ?>
            </div>
            <div class="saw-customer-info">
                <div class="saw-customer-name"><?php echo esc_html($this->current_customer['name']); ?></div>
                <?php if (!empty($this->current_customer['ico'])): ?>
                    <div class="saw-customer-ico">IČO: <?php echo esc_html($this->current_customer['ico']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue assets
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'saw-customer-switcher',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/customer-switcher/customer-switcher.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-customer-switcher',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/customer-switcher/customer-switcher.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script(
            'saw-customer-switcher',
            'sawCustomerSwitcher',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('saw_customer_switcher'),
            )
        );
    }
    
    /**
     * Get logo URL
     */
    private function get_logo_url() {
        if (!empty($this->current_customer['logo_url_full'])) {
            return $this->current_customer['logo_url_full'];
        }
        
        if (!empty($this->current_customer['logo_url'])) {
            return $this->current_customer['logo_url'];
        }
        
        return '';
    }
    
    /**
     * Check if current user is super admin
     */
    private function is_super_admin() {
        return current_user_can('manage_options');
    }
}
