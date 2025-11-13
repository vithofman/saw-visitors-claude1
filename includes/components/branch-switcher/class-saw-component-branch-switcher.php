<?php
/**
 * SAW Branch Switcher Component
 *
 * Global branch selector component with AJAX loading and context switching.
 * AJAX handlers are registered globally to work even if component doesn't render.
 *
 * @package     SAW_Visitors
 * @subpackage  Components
 * @version     7.0.2
 * @since       4.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SAW Component Branch Switcher
 *
 * Handles branch selection dropdown with AJAX-powered branch loading and switching.
 * Uses SAW_Context for branch management and customer isolation.
 *
 * @since 4.7.0
 */
class SAW_Component_Branch_Switcher {
    
    /**
     * Customer ID
     *
     * @since 4.7.0
     * @var int|null
     */
    private $customer_id;
    
    /**
     * Current branch data
     *
     * @since 4.7.0
     * @var array|null
     */
    private $current_branch;
    
    /**
     * Register AJAX handlers globally
     *
     * CRITICAL: This must be called early (in plugin init), NOT in constructor.
     * Ensures AJAX handlers work even if component doesn't render.
     *
     * @since 4.7.0
     * @return void
     */
    public static function register_ajax_handlers() {
        add_action('wp_ajax_saw_get_branches_for_switcher', array(__CLASS__, 'ajax_get_branches'));
        add_action('wp_ajax_saw_switch_branch', array(__CLASS__, 'ajax_switch_branch'));
    }
    
    /**
     * Constructor
     *
     * Initializes branch switcher with customer and branch data.
     * Auto-loads from SAW_Context if not provided.
     *
     * @since 4.7.0
     * @param int|null   $customer_id    Customer ID (null = auto-load from context)
     * @param array|null $current_branch Current branch data (null = auto-load from context)
     */
    public function __construct($customer_id = null, $current_branch = null) {
    // Auto-load from SAW_Context if not provided
    if ($customer_id === null && class_exists('SAW_Context')) {
        $customer_id = SAW_Context::get_customer_id();

        // ✅ Fallback přes saw_users, pokud v kontextu nic není
        if (!$customer_id) {
            global $wpdb;
            $saw_user = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT customer_id 
                     FROM {$wpdb->prefix}saw_users 
                     WHERE wp_user_id = %d AND is_active = 1",
                    get_current_user_id()
                ),
                ARRAY_A
            );

            if ($saw_user && !empty($saw_user['customer_id'])) {
                $customer_id = (int) $saw_user['customer_id'];
            }
        }
    }
    
    $this->customer_id = $customer_id;
    
    if ($current_branch === null && class_exists('SAW_Context')) {
        $current_branch = SAW_Context::get_branch_data();
    }
    
    $this->current_branch = $current_branch;
}

    
    /**
     * AJAX: Get branches for current customer
     *
     * Static method - can be called without instance.
     * Returns list of active branches for current customer with proper isolation.
     * Auto-selects first branch if none is currently selected.
     *
     * @since 4.7.0
     * @return void Outputs JSON response
     */
    public static function ajax_get_branches() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        
        if (!wp_verify_nonce($nonce, 'saw_branch_switcher')) {
            wp_send_json_error(array('message' => 'Neplatný bezpečnostní token'));
            return;
        }
        
        // Determine customer_id
        $customer_id = null;
        
        if (current_user_can('manage_options')) {
            // Super admin: Try SAW_Context first, then POST
            if (class_exists('SAW_Context')) {
                $customer_id = SAW_Context::get_customer_id();
            }
            
            if (!$customer_id && isset($_POST['customer_id'])) {
                $customer_id = intval($_POST['customer_id']);
            }
        } else {
            // Non-super admin: ALWAYS use SAW_Context
            if (class_exists('SAW_Context')) {
                $customer_id = SAW_Context::get_customer_id();
            }
            
            if (!$customer_id) {
                // Fallback: Load from database directly
                global $wpdb;
                $saw_user = $wpdb->get_row($wpdb->prepare(
                    "SELECT customer_id FROM %i WHERE wp_user_id = %d AND is_active = 1",
                    $wpdb->prefix . 'saw_users',
                    get_current_user_id()
                ), ARRAY_A);
                
                if ($saw_user && $saw_user['customer_id']) {
                    $customer_id = intval($saw_user['customer_id']);
                }
            }
        }
        
        // Validate customer_id
        if (!$customer_id) {
            wp_send_json_error(array('message' => 'Chybí ID zákazníka'));
            return;
        }
        
        // Load branches from database
        global $wpdb;
        $branches = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, city, street, code, is_headquarters
             FROM %i 
             WHERE customer_id = %d AND is_active = 1 
             ORDER BY is_headquarters DESC, name ASC",
            $wpdb->prefix . 'saw_branches',
            $customer_id
        ), ARRAY_A);
        
        // Format branches
        $formatted = array();
        foreach ($branches as $b) {
            $address = array_filter(array($b['street'] ?? '', $b['city'] ?? ''));
            $formatted[] = array(
                'id' => (int)$b['id'],
                'name' => $b['name'],
                'code' => $b['code'] ?? '',
                'city' => $b['city'] ?? '',
                'address' => implode(', ', $address),
                'is_headquarters' => (bool)($b['is_headquarters'] ?? 0)
            );
        }
        
        // Get current branch_id
        $current_branch_id = null;
        if (class_exists('SAW_Context')) {
            $current_branch_id = SAW_Context::get_branch_id();
        }
        
        // AUTO-SELECT: If no branch selected but branches exist, select first one
        $auto_selected = false;
        if (!$current_branch_id && !empty($formatted)) {
            $first_branch_id = $formatted[0]['id'];
            
            if (class_exists('SAW_Context')) {
                $result = SAW_Context::set_branch_id($first_branch_id);
                if ($result) {
                    $current_branch_id = $first_branch_id;
                    $auto_selected = true;
                }
            }
        }
        
        wp_send_json_success(array(
            'branches' => $formatted,
            'current_branch_id' => $current_branch_id,
            'customer_id' => $customer_id,
            'auto_selected' => $auto_selected,
        ));
    }
    
    /**
     * AJAX: Switch branch
     *
     * Static method - can be called without instance.
     * Changes active branch with proper customer isolation validation.
     *
     * @since 4.7.0
     * @return void Outputs JSON response
     */
    public static function ajax_switch_branch() {
        // Verify nonce
        $nonce = isset($_POST['nonce']) ? $_POST['nonce'] : '';
        
        if (!wp_verify_nonce($nonce, 'saw_branch_switcher')) {
            wp_send_json_error(array('message' => 'Neplatný bezpečnostní token'));
            return;
        }
        
        $branch_id = isset($_POST['branch_id']) ? intval($_POST['branch_id']) : 0;
        
        if (!$branch_id) {
            wp_send_json_error(array('message' => 'Chybí ID pobočky'));
            return;
        }
        
        // Validate branch exists
        global $wpdb;
        $branch = $wpdb->get_row($wpdb->prepare(
            "SELECT id, customer_id, name FROM %i WHERE id = %d AND is_active = 1",
            $wpdb->prefix . 'saw_branches',
            $branch_id
        ), ARRAY_A);
        
        if (!$branch) {
            wp_send_json_error(array('message' => 'Pobočka nenalezena'));
            return;
        }
        
        // Validate customer isolation (non-super admins only)
        if (!current_user_can('manage_options')) {
            $current_customer_id = null;
            if (class_exists('SAW_Context')) {
                $current_customer_id = SAW_Context::get_customer_id();
            }
            
            if ($branch['customer_id'] != $current_customer_id) {
                wp_send_json_error(array('message' => 'Nemáte oprávnění k této pobočce'));
                return;
            }
        }
        
        // Set branch ID via SAW_Context
        if (class_exists('SAW_Context')) {
            SAW_Context::set_branch_id($branch_id);
        }
        
        wp_send_json_success(array(
            'branch_id' => $branch_id,
            'branch_name' => $branch['name']
        ));
    }
    
    /**
     * Render branch switcher UI
     *
     * Outputs the branch switcher dropdown component HTML.
     * Only renders for users with appropriate permissions.
     *
     * @since 4.7.0
     * @return void Outputs HTML directly
     */
    public function render() {
        // Check access
        if (!$this->can_see_branch_switcher()) {
            return;
        }
        
        // Always enqueue assets
        $this->enqueue_assets();
        
        // Show error if customer_id missing
        if (!$this->customer_id) {
            ?>
            <div class="saw-branch-switcher">
                <div class="saw-branch-error">⚠️ Chybí ID zákazníka</div>
            </div>
            <?php
            return;
        }
        
        // Normal render
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
     *
     * Only super admins and admins can see the branch switcher.
     *
     * @since 4.7.0
     * @return bool True if user can see switcher
     */
    private function can_see_branch_switcher() {
        // Super admin always sees branch switcher
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check SAW role
        if (class_exists('SAW_Context')) {
            $role = SAW_Context::get_role();
            return $role === 'admin';
        }
        
        return false;
    }
    
    /**
     * Enqueue assets
     *
     * Loads CSS and JavaScript for branch switcher component.
     *
     * @since 4.7.0
     * @return void
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
        
        wp_localize_script('saw-branch-switcher', 'sawBranchSwitcher', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_branch_switcher')
        ));
    }
}