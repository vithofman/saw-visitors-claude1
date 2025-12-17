<?php
/**
 * Account Types Form Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     3.2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Prepare features text
$features_text = '';
if (!empty($item['features'])) {
    $features_array = json_decode($item['features'], true);
    if (is_array($features_array)) {
        $features_text = implode("\n", $features_array);
    }
}
?>

<?php if (!$in_sidebar): ?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit typ účtu' : 'Nový typ účtu'; ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/account-types/')); ?>" class="saw-back-button">
            ← Zpět na seznam
        </a>
    </div>
</div>
<?php endif; ?>

<div class="saw-form-container">
    <form method="post" class="saw-account-type-form">
        <?php
        $nonce_action = $is_edit ? 'saw_edit_account_types' : 'saw_create_account_types';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- BASIC INFO -->
        <details class="saw-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('badge-check', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="saw-section-emoji">💳</span>
                <?php endif; ?>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="name" class="saw-label saw-required">Interní název</label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['name'] ?? ''); ?>" 
                            required
                            pattern="[a-z0-9\-_]+"
                            placeholder="free"
                            <?php echo $is_edit ? 'readonly style="background-color: #f3f4f6; cursor: not-allowed;"' : ''; ?>
                        >
                        <p class="saw-help-text">
                            <?php if ($is_edit): ?>
                                🔒 Po vytvoření nelze měnit
                            <?php else: ?>
                                Unikátní slug (malá písmena, číslice, pomlčky)
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="display_name" class="saw-label saw-required">Zobrazovaný název</label>
                        <input 
                            type="text" 
                            id="display_name" 
                            name="display_name" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['display_name'] ?? ''); ?>" 
                            required
                            placeholder="Free"
                        >
                        <p class="saw-help-text">Název který uvidí uživatelé</p>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="price" class="saw-label">Cena (Kč/měsíc)</label>
                        <input 
                            type="number" 
                            id="price" 
                            name="price" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['price'] ?? '0'); ?>"
                            step="1"
                            min="0"
                            placeholder="0"
                        >
                        <p class="saw-help-text">0 = zdarma</p>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="sort_order" class="saw-label">Pořadí řazení</label>
                        <input 
                            type="number" 
                            id="sort_order" 
                            name="sort_order" 
                            class="saw-input"
                            value="<?php echo esc_attr($item['sort_order'] ?? '0'); ?>"
                            min="0"
                            placeholder="0"
                        >
                        <p class="saw-help-text">Nižší číslo = vyšší v seznamu</p>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- VISUAL -->
        <details class="saw-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('tag', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="saw-section-emoji">🎨</span>
                <?php endif; ?>
                <strong>Vizuální označení</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-group">
                    <?php
                    $color_picker_file = SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/color-picker-input.php';
                    if (file_exists($color_picker_file)):
                        $id = 'color';
                        $name = 'color';
                        $value = $item['color'] ?? '#6b7280';
                        $label = 'Barva';
                        $show_preview = true;
                        $preview_text = 'Náhled';
                        $help_text = 'Barva pro vizuální označení typu účtu';
                        include $color_picker_file;
                    else:
                    ?>
                    <label for="color" class="saw-label">Barva</label>
                    <input 
                        type="color" 
                        id="color" 
                        name="color" 
                        class="saw-input"
                        value="<?php echo esc_attr($item['color'] ?? '#6b7280'); ?>"
                        style="height: 40px; padding: 4px;"
                    >
                    <p class="saw-help-text">Barva pro vizuální označení typu účtu</p>
                    <?php endif; ?>
                </div>
                
            </div>
        </details>
        
        <!-- FEATURES -->
        <details class="saw-form-section">
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('star', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="saw-section-emoji">✨</span>
                <?php endif; ?>
                <strong>Funkce a možnosti</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-group">
                    <label for="features" class="saw-label">Seznam funkcí</label>
                    <textarea 
                        id="features" 
                        name="features" 
                        class="saw-textarea" 
                        rows="8"
                        placeholder="Každou funkci napište na nový řádek:&#10;✓ 10 návštěvníků měsíčně&#10;✓ Základní reporty&#10;✓ Email notifikace"
                    ><?php echo esc_textarea($features_text); ?></textarea>
                    <p class="saw-help-text">Každá funkce na nový řádek</p>
                </div>
                
            </div>
        </details>
        
        <!-- STATUS -->
        <details class="saw-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('settings', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="saw-section-emoji">⚙️</span>
                <?php endif; ?>
                <strong>Nastavení</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-group">
                    <label class="saw-checkbox-label">
                        <input 
                            type="checkbox" 
                            name="is_active" 
                            value="1"
                            <?php checked(!empty($item['is_active']) || !$is_edit, true); ?>
                        >
                        <span>Aktivní typ účtu</span>
                    </label>
                    <p class="saw-help-text">Pouze aktivní typy jsou dostupné při výběru</p>
                </div>
                
            </div>
        </details>
        
        <!-- ACTIONS -->
        <?php 
        // Form actions - only show outside sidebar (sidebar uses FAB save button)
        if (!$in_sidebar): 
        ?>
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                💾 <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit typ účtu'; ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/account-types/')); ?>" class="saw-button saw-button-secondary">
                Zrušit
            </a>
        </div>
        <?php endif; ?>
        
    </form>
</div>

<style>
.saw-section-emoji { font-size: 16px; margin-right: 8px; }
.saw-help-text { font-size: 12px; color: #64748b; margin-top: 4px; margin-bottom: 0; }
.saw-textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
    line-height: 1.6;
    resize: vertical;
}
.saw-checkbox-label {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-weight: 500;
}
.saw-checkbox-label input { width: 18px; height: 18px; }
</style>
