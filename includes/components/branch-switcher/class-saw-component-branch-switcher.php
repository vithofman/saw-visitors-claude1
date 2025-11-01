<?php
/**
 * Branch Switcher Component
 * 
 * Globální komponenta pro přepínání poboček vybraného zákazníka
 * Zobrazuje se jako první položka v sidebaru
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
    }
    
    /**
     * Render branch switcher
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
     * Enqueue assets
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
