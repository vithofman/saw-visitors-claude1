<?php
/**
 * SAW Customer Switcher Component - COMPLETE FIXED VERSION
 * 
 * CRITICAL FIXES:
 * - ✅ Added missing AJAX handlers (ajax_get_customers, ajax_switch_customer)
 * - ✅ Proper AJAX handler registration in constructor
 * - ✅ Uses SAW_Context for customer management
 * - ✅ Comprehensive error handling and logging
 * 
 * @package SAW_Visitors
 * @version 2.0.0 - COMPLETE
 * @since 4.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Customer_Switcher {
    
    private $current_customer;
    private $current_user;
    private static $ajax_registered = false;
    
    public function __construct($customer = null, $user = null) {
        // Load customer
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
        
        // ✅ Register AJAX handlers only once
        if (!self::$ajax_registered) {
            add_action('wp_ajax_saw_get_customers_for_switcher', [$this, 'ajax_get_customers']);
            add_action('wp_ajax_saw_switch_customer', [$this, 'ajax_switch_customer']);
            self::$ajax_registered = true;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Customer Switcher] AJAX handlers registered');
            }
        }
    }
    
    /**
     * ✅ NEW: AJAX handler - Get all customers for switcher dropdown
     */
    public function ajax_get_customers() {
        check_ajax_referer('saw_customer_switcher', 'nonce');
        
        // Only super admin can switch customers
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
            return;
        }
        
        global $wpdb;
        
        // Load all active customers
        $customers = $wpdb->get_results(
            "SELECT id, name, ico, logo_url, primary_color, status
             FROM {$wpdb->prefix}saw_customers 
             WHERE status = 'active' 
             ORDER BY name ASC",
            ARRAY_A
        );
        
        // Format customers for response
        $formatted = [];
        foreach ($customers as $c) {
            $logo_url = '';
            if (!empty($c['logo_url'])) {
                if (strpos($c['logo_url'], 'http') === 0) {
                    $logo_url = $c['logo_url'];
                } else {
                    $logo_url = wp_upload_dir()['baseurl'] . '/' . ltrim($c['logo_url'], '/');
                }
            }
            
            $formatted[] = [
                'id' => (int)$c['id'],
                'name' => $c['name'],
                'ico' => $c['ico'] ?? '',
                'logo_url' => $logo_url,
                'primary_color' => $c['primary_color'] ?? '#2563eb',
            ];
        }
        
        // Get current customer_id
        $current_customer_id = null;
        if (class_exists('SAW_Context')) {
            $current_customer_id = SAW_Context::get_customer_id();
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Customer Switcher] Loaded %d customers, current: %s',
                count($formatted),
                $current_customer_id ?? 'NULL'
            ));
        }
        
        wp_send_json_success([
            'customers' => $formatted,
            'current_customer_id' => $current_customer_id,
        ]);
    }
    
    /**
     * ✅ NEW: AJAX handler - Switch to different customer
     */
    public function ajax_switch_customer() {
        check_ajax_referer('saw_customer_switcher', 'nonce');
        
        // Only super admin can switch customers
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
            return;
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if (!$customer_id) {
            wp_send_json_error(['message' => 'Chybí ID zákazníka']);
            return;
        }
        
        // Validate customer exists
        global $wpdb;
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}saw_customers WHERE id = %d AND status = 'active'",
            $customer_id
        ), ARRAY_A);
        
        if (!$customer) {
            wp_send_json_error(['message' => 'Zákazník nenalezen']);
            return;
        }
        
        // Switch customer using SAW_Context
        if (class_exists('SAW_Context')) {
            SAW_Context::set_customer_id($customer_id);
        } else {
            // Fallback: Set in session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['saw_current_customer_id'] = $customer_id;
        }
        
        // Update user meta
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'saw_current_customer_id', $customer_id);
        }
        
        // Log the switch
        if (class_exists('SAW_Audit')) {
            global $wpdb;
            $wpdb->insert(
                $wpdb->prefix . 'saw_audit_log',
                [
                    'customer_id' => $customer_id,
                    'user_id' => null,
                    'action' => 'customer_switched',
                    'entity_type' => 'customer',
                    'entity_id' => $customer_id,
                    'old_values' => null,
                    'new_values' => wp_json_encode(['customer_id' => $customer_id, 'customer_name' => $customer['name']]),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
            );
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Customer Switcher] Customer switched to: %d (%s) by user %d',
                $customer_id,
                $customer['name'],
                get_current_user_id()
            ));
        }
        
        wp_send_json_success([
            'customer_id' => $customer_id,
            'customer_name' => $customer['name']
        ]);
    }
    
    /**
     * Get customer_id from various sources
     */
    private function get_customer_id_from_context() {
        // 1. SAW_Context (priority)
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
        
        // 4. DB for non-super admins
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
    
    /**
     * Render customer switcher UI
     */
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
    
    /**
     * Render static info for non-super admin
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
    
    /**
     * Get logo URL
     */
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
    
    /**
     * Check if super admin
     */
    private function is_super_admin() {
        return current_user_can('manage_options');
    }
}