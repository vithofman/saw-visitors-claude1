<?php
/**
 * Training Languages Form Template - FIXED
 *
 * @package SAW_Visitors
 * @version 3.8.0 - FIXED: Added hidden input for branches[active]
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
                        <div class="saw-lang-id-card" id="flag-preview">
                            <?php if (!empty($item['flag_emoji'])): ?>
                                <span class="saw-flag-emoji"><?php echo esc_html($item['flag_emoji']); ?></span>
                                <span class="saw-flag-name"><?php echo esc_html($item['language_name']); ?></span>
                                <span class="saw-flag-code"><?php echo esc_html(strtoupper($item['language_code'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
    
    <div class="saw-section">
        <div class="saw-section-head">
            <h3><span class="dashicons dashicons-building"></span> Aktivace pro pobočky</h3>
            <label class="saw-select-all-compact">
                <input type="checkbox" id="select-all-branches">
                <span>Vybrat vše</span>
            </label>
        </div>
        
        <div class="saw-branches-glossy-grid">
            <?php if (empty($branches_to_loop)): ?>
                <div class="saw-notice saw-notice-warning">
                    <p>Pro tohoto zákazníka nejsou k dispozici žádné aktivní pobočky.</p>
                </div>
            <?php else: ?>
                <?php foreach ($branches_to_loop as $branch): ?>
                    <?php 
                    $is_active = !empty($branch['is_active']) ? 1 : 0;
                    $is_default = !empty($branch['is_default']) ? 1 : 0;
                    $display_order = isset($branch['display_order']) ? intval($branch['display_order']) : 0;
                    ?>
                    <div class="saw-branch-glossy-row <?php echo $is_active ? 'is-active' : ''; ?>">
                        
                        <div class="saw-glossy-info">
                            <div class="saw-glossy-name"><?php echo esc_html($branch['name']); ?></div>
                            <?php if(!empty($branch['city'])): ?>
                                <div class="saw-glossy-city"><span class="dashicons dashicons-location"></span> <?php echo esc_html($branch['city']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="saw-glossy-controls">
                            
                            <!-- 🔥 CRITICAL FIX: Hidden input to always send value -->
                            <input type="hidden" 
                                   name="branches[<?php echo esc_attr($branch['id']); ?>][active]" 
                                   value="0"
                                   class="saw-branch-active-hidden"
                                   data-branch-id="<?php echo esc_attr($branch['id']); ?>">
                            
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

                            <div class="saw-separator"></div>

                            <div class="saw-control-item">
                                <label class="saw-order-label" title="Pořadí zobrazení">
                                    <span class="dashicons dashicons-sort"></span>
                                    <input type="number" 
                                           name="branches[<?php echo esc_attr($branch['id']); ?>][display_order]" 
                                           value="<?php echo esc_attr($display_order); ?>" 
                                           class="saw-order-input-compact"
                                           min="0"
                                           step="1"
                                           <?php disabled(!$is_active, true); ?>>
                                </label>
                            </div>
                            
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="saw-form-actions">
        <button type="submit" class="saw-btn saw-btn-primary">
            <span class="dashicons dashicons-saved"></span>
            <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit jazyk'; ?>
        </button>
    </div>
    
</form>

<script>
(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        $('#language_code').on('change', function() {
            const $selected = $(this).find('option:selected');
            const code = $selected.val();
            const name = $selected.data('name');
            const flag = $selected.data('flag');
            
            if (code && name && flag) {
                $('#language_name').val(name);
                $('#flag_emoji').val(flag);
                
                const html = `
                    <span class="saw-flag-emoji">${flag}</span>
                    <span class="saw-flag-name">${name}</span>
                    <span class="saw-flag-code">${code.toUpperCase()}</span>
                `;
                $('#flag-preview').html(html);
            } else {
                $('#flag-preview').empty();
            }
        });
        
        if ($('#language_code').val()) {
            const $selected = $('#language_code').find('option:selected');
            const code = $selected.val();
            const name = $('#language_name').val() || $selected.data('name');
            const flag = $('#flag_emoji').val() || $selected.data('flag');
            
            if (code && name && flag) {
                const html = `
                    <span class="saw-flag-emoji">${flag}</span>
                    <span class="saw-flag-name">${name}</span>
                    <span class="saw-flag-code">${code.toUpperCase()}</span>
                `;
                $('#flag-preview').html(html);
            }
        }
        
        $('.saw-branch-active-checkbox').on('change', function() {
            const branchId = $(this).data('branch-id');
            const isActive = $(this).is(':checked');
            
            // Update hidden input
            $(`.saw-branch-active-hidden[data-branch-id="${branchId}"]`).val(isActive ? '1' : '0');
            
            const $row = $(this).closest('.saw-branch-glossy-row');
            const $defaultRadio = $(`.saw-branch-default-radio[data-branch-id="${branchId}"]`);
            const $orderInput = $(`input[name="branches[${branchId}][display_order]"]`);
            
            if (isActive) {
                $row.addClass('is-active');
                $defaultRadio.prop('disabled', false);
                $orderInput.prop('disabled', false);
            } else {
                $row.removeClass('is-active');
                $defaultRadio.prop('checked', false).prop('disabled', true);
                $(`.saw-branch-default-hidden[data-branch-id="${branchId}"]`).val('0');
                $(`.saw-radio-pill-compact[data-branch-id="${branchId}"]`).removeClass('is-selected');
                $orderInput.val(0).prop('disabled', true);
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
.saw-lang-id-card { width: 100%; height: 200px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: 3px solid #667eea; border-radius: 16px; padding: 24px; transition: all 0.3s ease; box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3); }
.saw-lang-id-card:empty { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-color: #e2e8f0; box-shadow: none; }
.saw-lang-id-card:empty::before { content: '🏳️'; font-size: 64px; opacity: 0.3; }
.saw-flag-emoji { font-size: 72px; line-height: 1; text-shadow: 0 2px 8px rgba(0, 0, 0, 0.15); }
.saw-flag-name { font-size: 20px; font-weight: 700; color: white; text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); text-align: center; }
.saw-flag-code { font-size: 14px; font-weight: 600; color: rgba(255, 255, 255, 0.85); text-transform: uppercase; letter-spacing: 2px; padding: 4px 12px; background: rgba(0, 0, 0, 0.15); border-radius: 6px; backdrop-filter: blur(10px); }

/* === SELECT ALL === */
.saw-select-all-compact { display: flex; align-items: center; gap: 8px; padding: 6px 12px; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 2px solid #bfdbfe; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; color: #1e40af; transition: all 0.2s; }
.saw-select-all-compact:hover { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); }
.saw-select-all-compact input { width: 16px; height: 16px; cursor: pointer; accent-color: #2563eb; }

/* === BRANCHES GRID === */
.saw-branches-glossy-grid { display: flex; flex-direction: column; gap: 12px; }
.saw-branch-glossy-row { display: flex; justify-content: space-between; align-items: center; padding: 18px 20px; background: #ffffff; border: 2px solid #e2e8f0; border-radius: 12px; transition: all 0.2s; }
.saw-branch-glossy-row:hover { border-color: #cbd5e1; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06); }
.saw-branch-glossy-row.is-active { border-color: #93c5fd; background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%); }

.saw-glossy-info { display: flex; flex-direction: column; gap: 4px; }
.saw-glossy-name { font-size: 15px; font-weight: 600; color: #0f172a; }
.saw-glossy-city { font-size: 13px; color: #64748b; display: flex; align-items: center; gap: 4px; }

.saw-glossy-controls { display: flex; gap: 20px; align-items: center; }
.saw-control-item { display: flex; align-items: center; }
.saw-separator { width: 1px; height: 24px; background: #e2e8f0; }

/* === SWITCH === */
.saw-switch-compact { position: relative; display: flex; align-items: center; gap: 8px; cursor: pointer; user-select: none; }
.saw-switch-compact input { opacity: 0; width: 0; height: 0; position: absolute; }
.saw-slider-compact { position: relative; width: 44px; height: 24px; background: #cbd5e1; border-radius: 24px; transition: 0.3s; }
.saw-slider-compact:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: 0.3s; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); }
.saw-switch-compact input:checked + .saw-slider-compact { background: #2563eb; }
.saw-switch-compact input:checked + .saw-slider-compact:before { transform: translateX(20px); }
.saw-switch-label { font-size: 13px; font-weight: 600; color: #475569; }

/* === RADIO PILL === */
.saw-radio-pill-compact { display: flex; align-items: center; gap: 6px; padding: 6px 12px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; cursor: pointer; transition: all 0.2s; font-size: 13px; font-weight: 600; color: #64748b; }
.saw-radio-pill-compact:hover { background: #f1f5f9; border-color: #cbd5e1; }
.saw-radio-pill-compact.is-selected { background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-color: #fbbf24; color: #92400e; }
.saw-radio-pill-compact input { display: none; }
.saw-radio-pill-compact .dashicons { font-size: 16px; width: 16px; height: 16px; }

/* === ORDER INPUT === */
.saw-order-label { display: flex; align-items: center; gap: 6px; }
.saw-order-input-compact { width: 60px; height: 32px; text-align: center; font-weight: 600; font-size: 13px; border: 2px solid #e2e8f0; border-radius: 6px; background: white; color: #0f172a; transition: all 0.2s; }
.saw-order-input-compact:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); outline: none; }
.saw-order-input-compact:disabled { background: #f8fafc; color: #94a3b8; cursor: not-allowed; }

/* === FORM ACTIONS === */
.saw-form-actions { display: flex; gap: 12px; justify-content: flex-end; padding-top: 24px; border-top: 1px solid #e2e8f0; }
.saw-btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s; border: none; }
.saw-btn-primary { background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); color: white; box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3); }
.saw-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(37, 99, 235, 0.4); }

@media (max-width: 1024px) {
    .saw-language-grid { grid-template-columns: 1fr; }
    .saw-language-preview-area { order: -1; }
}
</style>