<?php
/**
 * Training Languages Form Template
 * 
 * @package SAW_Visitors
 * @version 2.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item);
$item = $item ?? [];

$customer_id = SAW_Context::get_customer_id();

// Load language data
$languages_data = require __DIR__ . '/languages-data.php';

// Get branches for this customer
global $wpdb;
if (!isset($branches)) {
    $branches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, code, city FROM %i WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $wpdb->prefix . 'saw_branches',
        $customer_id
    ), ARRAY_A);
}
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit jazyk' : 'Nový jazyk'; ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/training-languages/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>

<div class="saw-form-container">
    <form method="post" class="saw-language-form">
        <?php wp_nonce_field('saw_training_languages_form', 'saw_nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-translation"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-language-basic-row">
                    <div class="saw-language-select-col">
                        <label for="language_code" class="saw-label saw-required">
                            Jazyk
                        </label>
                        <select 
                            id="language_code" 
                            name="language_code" 
                            class="saw-select"
                            required
                            <?php echo $is_edit ? 'disabled' : ''; ?>
                        >
                            <option value="">Vyberte jazyk</option>
                            <?php foreach ($languages_data as $code => $lang): ?>
                                <option 
                                    value="<?php echo esc_attr($code); ?>"
                                    data-name="<?php echo esc_attr($lang['name_cs']); ?>"
                                    data-flag="<?php echo esc_attr($lang['flag']); ?>"
                                    <?php selected(!empty($item['language_code']) ? $item['language_code'] : '', $code); ?>
                                >
                                    <?php echo esc_html($lang['flag'] . ' ' . $lang['name_cs'] . ' (' . $code . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="language_code" value="<?php echo esc_attr($item['language_code']); ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="saw-language-preview-col">
                        <label class="saw-label">
                            Náhled
                        </label>
                        <div id="flag-preview" class="saw-flag-preview-large">
                            <?php if (!empty($item['flag_emoji'])): ?>
                                <span class="saw-flag-emoji"><?php echo esc_html($item['flag_emoji']); ?></span>
                                <span class="saw-flag-name"><?php echo esc_html($item['language_name']); ?></span>
                                <span class="saw-flag-code"><?php echo esc_html(strtoupper($item['language_code'])); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <input type="hidden" id="language_name" name="language_name" value="<?php echo esc_attr($item['language_name'] ?? ''); ?>">
                <input type="hidden" id="flag_emoji" name="flag_emoji" value="<?php echo esc_attr($item['flag_emoji'] ?? ''); ?>">
                
            </div>
        </details>
        
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-building"></span>
                <strong>Aktivace pro pobočky</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <?php if (empty($branches)): ?>
                    <div class="saw-notice saw-notice-warning">
                        <p>Žádné aktivní pobočky nenalezeny pro tohoto zákazníka.</p>
                    </div>
                <?php else: ?>
                    
                    <div style="margin-bottom: 16px; display: flex; align-items: center; gap: 12px;">
                        <label class="saw-select-all-wrapper">
                            <input type="checkbox" id="select-all-branches" class="saw-select-all-checkbox">
                            <span><strong>Vybrat všechny pobočky</strong></span>
                        </label>
                    </div>
                    
                    <div class="saw-branches-activation-grid">
                        <div class="saw-branches-activation-header">
                            <div class="saw-branch-col-name">Pobočka</div>
                            <div class="saw-branch-col-active">Aktivní</div>
                            <div class="saw-branch-col-default">Výchozí</div>
                            <div class="saw-branch-col-order">Pořadí</div>
                        </div>
                        
                        <?php foreach ($branches as $branch): ?>
                            <?php 
                            $is_active = false;
                            $is_default = false;
                            $display_order = 0;
                            
                            if ($is_edit && !empty($item['branches'])) {
                                foreach ($item['branches'] as $b) {
                                    if ($b['id'] == $branch['id']) {
                                        $is_active = !empty($b['is_active']);
                                        $is_default = !empty($b['is_default']);
                                        $display_order = $b['display_order'] ?? 0;
                                        break;
                                    }
                                }
                            }
                            ?>
                            <div class="saw-branch-activation-row">
                                <div class="saw-branch-col-name">
                                    <div class="saw-branch-info">
                                        <div class="saw-branch-name-line">
                                            <strong class="saw-branch-name"><?php echo esc_html($branch['name']); ?></strong>
                                            <?php if (!empty($branch['code'])): ?>
                                                <span class="saw-branch-code"><?php echo esc_html($branch['code']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($branch['city'])): ?>
                                            <div class="saw-branch-city"><?php echo esc_html($branch['city']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="saw-branch-col-active">
                                    <label class="saw-switch">
                                        <input 
                                            type="checkbox" 
                                            name="branches[<?php echo esc_attr($branch['id']); ?>][active]" 
                                            value="1"
                                            class="saw-branch-active-checkbox"
                                            data-branch-id="<?php echo esc_attr($branch['id']); ?>"
                                            <?php checked($is_active, true); ?>
                                        >
                                        <span class="saw-switch-slider"></span>
                                    </label>
                                </div>
                                
                                <div class="saw-branch-col-default">
                                    <label class="saw-radio-wrapper">
                                        <input 
                                            type="radio" 
                                            name="default_branch" 
                                            value="<?php echo esc_attr($branch['id']); ?>"
                                            class="saw-branch-default-radio"
                                            data-branch-id="<?php echo esc_attr($branch['id']); ?>"
                                            <?php checked($is_default, true); ?>
                                            <?php disabled(!$is_active, true); ?>
                                        >
                                        <input 
                                            type="hidden" 
                                            name="branches[<?php echo esc_attr($branch['id']); ?>][is_default]" 
                                            value="<?php echo $is_default ? '1' : '0'; ?>"
                                            class="saw-branch-default-hidden"
                                            data-branch-id="<?php echo esc_attr($branch['id']); ?>"
                                        >
                                        <span class="saw-radio-mark"></span>
                                    </label>
                                </div>
                                
                                <div class="saw-branch-col-order">
                                    <input 
                                        type="number" 
                                        name="branches[<?php echo esc_attr($branch['id']); ?>][display_order]" 
                                        value="<?php echo esc_attr($display_order); ?>"
                                        class="saw-order-input"
                                        min="0"
                                        step="1"
                                        <?php disabled(!$is_active, true); ?>
                                    >
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="saw-notice saw-notice-info" style="margin-top: 20px;">
                        <p><strong>💡 Tip:</strong> Aktivujte jazyk pro pobočky zapnutím přepínače. Výchozí jazyk se zobrazí jako první v seznamu.</p>
                    </div>
                    
                <?php endif; ?>
                
            </div>
        </details>
        
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit jazyk'; ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/training-languages/')); ?>" class="saw-button saw-button-secondary">
                <span class="dashicons dashicons-no-alt"></span>
                Zrušit
            </a>
        </div>
        
    </form>
</div>