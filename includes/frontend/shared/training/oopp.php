<?php
/**
 * Training OOPP Step - Shared Template
 * 
 * Displays OOPP (Personal Protective Equipment) cards.
 * Works for Terminal, Invitation and Visitor Info flows.
 * 
 * @package     SAW_Visitors
 * @subpackage  Frontend/Shared/Training
 * @version     3.9.9
 * 
 * ZMĚNA v 3.9.9:
 * - REMOVED: Skip training sekce úplně odstraněna
 * - NEW: Free mode pro invitation - checkbox volitelný, button aktivní
 */

if (!defined('ABSPATH')) {
    exit;
}

// ===== CONTEXT DETECTION =====
$context = 'terminal';
if (isset($is_invitation) && $is_invitation === true) {
    $context = 'invitation';
}
if (isset($is_visitor_info) && $is_visitor_info === true) {
    $context = 'visitor_info';
}

$context_settings = array(
    'terminal' => array(
        'nonce_name' => 'saw_terminal_step',
        'nonce_field' => 'terminal_nonce',
        'action_name' => 'terminal_action',
        'complete_action' => 'complete_training_oopp',
    ),
    'invitation' => array(
        'nonce_name' => 'saw_invitation_step',
        'nonce_field' => 'invitation_nonce',
        'action_name' => 'invitation_action',
        'complete_action' => 'complete_training',
    ),
    'visitor_info' => array(
        'nonce_name' => 'saw_visitor_info_step',
        'nonce_field' => 'visitor_info_nonce',
        'action_name' => 'visitor_info_action',
        'complete_action' => 'complete_training_oopp',
    ),
);

$ctx = $context_settings[$context];
$nonce_name = $ctx['nonce_name'];
$nonce_field = $ctx['nonce_field'];
$action_name = $ctx['action_name'];
$complete_action = $ctx['complete_action'];

// FREE MODE for invitation - no confirmation required
$free_mode = ($context === 'invitation');

// Get data from controller
$oopp_items = isset($oopp_items) ? $oopp_items : array();
$token = isset($token) ? $token : '';

// Detect flow type (legacy support)
$is_invitation = ($context === 'invitation');

// Get language from session or flow
$lang = 'cs';
if ($context === 'visitor_info') {
    $flow = isset($flow) ? $flow : array();
    $lang = isset($flow['language']) ? $flow['language'] : 'cs';
} else {
    if (!class_exists('SAW_Session_Manager')) {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/core/class-saw-session-manager.php';
    }
    $session = SAW_Session_Manager::instance();
    $flow_key = $is_invitation ? 'invitation_flow' : 'terminal_flow';
    $flow = $session->get($flow_key);
    $lang = $flow['language'] ?? 'cs';
}

// Translations
$t = array(
    'cs' => array(
        'title' => 'Osobní ochranné pracovní prostředky',
        'subtitle' => 'Seznámení s požadavky na OOPP při práci v areálu',
        'confirm' => 'Potvrzuji seznámení s požadavky na OOPP',
        'continue' => 'Pokračovat',
        'no_oopp' => 'Žádné OOPP nejsou vyžadovány.',
        'risk_description' => 'Popis rizika',
        'protective_properties' => 'Ochranné vlastnosti',
    ),
    'en' => array(
        'title' => 'Personal Protective Equipment',
        'subtitle' => 'Familiarization with PPE requirements when working on premises',
        'confirm' => 'I confirm familiarization with PPE requirements',
        'continue' => 'Continue',
        'no_oopp' => 'No PPE required.',
        'risk_description' => 'Risk Description',
        'protective_properties' => 'Protective Properties',
    ),
    'sk' => array(
        'title' => 'Osobné ochranné pracovné prostriedky',
        'subtitle' => 'Oboznámenie s požiadavkami na OOPP pri práci v areáli',
        'confirm' => 'Potvrdzujem oboznámenie s požiadavkami na OOPP',
        'continue' => 'Pokračovať',
        'no_oopp' => 'Žiadne OOPP nie sú vyžadované.',
        'risk_description' => 'Popis rizika',
        'protective_properties' => 'Ochranné vlastnosti',
    ),
    'uk' => array(
        'title' => 'Засоби індивідуального захисту',
        'subtitle' => 'Ознайомлення з вимогами до ЗІЗ при роботі на території',
        'confirm' => 'Підтверджую ознайомлення з вимогами до ЗІЗ',
        'continue' => 'Продовжити',
        'no_oopp' => 'ЗІЗ не потрібні.',
        'risk_description' => 'Опис ризику',
        'protective_properties' => 'Захисні властивості',
    ),
);

$texts = isset($t[$lang]) ? $t[$lang] : $t['cs'];
?>

<div class="saw-page-aurora saw-step-oopp saw-page-scrollable">
    <div class="saw-page-content saw-page-content-scroll">
        <div class="saw-page-container">
            
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
                                <span><?php echo esc_html($item['group_code']); ?>.<?php echo esc_html($item['group_name']); ?></span>
                            </div>
                        </button>
                        
                        <div class="saw-accordion-content">
                            <div class="saw-accordion-body saw-oopp-grid">
                                
                                <!-- LEFT COLUMN: Image -->
                                <div class="saw-oopp-image-wrapper">
                                    <?php if (!empty($item['image_url'])): ?>
                                        <div style="background: rgba(255,255,255,0.05); padding: 1rem; border-radius: 16px; border: 1px solid rgba(255,255,255,0.1);">
                                            <img src="<?php echo esc_url($item['image_url']); ?>" 
                                                 alt="<?php echo esc_attr($item['name']); ?>"
                                                 style="width: 100%; height: auto; border-radius: 12px; display: block;">
                                        </div>
                                    <?php else: ?>
                                        <div style="aspect-ratio: 1; background: rgba(255,255,255,0.05); border-radius: 16px; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(255,255,255,0.1);">
                                            <span style="font-size: 4rem;">🦺</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- RIGHT COLUMN: Details -->
                                <div class="saw-text-content" style="padding-top: 0 !important;">
                                    
                                    <!-- Risk description -->
                                    <?php if (!empty($item['risk_description'])): ?>
                                        <div style="background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; padding: 1rem; border-radius: 0 8px 8px 0; margin-bottom: 1rem;">
                                            <strong style="color: #ef4444; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 0.5rem;">
                                                ⚠️ <?php echo esc_html($texts['risk_description']); ?>
                                            </strong>
                                            <div style="color: #fca5a5;">
                                                <?php echo nl2br(esc_html($item['risk_description'])); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Protective properties -->
                                    <?php if (!empty($item['protective_properties'])): ?>
                                        <div style="background: rgba(16, 185, 129, 0.1); border-left: 4px solid #10b981; padding: 1rem; border-radius: 0 8px 8px 0;">
                                            <strong style="color: #10b981; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 0.5rem;">
                                                🛡️ <?php echo esc_html($texts['protective_properties']); ?>
                                            </strong>
                                            <div style="color: #a7f3d0;">
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
    
    <!-- Floating Panel -->
    <form method="POST" id="oopp-form" class="saw-panel-confirm">
        <?php wp_nonce_field($nonce_name, $nonce_field); ?>
        <input type="hidden" name="<?php echo esc_attr($action_name); ?>" value="<?php echo esc_attr($complete_action); ?>">

        <label class="saw-panel-checkbox" id="checkbox-wrapper">
            <input type="checkbox"
                   name="oopp_confirmed"
                   id="oopp-confirmed"
                   value="1"
                   <?php if (!$free_mode): ?>required<?php endif; ?>>
            <span><?php echo esc_html($texts['confirm']); ?></span>
        </label>

        <button type="submit"
                class="saw-panel-btn"
                id="continue-btn"
                <?php echo $free_mode ? '' : 'disabled'; ?>>
            <?php echo esc_html($texts['continue']); ?> →
        </button>
    </form>
</div>

<script>
(function() {
    'use strict';

    document.querySelectorAll('.saw-accordion-header').forEach(function(header) {
        header.addEventListener('click', function() {
            var item = this.closest('.saw-accordion-item');
            item.classList.toggle('expanded');
        });
    });

    var checkbox = document.getElementById('oopp-confirmed');
    var continueBtn = document.getElementById('continue-btn');
    var wrapper = document.getElementById('checkbox-wrapper');

    if (checkbox && continueBtn) {
        checkbox.addEventListener('change', function() {
            continueBtn.disabled = !this.checked;
            if (wrapper) {
                wrapper.classList.toggle('checked', this.checked);
            }
        });
    }
})();
</script>
