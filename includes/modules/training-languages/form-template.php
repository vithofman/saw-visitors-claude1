<?php
/**
 * Training Languages Form Template - FINAL GLOSSY EDITION
 *
 * @package SAW_Visitors
 * @version 3.7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$languages_data = $config['languages_data'] ?? [];
$branches_to_loop = $is_edit ? ($item['branches'] ?? []) : ($config['available_branches'] ?? []);

$nonce_action = $is_edit ? 'saw_edit_training_languages' : 'saw_create_training_languages';
?>

<form method="post" class="saw-form saw-language-form">
    
    <?php wp_nonce_field($nonce_action); ?>
    <?php if ($is_edit): ?><input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>"><?php endif; ?>
    
    <div class="saw-section">
        <div class="saw-section-head">
            <h3><span class="dashicons dashicons-translation"></span> Základní údaje</h3>
        </div>
        
        <div class="saw-card saw-hero-card">
            <div class="saw-card-body">
                <div class="saw-language-grid">
                    
                    <div class="saw-language-input-area">
                        <label for="language_code" class="saw-label-heading">Vyberte jazyk ze seznamu</label>
                        <div class="saw-select-wrapper">
                            <select id="language_code" name="language_code" class="saw-select-modern" required <?php echo $is_edit ? 'disabled' : ''; ?>>
                                <option value="">-- Zvolte jazyk --</option>
                                <?php foreach ($languages_data as $code => $lang): ?>
                                    <option value="<?php echo esc_attr($code); ?>" 
                                            data-name="<?php echo esc_attr($lang['name_cs']); ?>" 
                                            data-flag="<?php echo esc_attr($lang['flag']); ?>"
                                            <?php selected(!empty($item['language_code']) ? $item['language_code'] : '', $code); ?>>
                                        <?php echo esc_html($lang['name_cs']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <span class="saw-select-icon dashicons dashicons-arrow-down-alt2"></span>
                        </div>
                        
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="language_code" value="<?php echo esc_attr($item['language_code']); ?>">
                        <?php endif; ?>
                        <input type="hidden" id="language_name" name="language_name" value="<?php echo esc_attr($item['language_name'] ?? ''); ?>">
                        <input type="hidden" id="flag_emoji" name="flag_emoji" value="<?php echo esc_attr($item['flag_emoji'] ?? ''); ?>">
                    </div>

                    <div class="saw-language-preview-area">
                        <div id="flag-preview-card" class="saw-id-card <?php echo !empty($item['flag_emoji']) ? 'is-visible' : ''; ?>">
                            
                            <div class="saw-id-placeholder" style="<?php echo !empty($item['flag_emoji']) ? 'display:none;' : ''; ?>">
                                <span class="dashicons dashicons-format-image"></span>
                                <span>Náhled karty jazyka</span>
                            </div>

                            <div class="saw-id-content-wrapper" style="<?php echo empty($item['flag_emoji']) ? 'display:none;' : ''; ?>">
                                <div class="saw-id-flag"><?php echo esc_html($item['flag_emoji'] ?? ''); ?></div>
                                <div class="saw-id-info">
                                    <span class="saw-id-label">Jazyk školení</span>
                                    <h2 class="saw-id-title"><?php echo esc_html($item['language_name'] ?? ''); ?></h2>
                                </div>
                                <div class="saw-id-badge"><?php echo esc_html(strtoupper($item['language_code'] ?? '')); ?></div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="saw-section">
        <div class="saw-section-head saw-flex-between">
            <h3><span class="dashicons dashicons-building"></span> Aktivace pro pobočky</h3>
            <label class="saw-toggle-all">
                <input type="checkbox" id="select-all-branches">
                <span>Vybrat vše</span>
            </label>
        </div>

        <?php if (empty($branches_to_loop)): ?>
            <div class="saw-empty-box">
                <span class="dashicons dashicons-store"></span>
                <p>Žádné pobočky k dispozici.</p>
            </div>
        <?php else: ?>
            <div class="saw-branches-list">
                <?php foreach ($branches_to_loop as $branch): ?>
                    <?php 
                    $is_active = !empty($branch['is_active']);
                    $is_default = !empty($branch['is_default']);
                    $order = $branch['display_order'] ?? 0;
                    ?>
                    <div class="saw-branch-glossy-row <?php echo $is_active ? 'is-active' : ''; ?>">
                        
                        <div class="saw-glossy-info">
                            <div class="saw-glossy-name"><?php echo esc_html($branch['name']); ?></div>
                            <?php if(!empty($branch['city'])): ?>
                                <div class="saw-glossy-city"><span class="dashicons dashicons-location"></span> <?php echo esc_html($branch['city']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="saw-glossy-controls">
                            
                            <div class="saw-control-item">
                                <label class="saw-switch-compact" title="Aktivovat jazyk">
                                    <input type="checkbox" 
                                           name="branches[<?php echo esc_attr($branch['id']); ?>][active]" 
                                           value="1" 
                                           class="saw-branch-active-checkbox"
                                           data-branch-id="<?php echo esc_attr($branch['id']); ?>"
                                           <?php checked($is_active, true); ?>>
                                    <span class="saw-slider-compact"></span>
                                    <span class="saw-switch-label">Aktivní</span>
                                </label>
                            </div>

                            <div class="saw-separator"></div>

                            <div class="saw-control-item">
                                <label class="saw-radio-pill-compact <?php echo $is_default ? 'is-selected' : ''; ?>" title="Nastavit jako výchozí">
                                    <input type="radio" 
                                           name="default_branch_dummy" 
                                           class="saw-branch-default-radio"
                                           data-branch-id="<?php echo esc_attr($branch['id']); ?>"
                                           <?php checked($is_default, true); ?>
                                           <?php disabled(!$is_active, true); ?>>
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <span>Výchozí</span>
                                </label>
                                <input type="hidden" 
                                       name="branches[<?php echo esc_attr($branch['id']); ?>][is_default]" 
                                       value="<?php echo $is_default ? '1' : '0'; ?>"
                                       class="saw-branch-default-hidden"
                                       data-branch-id="<?php echo esc_attr($branch['id']); ?>">
                            </div>

                            <div class="saw-control-item">
                                <span class="saw-order-label">Pořadí:</span>
                                <input type="number" 
                                       name="branches[<?php echo esc_attr($branch['id']); ?>][display_order]" 
                                       value="<?php echo esc_attr($order); ?>" 
                                       class="saw-input-micro saw-order-input" 
                                       min="0"
                                       <?php disabled(!$is_active, true); ?>>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="saw-form-footer">
        <button type="submit" class="saw-button saw-button-primary saw-button-wide">
            <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit jazyk'; ?>
        </button>
        <button type="button" class="saw-button saw-button-text saw-form-cancel-btn">Zrušit</button>
    </div>
</form>

<script>
(function($) {
    // Live Preview
    $('#language_code').on('change', function() {
        const $opt = $(this).find('option:selected');
        const code = $opt.val();
        
        const $card = $('#flag-preview-card');
        const $ph = $card.find('.saw-id-placeholder');
        const $content = $card.find('.saw-id-content-wrapper');

        if (code) {
            $('#language_name').val($opt.data('name'));
            $('#flag_emoji').val($opt.data('flag'));
            
            $content.find('.saw-id-flag').text($opt.data('flag'));
            $content.find('.saw-id-title').text($opt.data('name'));
            $content.find('.saw-id-badge').text(code.toUpperCase());

            $ph.hide();
            $content.css('display', 'flex');
            $card.addClass('is-visible');
        } else {
            $ph.show();
            $content.hide();
            $card.removeClass('is-visible');
        }
    });

    // Branch UI Logic
    $('.saw-branch-active-checkbox').on('change', function() {
        const $row = $(this).closest('.saw-branch-glossy-row');
        if ($(this).is(':checked')) {
            $row.addClass('is-active');
            $row.find('input:not(.saw-branch-active-checkbox)').prop('disabled', false);
        } else {
            $row.removeClass('is-active');
            $row.find('input[type="radio"]').prop('checked', false).prop('disabled', true);
            $row.find('.saw-branch-default-hidden').val('0');
            $row.find('.saw-order-input').prop('disabled', true);
            $row.find('.saw-radio-pill-compact').removeClass('is-selected');
        }
    });

    $('.saw-branch-default-radio').on('change', function() {
        $('.saw-radio-pill-compact').removeClass('is-selected');
        $('.saw-branch-default-hidden').val('0');
        
        const branchId = $(this).data('branch-id');
        $(this).closest('.saw-radio-pill-compact').addClass('is-selected');
        $(`.saw-branch-default-hidden[data-branch-id="${branchId}"]`).val('1');
    });

    $('#select-all-branches').on('change', function() {
        $('.saw-branch-active-checkbox').prop('checked', $(this).is(':checked')).trigger('change');
    });

})(jQuery);
</script>
<style>
/* === BASE === */
.saw-form { max-width: 100%; margin: 0 auto; }
.saw-section { margin-bottom: 32px; }
.saw-section-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; border-bottom: 1px solid #e2e8f0; padding-bottom: 12px; }
.saw-section-head h3 { margin: 0; font-size: 16px; font-weight: 700; color: #334155; display: flex; align-items: center; gap: 8px; text-transform: uppercase; letter-spacing: 0.5px; }

/* === HERO CARD (Blue/Grey ID Style) === */
.saw-hero-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); border: 1px solid #e2e8f0; overflow: hidden; }
.saw-card-body { padding: 24px; }

.saw-language-grid { display: grid; grid-template-columns: 1fr 320px; gap: 32px; align-items: center; }

.saw-label-heading { display: block; font-weight: 600; color: #1e293b; margin-bottom: 8px; font-size: 14px; }
.saw-select-wrapper { position: relative; }
.saw-select-modern { width: 100%; padding: 14px 16px; font-size: 16px; border: 2px solid #e2e8f0; border-radius: 10px; background: #f8fafc; appearance: none; color: #334155; font-weight: 500; transition: all 0.2s; cursor: pointer; }
.saw-select-modern:focus { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); outline: none; }
.saw-select-icon { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #64748b; pointer-events: none; }

/* === ID CARD PREVIEW === */
.saw-language-preview-area { display: flex; justify-content: flex-end; }

.saw-id-card {
    display: flex; align-items: center;
    width: 100%; height: 100px;
    background: #f1f5f9; border: 2px dashed #cbd5e1; border-radius: 12px;
    padding: 0 20px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.saw-id-card.is-visible {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); /* Dark Blue Theme */
    border: none;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    color: #fff;
}

.saw-id-content-wrapper { display: flex; align-items: center; gap: 16px; width: 100%; }

.saw-id-flag { font-size: 48px; line-height: 1; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2)); flex-shrink: 0; }
.saw-id-info { flex: 1; display: flex; flex-direction: column; }
.saw-id-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #94a3b8; font-weight: 700; margin-bottom: 2px; }
.saw-id-title { font-size: 20px; font-weight: 700; color: #fff; margin: 0; letter-spacing: -0.5px; line-height: 1.2; }
.saw-id-badge { 
    background: rgba(255,255,255,0.1); color: #fff; 
    padding: 4px 8px; border-radius: 6px; 
    font-size: 13px; font-weight: 700; letter-spacing: 1px; 
    border: 1px solid rgba(255,255,255,0.2);
}

.saw-id-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 100%; color: #94a3b8; gap: 8px; font-size: 13px; font-weight: 500; }

/* === GLOSSY LIST STYLES === */
.saw-branches-list { display: flex; flex-direction: column; gap: 10px; }

.saw-branch-glossy-row {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 20px; background: #ffffff;
    border: 1px solid #e2e8f0; border-radius: 10px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.saw-branch-glossy-row:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.08);
    border-color: #cbd5e1;
    background: linear-gradient(to bottom, #ffffff, #f8fafc);
}

.saw-branch-glossy-row.is-active { border-left: 4px solid #3b82f6; background: #fcfdff; }

.saw-glossy-info { display: flex; flex-direction: column; gap: 2px; }
.saw-glossy-name { font-size: 15px; font-weight: 600; color: #1e293b; }
.saw-branch-glossy-row.is-active .saw-glossy-name { color: #2563eb; }
.saw-glossy-city { font-size: 12px; color: #94a3b8; display: flex; align-items: center; gap: 4px; }
.saw-glossy-city .dashicons { font-size: 14px; width: 14px; height: 14px; }

.saw-glossy-controls { display: flex; align-items: center; gap: 16px; }
.saw-separator { width: 1px; height: 24px; background: #e2e8f0; }

/* Compact Switch */
.saw-switch-compact { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.saw-switch-compact input { opacity: 0; width: 0; height: 0; position: absolute; }
.saw-slider-compact { position: relative; width: 36px; height: 20px; background-color: #e2e8f0; border-radius: 20px; transition: .3s; }
.saw-slider-compact:before { position: absolute; content: ""; height: 16px; width: 16px; left: 2px; bottom: 2px; background-color: white; border-radius: 50%; transition: .3s; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
.saw-switch-compact input:checked + .saw-slider-compact { background-color: #3b82f6; }
.saw-switch-compact input:checked + .saw-slider-compact:before { transform: translateX(16px); }
.saw-switch-label { font-size: 12px; font-weight: 600; color: #64748b; user-select: none; }
.saw-branch-glossy-row.is-active .saw-switch-label { color: #3b82f6; }

/* Compact Radio Pill */
.saw-radio-pill-compact {
    display: flex; align-items: center; gap: 6px; padding: 4px 10px;
    border-radius: 20px; border: 1px solid transparent;
    cursor: pointer; transition: all 0.2s; color: #94a3b8; background: transparent;
}
.saw-radio-pill-compact:hover:not(.is-selected) { background: #f1f5f9; color: #64748b; }
.saw-radio-pill-compact.is-selected { background: #fff7ed; color: #ea580c; border-color: #fdba74; }
.saw-radio-pill-compact input { display: none; }
.saw-radio-pill-compact .dashicons { font-size: 16px; width: 16px; height: 16px; }
.saw-radio-pill-compact span:last-child { font-size: 12px; font-weight: 600; }

/* Order Input */
.saw-order-label { font-size: 12px; color: #94a3b8; margin-right: 4px; }
.saw-input-micro {
    width: 48px; text-align: center; padding: 4px; border: 1px solid #e2e8f0;
    border-radius: 6px; font-size: 13px; font-weight: 600; color: #334155; background: #f8fafc; transition: all 0.2s;
}
.saw-input-micro:focus { background: #fff; border-color: #3b82f6; outline: none; }
.saw-branch-glossy-row.is-active .saw-input-micro { background: #fff; border-color: #cbd5e1; }

/* Select All */
.saw-toggle-all { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; color: #3b82f6; cursor: pointer; }
.saw-toggle-all input { accent-color: #3b82f6; }

/* Footer */
.saw-form-footer { display: flex; gap: 12px; margin-top: 40px; padding-top: 20px; border-top: 1px solid #f1f5f9; }
.saw-button-wide { flex: 1; justify-content: center; height: 48px; font-size: 16px; font-weight: 600; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2); }
.saw-button-text { background: transparent; border: none; color: #64748b; font-weight: 600; }
.saw-button-text:hover { color: #1e293b; background: #f1f5f9; }

/* Mobile */
@media (max-width: 768px) {
    .saw-language-grid { grid-template-columns: 1fr; gap: 20px; }
    .saw-language-preview-area { justify-content: center; }
    .saw-id-card { max-width: 100%; }
    .saw-branch-glossy-row { flex-direction: column; align-items: flex-start; gap: 12px; }
    .saw-glossy-controls { width: 100%; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 12px; }
    .saw-separator { display: none; }
}
</style>