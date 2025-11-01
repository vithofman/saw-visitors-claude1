<?php
/**
 * Account Types Form Template
 * 
 * Formulář pro vytvoření/editaci typu účtu.
 * Obsahuje:
 * - Základní info (name, display_name)
 * - Branding (color)
 * - Cena
 * - Features (textarea - každý řádek = 1 feature)
 * - Nastavení (sort_order, is_active)
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since   4.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Zjisti zda editujeme nebo vytváříme nový
$is_edit = !empty($item);
$item = $item ?? [];

// Převeď features z JSON na textarea (každý feature na řádek)
$features_text = '';
if (!empty($item['features'])) {
    $features_array = json_decode($item['features'], true);
    if (is_array($features_array)) {
        $features_text = implode("\n", $features_array);
    }
}
?>

<!-- PAGE HEADER -->
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit typ účtu' : 'Nový typ účtu'; ?>
        </h1>
        <a href="<?php echo home_url('/admin/settings/account-types/'); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>

<!-- FORM CONTAINER -->
<div class="saw-form-container">
    <form method="post" class="saw-account-type-form">
        <?php 
        // Security nonce
        wp_nonce_field('saw_account-types_form', 'saw_nonce'); 
        ?>
        
        <?php if ($is_edit): ?>
            <!-- Hidden ID pro editaci -->
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- ========================================
             ZÁKLADNÍ INFORMACE
             ======================================== -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Interní název (slug) -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="name" class="saw-label saw-required">
                            Interní název
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
                            Unikátní slug (jen malá písmena, číslice a pomlčky). <?php echo $is_edit ? 'Po vytvoření nelze měnit.' : ''; ?>
                        </span>
                    </div>
                    
                    <!-- Zobrazovaný název -->
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
                
                <!-- Cena -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="price" class="saw-label">
                            Cena (Kč/měsíc)
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
                            <span class="saw-input-addon">Kč</span>
                        </div>
                        <span class="saw-help-text">
                            Měsíční cena v Kč (0 = zdarma)
                        </span>
                    </div>
                    
                    <!-- Sort order -->
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
        
        <!-- ========================================
             BRANDING (BARVA)
             ======================================== -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-art"></span>
                <strong>Vizuální označení</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <?php
                        $id = 'color';
                        $name = 'color';
                        $value = $item['color'] ?? '#6b7280';
                        $label = 'Barva';
                        $show_preview = true;
                        $preview_text = 'Náhled';
                        $help_text = 'Barva pro vizuální označení typu účtu';
                        include SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/color-picker-input.php';
                        ?>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ========================================
             FUNKCE (FEATURES)
             ======================================== -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-list-view"></span>
                <strong>Funkce a možnosti</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <div class="saw-form-row">
                    <div class="saw-form-group">
                        <label for="features" class="saw-label">
                            Seznam funkcí
                        </label>
                        <textarea 
                            id="features" 
                            name="features" 
                            class="saw-textarea" 
                            rows="10"
                            placeholder="Každou funkci napište na nový řádek, např.:&#10;✓ 10 návštěvníků měsíčně&#10;✓ Základní reporty&#10;✓ Email notifikace"
                        ><?php echo esc_textarea($features_text); ?></textarea>
                        <span class="saw-help-text">
                            Každá funkce na nový řádek. Můžete použít emoji nebo symboly (✓, ✗, 🎯, atd.)
                        </span>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ========================================
             NASTAVENÍ
             ======================================== -->
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-settings"></span>
                <strong>Nastavení dostupnosti</strong>
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
                                <strong>Aktivní typ účtu</strong>
                                <small>Pouze aktivní typy jsou dostupné pro výběr při vytváření zákazníků</small>
                            </span>
                        </label>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ========================================
             ACTION BUTTONS
             ======================================== -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit typ účtu'; ?>
            </button>
            <a href="<?php echo home_url('/admin/settings/account-types/'); ?>" class="saw-button saw-button-secondary">
                <span class="dashicons dashicons-no-alt"></span>
                Zrušit
            </a>
        </div>
        
    </form>
</div>

<script>
// Sync display_name do preview badge
jQuery(document).ready(function($) {
    $('#display_name').on('input', function() {
        const name = $(this).val() || 'Název';
        $('#color-preview-badge').text(name);
    });
});
</script>
