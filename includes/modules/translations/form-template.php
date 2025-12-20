<?php
/**
 * Translations Form Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Translations
 * @version     1.0.0
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
    ? saw_get_translations($lang, 'admin', 'translations') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// VARIABLES SETUP
// ============================================
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];
$is_edit = !empty($item);
$item = $item ?? array();

$form_action = $is_edit 
    ? home_url('/admin/translations/' . $item['id'] . '/edit')
    : home_url('/admin/translations/create');
?>

<?php if (!$in_sidebar): ?>
<div class="sa-page-header">
    <div class="sa-page-header-content">
        <h1 class="sa-page-title">
            <?php echo $is_edit ? esc_html($tr('form_title_edit', 'Upravit překlad')) : esc_html($tr('form_title_create', 'Nový překlad')); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/translations/')); ?>" class="sa-back-button">
            <?php if (class_exists('SAW_Icons')): ?>
                <?php echo SAW_Icons::get('chevron-left'); ?>
            <?php else: ?>
                <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php endif; ?>
            <?php echo esc_html($tr('btn_back_to_list', 'Zpět na seznam')); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="sa-form-container sa-module-translations">
    <form method="POST" action="<?php echo esc_url($form_action); ?>" class="sa-form sa-translation-form">
        <?php 
        $nonce_action = $is_edit ? 'saw_edit_translations' : 'saw_create_translations';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- BASIC INFORMATION -->
        <details class="sa-form-section" open>
            <summary style="display: flex; align-items: center; gap: 10px;">
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('globe', 'sa-section-icon'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-translation" style="display: flex !important; align-items: center !important; justify-content: center !important; line-height: 1 !important;"></span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('form_section_basic', 'Základní informace')); ?></strong>
            </summary>
            <div class="sa-form-section-content">
                
                <div class="sa-form-row">
                    <div class="sa-form-group sa-col-6">
                        <label for="translation_key" class="sa-form-label sa-required"><?php echo esc_html($tr('form_translation_key', 'Klíč překladu')); ?></label>
                        <input type="text" 
                               id="translation_key" 
                               name="translation_key" 
                               class="sa-input" 
                               value="<?php echo esc_attr($item['translation_key'] ?? ''); ?>" 
                               maxlength="100"
                               required
                               placeholder="<?php echo esc_attr($tr('form_translation_key_placeholder', 'např. button_save')); ?>">
                        <small class="sa-form-help"><?php echo esc_html($tr('form_translation_key_help', 'Unikátní identifikátor překladu')); ?></small>
                    </div>
                    
                    <div class="sa-form-group sa-col-6">
                        <label for="language_code" class="sa-form-label sa-required"><?php echo esc_html($tr('form_language_code', 'Kód jazyka')); ?></label>
                        <select id="language_code" name="language_code" class="sa-input" required>
                            <option value="">-- <?php echo esc_html($tr('form_select_language', 'Vyberte jazyk')); ?> --</option>
                            <option value="cs" <?php selected($item['language_code'] ?? '', 'cs'); ?>>🇨🇿 Čeština</option>
                            <option value="en" <?php selected($item['language_code'] ?? '', 'en'); ?>>🇬🇧 English</option>
                            <option value="de" <?php selected($item['language_code'] ?? '', 'de'); ?>>🇩🇪 Deutsch</option>
                            <option value="sk" <?php selected($item['language_code'] ?? '', 'sk'); ?>>🇸🇰 Slovenčina</option>
                        </select>
                    </div>
                </div>
                
                <div class="sa-form-row">
                    <div class="sa-form-group sa-col-6">
                        <label for="context" class="sa-form-label sa-required"><?php echo esc_html($tr('form_context', 'Kontext')); ?></label>
                        <select id="context" name="context" class="sa-input" required>
                            <option value="">-- <?php echo esc_html($tr('form_select_context', 'Vyberte kontext')); ?> --</option>
                            <option value="terminal" <?php selected($item['context'] ?? '', 'terminal'); ?>>🖥️ Terminal</option>
                            <option value="invitation" <?php selected($item['context'] ?? '', 'invitation'); ?>>📧 Pozvánka</option>
                            <option value="admin" <?php selected($item['context'] ?? '', 'admin'); ?>>⚙️ Admin</option>
                            <option value="common" <?php selected($item['context'] ?? '', 'common'); ?>>🌐 Společné</option>
                        </select>
                    </div>
                    
                    <div class="sa-form-group sa-col-6">
                        <label for="section" class="sa-form-label"><?php echo esc_html($tr('form_section', 'Sekce')); ?></label>
                        <input type="text" 
                               id="section" 
                               name="section" 
                               class="sa-input" 
                               value="<?php echo esc_attr($item['section'] ?? ''); ?>" 
                               maxlength="50"
                               placeholder="<?php echo esc_attr($tr('form_section_placeholder', 'např. video, risks')); ?>">
                        <small class="sa-form-help"><?php echo esc_html($tr('form_section_help', 'Volitelné - pro seskupení překladů')); ?></small>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- TRANSLATION TEXT -->
        <details class="sa-form-section" open>
            <summary style="display: flex; align-items: center; gap: 10px;">
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('pencil', 'sa-section-icon'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-edit" style="display: flex !important; align-items: center !important; justify-content: center !important; line-height: 1 !important;"></span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('form_section_text', 'Text překladu')); ?></strong>
            </summary>
            <div class="sa-form-section-content">
                
                <div class="sa-form-row">
                    <div class="sa-form-group sa-col-12">
                        <label for="translation_text" class="sa-form-label sa-required"><?php echo esc_html($tr('form_translation_text', 'Text překladu')); ?></label>
                        <textarea id="translation_text" 
                                  name="translation_text" 
                                  class="sa-input" 
                                  rows="5"
                                  required><?php echo esc_textarea($item['translation_text'] ?? ''); ?></textarea>
                        <small class="sa-form-help"><?php echo esc_html($tr('form_translation_text_help', 'Hlavní text překladu')); ?></small>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ADDITIONAL INFORMATION -->
        <details class="sa-form-section">
            <summary style="display: flex; align-items: center; gap: 10px;">
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('info', 'sa-section-icon'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-info" style="display: flex !important; align-items: center !important; justify-content: center !important; line-height: 1 !important;"></span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('form_section_additional', 'Další informace')); ?></strong>
            </summary>
            <div class="sa-form-section-content">
                
                <div class="sa-form-row">
                    <div class="sa-form-group sa-col-12">
                        <label for="description" class="sa-form-label"><?php echo esc_html($tr('form_description', 'Popis')); ?></label>
                        <input type="text" 
                               id="description" 
                               name="description" 
                               class="sa-input" 
                               value="<?php echo esc_attr($item['description'] ?? ''); ?>" 
                               maxlength="255"
                               placeholder="<?php echo esc_attr($tr('form_description_placeholder', 'Popis pro admina')); ?>">
                        <small class="sa-form-help"><?php echo esc_html($tr('form_description_help', 'Volitelný popis pro lepší orientaci')); ?></small>
                    </div>
                </div>
                
                <div class="sa-form-row">
                    <div class="sa-form-group sa-col-12">
                        <label for="placeholders" class="sa-form-label"><?php echo esc_html($tr('form_placeholders', 'Placeholdery')); ?></label>
                        <input type="text" 
                               id="placeholders" 
                               name="placeholders" 
                               class="sa-input" 
                               value="<?php echo esc_attr($item['placeholders'] ?? ''); ?>" 
                               maxlength="255"
                               placeholder="<?php echo esc_attr($tr('form_placeholders_placeholder', 'např. {name}, {date}')); ?>">
                        <small class="sa-form-help"><?php echo esc_html($tr('form_placeholders_help', 'Seznam dostupných placeholderů oddělených čárkou')); ?></small>
                    </div>
                </div>
                
            </div>
        </details>
        
        <?php 
        // Form actions - only show outside sidebar (sidebar uses FAB save button)
        if (!$in_sidebar): 
        ?>
        <div class="sa-form-actions">
            <button type="submit" class="sa-button sa-button--primary">
                <?php echo $is_edit ? esc_html($tr('btn_save_changes', 'Uložit změny')) : esc_html($tr('btn_create_translation', 'Vytvořit překlad')); ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/translations/')); ?>" class="sa-button sa-button--secondary">
                <?php echo esc_html($tr('btn_cancel', 'Zrušit')); ?>
            </a>
        </div>
        <?php endif; ?>
        
    </form>
</div>

