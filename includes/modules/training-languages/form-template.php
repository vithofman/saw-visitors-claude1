<?php
/**
 * Training Languages Form Template
 *
 * @package    SAW_Visitors
 * @subpackage Modules/TrainingLanguages
 * @version    5.2.0 - REMOVED: display_order field, FIXED: emoji icons
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'training_languages') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// SETUP
// ============================================
$is_edit = !empty($item['id']);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Use route from config (training-languages), NOT entity (training_languages)
$route = $config['route'] ?? 'training-languages';

$languages_data = $config['languages_data'] ?? array();
$branches_to_loop = $is_edit ? ($item['branches'] ?? array()) : ($config['available_branches'] ?? array());

$nonce_action = $is_edit ? 'saw_edit_training_languages' : 'saw_create_training_languages';

$form_action = $is_edit 
    ? home_url('/admin/' . $route . '/' . $item['id'] . '/edit')
    : home_url('/admin/' . $route . '/create');
?>

<?php if (!$in_sidebar): ?>
<div class="sa-page-header">
    <div class="sa-page-header-content">
        <h1 class="sa-page-title">
            <?php echo $is_edit 
                ? esc_html($tr('label_edit', 'Upravit jazyk školení')) 
                : esc_html($tr('label_create', 'Nový jazyk školení')); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/' . $route . '/')); ?>" class="sa-btn sa-btn--ghost">
            <?php if (class_exists('SAW_Icons')): ?>
                <?php echo SAW_Icons::get('chevron-left'); ?>
            <?php else: ?>
                <span class="sa-back-arrow">←</span>
            <?php endif; ?>
            <?php echo esc_html($tr('btn_back', 'Zpět na seznam')); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="sa-form-container sa-module-training-languages">
    <form method="post" action="<?php echo esc_url($form_action); ?>" class="sa-form" data-no-autosave="true">
        <?php wp_nonce_field($nonce_action); ?>
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
            <input type="hidden" name="_ajax_sidebar_submit" value="1">
        <?php endif; ?>
        
        <!-- ============================================
             SECTION: Language Selection
             ============================================ -->
        <details class="sa-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('globe', 'sa-form-section-icon'); ?>
                <?php else: ?>
                    <span class="sa-section-emoji">🌐</span>
                <?php endif; ?>
                <strong class="sa-form-section-title"><?php echo esc_html($tr('section_basic', 'Základní údaje')); ?></strong>
            </summary>
            <div class="sa-form-section-content">
                
                <div class="sa-form-row">
                    <!-- Language Select -->
                    <div class="sa-form-group sa-col-8">
                        <label for="language_code" class="sa-form-label sa-form-label--required">
                            <?php echo esc_html($tr('form_select_language', 'Vyberte jazyk ze seznamu')); ?>
                        </label>
                        <select id="language_code" 
                                name="language_code" 
                                class="sa-select"
                                required 
                                <?php echo $is_edit ? 'disabled' : ''; ?>>
                            <option value="">-- <?php echo esc_html($tr('form_choose_language', 'Zvolte jazyk')); ?> --</option>
                            <?php foreach ($languages_data as $code => $lang_item): ?>
                                <option value="<?php echo esc_attr($code); ?>" 
                                        data-name="<?php echo esc_attr($lang_item['name_cs']); ?>" 
                                        data-flag="<?php echo esc_attr($lang_item['flag']); ?>"
                                        <?php selected(!empty($item['language_code']) ? $item['language_code'] : '', $code); ?>>
                                    <?php echo esc_html($lang_item['flag'] . ' ' . $lang_item['name_cs']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="language_code" value="<?php echo esc_attr($item['language_code']); ?>">
                        <?php endif; ?>
                        
                        <!-- Hidden fields for name and flag -->
                        <input type="hidden" id="language_name" name="language_name" value="<?php echo esc_attr($item['language_name'] ?? ''); ?>">
                        <input type="hidden" id="flag_emoji" name="flag_emoji" value="<?php echo esc_attr($item['flag_emoji'] ?? ''); ?>">
                    </div>
                    
                    <!-- Preview Card -->
                    <div class="sa-form-group sa-col-4">
                        <label class="sa-form-label"><?php echo esc_html($tr('form_preview', 'Náhled')); ?></label>
                        <div class="sa-language-preview" id="language-preview">
                            <?php if (!empty($item['flag_emoji'])): ?>
                                <div class="sa-preview-flag"><?php echo esc_html($item['flag_emoji']); ?></div>
                                <div class="sa-preview-name"><?php echo esc_html($item['language_name']); ?></div>
                                <div class="sa-preview-code"><?php echo esc_html(strtoupper($item['language_code'])); ?></div>
                            <?php else: ?>
                                <div class="sa-preview-empty">
                                    <span class="sa-preview-empty-icon">🏳️</span>
                                    <span><?php echo esc_html($tr('form_select_to_preview', 'Vyberte jazyk')); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ============================================
             SECTION: Branch Activation
             ============================================ -->
        <details class="sa-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('building-2', 'sa-form-section-icon'); ?>
                <?php else: ?>
                    <span class="sa-section-emoji">🏢</span>
                <?php endif; ?>
                <strong class="sa-form-section-title"><?php echo esc_html($tr('section_branches', 'Aktivace pro pobočky')); ?></strong>
                <span class="sa-section-badge" id="branches-count">0</span>
            </summary>
            <div class="sa-form-section-content">
                
                <!-- Select All -->
                <div class="sa-form-row sa-form-row-actions">
                    <label class="sa-checkbox-inline">
                        <input type="checkbox" id="select-all-branches">
                        <span><?php echo esc_html($tr('form_select_all', 'Vybrat vše')); ?></span>
                    </label>
                </div>
                
                <?php if (empty($branches_to_loop)): ?>
                    <div class="sa-alert sa-alert--warning">
                        <span class="sa-alert-icon">⚠️</span>
                        <p><?php echo esc_html($tr('form_no_branches', 'Pro tohoto zákazníka nejsou k dispozici žádné aktivní pobočky.')); ?></p>
                    </div>
                <?php else: ?>
                    <div class="sa-branches-grid">
                        <?php foreach ($branches_to_loop as $branch): ?>
                            <?php 
                            $branch_id = $branch['id'];
                            $is_active = !empty($branch['is_active']) ? 1 : 0;
                            $is_default = !empty($branch['is_default']) ? 1 : 0;
                            ?>
                            <div class="sa-branch-row <?php echo $is_active ? 'is-active' : ''; ?>" data-branch-id="<?php echo esc_attr($branch_id); ?>">
                                
                                <div class="sa-branch-info">
                                    <span class="sa-branch-name"><?php echo esc_html($branch['name']); ?></span>
                                    <?php if (!empty($branch['city'])): ?>
                                        <span class="sa-branch-city"><?php echo esc_html($branch['city']); ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="sa-branch-controls">
                                    
                                    <!-- Hidden input for unchecked state -->
                                    <input type="hidden" 
                                           name="branches[<?php echo esc_attr($branch_id); ?>][active]" 
                                           value="0"
                                           class="sa-branch-active-hidden">
                                    
                                    <!-- Active toggle -->
                                    <label class="sa-toggle" title="<?php echo esc_attr($tr('form_active', 'Aktivní')); ?>">
                                        <input type="checkbox" 
                                               name="branches[<?php echo esc_attr($branch_id); ?>][active]" 
                                               value="1" 
                                               class="sa-branch-active-checkbox"
                                               <?php checked($is_active, 1); ?>>
                                        <span class="sa-toggle-slider"></span>
                                        <span class="sa-toggle-label"><?php echo esc_html($tr('form_active', 'Aktivní')); ?></span>
                                    </label>
                                    
                                    <!-- Default radio -->
                                    <label class="sa-radio-button <?php echo $is_default ? 'is-selected' : ''; ?>" title="<?php echo esc_attr($tr('form_set_default', 'Nastavit jako výchozí')); ?>">
                                        <input type="checkbox" 
                                               class="sa-branch-default-checkbox"
                                               data-branch-id="<?php echo esc_attr($branch_id); ?>"
                                               <?php checked($is_default, 1); ?>
                                               <?php disabled(!$is_active, true); ?>>
                                        <span class="sa-default-star">⭐</span>
                                        <span><?php echo esc_html($tr('form_default', 'Výchozí')); ?></span>
                                    </label>
                                    <input type="hidden" 
                                           name="branches[<?php echo esc_attr($branch_id); ?>][is_default]" 
                                           value="<?php echo $is_default ? '1' : '0'; ?>" 
                                           class="sa-branch-default-hidden">
                                    
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
            </div>
        </details>
        
        <!-- ============================================
             FORM ACTIONS
             ============================================ -->
        <?php 
        // Form actions - only show outside sidebar (sidebar uses FAB save button)
        $in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];
        if (!$in_sidebar): 
        ?>
        <div class="sa-form-actions">
            <button type="submit" class="sa-btn sa-btn--primary">
                <?php echo $is_edit 
                    ? esc_html($tr('btn_save_changes', 'Uložit změny')) 
                    : esc_html($tr('btn_create', 'Vytvořit jazyk')); ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/' . $route . '/')); ?>" class="sa-btn sa-btn--secondary">
                <?php echo esc_html($tr('btn_cancel', 'Zrušit')); ?>
            </a>
        </div>
        <?php endif; ?>
        
    </form>
</div>

<!-- ============================================
     FORM STYLES
     ============================================ -->
<style>
/* Form container - support both sa-* and saw-* classes */
.saw-sidebar .saw-form-container.saw-module-training-languages,
.sa-sidebar .sa-form-container.sa-module-training-languages {
    margin: 0;
    padding: 0;
}

.saw-sidebar .saw-form-container.saw-module-training-languages .saw-form-section-content,
.sa-sidebar .sa-form-container.sa-module-training-languages .sa-form-section-content {
    padding: 16px 20px;
}

@media (min-width: 768px) {
    .saw-sidebar .saw-form-container.saw-module-training-languages .saw-form-section-content,
    .sa-sidebar .sa-form-container.sa-module-training-languages .sa-form-section-content {
        padding: 20px 24px;
    }
}

/* Language Preview Card */
.saw-language-preview,
.sa-language-preview {
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border: 2px solid #cbd5e1;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.saw-preview-flag,
.sa-preview-flag {
    font-size: 48px;
    line-height: 1;
}

.saw-preview-name,
.sa-preview-name {
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}

.saw-preview-code,
.sa-preview-code {
    display: inline-block;
    padding: 4px 12px;
    background: #0f172a;
    color: #ffffff;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 700;
    font-family: monospace;
    letter-spacing: 1px;
}

.saw-preview-empty,
.sa-preview-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    color: #94a3b8;
}

.saw-preview-empty-icon,
.sa-preview-empty-icon {
    font-size: 32px;
    line-height: 1;
}

/* Section Icons */
.saw-section-emoji,
.sa-section-emoji {
    font-size: 18px;
    line-height: 1;
    margin-right: 4px;
}

/* Default Star */
.saw-default-star,
.sa-default-star {
    font-size: 14px;
    line-height: 1;
}

/* Branches Grid */
.saw-branches-grid,
.sa-branches-grid {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.saw-branch-row,
.sa-branch-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.saw-branch-row:hover,
.sa-branch-row:hover {
    border-color: #cbd5e1;
    background: #ffffff;
}

.saw-branch-row.is-active,
.sa-branch-row.is-active {
    background: #f0fdf4;
    border-color: #86efac;
}

.saw-branch-info,
.sa-branch-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    flex: 1;
    min-width: 0;
}

.saw-branch-name,
.sa-branch-name {
    font-weight: 600;
    color: #0f172a;
    font-size: 14px;
}

.saw-branch-city,
.sa-branch-city {
    font-size: 12px;
    color: #64748b;
}

.saw-branch-controls,
.sa-branch-controls {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
}

/* Toggle Switch */
.saw-toggle,
.sa-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.saw-toggle input,
.sa-toggle input {
    display: none;
}

.saw-toggle-slider,
.sa-toggle-slider {
    width: 36px;
    height: 20px;
    background: #cbd5e1;
    border-radius: 10px;
    position: relative;
    transition: background 0.2s;
}

.saw-toggle-slider::after,
.sa-toggle-slider::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 16px;
    height: 16px;
    background: white;
    border-radius: 50%;
    transition: transform 0.2s;
}

.saw-toggle input:checked + .saw-toggle-slider,
.sa-toggle input:checked + .sa-toggle-slider {
    background: #22c55e;
}

.saw-toggle input:checked + .saw-toggle-slider::after,
.sa-toggle input:checked + .sa-toggle-slider::after {
    transform: translateX(16px);
}

.saw-toggle-label,
.sa-toggle-label {
    font-size: 13px;
    color: #64748b;
}

/* Radio Button Style */
.saw-radio-button,
.sa-radio-button {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13px;
    color: #64748b;
    background: #ffffff;
    transition: all 0.2s;
}

.saw-radio-button input,
.sa-radio-button input {
    display: none;
}

.saw-radio-button .saw-default-star,
.sa-radio-button .sa-default-star {
    opacity: 0.3;
    transition: opacity 0.2s;
}

.saw-radio-button.is-selected,
.saw-radio-button:has(input:checked),
.sa-radio-button.is-selected,
.sa-radio-button:has(input:checked) {
    background: #fef3c7;
    border-color: #f59e0b;
    color: #92400e;
}

.saw-radio-button.is-selected .saw-default-star,
.saw-radio-button:has(input:checked) .saw-default-star,
.sa-radio-button.is-selected .sa-default-star,
.sa-radio-button:has(input:checked) .sa-default-star {
    opacity: 1;
}

/* Actions Row */
.saw-form-row-actions,
.sa-form-row-actions {
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e2e8f0;
}

.saw-checkbox-inline,
.sa-checkbox-inline {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
    color: #475569;
}

.saw-checkbox-inline input[type="checkbox"],
.sa-checkbox-inline input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

/* Section Badge */
.saw-section-badge,
.sa-section-badge {
    margin-left: auto;
    padding: 2px 10px;
    background: #3b82f6;
    color: white;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}
</style>

<!-- ============================================
     FORM JAVASCRIPT
     ============================================ -->
<script>
(function($) {
    'use strict';
    
    $(document).ready(function() {
        initLanguageForm();
    });
    
    // Also init on AJAX sidebar load
    $(document).on('saw:page-loaded saw:sidebar-loaded', function() {
        setTimeout(initLanguageForm, 100);
    });
    
    function initLanguageForm() {
        var $form = $('.sa-module-training-languages .sa-form');
        if (!$form.length) {
            // Fallback for legacy class names
            $form = $('.saw-module-training-languages .saw-form');
            if (!$form.length) return;
        }
        
        var $select = $form.find('#language_code');
        var $preview = $form.find('#language-preview');
        var $nameInput = $form.find('#language_name');
        var $flagInput = $form.find('#flag_emoji');
        var $selectAll = $form.find('#select-all-branches');
        var $branchesCount = $form.find('#branches-count');
        
        // Language select change
        $select.off('change.lang').on('change.lang', function() {
            var $selected = $(this).find('option:selected');
            var name = $selected.data('name') || '';
            var flag = $selected.data('flag') || '';
            var code = $(this).val() || '';
            
            $nameInput.val(name);
            $flagInput.val(flag);
            
            if (flag && name && code) {
                $preview.html(
                    '<div class="sa-preview-flag">' + flag + '</div>' +
                    '<div class="sa-preview-name">' + name + '</div>' +
                    '<div class="sa-preview-code">' + code.toUpperCase() + '</div>'
                );
            } else {
                $preview.html(
                    '<div class="sa-preview-empty">' +
                    '<span class="sa-preview-empty-icon">🏳️</span>' +
                    '<span><?php echo esc_js($tr('form_select_to_preview', 'Vyberte jazyk')); ?></span>' +
                    '</div>'
                );
            }
        });
        
        // Select all branches
        $selectAll.off('change.all').on('change.all', function() {
            var checked = $(this).is(':checked');
            $form.find('.sa-branch-active-checkbox').each(function() {
                $(this).prop('checked', checked).trigger('change');
            });
        });
        
        // Branch active toggle
        $form.find('.sa-branch-active-checkbox').off('change.active').on('change.active', function() {
            var $row = $(this).closest('.sa-branch-row');
            var isActive = $(this).is(':checked');
            var $checkbox = $(this);
            
            $row.toggleClass('is-active', isActive);
            
            // Enable/disable controls
            $row.find('.sa-branch-default-checkbox, .sa-input-mini').prop('disabled', !isActive);
            
            // If deactivated, also uncheck default
            if (!isActive) {
                $row.find('.sa-branch-default-checkbox').prop('checked', false).trigger('change');
            }
            
            updateBranchesCount();
        });
        
        // Default checkbox (only one can be default)
        $form.find('.sa-branch-default-checkbox').off('change.default').on('change.default', function() {
            var $this = $(this);
            var branchId = $this.data('branch-id');
            var $row = $this.closest('.sa-branch-row');
            var $hidden = $row.find('.sa-branch-default-hidden');
            var $label = $this.closest('.sa-radio-button');
            
            if ($this.is(':checked')) {
                // Uncheck all others
                $form.find('.sa-branch-default-checkbox').not(this).each(function() {
                    $(this).prop('checked', false);
                    $(this).closest('.sa-branch-row').find('.sa-branch-default-hidden').val('0');
                    $(this).closest('.sa-radio-button').removeClass('is-selected');
                });
                $hidden.val('1');
                $label.addClass('is-selected');
            } else {
                $hidden.val('0');
                $label.removeClass('is-selected');
            }
        });
        
        function updateBranchesCount() {
            var count = $form.find('.sa-branch-active-checkbox:checked').length;
            $branchesCount.text(count);
        }
        
        // Initial count
        updateBranchesCount();
        
        // Handle sidebar form submission via AJAX
        $form.off('submit.sidebar').on('submit.sidebar', function(e) {
            // Only handle if in sidebar and has _ajax_sidebar_submit
            var $sidebar = $form.closest('.saw-sidebar');
            if (!$sidebar.length || !$form.find('input[name="_ajax_sidebar_submit"]').length) {
                return; // Let default submit happen
            }
            
            e.preventDefault();
            
            // Remove hidden inputs for checked checkboxes (so checkbox value takes precedence)
            $form.find('.sa-branch-active-checkbox:checked').each(function() {
                var branchId = $(this).closest('.sa-branch-row').data('branch-id');
                $form.find('input[name="branches[' + branchId + '][active]"][type="hidden"]').remove();
            });
            
            var formData = $form.serialize();
            
            // Get entity from sidebar (has underscores, e.g. 'training_languages')
            var entity = $sidebar.data('entity') || 'training_languages';
            // Convert underscores to dashes to get slug (AJAX actions use slug with dashes)
            var slug = entity.replace(/_/g, '-'); // training_languages -> training-languages
            
            // Use AJAX endpoint instead of form action URL
            var ajaxUrl = typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php';
            var action = 'saw_edit_' + slug; // e.g., 'saw_edit_training-languages'
            
            // Add action and nonce to form data
            formData += '&action=' + encodeURIComponent(action);
            if (typeof sawGlobal !== 'undefined' && sawGlobal.nonce) {
                formData += '&nonce=' + encodeURIComponent(sawGlobal.nonce);
            }
            
            // Show loading
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<span class="spinner is-active"></span> Ukládám...');
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        var entityId = response.data.id;
                        
                        // Get entity from sidebar
                        var entity = $sidebar.data('entity') || 'training_languages';
                        // Convert underscores to dashes for URL (route)
                        var route = entity.replace(/_/g, '-');
                        
                        // Build detail URL: /admin/{route}/{id}/
                        var detailUrl = window.location.origin + '/admin/' + route + '/' + entityId + '/';
                        
                        console.log('[Training Languages] Navigating to detail:', detailUrl);
                        
                        // Navigate to detail using viewTransition or fallback
                        if (window.viewTransition && window.viewTransition.navigateTo) {
                            window.viewTransition.navigateTo(detailUrl);
                        } else {
                            window.location.href = detailUrl;
                        }
                    } else {
                        alert(response.data?.message || 'Chyba při ukládání');
                        $submitBtn.prop('disabled', false).html(originalText);
                    }
                },
                error: function(xhr, status, error) {
                    // If server returns JSON error
                    if (xhr.responseJSON) {
                        alert(xhr.responseJSON.data?.message || 'Chyba při ukládání');
                        $submitBtn.prop('disabled', false).html(originalText);
                    } else {
                        // Server returned HTML (probably redirect) - reload
                        alert('Chyba při ukládání: ' + error);
                        $submitBtn.prop('disabled', false).html(originalText);
                    }
                }
            });
        });
    }
    
})(jQuery);
</script>