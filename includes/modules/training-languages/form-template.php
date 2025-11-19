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
