<?php
/**
 * Account Types Form Template
 * 
 * Create/Edit form for account types with complete data structure:
 * - Basic information (internal name slug, display name, price, sort order)
 * - Visual identification (color picker component)
 * - Features and options (textarea with line-by-line features)
 * - Availability settings (is_active checkbox)
 * 
 * Features:
 * - Internal name (slug) is readonly in edit mode to prevent breaking references
 * - Auto-slug generation from display name in create mode (via JavaScript)
 * - Color picker component with preview
 * - Features textarea with emoji support
 * - Price input with currency addon (Kč)
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes/Templates
 * @since       1.0.0
 * @version     2.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Force load color picker assets
wp_enqueue_style(
    'saw-color-picker-component',
    SAW_VISITORS_PLUGIN_URL . 'includes/components/color-picker/color-picker.css',
    array(),
    SAW_VISITORS_VERSION
);

wp_enqueue_script(
    'saw-color-picker-component',
    SAW_VISITORS_PLUGIN_URL . 'includes/components/color-picker/color-picker.js',
    array('jquery'),
    SAW_VISITORS_VERSION,
    true
);

$is_edit = !empty($item);
$item = $item ?? array();

// Prepare features text from JSON array
$features_text = '';
if (!empty($item['features'])) {
    $features_array = json_decode($item['features'], true);
    if (is_array($features_array)) {
        $features_text = implode("\n", $features_array);
    }
}
?>

<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? esc_html__('Upravit typ účtu', 'saw-visitors') : esc_html__('Nový typ účtu', 'saw-visitors'); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/settings/account-types/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php echo esc_html__('Zpět na seznam', 'saw-visitors'); ?>
        </a>
    </div>
</div>

<div class="saw-form-container">
    <form method="post" class="saw-account-type-form">
        <?php wp_nonce_field('saw_account_types_form', 'saw_nonce'); ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- BASIC INFORMATION -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong><?php echo esc_html__('Základní informace', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="name" class="saw-label saw-required">
                            <?php echo esc_html__('Interní název', 'saw-visitors'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['name'] ?? ''); ?>" 
                            required
                            pattern="[a-z0-9\-]+"
                            placeholder="free"
                            <?php echo $is_edit ? 'readonly' : ''; ?>
                        >
                        <span class="saw-help-text">
                            <?php echo esc_html__('Unikátní slug (jen malá písmena, číslice a pomlčky).', 'saw-visitors'); ?>
                            <?php if ($is_edit): ?>
                                <?php echo esc_html__('Po vytvoření nelze měnit.', 'saw-visitors'); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="display_name" class="saw-label saw-required">
                            <?php echo esc_html__('Zobrazovaný název', 'saw-visitors'); ?>
                        </label>
                        <input 
                            type="text" 
                            id="display_name" 
                            name="display_name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['display_name'] ?? ''); ?>" 
                            required
                            placeholder="Free"
                        >
                        <span class="saw-help-text">
                            <?php echo esc_html__('Název který uvidí uživatelé', 'saw-visitors'); ?>
                        </span>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="price" class="saw-label">
                            <?php echo esc_html__('Cena (Kč/měsíc)', 'saw-visitors'); ?>
                        </label>
                        <div class="saw-input-with-addon">
                            <input 
                                type="number" 
                                id="price" 
                                name="price" 
                                class="saw-input"
                                value="<?php echo esc_attr($item['price'] ?? '0.00'); ?>"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                            >
                            <span class="saw-input-addon"><?php echo esc_html__('Kč', 'saw-visitors'); ?></span>
                        </div>
                        <span class="saw-help-text">
                            <?php echo esc_html__('Měsíční cena v Kč (0 = zdarma)', 'saw-visitors'); ?>
                        </span>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="sort_order" class="saw-label">
                            <?php echo esc_html__('Pořadí řazení', 'saw-visitors'); ?>
                        </label>
                        <input 
                            type="number" 
                            id="sort_order" 
                            name="sort_order" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['sort_order'] ?? '0'); ?>"
                            min="0"
                            placeholder="0"
                        >
                        <span class="saw-help-text">
                            <?php echo esc_html__('Nižší číslo = vyšší v seznamu', 'saw-visitors'); ?>
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- VISUAL IDENTIFICATION -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-art"></span>
                <strong><?php echo esc_html__('Vizuální označení', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <?php
                        $id = 'color';
                        $name = 'color';
                        $value = $item['color'] ?? '#6b7280';
                        $label = __('Barva', 'saw-visitors');
                        $show_preview = true;
                        $preview_text = __('Náhled', 'saw-visitors');
                        $help_text = __('Barva pro vizuální označení typu účtu', 'saw-visitors');
                        include SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/color-picker-input.php';
                        ?>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- FEATURES AND OPTIONS -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-list-view"></span>
                <strong><?php echo esc_html__('Funkce a možnosti', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="features" class="saw-label">
                            <?php echo esc_html__('Seznam funkcí', 'saw-visitors'); ?>
                        </label>
                        <textarea 
                            id="features" 
                            name="features" 
                            class="saw-textarea saw-features-textarea" 
                            rows="10"
                            placeholder="<?php echo esc_attr__('Každou funkci napište na nový řádek, např.:', 'saw-visitors'); ?>&#10;✓ <?php echo esc_attr__('10 návštěvníků měsíčně', 'saw-visitors'); ?>&#10;✓ <?php echo esc_attr__('Základní reporty', 'saw-visitors'); ?>&#10;✓ <?php echo esc_attr__('Email notifikace', 'saw-visitors'); ?>"
                        ><?php echo esc_textarea($features_text); ?></textarea>
                        <span class="saw-help-text">
                            <?php echo esc_html__('Každá funkce na nový řádek. Můžete použít emoji nebo symboly (✓, ✗, 🎯, atd.)', 'saw-visitors'); ?>
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- AVAILABILITY SETTINGS -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-settings"></span>
                <strong><?php echo esc_html__('Nastavení dostupnosti', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                id="is_active" 
                                name="is_active" 
                                value="1"
                                <?php checked(!empty($item['is_active']) ? $item['is_active'] : 1, 1); ?>
                            >
                            <span class="saw-checkbox-text">
                                <strong><?php echo esc_html__('Aktivní typ účtu', 'saw-visitors'); ?></strong>
                                <small><?php echo esc_html__('Pouze aktivní typy jsou dostupné pro výběr při vytváření zákazníků', 'saw-visitors'); ?></small>
                            </span>
                        </label>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- FORM ACTIONS -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php echo $is_edit ? esc_html__('Uložit změny', 'saw-visitors') : esc_html__('Vytvořit typ účtu', 'saw-visitors'); ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/settings/account-types/')); ?>" class="saw-button saw-button-secondary">
                <span class="dashicons dashicons-no-alt"></span>
                <?php echo esc_html__('Zrušit', 'saw-visitors'); ?>
            </a>
        </div>
        
    </form>
</div>