<?php
if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item['id']);
$is_czech = $is_edit && $item['language_code'] === 'cs';

global $wpdb;
$customer_id = isset($_SESSION['saw_current_customer_id']) ? absint($_SESSION['saw_current_customer_id']) : 0;

$branches = $wpdb->get_results($wpdb->prepare(
    "SELECT id, name, code, city FROM {$wpdb->prefix}saw_branches 
     WHERE customer_id = %d AND is_active = 1 
     ORDER BY is_headquarters DESC, name ASC",
    $customer_id
), ARRAY_A);

$active_branches = [];
if ($is_edit) {
    $active_branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT branch_id, is_default, display_order 
         FROM {$wpdb->prefix}saw_training_language_branches 
         WHERE language_id = %d AND is_active = 1",
        $item['id']
    ), ARRAY_A);
    
    foreach ($active_branches_data as $branch_data) {
        $active_branches[$branch_data['branch_id']] = [
            'is_default' => $branch_data['is_default'],
            'display_order' => $branch_data['display_order'],
        ];
    }
}
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? '✏️ Upravit jazyk' : '➕ Nový jazyk'; ?>
        </h1>
    </div>
</div>

<div class="saw-form-container">
    
    <form method="post" class="saw-language-form">
        <?php wp_nonce_field('saw_training_languages_form', 'saw_nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <div class="saw-form-section">
            <h2 class="saw-section-title">🌐 Základní informace</h2>
            
            <div class="saw-form-row">
                
                <div class="saw-form-group saw-col-4">
                    <label for="language_code" class="saw-label">
                        Kód jazyka <span class="saw-required">*</span>
                    </label>
                    <select name="language_code" id="language_code" class="saw-input" required <?php echo $is_edit ? 'disabled' : ''; ?>>
                        <option value="">-- Vyberte kód --</option>
                        <option value="cs" <?php selected($item['language_code'] ?? '', 'cs'); ?>>cs - Čeština</option>
                        <option value="en" <?php selected($item['language_code'] ?? '', 'en'); ?>>en - English</option>
                        <option value="sk" <?php selected($item['language_code'] ?? '', 'sk'); ?>>sk - Slovenčina</option>
                        <option value="de" <?php selected($item['language_code'] ?? '', 'de'); ?>>de - Deutsch</option>
                        <option value="pl" <?php selected($item['language_code'] ?? '', 'pl'); ?>>pl - Polski</option>
                        <option value="uk" <?php selected($item['language_code'] ?? '', 'uk'); ?>>uk - Українська</option>
                        <option value="ru" <?php selected($item['language_code'] ?? '', 'ru'); ?>>ru - Русский</option>
                    </select>
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="language_code" value="<?php echo esc_attr($item['language_code']); ?>">
                    <?php endif; ?>
                    <p class="saw-help-text">ISO 639-1 kód jazyka</p>
                </div>
                
                <div class="saw-form-group saw-col-4">
                    <label for="language_name" class="saw-label">
                        Název jazyka <span class="saw-required">*</span>
                    </label>
                    <input type="text" 
                           name="language_name" 
                           id="language_name" 
                           class="saw-input" 
                           value="<?php echo esc_attr($item['language_name'] ?? ''); ?>" 
                           required
                           maxlength="50">
                    <p class="saw-help-text">Plný název (např. "Čeština")</p>
                </div>
                
                <div class="saw-form-group saw-col-4">
                    <label for="flag_emoji" class="saw-label">
                        Vlajka <span class="saw-required">*</span>
                    </label>
                    <input type="text" 
                           name="flag_emoji" 
                           id="flag_emoji" 
                           class="saw-input saw-flag-input" 
                           value="<?php echo esc_attr($item['flag_emoji'] ?? ''); ?>" 
                           required
                           maxlength="10"
                           placeholder="🇨🇿">
                    <p class="saw-help-text">Emoji vlajky</p>
                </div>
                
            </div>
        </div>
        
        <div class="saw-form-section">
            <h2 class="saw-section-title">🏢 Aktivace pro pobočky</h2>
            <p class="saw-section-description">Vyberte pobočky, pro které bude tento jazyk dostupný. Můžete nastavit výchozí jazyk a pořadí zobrazení pro každou pobočku.</p>
            
            <?php if (empty($branches)): ?>
                <div class="saw-notice saw-notice-warning">
                    <p>Nejsou k dispozici žádné pobočky. Nejprve vytvořte pobočku.</p>
                </div>
            <?php else: ?>
                
                <div class="saw-branches-grid">
                    
                    <div class="saw-branches-header">
                        <div class="saw-branch-col-name">Pobočka</div>
                        <div class="saw-branch-col-active">Aktivní</div>
                        <div class="saw-branch-col-default">Výchozí</div>
                        <div class="saw-branch-col-order">Pořadí</div>
                    </div>
                    
                    <?php foreach ($branches as $branch): ?>
                        <?php 
                        $is_active = isset($active_branches[$branch['id']]);
                        $is_default = $is_active && !empty($active_branches[$branch['id']]['is_default']);
                        $display_order = $is_active ? $active_branches[$branch['id']]['display_order'] : 0;
                        ?>
                        <div class="saw-branch-row">
                            
                            <div class="saw-branch-col-name">
                                <strong><?php echo esc_html($branch['name']); ?></strong>
                                <?php if (!empty($branch['code'])): ?>
                                    <span class="saw-branch-code"><?php echo esc_html($branch['code']); ?></span>
                                <?php endif; ?>
                                <?php if (!empty($branch['city'])): ?>
                                    <span class="saw-branch-city"><?php echo esc_html($branch['city']); ?></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="saw-branch-col-active">
                                <label class="saw-checkbox-wrapper">
                                    <input type="checkbox" 
                                           name="branches[<?php echo esc_attr($branch['id']); ?>][active]" 
                                           value="1"
                                           class="saw-branch-active-checkbox"
                                           data-branch-id="<?php echo esc_attr($branch['id']); ?>"
                                           <?php checked($is_active); ?>>
                                    <span class="saw-checkbox-label">Aktivní</span>
                                </label>
                            </div>
                            
                            <div class="saw-branch-col-default">
                                <label class="saw-checkbox-wrapper">
                                    <input type="checkbox" 
                                           name="branches[<?php echo esc_attr($branch['id']); ?>][is_default]" 
                                           value="1"
                                           class="saw-branch-default-checkbox"
                                           data-branch-id="<?php echo esc_attr($branch['id']); ?>"
                                           <?php checked($is_default); ?>
                                           <?php echo !$is_active ? 'disabled' : ''; ?>>
                                    <span class="saw-checkbox-label">Výchozí</span>
                                </label>
                            </div>
                            
                            <div class="saw-branch-col-order">
                                <input type="number" 
                                       name="branches[<?php echo esc_attr($branch['id']); ?>][display_order]" 
                                       value="<?php echo esc_attr($display_order); ?>" 
                                       min="0" 
                                       step="1"
                                       class="saw-input saw-order-input"
                                       placeholder="0"
                                       <?php echo !$is_active ? 'disabled' : ''; ?>>
                            </div>
                            
                        </div>
                    <?php endforeach; ?>
                    
                </div>
                
                <div class="saw-notice saw-notice-info" style="margin-top: 16px;">
                    <p><strong>💡 Tip:</strong> Výchozí jazyk bude automaticky předvybrán ve výběru jazyků. Pořadí určuje, v jakém pořadí se jazyky zobrazí v selectu (nižší číslo = výše).</p>
                </div>
                
            <?php endif; ?>
            
        </div>
        
        <div class="saw-form-actions">
            <a href="<?php echo home_url('/admin/training-languages/'); ?>" class="saw-button saw-button-secondary">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                Zrušit
            </a>
            <button type="submit" class="saw-button saw-button-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit jazyk'; ?>
            </button>
        </div>
        
    </form>
    
</div>

<style>
.saw-branches-grid {
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}

.saw-branches-header {
    display: grid;
    grid-template-columns: 1fr 120px 120px 100px;
    gap: 16px;
    padding: 16px 20px;
    background: #f9fafb;
    border-bottom: 2px solid #e5e7eb;
    font-weight: 700;
    font-size: 13px;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.saw-branch-row {
    display: grid;
    grid-template-columns: 1fr 120px 120px 100px;
    gap: 16px;
    padding: 16px 20px;
    border-bottom: 1px solid #f3f4f6;
    align-items: center;
    transition: background 0.2s ease;
}

.saw-branch-row:last-child {
    border-bottom: none;
}

.saw-branch-row:hover {
    background: #fafbfc;
}

.saw-branch-col-name {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.saw-branch-col-name strong {
    font-size: 15px;
    color: #111827;
}

.saw-branch-code {
    display: inline-block;
    padding: 2px 8px;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    font-family: monospace;
    margin-right: 8px;
}

.saw-branch-city {
    font-size: 13px;
    color: #6b7280;
}

.saw-branch-col-active,
.saw-branch-col-default {
    display: flex;
    align-items: center;
    justify-content: center;
}

.saw-checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.saw-checkbox-wrapper input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.saw-checkbox-wrapper input[type="checkbox"]:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.saw-checkbox-label {
    font-size: 13px;
    color: #374151;
    font-weight: 500;
}

.saw-order-input {
    width: 80px;
    text-align: center;
    font-weight: 600;
}

.saw-order-input:disabled {
    background: #f9fafb;
    opacity: 0.5;
}

.saw-flag-input {
    font-size: 24px;
    text-align: center;
}

@media (max-width: 768px) {
    .saw-branches-header,
    .saw-branch-row {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .saw-branches-header {
        display: none;
    }
    
    .saw-branch-col-active,
    .saw-branch-col-default {
        justify-content: flex-start;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    $('.saw-branch-active-checkbox').on('change', function() {
        const branchId = $(this).data('branch-id');
        const isActive = $(this).is(':checked');
        
        const $defaultCheckbox = $(`.saw-branch-default-checkbox[data-branch-id="${branchId}"]`);
        const $orderInput = $(`input[name="branches[${branchId}][display_order]"]`);
        
        if (isActive) {
            $defaultCheckbox.prop('disabled', false);
            $orderInput.prop('disabled', false);
        } else {
            $defaultCheckbox.prop('checked', false).prop('disabled', true);
            $orderInput.val(0).prop('disabled', true);
        }
    });
    
    $('.saw-branch-default-checkbox').on('change', function() {
        if ($(this).is(':checked')) {
            const currentBranchId = $(this).data('branch-id');
            
            $('.saw-branch-default-checkbox').not(this).each(function() {
                $(this).prop('checked', false);
            });
        }
    });
    
    $('#language_code').on('change', function() {
        const code = $(this).val();
        const languageNames = {
            'cs': 'Čeština',
            'en': 'English',
            'sk': 'Slovenčina',
            'de': 'Deutsch',
            'pl': 'Polski',
            'uk': 'Українська',
            'ru': 'Русский'
        };
        const flags = {
            'cs': '🇨🇿',
            'en': '🇬🇧',
            'sk': '🇸🇰',
            'de': '🇩🇪',
            'pl': '🇵🇱',
            'uk': '🇺🇦',
            'ru': '🇷🇺'
        };
        
        if (languageNames[code] && !$('#language_name').val()) {
            $('#language_name').val(languageNames[code]);
        }
        
        if (flags[code] && !$('#flag_emoji').val()) {
            $('#flag_emoji').val(flags[code]);
        }
    });
    
    $('.saw-language-form').on('submit', function(e) {
        const name = $('#language_name').val().trim();
        const code = $('#language_code').val();
        const flag = $('#flag_emoji').val().trim();
        
        if (!name || !code || !flag) {
            e.preventDefault();
            alert('Vyplňte všechna povinná pole!');
            return false;
        }
        
        return true;
    });
    
});
</script>