<?php
/**
 * Account Types Form Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.0.0 - FIXED: Sidebar compatibility
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Prepare features text from JSON array
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
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
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
        
        <!-- BASIC INFORMATION -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="name" class="saw-label saw-required">
                            Interní název
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            class="saw-input <?php echo $is_edit ? 'saw-input-readonly' : ''; ?>"
                            value="<?php echo esc_attr($item['name'] ?? ''); ?>" 
                            required
                            pattern="[a-z0-9\-]+"
                            placeholder="free"
                            <?php echo $is_edit ? 'readonly style="background-color: #f3f4f6; cursor: not-allowed; color: #6b7280;"' : ''; ?>
                        >
                        <span class="saw-help-text">
                            <?php if ($is_edit): ?>
                                🔒 Po vytvoření nelze měnit (zajištění integrity dat)
                            <?php else: ?>
                                Unikátní slug (jen malá písmena, číslice a pomlčky)
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="display_name" class="saw-label saw-required">
                            Zobrazovaný název
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
                            Název který uvidí uživatelé
                        </span>
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="price" class="saw-label">
                            Cena (Kč/měsíc)
                        </label>
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
                        <span class="saw-help-text">
                            Měsíční cena v Kč (0 = zdarma)
                        </span>
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="sort_order" class="saw-label">
                            Pořadí řazení
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
                            Nižší číslo = vyšší v seznamu
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- VISUAL IDENTIFICATION -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-art"></span>
                <strong>Vizuální označení</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <?php
                        // ✅ CORRECT: Use color picker component via include
                        $id = 'color';
                        $name = 'color';
                        $value = $item['color'] ?? '#6b7280';
                        $label = 'Barva';
                        $show_preview = true;
                        $preview_text = 'Náhled';
                        $help_text = 'Barva pro vizuální označení typu účtu v seznamech';
                        
                        include SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/color-picker-input.php';
                        ?>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- FEATURES -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-list-view"></span>
                <strong>Funkce a možnosti</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-group">
                    <label for="features" class="saw-label">
                        Seznam funkcí
                    </label>
                    <textarea 
                        id="features" 
                        name="features" 
                        class="saw-textarea" 
                        rows="8"
                        placeholder="Každou funkci napište na nový řádek, např.:&#10;✓ 10 návštěvníků měsíčně&#10;✓ Základní reporty&#10;✓ Email notifikace"
                    ><?php echo esc_textarea($features_text); ?></textarea>
                    <span class="saw-help-text">
                        Každá funkce na nový řádek. Můžete použít emoji (✓, ✗, 🎯)
                    </span>
                </div>
                
            </div>
        </details>
        
        <!-- STATUS -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-settings"></span>
                <strong>Nastavení dostupnosti</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="is_active" 
                                value="1"
                                <?php checked(!empty($item['is_active']) ? $item['is_active'] : 1, 1); ?>
                            >
                            <span>Aktivní typ účtu</span>
                        </label>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ACTIONS -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit typ účtu'; ?>
            </button>
            
            <?php if (!$in_sidebar): ?>
                <a href="<?php echo esc_url(home_url('/admin/account-types/')); ?>" class="saw-button saw-button-secondary">
                    Zrušit
                </a>
            <?php endif; ?>
        </div>
        
    </form>
</div>