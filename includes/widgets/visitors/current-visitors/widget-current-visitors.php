<?php
/**
 * Dashboard Widget - Currently Present Visitors
 * 
 * Shows who is currently inside the branch (fire alarm support)
 * 
 * @package     SAW_Visitors
 * @subpackage  Admin/Widgets
 * @version     2.0.0 - UPDATED: Removed redundant counter, modern styling
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAW_Widget_Current_Visitors {
    
    /**
     * Register widget
     */
    public static function init() {
        // AJAX handler for manual checkout
        add_action('wp_ajax_saw_manual_checkout_visitor', array(__CLASS__, 'ajax_manual_checkout'));
    }
    
    /**
     * Enqueue widget assets
     */
    public static function enqueue_assets() {
        wp_enqueue_style(
            'saw-widget-current-visitors',
            SAW_VISITORS_PLUGIN_URL . 'includes/widgets/visitors/current-visitors/current-visitors.css',
            array(),
            SAW_VISITORS_VERSION
        );
        
        wp_enqueue_script(
            'saw-widget-current-visitors',
            SAW_VISITORS_PLUGIN_URL . 'includes/widgets/visitors/current-visitors/current-visitors.js',
            array('jquery'),
            SAW_VISITORS_VERSION,
            true
        );
        
        wp_localize_script('saw-widget-current-visitors', 'sawCurrentVisitors', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('saw_ajax_nonce'),
        ));
    }
    
    /**
     * Render widget content
     * Can be called from anywhere (dashboard, pages, etc.)
     */
    public static function render($branch_id = null) {
        if (!$branch_id) {
            $branch_id = SAW_Context::get_branch_id();
        }
        
        if (!$branch_id) {
            echo '<p class="saw-text-muted">Není vybrána pobočka</p>';
            return;
        }
        
        // Load visits model
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/model.php';
        $visits_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visits/config.php';
        $visits_model = new SAW_Module_Visits_Model($visits_config);
        
        $present = $visits_model->get_currently_present($branch_id);
        
        if (empty($present)) {
            echo '<div class="saw-empty-state">';
            echo '<span class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #10b981;"></span>';
            echo '<p style="margin: 12px 0 0; color: #6b7280;">Nikdo aktuálně uvnitř</p>';
            echo '</div>';
            return;
        }
        
        ?>
        <div class="saw-current-visitors-widget">
    <div class="saw-visitors-list">
        <?php foreach ($present as $person): ?>
        <a href="<?php echo home_url('/admin/visitors/' . $person['visitor_id']); ?>" 
           class="saw-visitor-card" 
           data-visitor-id="<?php echo esc_attr($person['visitor_id']); ?>">
            <div class="saw-visitor-avatar">
                <span class="dashicons dashicons-admin-users"></span>
            </div>
            
            <div class="saw-visitor-info">
                <div class="saw-visitor-name">
                    <strong><?php echo esc_html($person['visitor_name']); ?></strong>
                    <?php if (!empty($person['company_name'])): ?>
                        <span class="saw-visitor-company">
                            🏢 <?php echo esc_html($person['company_name']); ?>
                        </span>
                    <?php else: ?>
                        <span class="saw-visitor-company saw-visitor-physical">
                            👤 Fyzická osoba
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="saw-visitor-meta">
                    <span class="saw-visitor-time">
                        <span class="dashicons dashicons-clock"></span>
                        <?php echo date('H:i', strtotime($person['today_checkin'])); ?>
                    </span>
                    <span class="saw-visitor-duration">
                        <span class="dashicons dashicons-backup"></span>
                        <?php echo $person['minutes_inside']; ?> min
                    </span>
                    <?php if (!empty($person['phone'])): ?>
                    <span class="saw-visitor-phone">
                        <span class="dashicons dashicons-phone"></span>
                        <?php echo esc_html($person['phone']); ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="saw-visitor-actions">
                <button type="button" 
                        class="saw-manual-checkout-btn"
                        data-visitor-id="<?php echo esc_attr($person['visitor_id']); ?>"
                        title="Ručně odhlásit"
                        onclick="event.preventDefault(); event.stopPropagation(); handleCheckout(this);">
                    <span class="dashicons dashicons-exit"></span>
                    Check-out
                </button>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
        <?php
    }
    
    /**
     * AJAX: Manual checkout
     */
    public static function ajax_manual_checkout() {
        check_ajax_referer('saw_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Nemáte oprávnění'));
            return;
        }
        
        $visitor_id = isset($_POST['visitor_id']) ? intval($_POST['visitor_id']) : 0;
        $reason = isset($_POST['reason']) ? sanitize_textarea_field($_POST['reason']) : 'Ruční odhlášení administrátorem';
        
        if (!$visitor_id) {
            wp_send_json_error(array('message' => 'Neplatný návštěvník'));
            return;
        }
        
        // Load visitors model
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/model.php';
        $visitors_config = require SAW_VISITORS_PLUGIN_DIR . 'includes/modules/visitors/config.php';
        $visitors_model = new SAW_Module_Visitors_Model($visitors_config);
        
        $result = $visitors_model->daily_checkout(
            $visitor_id, 
            current_time('Y-m-d'), 
            true, // manual
            get_current_user_id(), 
            $reason
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success(array('message' => 'Návštěvník odhlášen'));
    }
}

// Initialize widget
SAW_Widget_Current_Visitors::init();