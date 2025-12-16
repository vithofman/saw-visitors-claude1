<?php
/**
 * SAW Customer Switcher Component
 *
 * Customer selector component for super admins with AJAX loading and switching.
 * Provides static customer info display for non-super admin users.
 *
 * @package     SAW_Visitors
 * @subpackage  Components
 * @version     2.1.2
 * @since       4.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Component Customer Switcher
 *
 * Manages customer selection dropdown for super admins and static display for others.
 * Uses SAW_Context for customer management.
 *
 * @since 4.7.0
 */
class SAW_Component_Customer_Switcher {
    
    /**
     * Current customer data
     *
     * @since 4.7.0
     * @var array
     */
    private $current_customer;
    
    /**
     * Current user data
     *
     * @since 4.7.0
     * @var array
     */
    private $current_user;
    
    /**
     * AJAX handlers registration flag
     *
     * @since 4.7.0
     * @var bool
     */
    private static $ajax_registered = false;
    
    /**
     * Assets enqueued flag
     *
     * @since 4.7.0
     * @var bool
     */
    private static $assets_enqueued = false;
    
    /**
     * Register AJAX handlers globally
     *
     * CRITICAL: This must be called early (via Component Manager), NOT in constructor.
     * Ensures AJAX handlers work even if component doesn't render.
     *
     * @since 8.0.0
     * @return void
     */
    public static function register_ajax_handlers() {
        add_action('wp_ajax_saw_get_customers_for_switcher', array(__CLASS__, 'ajax_get_customers_static'));
        add_action('wp_ajax_saw_switch_customer', array(__CLASS__, 'ajax_switch_customer_static'));
    }
    
    /**
     * Constructor
     *
     * Initializes customer switcher with customer and user data.
     * Auto-loads customer from context if not provided.
     * CRITICAL: Enqueues CSS immediately to prevent FOUC.
     *
     * @since 4.7.0
     * @param array|null $customer Customer data (null = auto-load)
     * @param array|null $user     User data (null = auto-load)
     */
    public function __construct($customer = null, $user = null) {
        if (!$customer) {
            $customer_id = $this->get_customer_id_from_context();
            if ($customer_id) {
                global $wpdb;
                $customer = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM %i WHERE id = %d",
                    $wpdb->prefix . 'saw_customers',
                    $customer_id
                ), ARRAY_A);
            }
        }
        
        if (!$customer) {
            wp_die('KRITICKÁ CHYBA: Nepodařilo se načíst zákazníka. Customer ID: ' . ($customer_id ?? 'NULL'));
        }
        
        $this->current_customer = $customer;
        
        $this->current_user = $user ?: array(
            'id' => get_current_user_id(),
            'role' => current_user_can('manage_options') ? 'super_admin' : 'admin',
        );
        
        // Assets and AJAX handlers are registered globally via SAW_Component_Manager
    }
    
    /**
     * Enqueue assets early (in constructor)
     *
     * DEPRECATED: Assets are now loaded globally via SAW_Asset_Loader.
     * This method is kept for backwards compatibility but does nothing.
     *
     * @since 4.7.0
     * @deprecated 8.0.0 Use SAW_Asset_Loader instead
     * @return void
     */
    private function enqueue_assets_early() {
        // Assets are now enqueued globally via SAW_Asset_Loader
        // to prevent FOUC on first page load. Do not re-enqueue here.
        return;
    }
    
    /**
     * AJAX: Get customers list (static wrapper)
     *
     * Static method called by WordPress AJAX system.
     *
     * @since 8.0.0
     * @return void
     */
    public static function ajax_get_customers_static() {
        $instance = new self();
        $instance->ajax_get_customers();
    }
    
    /**
     * AJAX: Get customers list
     *
     * Returns list of active customers for switcher dropdown.
     * Super admin permission required.
     *
     * @since 4.7.0
     * @return void Outputs JSON response
     */
    public function ajax_get_customers() {
        check_ajax_referer('saw_customer_switcher', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nedostatečná oprávnění'));
            return;
        }
        
        global $wpdb;
        
        $customers = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, ico, logo_url, primary_color, status
             FROM %i 
             WHERE status = 'active' 
             ORDER BY name ASC",
            $wpdb->prefix . 'saw_customers'
        ), ARRAY_A);
        
        $formatted = array();
        foreach ($customers as $c) {
            $logo_url = '';
            if (!empty($c['logo_url'])) {
                if (strpos($c['logo_url'], 'http') === 0) {
                    $logo_url = $c['logo_url'];
                } else {
                    $logo_url = wp_upload_dir()['baseurl'] . '/' . ltrim($c['logo_url'], '/');
                }
            }
            
            $formatted[] = array(
                'id' => (int)$c['id'],
                'name' => $c['name'],
                'ico' => $c['ico'] ?? '',
                'logo_url' => $logo_url,
                'primary_color' => $c['primary_color'] ?? '#2563eb',
            );
        }
        
        $current_customer_id = null;
        if (class_exists('SAW_Context')) {
            $current_customer_id = SAW_Context::get_customer_id();
        }
        
        wp_send_json_success(array(
            'customers' => $formatted,
            'current_customer_id' => $current_customer_id,
        ));
    }
    
    /**
     * AJAX: Switch customer (static wrapper)
     *
     * Static method called by WordPress AJAX system.
     *
     * @since 8.0.0
     * @return void
     */
    public static function ajax_switch_customer_static() {
        $instance = new self();
        $instance->ajax_switch_customer();
    }
    
    /**
     * AJAX: Switch customer
     *
     * Changes active customer via SAW_Context.
     * Super admin permission required.
     *
     * @since 4.7.0
     * @return void Outputs JSON response
     */
    public function ajax_switch_customer() {
        check_ajax_referer('saw_customer_switcher', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Nedostatečná oprávnění'));
            return;
        }
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'Chybí ID zákazníka'));
            return;
        }
        
        global $wpdb;
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name FROM %i WHERE id = %d AND status = 'active'",
            $wpdb->prefix . 'saw_customers',
            $customer_id
        ), ARRAY_A);
        
        if (!$customer) {
            wp_send_json_error(array('message' => 'Zákazník nenalezen'));
            return;
        }
        
        // Set customer ID via SAW_Context
        if (class_exists('SAW_Context')) {
            SAW_Context::set_customer_id($customer_id);
        }
        
        // Log audit trail if available
        if (class_exists('SAW_Audit')) {
            $wpdb->insert(
                $wpdb->prefix . 'saw_audit_log',
                array(
                    'customer_id' => $customer_id,
                    'user_id' => null,
                    'action' => 'customer_switched',
                    'entity_type' => 'customer',
                    'entity_id' => $customer_id,
                    'old_values' => null,
                    'new_values' => wp_json_encode(array('customer_id' => $customer_id, 'customer_name' => $customer['name'])),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                    'created_at' => current_time('mysql'),
                ),
                array('%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        wp_send_json_success(array(
            'customer_id' => $customer_id,
            'customer_name' => $customer['name']
        ));
    }
    
    /**
     * Get customer ID from context
     *
     * Retrieves customer ID from SAW_Context with fallback logic.
     *
     * @since 4.7.0
     * @return int|null Customer ID or null
     */
    private function get_customer_id_from_context() {
        // Primary: SAW_Context
        if (class_exists('SAW_Context')) {
            $context_id = SAW_Context::get_customer_id();
            if ($context_id) {
                return $context_id;
            }
        }
        
        // Fallback: Load from saw_users table for non-super admins
        if (is_user_logged_in() && !current_user_can('manage_options')) {
            global $wpdb;
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT customer_id FROM %i WHERE wp_user_id = %d AND is_active = 1",
                $wpdb->prefix . 'saw_users',
                get_current_user_id()
            ), ARRAY_A);
            
            if ($saw_user && $saw_user['customer_id']) {
                return intval($saw_user['customer_id']);
            }
        }
        
        return null;
    }
    
    /**
     * Render customer switcher UI
     *
     * Outputs either interactive switcher (super admin) or static info (others).
     * Assets are already enqueued in constructor.
     *
     * @since 4.7.0
     * @return void Outputs HTML directly
     */
    public function render() {
        if (!$this->is_super_admin()) {
            $this->render_static_info();
            return;
        }
        
        $logo_url = $this->get_logo_url();
        ?>
        <div class="sa-customer-switcher" id="sawCustomerSwitcher">
            <button class="sa-customer-switcher-button" id="sawCustomerSwitcherButton" 
                    data-current-customer-id="<?php echo esc_attr($this->current_customer['id']); ?>">
                <div class="sa-switcher-logo">
                    <?php if ($logo_url): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" 
                             alt="<?php echo esc_attr($this->current_customer['name']); ?>" 
                             width="40"
                             height="40"
                             class="sa-switcher-logo-image">
                    <?php else: ?>
                        <svg width="32" height="32" viewBox="0 0 40 40" fill="none" class="sa-switcher-logo-fallback">
                            <rect width="40" height="40" rx="8" fill="#2563eb"/>
                            <text x="20" y="28" font-size="20" font-weight="bold" fill="white" text-anchor="middle">SAW</text>
                        </svg>
                    <?php endif; ?>
                </div>
                <div class="sa-switcher-info">
                    <div class="sa-switcher-name"><?php echo esc_html($this->current_customer['name']); ?></div>
                    <?php if (!empty($this->current_customer['ico'])): ?>
                        <div class="sa-switcher-ico">IČO: <?php echo esc_html($this->current_customer['ico']); ?></div>
                    <?php endif; ?>
                </div>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="sa-switcher-arrow">
                    <path d="M8 10.5l-4-4h8l-4 4z"/>
                </svg>
            </button>
            
            <div class="sa-customer-switcher-dropdown" id="sawCustomerSwitcherDropdown">
                <div class="sa-switcher-search">
                    <input type="text" 
                           class="sa-switcher-search-input" 
                           id="sawCustomerSwitcherSearch" 
                           placeholder="Hledat zákazníka...">
                </div>
                <div class="sa-switcher-list" id="sawCustomerSwitcherList">
                    <div class="sa-switcher-loading">
                        <div class="sa-spinner"></div>
                        <span>Načítání zákazníků...</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render static customer info
     *
     * Displays non-interactive customer information for non-super admins.
     *
     * @since 4.7.0
     * @return void Outputs HTML directly
     */
    private function render_static_info() {
        $logo_url = $this->get_logo_url();
        ?>
        <div class="sa-customer-info-static">
            <div class="sa-switcher-logo">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" 
                         alt="<?php echo esc_attr($this->current_customer['name']); ?>"
                         width="40"
                         height="40"
                         class="sa-switcher-logo-image">
                <?php else: ?>
                    <svg width="40" height="40" viewBox="0 0 40 40" fill="none" class="sa-switcher-logo-fallback">
                        <rect width="40" height="40" rx="8" fill="#2563eb"/>
                        <text x="20" y="28" font-size="20" font-weight="bold" fill="white" text-anchor="middle">SAW</text>
                    </svg>
                <?php endif; ?>
            </div>
            <div class="sa-switcher-info">
                <div class="sa-switcher-name"><?php echo esc_html($this->current_customer['name']); ?></div>
                <?php if (!empty($this->current_customer['ico'])): ?>
                    <div class="sa-switcher-ico">IČO: <?php echo esc_html($this->current_customer['ico']); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Get customer logo URL
     *
     * Returns full URL to customer logo or empty string.
     *
     * @since 4.7.0
     * @return string Logo URL or empty string
     */
    private function get_logo_url() {
    // Zkus nejdřív logo_url_full
    if (isset($this->current_customer['logo_url_full']) && $this->current_customer['logo_url_full'] !== '') {
        return $this->current_customer['logo_url_full'];
    }
    
    // Pak zkus logo_url
    if (isset($this->current_customer['logo_url']) && $this->current_customer['logo_url'] !== '') {
        // Už je full URL?
        if (strpos($this->current_customer['logo_url'], 'http') === 0) {
            return $this->current_customer['logo_url'];
        }
        // Sestav full URL
        return wp_get_upload_dir()['baseurl'] . '/' . ltrim($this->current_customer['logo_url'], '/');
    }
    
    return '';
}
    
    /**
     * Check if current user is super admin
     *
     * Uses SAW_Context for role checking with fallback.
     *
     * @since 4.7.0
     * @return bool True if super admin
     */
    private function is_super_admin() {
        if (class_exists('SAW_Context')) {
            $role = SAW_Context::get_role();
            return $role === 'super_admin';
        }
        
        return current_user_can('manage_options');
    }
}