<?php
/**
 * SAW Branch Switcher Component - COMPLETE FIXED VERSION
 * 
 * CRITICAL FIXES:
 * - ✅ Uses SAW_Context::get_customer_id() as PRIMARY source
 * - ✅ Proper customer isolation validation
 * - ✅ Single AJAX handler registration (static flag)
 * - ✅ Comprehensive error handling and logging
 * 
 * @package SAW_Visitors
 * @version 2.1.0 - COMPLETE
 * @since 4.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Branch_Switcher {
    private $customer_id;
    private $current_branch;
    private static $ajax_registered = false;
    
    public function __construct($customer_id = null, $current_branch = null) {
        $this->customer_id = $customer_id;
        $this->current_branch = $current_branch;
        
        // ✅ Register AJAX actions only once (singleton pattern)
        if (!self::$ajax_registered) {
            add_action('wp_ajax_saw_get_branches_for_switcher', [$this, 'ajax_get_branches']);
            add_action('wp_ajax_saw_switch_branch', [$this, 'ajax_switch_branch']);
            self::$ajax_registered = true;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Branch Switcher] AJAX handlers registered');
            }
        }
    }
    
    /**
     * AJAX: Get branches for current customer
     */
    public function ajax_get_branches() {
        check_ajax_referer('saw_branch_switcher', 'nonce');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Branch Switcher] AJAX get_branches - User: %d, Logged: %s, Super: %s',
                get_current_user_id(),
                is_user_logged_in() ? 'YES' : 'NO',
                current_user_can('manage_options') ? 'YES' : 'NO'
            ));
        }
        
        // ================================================
        // DETERMINE CUSTOMER_ID
        // ================================================
        $customer_id = null;
        
        // For non-super admin: ALWAYS use SAW_Context
        if (!current_user_can('manage_options')) {
            if (class_exists('SAW_Context')) {
                $customer_id = SAW_Context::get_customer_id();
            }
            
            if (!$customer_id) {
                // Fallback: Load from database directly
                global $wpdb;
                $saw_user = $wpdb->get_row($wpdb->prepare(
                    "SELECT customer_id FROM {$wpdb->prefix}saw_users WHERE wp_user_id = %d AND is_active = 1",
                    get_current_user_id()
                ), ARRAY_A);
                
                if ($saw_user && $saw_user['customer_id']) {
                    $customer_id = intval($saw_user['customer_id']);
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[Branch Switcher] Non-super admin - Customer: %s',
                    $customer_id ?? 'NULL'
                ));
            }
        }
        
        // For super admin: Use SAW_Context or POST
        if (current_user_can('manage_options')) {
            if (class_exists('SAW_Context')) {
                $customer_id = SAW_Context::get_customer_id();
            }
            
            if (!$customer_id && isset($_POST['customer_id'])) {
                $customer_id = intval($_POST['customer_id']);
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[Branch Switcher] Super admin - Customer: %s',
                    $customer_id ?? 'NULL'
                ));
            }
        }
        
        // Validate customer_id
        if (!$customer_id) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Branch Switcher] ERROR: No customer_id available');
            }
            wp_send_json_error(['message' => 'Chybí ID zákazníka']);
            return;
        }
        
        // ================================================
        // LOAD BRANCHES FROM DATABASE
        // ================================================
        global $wpdb;
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, city, street, code, is_headquarters
             FROM {$wpdb->prefix}saw_branches 
             WHERE customer_id = %d AND is_active = 1 
             ORDER BY is_headquarters DESC, name ASC",
            $customer_id
        ), ARRAY_A);
        
        // Format branches
        $formatted = [];
        foreach ($branches as $b) {
            $address = array_filter([$b['street'] ?? '', $b['city'] ?? '']);
            $formatted[] = [
                'id' => (int)$b['id'],
                'name' => $b['name'],
                'code' => $b['code'] ?? '',
                'city' => $b['city'] ?? '',
                'address' => implode(', ', $address),
                'is_headquarters' => (bool)($b['is_headquarters'] ?? 0)
            ];
        }
        
        // Get current branch_id
        $current_branch_id = null;
        if (class_exists('SAW_Context')) {
            $current_branch_id = SAW_Context::get_branch_id();
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Branch Switcher] SUCCESS - Loaded %d branches for customer %d',
                count($formatted),
                $customer_id
            ));
        }
        
        wp_send_json_success([
            'branches' => $formatted,
            'current_branch_id' => $current_branch_id,
            'customer_id' => $customer_id,
        ]);
    }
    
    /**
     * AJAX: Switch branch
     */
    public function ajax_switch_branch() {
        check_ajax_referer('saw_branch_switcher', 'nonce');
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        
        if (!$branch_id) {
            wp_send_json_error(['message' => 'Chybí ID pobočky']);
            return;
        }
        
        // Validate branch exists
        global $wpdb;
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT id, customer_id, name FROM {$wpdb->prefix}saw_branches WHERE id = %d AND is_active = 1",
            $branch_id
        ), ARRAY_A);
        
        if (!$branch) {
            wp_send_json_error(['message' => 'Pobočka nenalezena']);
            return;
        }
        
        // Validate customer isolation
        $current_customer_id = null;
        if (class_exists('SAW_Context')) {
            $current_customer_id = SAW_Context::get_customer_id();
        }
        
        if (!current_user_can('manage_options')) {
            if ($branch['customer_id'] != $current_customer_id) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[Branch Switcher] SECURITY: Isolation violation - Branch customer: %d, User customer: %d',
                        $branch['customer_id'],
                        $current_customer_id
                    ));
                }
                wp_send_json_error(['message' => 'Nemáte oprávnění k této pobočce']);
                return;
            }
        }
        
        // Set branch ID
        if (class_exists('SAW_Context')) {
            SAW_Context::set_branch_id($branch_id);
        } else {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['saw_current_branch_id'] = $branch_id;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[Branch Switcher] Branch switched to: %d (%s)',
                $branch_id,
                $branch['name']
            ));
        }
        
        wp_send_json_success([
            'branch_id' => $branch_id,
            'branch_name' => $branch['name']
        ]);
    }
    
    /**
     * Render branch switcher UI
     */
    public function render() {
        if (!$this->can_see_branch_switcher()) {
            return;
        }
        
        if (!$this->customer_id) {
            return;
        }
        
        $this->enqueue_assets();
        ?>
        <div class="saw-branch-switcher" id="sawBranchSwitcher" data-customer-id="<?php echo esc_attr($this->customer_id); ?>">
            <button class="saw-branch-switcher-button" id="sawBranchSwitcherButton" data-current-branch-id="<?php echo esc_attr($this->current_branch['id'] ?? 0); ?>">
                <span class="saw-branch-icon">🏢</span>
                <span class="saw-branch-name"><?php echo esc_html($this->current_branch['name'] ?? 'Všechny pobočky'); ?></span>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="saw-branch-arrow"><path d="M8 10.5l-4-4h8l-4 4z"/></svg>
            </button>
            <div class="saw-branch-switcher-dropdown" id="sawBranchSwitcherDropdown">
                <div class="saw-branch-list" id="sawBranchSwitcherList">
                    <div class="saw-branch-loading"><div class="saw-spinner"></div><span>Načítání...</span></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Check if current user can see branch switcher
     */
    private function can_see_branch_switcher() {
        if (current_user_can('manage_options')) {
            return true;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $saw_role = $_SESSION['saw_role'] ?? null;
        
        if (!$saw_role) {
            global $wpdb;
            $saw_user = $wpdb->get_row($wpdb->prepare(
                "SELECT role FROM {$wpdb->prefix}saw_users 
                 WHERE wp_user_id = %d AND is_active = 1",
                get_current_user_id()
            ));
            
            $saw_role = $saw_user->role ?? null;
        }
        
        return $saw_role === 'admin';
    }
    
    /**
     * Enqueue assets
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'saw-branch-switcher',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/branch-switcher/branch-switcher.css',
            [],
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-branch-switcher',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/branch-switcher/branch-switcher.js',
            ['jquery'],
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script('saw-branch-switcher', 'sawBranchSwitcher', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_branch_switcher')
        ]);
    }
}