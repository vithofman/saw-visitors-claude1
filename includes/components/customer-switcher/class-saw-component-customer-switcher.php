<?php
if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Customer_Switcher {
    
    private $current_customer;
    private $current_user;
    
    public function __construct($customer = null, $user = null) {
        if (!$customer) {
            $customer_id = $this->get_customer_id_from_context();
            if ($customer_id) {
                global $wpdb;
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}saw_customers WHERE id = %d",
                    $customer_id
                ), ARRAY_A);
            }
        }
        
        if (!$customer) {
            wp_die('KRITICKÁ CHYBA: Nepodařilo se načíst zákazníka. Customer ID: ' . ($customer_id ?? 'NULL'));
        }
        
        $this->current_customer = $customer;
        
        $this->current_user = $user ?: [
            'id' => get_current_user_id(),
            'role' => current_user_can('manage_options') ? 'super_admin' : 'admin',
        ];
    }
    
    private function get_customer_id_from_context() {
        // 1. SAW_Context (priorita)
        if (class_exists('SAW_Context')) {
            $context_id = SAW_Context::get_customer_id();
            if ($context_id) {
                return $context_id;
            }
        }
        
        // 2. Session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['saw_current_customer_id'])) {
            return intval($_SESSION['saw_current_customer_id']);
        }
        
        // 3. User meta
        if (is_user_logged_in()) {
            $meta = get_user_meta(get_current_user_id(), 'saw_current_customer_id', true);
            if ($meta) {
                return intval($meta);
            }
        }
        
        // 4. DB pro non-super admins
        if (is_user_logged_in() && !current_user_can('manage_options')) {
            global $wpdb;
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT customer_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                get_current_user_id()
            ), ARRAY_A);
            
            if ($saw_user && $saw_user['customer_id']) {
                $customer_id = intval($saw_user['customer_id']);
                $_SESSION['saw_current_customer_id'] = $customer_id;
                return $customer_id;
            }
        }
        
        return null;
    }
    
    public function render() {
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
    
    private function enqueue_assets() {
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
        
        wp_localize_script(
            'saw-customer-switcher',
            'sawCustomerSwitcher',
            [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('saw_customer_switcher'),
                'currentCustomerId' => $this->current_customer['id'],
                'currentCustomerName' => $this->current_customer['name'],
            ]
        );
    }
    
    private function get_logo_url() {
        if (!empty($this->current_customer['logo_url_full'])) {
            return $this->current_customer['logo_url_full'];
        }
        
        if (!empty($this->current_customer['logo_url'])) {
            if (strpos($this->current_customer['logo_url'], 'http') === 0) {
                return $this->current_customer['logo_url'];
            }
            return wp_upload_dir()['baseurl'] . '/' . ltrim($this->current_customer['logo_url'], '/');
        }
        
        return '';
    }
    
    private function is_super_admin() {
        return current_user_can('manage_options');
    }
}