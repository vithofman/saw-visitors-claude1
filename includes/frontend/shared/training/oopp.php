<?php
/**
 * Training OOPP Step - Shared Template
 * 
 * Displays OOPP (Personal Protective Equipment) cards.
 * Used by both Invitation and Terminal flows.
 * 
 * ⚠️ VYUŽÍVÁ EXISTUJÍCÍ CSS TŘÍDY - žádné vlastní styly!
 * 
 * @package     SAW_Visitors
 * @subpackage  Frontend/Shared/Training
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get data from controller
$oopp_items = $oopp_items ?? [];

$is_invitation = $is_invitation ?? false;
$token = $token ?? '';

// Get language from session
$lang = 'cs';
if (!class_exists('SAW_Session_Manager')) {
    require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php';
}
$session = SAW_Session_Manager::instance();
$flow_key = $is_invitation ? 'invitation_flow' : 'terminal_flow';
$flow = $session->get($flow_key);
$lang = $flow['language'] ?? 'cs';

// Translations
$t = [
    'cs' => [
        'title' => 'Osobní ochranné pracovní prostředky',
        'subtitle' => 'Následující OOPP jsou vyžadovány pro vaši návštěvu',
        'group' => 'Skupina',
        'risks' => 'Chrání proti',
        'usage' => 'Pokyny pro použití',
        'no_oopp' => 'Pro tuto návštěvu nejsou vyžadovány žádné OOPP',
        'confirm' => 'Beru na vědomí požadavky na OOPP',
        'continue' => 'Pokračovat',
    ],
    'en' => [
        'title' => 'Personal Protective Equipment',
        'subtitle' => 'The following PPE is required for your visit',
        'group' => 'Category',
        'risks' => 'Protects against',
        'usage' => 'Usage instructions',
        'no_oopp' => 'No PPE is required for this visit',
        'confirm' => 'I acknowledge the PPE requirements',
        'continue' => 'Continue',
    ],
];

$texts = $t[$lang] ?? $t['cs'];

// Form action URL
$form_action = $is_invitation 
    ? home_url('/visitor-invitation/' . $token . '/') 
    : home_url('/terminal/');
?>

<div class="saw-page-aurora saw-step-oopp saw-page-scrollable">
    <div class="saw-page-content saw-page-content-scroll">
        <div class="saw-page-container saw-page-container-wide">
            
            <div class="saw-page-header saw-page-header-left">
                <div class="saw-header-icon">🦺</div>
                <div class="saw-header-text">
                    <h1 class="saw-header-title"><?php echo esc_html($texts['title']); ?></h1>
                    <p class="saw-header-subtitle"><?php echo esc_html($texts['subtitle']); ?></p>
                </div>
            </div>

            <?php if (empty($oopp_items)): ?>
                <div class="saw-card-content">
                    <div class="saw-card-body">
                        <div class="saw-empty-state">
                            <div class="saw-empty-state-icon">🦺</div>
                            <p class="saw-empty-state-text"><?php echo esc_html($texts['no_oopp']); ?></p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="saw-accordion">
                    <?php foreach ($oopp_items as $index => $item): ?>
                    <?php 
                    $oopp_id = 'oopp-' . $index;
                    ?>
                    
                    <div class="saw-accordion-item <?php echo $index === 0 ? 'expanded' : ''; ?>" data-oopp="<?php echo $oopp_id; ?>">
                        <button type="button" class="saw-accordion-header">
                            <div class="saw-accordion-title-wrapper">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?php echo esc_url($item['image_url']); ?>" 
                                         alt="<?php echo esc_attr($item['name']); ?>"
                                         style="width: 48px; height: 48px; object-fit: cover; border-radius: 8px; margin-right: 12px; flex-shrink: 0;">
                                <?php else: ?>
                                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0;">
                                        <span style="font-size: 24px;">🦺</span>
                                    </div>
                                <?php endif; ?>
                                
                                <svg class="saw-accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 2rem; height: 2rem;">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                                <h3 class="saw-accordion-title"><?php echo esc_html($item['name']); ?></h3>
                            </div>
                            
                            <div class="saw-accordion-badge">
                                <span><?php echo esc_html($item['group_code']); ?>. <?php echo esc_html($item['group_name']); ?></span>
                            </div>
                        </button>
                        
                        <div class="saw-accordion-content">
                            <div class="saw-accordion-body saw-accordion-body-grid">
                                
                                <!-- Large image -->
                                <?php if (!empty($item['image_url'])): ?>
                                    <div style="text-align: center; margin-bottom: 1.5rem; grid-column: 1 / -1;">
                                        <img src="<?php echo esc_url($item['image_url']); ?>" 
                                             alt="<?php echo esc_attr($item['name']); ?>"
                                             style="max-width: 100%; max-height: 300px; object-fit: contain; border-radius: 12px; border: 2px solid rgba(255,255,255,0.1);">
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Text content -->
                                <div class="saw-text-content">
                                    
                                    <!-- Risk description -->
                                    <?php if (!empty($item['risk_description'])): ?>
                                        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; padding: 1rem; border-radius: 0 8px 8px 0; margin-bottom: 1rem;">
                                            <strong style="color: #ef4444; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 0.5rem;">
                                                ⚠️ <?php echo esc_html($texts['risks']); ?>
                                            </strong>
                                            <div>
                                                <?php echo nl2br(esc_html($item['risk_description'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Usage instructions -->
                                    <?php if (!empty($item['usage_instructions'])): ?>
                                        <div style="background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; padding: 1rem; border-radius: 0 8px 8px 0; margin-bottom: 1rem;">
                                            <strong style="color: #3b82f6; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 0.5rem;">
                                                📋 <?php echo esc_html($texts['usage']); ?>
                                            </strong>
                                            <div>
                                                <?php echo nl2br(esc_html($item['usage_instructions'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Protective properties -->
                                    <?php if (!empty($item['protective_properties'])): ?>
                                        <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; padding: 1rem; border-radius: 0 8px 8px 0;">
                                            <strong style="color: #10b981; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 0.5rem;">
                                                🛡️ <?php echo $lang === 'en' ? 'Protective properties' : 'Ochranné vlastnosti'; ?>
                                            </strong>
                                            <div>
                                                <?php echo nl2br(esc_html($item['protective_properties'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                </div>
                                
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
    
    <?php if ($is_invitation): ?>
    <!-- Skip button for invitation mode -->
    <div class="saw-panel-skip">
        <p class="saw-panel-skip-info">
            💡 Toto školení je volitelné. Můžete ho přeskočit a projít si později.
        </p>
        <form method="POST" style="display: inline-block;">
            <?php 
            $nonce_name = $is_invitation ? 'saw_invitation_step' : 'saw_terminal_step';
            $nonce_field = $is_invitation ? 'invitation_nonce' : 'terminal_nonce';
            $action_name = $is_invitation ? 'invitation_action' : 'terminal_action';
            wp_nonce_field($nonce_name, $nonce_field); 
            ?>
            <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="skip_training">
            <button type="submit" class="saw-panel-skip-btn">
                ⏭️ Přeskočit školení
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- UNIFIED Floating Panel -->
    <form method="POST" id="oopp-form" class="saw-panel-confirm">
        <?php 
        $nonce_name = $is_invitation ? 'saw_invitation_step' : 'saw_terminal_step';
        $nonce_field = $is_invitation ? 'invitation_nonce' : 'terminal_nonce';
        $action_name = $is_invitation ? 'invitation_action' : 'terminal_action';
        $complete_action = $is_invitation ? 'complete_training' : 'complete_training';
        wp_nonce_field($nonce_name, $nonce_field); 
        ?>
        <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($complete_action); ?>">

        <label class="saw-panel-checkbox" id="checkbox-wrapper">
            <input type="checkbox"
                   name="oopp_confirmed"
                   id="oopp-confirmed"
                   value="1"
                   required>
            <span><?php echo esc_html($texts['confirm']); ?></span>
        </label>

        <button type="submit"
                class="saw-panel-btn"
                id="continue-btn"
                disabled>
            <?php echo esc_html($texts['continue']); ?> →
        </button>
    </form>
</div>

<script>
(function() {
    'use strict';

    // Accordion functionality
    document.querySelectorAll('.saw-accordion-header').forEach(header => {
        header.addEventListener('click', function() {
            const item = this.closest('.saw-accordion-item');
            item.classList.toggle('expanded');
        });
    });

    // Checkbox listener
    const checkbox = document.getElementById('oopp-confirmed');
    const continueBtn = document.getElementById('continue-btn');
    const wrapper = document.getElementById('checkbox-wrapper');

    if (checkbox && continueBtn) {
        checkbox.addEventListener('change', function() {
            continueBtn.disabled = !this.checked;
            if (wrapper) {
                if (this.checked) {
                    wrapper.classList.add('checked');
                } else {
                    wrapper.classList.remove('checked');
                }
            }
        });
    }
})();
</script>

<?php
error_log("[OOPP.PHP] Shared template loaded (v1.0.0)");
?>
