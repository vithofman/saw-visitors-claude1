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

// Načteme databázi jazyků
$languages_file = __DIR__ . '/languages-data.php';
if (file_exists($languages_file)) {
    $languages = require $languages_file;
} else {
    // Fallback na základní jazyky
    $languages = [
        'cs' => ['name' => 'Čeština', 'flag' => '🇨🇿'],
        'en' => ['name' => 'English', 'flag' => '🇬🇧'],
        'sk' => ['name' => 'Slovenčina', 'flag' => '🇸🇰'],
        'de' => ['name' => 'Deutsch', 'flag' => '🇩🇪'],
        'pl' => ['name' => 'Polski', 'flag' => '🇵🇱'],
        'uk' => ['name' => 'Українська', 'flag' => '🇺🇦'],
        'ru' => ['name' => 'Русский', 'flag' => '🇷🇺'],
    ];
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
                    <select name="language_code" id="language_code" class="saw-input saw-language-select" required <?php echo $is_edit ? 'disabled' : ''; ?>>
                        <option value="">-- Vyberte jazyk --</option>
                        <?php foreach ($languages as $code => $lang): ?>
                            <option value="<?php echo esc_attr($code); ?>" 
                                    data-flag="<?php echo esc_attr($lang['flag']); ?>"
                                    data-name="<?php echo esc_attr($lang['name']); ?>"
                                    <?php selected($item['language_code'] ?? '', $code); ?>>
                                <?php echo esc_html($lang['flag']); ?> <?php echo esc_html($code); ?> - <?php echo esc_html($lang['name']); ?><?php if (!empty($lang['name_cs']) && $lang['name_cs'] !== $lang['name']): ?> (<?php echo esc_html($lang['name_cs']); ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($is_edit): ?>
                        <input type="hidden" name="language_code" value="<?php echo esc_attr($item['language_code']); ?>">
                    <?php endif; ?>
                    <p class="saw-help-text">Vyhledejte jazyk podle názvu nebo kódu</p>
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
                    <div class="saw-flag-input-wrapper">
                        <input type="text" 
                               name="flag_emoji" 
                               id="flag_emoji" 
                               class="saw-input saw-flag-input" 
                               value="<?php echo esc_attr($item['flag_emoji'] ?? ''); ?>" 
                               required
                               maxlength="10"
                               placeholder="🇨🇿">
                        <div class="saw-flag-preview <?php echo !empty($item['flag_emoji']) ? 'has-flag' : ''; ?>" id="flag_preview">
                            <?php if (!empty($item['flag_emoji'])) echo esc_html($item['flag_emoji']); ?>
                        </div>
                    </div>
                    <p class="saw-help-text">Emoji vlajky (např. 🇨🇿)</p>
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
                        <div class="saw-branch-col-active">
                            <label class="saw-checkbox-wrapper saw-select-all-wrapper">
                                <input type="checkbox" id="select_all_branches" class="saw-select-all-checkbox">
                                <span class="saw-checkbox-label">Vybrat vše</span>
                            </label>
                        </div>
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

<!-- SELECT2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- INLINE CSS -->
<style>
<?php 
$css_file = __DIR__ . '/styles.css';
if (file_exists($css_file)) {
    echo file_get_contents($css_file);
}
?>

/* Select2 custom styling */
.select2-container--default .select2-selection--single {
    height: 44px !important;
    border: 2px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 0 !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 40px !important;
    padding-left: 12px !important;
    font-size: 15px !important;
    color: #0f172a !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 42px !important;
    right: 8px !important;
}

.select2-container--default.select2-container--focus .select2-selection--single {
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1) !important;
}

.select2-dropdown {
    border: 2px solid #e2e8f0 !important;
    border-radius: 8px !important;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1) !important;
}

.select2-results__option {
    padding: 10px 12px !important;
    font-size: 15px !important;
}

.select2-results__option--highlighted {
    background: #eff6ff !important;
    color: #1e40af !important;
}
</style>

<!-- SELECT2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- INLINE JAVASCRIPT -->
<script>
jQuery(document).ready(function($) {
    
    // === INICIALIZACE SELECT2 ===
    $('#language_code').select2({
        placeholder: '🔍 Vyhledejte jazyk...',
        allowClear: true,
        width: '100%',
        language: {
            noResults: function() {
                return 'Jazyk nenalezen';
            },
            searching: function() {
                return 'Hledám...';
            }
        }
    });
    
    // === LIVE NÁHLED VLAJKY ===
    $('#flag_emoji').on('input', function() {
        const flag = $(this).val().trim();
        const $preview = $('#flag_preview');
        
        if (flag) {
            $preview.text(flag).addClass('has-flag');
        } else {
            $preview.text('').removeClass('has-flag');
        }
    });
    
    // === AUTO-FILL PŘI VÝBĚRU JAZYKA (OPRAVENO) ===
    $('#language_code').on('change', function() {
        const $selected = $(this).find('option:selected');
        const flag = $selected.data('flag');
        const name = $selected.data('name');
        
        // VŽDY přepsat hodnoty (i když už tam něco je)
        if (name) {
            $('#language_name').val(name);
        }
        
        if (flag) {
            $('#flag_emoji').val(flag).trigger('input'); // Trigger input pro live preview
        }
    });
    
    // === BRANCH CHECKBOXY ===
    
    // "Vybrat vše" checkbox
    $('#select_all_branches').on('change', function() {
        const isChecked = $(this).is(':checked');
        
        $('.saw-branch-active-checkbox').each(function() {
            $(this).prop('checked', isChecked).trigger('change');
        });
        
        // Aktualizovat stav "Vybrat vše" checkboxu
        updateSelectAllState();
    });
    
    // Funkce pro aktualizaci stavu "Vybrat vše" checkboxu
    function updateSelectAllState() {
        const totalCheckboxes = $('.saw-branch-active-checkbox').length;
        const checkedCheckboxes = $('.saw-branch-active-checkbox:checked').length;
        
        if (checkedCheckboxes === 0) {
            $('#select_all_branches').prop('checked', false).prop('indeterminate', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $('#select_all_branches').prop('checked', true).prop('indeterminate', false);
        } else {
            $('#select_all_branches').prop('checked', false).prop('indeterminate', true);
        }
    }
    
    // Jednotlivé checkboxy
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
        
        // Aktualizovat "Vybrat vše" checkbox
        updateSelectAllState();
    });
    
    // Inicializovat stav "Vybrat vše" při načtení stránky
    updateSelectAllState();
    
    $('.saw-branch-default-checkbox').on('change', function() {
        if ($(this).is(':checked')) {
            $('.saw-branch-default-checkbox').not(this).prop('checked', false);
        }
    });
    
    // === VALIDACE FORMULÁŘE ===
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