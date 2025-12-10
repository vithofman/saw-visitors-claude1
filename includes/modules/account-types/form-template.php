<?php
/**
 * Account Types Form Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.0.0 - SAW Table migration (sawt-* classes, no inline styles)
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Prepare features text from JSON
$features_text = '';
if (!empty($item['features'])) {
    $features_array = json_decode($item['features'], true);
    if (is_array($features_array)) {
        $features_text = implode("\n", $features_array);
    }
}

// Form config from module config
$form_config = $config['form'] ?? array();
$sections = $form_config['sections'] ?? array();
$fields = $form_config['fields'] ?? array();
?>

<?php if (!$in_sidebar): ?>
<div class="sawt-page-header">
    <div class="sawt-page-header-content">
        <h1 class="sawt-page-title">
            <?php echo $is_edit ? 'Upravit typ účtu' : 'Nový typ účtu'; ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/account-types/')); ?>" class="sawt-btn sawt-btn-ghost">
            ← Zpět na seznam
        </a>
    </div>
</div>
<?php endif; ?>

<div class="sawt-form">
    <form method="post" class="sawt-form-body" data-entity="account_types">
        <?php
        $nonce_action = $is_edit ? 'saw_edit_account_types' : 'saw_create_account_types';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- SECTION: Basic Info -->
        <div class="sawt-form-section">
            <div class="sawt-form-section-title">
                <span class="sawt-form-section-icon">💳</span>
                Základní informace
            </div>
            
            <div class="sawt-form-row">
                <!-- Name (internal) -->
                <div class="sawt-form-group sawt-w-50">
                    <label for="name" class="sawt-form-label sawt-form-label-required">
                        Interní název
                    </label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        class="sawt-input"
                        value="<?php echo esc_attr($item['name'] ?? ''); ?>" 
                        required
                        pattern="[a-z0-9\-_]+"
                        placeholder="free"
                        <?php echo $is_edit ? 'readonly' : ''; ?>
                    >
                    <span class="sawt-form-help">
                        <?php if ($is_edit): ?>
                            🔒 Po vytvoření nelze měnit
                        <?php else: ?>
                            Unikátní slug (malá písmena, číslice, pomlčky)
                        <?php endif; ?>
                    </span>
                </div>
                
                <!-- Display Name -->
                <div class="sawt-form-group sawt-w-50">
                    <label for="display_name" class="sawt-form-label sawt-form-label-required">
                        Zobrazovaný název
                    </label>
                    <input 
                        type="text" 
                        id="display_name" 
                        name="display_name" 
                        class="sawt-input"
                        value="<?php echo esc_attr($item['display_name'] ?? ''); ?>" 
                        required
                        placeholder="Free"
                    >
                    <span class="sawt-form-help">Název který uvidí uživatelé</span>
                </div>
            </div>
            
            <div class="sawt-form-row">
                <!-- Price -->
                <div class="sawt-form-group sawt-w-50">
                    <label for="price" class="sawt-form-label">
                        Cena (Kč/měsíc)
                    </label>
                    <div class="sawt-input-group">
                        <input 
                            type="number" 
                            id="price" 
                            name="price" 
                            class="sawt-input"
                            value="<?php echo esc_attr($item['price'] ?? '0'); ?>"
                            step="1"
                            min="0"
                            placeholder="0"
                        >
                        <span class="sawt-input-addon">Kč</span>
                    </div>
                    <span class="sawt-form-help">0 = zdarma</span>
                </div>
                
                <!-- Sort Order -->
                <div class="sawt-form-group sawt-w-50">
                    <label for="sort_order" class="sawt-form-label">
                        Pořadí řazení
                    </label>
                    <input 
                        type="number" 
                        id="sort_order" 
                        name="sort_order" 
                        class="sawt-input"
                        value="<?php echo esc_attr($item['sort_order'] ?? '0'); ?>"
                        min="0"
                        placeholder="0"
                    >
                    <span class="sawt-form-help">Nižší číslo = vyšší v seznamu</span>
                </div>
            </div>
        </div>
        
        <!-- SECTION: Visual -->
        <div class="sawt-form-section">
            <div class="sawt-form-section-title">
                <span class="sawt-form-section-icon">🎨</span>
                Vizuální označení
            </div>
            
            <div class="sawt-form-group">
                <label for="color" class="sawt-form-label">Barva</label>
                <?php
                // Try to use color picker component
                $color_picker_file = SAW_VISITORS_PLUGIN_DIR . 'includes/components/color-picker/color-picker-input.php';
                if (file_exists($color_picker_file)):
                    $id = 'color';
                    $name = 'color';
                    $value = $item['color'] ?? '#6b7280';
                    $label = '';
                    $show_preview = true;
                    $preview_text = 'Náhled';
                    $help_text = '';
                    include $color_picker_file;
                else:
                ?>
                <div class="sawt-color-picker">
                    <input 
                        type="color" 
                        id="color" 
                        name="color" 
                        class="sawt-color-input"
                        value="<?php echo esc_attr($item['color'] ?? '#6b7280'); ?>"
                    >
                    <input 
                        type="text" 
                        class="sawt-input sawt-color-text"
                        value="<?php echo esc_attr(strtoupper($item['color'] ?? '#6B7280')); ?>"
                        readonly
                    >
                </div>
                <?php endif; ?>
                <span class="sawt-form-help">Barva pro vizuální označení typu účtu</span>
            </div>
        </div>
        
        <!-- SECTION: Features -->
        <div class="sawt-form-section">
            <div class="sawt-form-section-title">
                <span class="sawt-form-section-icon">✨</span>
                Funkce a možnosti
            </div>
            
            <div class="sawt-form-group">
                <label for="features" class="sawt-form-label">Seznam funkcí</label>
                <textarea 
                    id="features" 
                    name="features" 
                    class="sawt-input sawt-textarea" 
                    rows="8"
                    placeholder="Každou funkci napište na nový řádek:&#10;✓ 10 návštěvníků měsíčně&#10;✓ Základní reporty&#10;✓ Email notifikace"
                ><?php echo esc_textarea($features_text); ?></textarea>
                <span class="sawt-form-help">Každá funkce na nový řádek</span>
            </div>
        </div>
        
        <!-- SECTION: Settings -->
        <div class="sawt-form-section">
            <div class="sawt-form-section-title">
                <span class="sawt-form-section-icon">⚙️</span>
                Nastavení
            </div>
            
            <div class="sawt-form-group">
                <label class="sawt-toggle">
                    <input 
                        type="checkbox" 
                        name="is_active" 
                        value="1"
                        class="sawt-toggle-input"
                        <?php checked(!empty($item['is_active']) || !$is_edit, true); ?>
                    >
                    <span class="sawt-toggle-slider"></span>
                    <span class="sawt-toggle-text">Aktivní typ účtu</span>
                </label>
                <span class="sawt-form-help">Pouze aktivní typy jsou dostupné při výběru</span>
            </div>
        </div>
        
        <!-- ACTIONS -->
        <div class="sawt-form-actions">
            <?php if (!$in_sidebar): ?>
            <div class="sawt-form-actions-left">
                <a href="<?php echo esc_url(home_url('/admin/account-types/')); ?>" class="sawt-btn sawt-btn-secondary">
                    Zrušit
                </a>
            </div>
            <?php endif; ?>
            
            <div class="sawt-form-actions-right">
                <button type="submit" class="sawt-btn sawt-btn-primary">
                    💾 <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit typ účtu'; ?>
                </button>
            </div>
        </div>
        
    </form>
</div>
