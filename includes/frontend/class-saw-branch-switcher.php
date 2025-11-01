<?php
/**
 * SAW Branch Switcher Component
 * 
 * Provides branch switching functionality for multi-branch customers
 * Displays in sidebar as first menu item
 * 
 * @package SAW_Visitors
 * @since 4.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Branch_Switcher {
    
    private $current_customer_id;
    private $current_branch_id;
    
    /**
     * Constructor
     * 
     * @param int $customer_id Current customer ID
     * @param int|null $branch_id Current branch ID (optional)
     */
    public function __construct($customer_id, $branch_id = null) {
        $this->current_customer_id = $customer_id;
        $this->current_branch_id = $branch_id;
        
        // Register AJAX handlers
        add_action('wp_ajax_saw_get_branches_for_switcher', [$this, 'ajax_get_branches']);
        add_action('wp_ajax_saw_switch_branch', [$this, 'ajax_switch_branch']);
        add_action('wp_ajax_nopriv_saw_get_branches_for_switcher', [$this, 'ajax_get_branches']);
        add_action('wp_ajax_nopriv_saw_switch_branch', [$this, 'ajax_switch_branch']);
    }
    
    /**
     * AJAX: Get branches for current customer
     */
    public function ajax_get_branches() {
        // Verify permissions
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if (!$customer_id) {
            wp_send_json_error(['message' => 'Chybí ID zákazníka']);
        }
        
        // Try cache first
        $cache_key = 'branches_for_switcher_' . $customer_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            wp_send_json_success([
                'branches' => $cached,
                'current_branch_id' => $this->get_current_branch_id(),
                'cached' => true
            ]);
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'saw_branches';
        
        // Get active branches for customer
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, code, city, is_headquarters, sort_order 
             FROM {$table} 
             WHERE customer_id = %d 
             AND is_active = 1 
             ORDER BY sort_order ASC, is_headquarters DESC, name ASC",
            $customer_id
        ), ARRAY_A);
        
        // Format for selectbox
        $formatted = array_map(function($branch) {
            $label = $branch['name'];
            
            if (!empty($branch['code'])) {
                $label .= ' [' . $branch['code'] . ']';
            }
            
            if ($branch['is_headquarters']) {
                $label .= ' 🏛️';
            }
            
            return [
                'id' => $branch['id'],
                'name' => $branch['name'],
                'code' => $branch['code'] ?? '',
                'city' => $branch['city'] ?? '',
                'is_headquarters' => $branch['is_headquarters'],
                'label' => $label,
                'meta' => !empty($branch['city']) ? $branch['city'] : ''
            ];
        }, $branches);
        
        // Cache for 30 minutes
        set_transient($cache_key, $formatted, 1800);
        
        wp_send_json_success([
            'branches' => $formatted,
            'current_branch_id' => $this->get_current_branch_id(),
            'cached' => false
        ]);
    }
    
    /**
     * AJAX: Switch branch
     */
    public function ajax_switch_branch() {
        // Verify permissions
        if (!current_user_can('read')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        }
        
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        
        if ($branch_id <= 0) {
            // Allow clearing branch selection
            $this->clear_branch_session();
            wp_send_json_success([
                'message' => 'Pobočka byla odstraněna',
                'branch_id' => null
            ]);
            return;
        }
        
        // Verify branch exists and belongs to current customer
        global $wpdb;
        $table = $wpdb->prefix . 'saw_branches';
        
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, customer_id FROM {$table} WHERE id = %d AND is_active = 1",
            $branch_id
        ), ARRAY_A);
        
        if (!$branch) {
            wp_send_json_error(['message' => 'Pobočka nebyla nalezena']);
        }
        
        if ($branch['customer_id'] != $this->current_customer_id) {
            wp_send_json_error(['message' => 'Pobočka nepatří aktuálnímu zákazníkovi']);
        }
        
        // Save to session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['saw_current_branch_id'] = $branch_id;
        
        // Also save to user meta if logged in
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'saw_current_branch_id', $branch_id);
        }
        
        wp_send_json_success([
            'message' => 'Pobočka byla úspěšně přepnuta',
            'branch_id' => $branch_id,
            'branch_name' => $branch['name']
        ]);
    }
    
    /**
     * Get current branch ID from session/user meta
     * 
     * @return int|null
     */
    private function get_current_branch_id() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Try session first
        if (isset($_SESSION['saw_current_branch_id'])) {
            return intval($_SESSION['saw_current_branch_id']);
        }
        
        // Try user meta
        if (is_user_logged_in()) {
            $meta_id = get_user_meta(get_current_user_id(), 'saw_current_branch_id', true);
            if ($meta_id) {
                $_SESSION['saw_current_branch_id'] = intval($meta_id);
                return intval($meta_id);
            }
        }
        
        return null;
    }
    
    /**
     * Clear branch from session
     */
    private function clear_branch_session() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION['saw_current_branch_id']);
        
        if (is_user_logged_in()) {
            delete_user_meta(get_current_user_id(), 'saw_current_branch_id');
        }
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets() {
        // Enqueue CSS
        wp_enqueue_style(
            'saw-branch-switcher',
            SAW_VISITORS_PLUGIN_URL . 'assets/css/saw-branch-switcher.css',
            [],
            SAW_VISITORS_VERSION
        );
        
        // Enqueue JS
        wp_enqueue_script(
            'saw-branch-switcher',
            SAW_VISITORS_PLUGIN_URL . 'assets/js/saw-branch-switcher.js',
            ['jquery'],
            SAW_VISITORS_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('saw-branch-switcher', 'sawBranchSwitcher', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce'),
            'customerId' => $this->current_customer_id,
            'currentBranchId' => $this->current_branch_id,
        ]);
    }
    
    /**
     * Render branch switcher in sidebar
     */
    public function render() {
        $this->enqueue_assets();
        
        // Get branch count for current customer
        global $wpdb;
        $table = $wpdb->prefix . 'saw_branches';
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE customer_id = %d AND is_active = 1",
            $this->current_customer_id
        ));
        
        $current_branch_name = '';
        if ($this->current_branch_id) {
            $current_branch = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$table} WHERE id = %d",
                $this->current_branch_id
            ), ARRAY_A);
            
            $current_branch_name = $current_branch['name'] ?? '';
        }
        
        ?>
        <div class="saw-nav-item-wrapper saw-branch-switcher-wrapper">
            <?php if ($count > 0): ?>
                <!-- Has branches - show switcher -->
                <button class="saw-nav-item saw-branch-switcher-button" id="sawBranchSwitcherButton" 
                        data-customer-id="<?php echo esc_attr($this->current_customer_id); ?>"
                        data-current-branch-id="<?php echo esc_attr($this->current_branch_id ?: ''); ?>"
                        data-current-branch-name="<?php echo esc_attr($current_branch_name); ?>">
                    <span class="saw-nav-icon">🏢</span>
                    <span class="saw-nav-label">
                        <?php if ($current_branch_name): ?>
                            <?php echo esc_html($current_branch_name); ?>
                        <?php else: ?>
                            Vyberte pobočku
                        <?php endif; ?>
                    </span>
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="saw-branch-dropdown-arrow">
                        <path d="M8 10.5l-4-4h8l-4 4z"/>
                    </svg>
                </button>
                
                <!-- Dropdown will be inserted here by JS -->
                
            <?php else: ?>
                <!-- No branches - show create button -->
                <a href="<?php echo home_url('/admin/branches/new/'); ?>" class="saw-nav-item saw-branch-create-link">
                    <span class="saw-nav-icon">➕</span>
                    <span class="saw-nav-label">Vytvořit pobočku</span>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }
}
