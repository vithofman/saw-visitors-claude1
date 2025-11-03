<?php
/**
 * Branch Switcher Component - COMPLETE WITH AJAX
 * 
 * @package SAW_Visitors
 * @since 4.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Component_Branch_Switcher {
    
    private $customer_id;
    private $current_branch;
    
    public function __construct($customer_id = null, $current_branch = null) {
        $this->customer_id = $customer_id;
        $this->current_branch = $current_branch;
        
        // Register AJAX handlers
        add_action('wp_ajax_saw_get_branches_for_switcher', [$this, 'ajax_get_branches']);
        add_action('wp_ajax_saw_switch_branch', [$this, 'ajax_switch_branch']);
    }
    
    /**
     * AJAX: Get branches for dropdown
     */
    public function ajax_get_branches() {
        check_ajax_referer('saw_branch_switcher', 'nonce');
        
        $customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        
        if (!$customer_id) {
            wp_send_json_error(['message' => 'Chybí ID zákazníka']);
        }
        
        global $wpdb;
        
        // ✅ Load ALL branches for customer (admin sees all)
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, city, street 
             FROM {$wpdb->prefix}saw_branches 
             WHERE customer_id = %d AND is_active = 1 
             ORDER BY name ASC",
            $customer_id
        ), ARRAY_A);
        
        // Format addresses
        foreach ($branches as &$branch) {
            $address_parts = array_filter([$branch['street'], $branch['city']]);
            $branch['address'] = implode(', ', $address_parts);
            unset($branch['street'], $branch['city']);
        }
        
        // Get current branch from session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $current_branch_id = $_SESSION['saw_current_branch_id'] ?? null;
        
        wp_send_json_success([
            'branches' => $branches,
            'current_branch_id' => $current_branch_id,
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
        }
        
        // Verify branch exists and get customer_id
        global $wpdb;
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT id, customer_id, name FROM {$wpdb->prefix}saw_branches WHERE id = %d AND is_active = 1",
            $branch_id
        ));
        
        if (!$branch) {
            wp_send_json_error(['message' => 'Pobočka nenalezena']);
        }
        
        // Save to session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION['saw_current_branch_id'] = $branch_id;
        
        // Save to user meta (persistent per customer)
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, 'saw_current_branch_id', $branch_id);
            update_user_meta($user_id, 'saw_branch_customer_' . $branch->customer_id, $branch_id);
        }
        
        wp_send_json_success([
            'branch_id' => $branch_id,
            'branch_name' => $branch->name,
        ]);
    }
    
    /**
     * Render branch switcher HTML
     */
    public function render() {
        if (!$this->customer_id) {
            return;
        }
        
        $this->enqueue_assets();
        ?>
        <div class="saw-branch-switcher" id="sawBranchSwitcher" data-customer-id="<?php echo esc_attr($this->customer_id); ?>">
            <button class="saw-branch-switcher-button" id="sawBranchSwitcherButton"
                    data-current-branch-id="<?php echo esc_attr($this->current_branch['id'] ?? 0); ?>">
                <span class="saw-branch-icon">🏢</span>
                <div class="saw-branch-info">
                    <span class="saw-branch-label">Pobočka</span>
                    <span class="saw-branch-name"><?php echo esc_html($this->current_branch['name'] ?? 'Vyberte pobočku'); ?></span>
                </div>
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" class="saw-branch-arrow">
                    <path d="M8 10.5l-4-4h8l-4 4z"/>
                </svg>
            </button>
            
            <div class="saw-branch-switcher-dropdown" id="sawBranchSwitcherDropdown">
                <div class="saw-branch-list" id="sawBranchSwitcherList">
                    <div class="saw-branch-loading">
                        <div class="saw-spinner"></div>
                        <span>Načítání poboček...</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue CSS + JS
     */
    private function enqueue_assets() {
        wp_enqueue_style(
            'saw-branch-switcher',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/branch-switcher/branch-switcher.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-branch-switcher',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/branch-switcher/branch-switcher.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script(
            'saw-branch-switcher',
            'sawBranchSwitcher',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('saw_branch_switcher'),
            )
        );
    }
}