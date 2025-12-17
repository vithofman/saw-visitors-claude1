<?php
/**
 * OOPP Form Template
 * 
 * Form for creating/editing OOPP (Osobní ochranné pracovní prostředky)
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/OOPP
 * @version     2.0.0 - ADDED: Translation support
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
    ? saw_get_translations($lang, 'admin', 'oopp') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// Check if we're in sidebar mode
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Determine if this is edit mode
$is_edit = !empty($item);
$item = $item ?? array();

// Get current customer context
$customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : 0;

// Get languages and translations for form
$languages = $config['form_languages'] ?? array();
$translations = $item['translations'] ?? array();

// Get OOPP groups
global $wpdb;
$oopp_groups = array();
if ($customer_id) {
    $oopp_groups = $wpdb->get_results(
        "SELECT id, code, name FROM {$wpdb->prefix}saw_oopp_groups ORDER BY display_order ASC",
        ARRAY_A
    );
}

// Get branches for multiselect
$branches = array();
if ($customer_id) {
    $branches = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $customer_id
    ), ARRAY_A);
}

// Get departments grouped by branch
$departments_by_branch = array();
if ($customer_id) {
    $departments = $wpdb->get_results($wpdb->prepare(
        "SELECT d.id, d.name, d.branch_id, b.name as branch_name 
         FROM {$wpdb->prefix}saw_departments d
         LEFT JOIN {$wpdb->prefix}saw_branches b ON d.branch_id = b.id
         WHERE d.customer_id = %d AND d.is_active = 1 
         ORDER BY b.name ASC, d.name ASC",
        $customer_id
    ), ARRAY_A);
    
    // Group by branch
    foreach ($departments as $dept) {
        $branch_id = $dept['branch_id'] ?: 0;
        if (!isset($departments_by_branch[$branch_id])) {
            $departments_by_branch[$branch_id] = array(
                'branch_name' => $dept['branch_name'] ?: $tr('form_no_branch', 'Bez pobočky'),
                'departments' => array()
            );
        }
        $departments_by_branch[$branch_id]['departments'][] = $dept;
    }
}

// Count total departments
$total_departments = 0;
foreach ($departments_by_branch as $group) {
    $total_departments += count($group['departments']);
}

// Selected branch/department IDs
$selected_branch_ids = $item['branch_ids'] ?? array();
$selected_department_ids = $item['department_ids'] ?? array();

// ============================================
// FIXED: Generate image URL from image_path
// ============================================
$current_image_url = '';
if (!empty($item['image_url'])) {
    // Already have URL (from format_detail_data)
    $current_image_url = $item['image_url'];
} elseif (!empty($item['image_path'])) {
    // Generate URL from path
    $upload_dir = wp_upload_dir();
    $current_image_url = $upload_dir['baseurl'] . '/' . ltrim($item['image_path'], '/');
}
$has_image = !empty($current_image_url);

// Form action URL
$form_action = $is_edit 
    ? home_url('/admin/oopp/' . $item['id'] . '/edit')
    : home_url('/admin/oopp/create');

// ============================================
// TRANSLATIONS FOR JS (will be passed via data attributes)
// ============================================
$js_translations = array(
    'file_too_large' => $tr('js_file_too_large', 'Soubor je příliš velký. Maximální velikost je {size}MB.'),
    'invalid_file_type' => $tr('js_invalid_file_type', 'Neplatný typ souboru!'),
    'file_size_label' => $tr('js_file_size', 'Velikost'),
    'file_type_label' => $tr('js_file_type', 'Typ'),
    'no_photo' => $tr('form_no_photo', 'Žádná fotografie'),
    'remove' => $tr('form_remove', 'Odstranit'),
);
?>

<?php if (!$in_sidebar): ?>
<!-- Page Header (only when NOT in sidebar) -->
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit 
                ? esc_html($tr('form_title_edit', 'Upravit OOPP')) 
                : esc_html($tr('form_title_create', 'Nový OOPP')); ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/oopp/')); ?>" class="saw-back-button">
            <?php if (class_exists('SAW_Icons')): ?>
                <?php echo SAW_Icons::get('chevron-left'); ?>
            <?php else: ?>
                <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php endif; ?>
            <?php echo esc_html($tr('form_back_to_list', 'Zpět na seznam')); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Form Container -->
<div class="saw-form-container saw-module-oopp" data-translations="<?php echo esc_attr(json_encode($js_translations)); ?>">
    <form method="POST" action="<?php echo esc_url($form_action); ?>" enctype="multipart/form-data" class="saw-oopp-form">
        <?php 
        $nonce_action = $is_edit ? 'saw_edit_oopp' : 'saw_create_oopp';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($in_sidebar): ?>
            <input type="hidden" name="_ajax_sidebar_submit" value="1">
        <?php endif; ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <!-- Hidden Fields -->
        <input type="hidden" name="customer_id" value="<?php echo esc_attr($customer_id); ?>">
        
        <!-- ================================================ -->
        <!-- ZÁKLADNÍ INFORMACE -->
        <!-- ================================================ -->
        <details class="saw-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('settings', 'saw-section-icon'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-admin-generic"></span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('section_basic', 'Základní informace')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Skupina OOPP -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="group_id" class="saw-label saw-required">
                            <?php echo esc_html($tr('field_group', 'Skupina OOPP')); ?>
                        </label>
                        <select 
                            name="group_id" 
                            id="group_id" 
                            class="saw-input" 
                            required
                        >
                            <option value=""><?php echo esc_html($tr('form_select_group', '-- Vyberte skupinu --')); ?></option>
                            <?php foreach ($oopp_groups as $group): ?>
                                <option 
                                    value="<?php echo esc_attr($group['id']); ?>"
                                    <?php selected($item['group_id'] ?? '', $group['id']); ?>
                                >
                                    <?php echo esc_html($group['code'] . '. ' . $group['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="saw-help-text"><?php echo esc_html($tr('form_group_help', 'Vyberte skupinu, do které OOPP patří')); ?></p>
                    </div>
                </div>
                
                <!-- ================================================ -->
                <!-- FOTOGRAFIE - FILE UPLOAD COMPONENT -->
                <!-- ================================================ -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label"><?php echo esc_html($tr('field_image', 'Fotografie')); ?></label>
                        
                        <div class="saw-file-upload-component" data-context="oopp">
                            <div class="saw-file-upload-area">
                                
                                <!-- Preview Section -->
                                <div class="saw-file-preview-section">
                                    <div class="saw-file-preview-box<?php echo $has_image ? ' has-file' : ''; ?>">
                                        <?php if ($has_image): ?>
                                            <img src="<?php echo esc_url($current_image_url); ?>" alt="<?php echo esc_attr($tr('form_current_photo', 'Aktuální fotografie')); ?>" class="saw-preview-image">
                                            <button type="button" class="saw-file-remove-overlay" title="<?php echo esc_attr($tr('form_remove_photo', 'Odstranit fotografii')); ?>">
                                                <?php if (class_exists('SAW_Icons')): ?>
                                                    <?php echo SAW_Icons::get('x'); ?>
                                                <?php else: ?>
                                                    <span class="dashicons dashicons-no-alt"></span>
                                                <?php endif; ?>
                                            </button>
                                        <?php else: ?>
                                            <div class="saw-file-empty-state">
                                                <div class="saw-file-icon-wrapper">
                                                    <?php if (class_exists('SAW_Icons')): ?>
                                                        <?php echo SAW_Icons::get('image'); ?>
                                                    <?php else: ?>
                                                        <span class="dashicons dashicons-format-image"></span>
                                                    <?php endif; ?>
                                                </div>
                                                <p class="saw-file-empty-text"><?php echo esc_html($tr('form_no_photo', 'Žádná fotografie')); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($has_image): ?>
                                        <p class="saw-current-file-label"><?php echo esc_html($tr('form_current_photo_label', 'Současná fotografie')); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Upload Controls -->
                                <div class="saw-file-upload-controls">
                                    <input 
                                        type="file" 
                                        name="image" 
                                        id="image" 
                                        class="saw-file-input"
                                        accept="image/jpeg,image/png,image/gif,image/webp"
                                        data-max-size="2097152"
                                    >
                                    <label for="image" class="saw-file-upload-trigger">
                                        <?php if (class_exists('SAW_Icons')): ?>
                                            <?php echo SAW_Icons::get('upload'); ?>
                                        <?php else: ?>
                                            <span class="dashicons dashicons-upload"></span>
                                        <?php endif; ?>
                                        <?php echo esc_html($tr('form_upload_photo', 'Nahrát fotografii')); ?>
                                    </label>
                                    
                                    <!-- Selected File Info (hidden by default) -->
                                    <div class="saw-file-selected-info hidden">
                                        <div class="saw-file-selected-icon">
                                            <?php if (class_exists('SAW_Icons')): ?>
                                                <?php echo SAW_Icons::get('check-circle'); ?>
                                            <?php else: ?>
                                                <span class="dashicons dashicons-yes-alt"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="saw-file-selected-details">
                                            <span class="saw-file-selected-name"></span>
                                            <span class="saw-file-selected-meta"></span>
                                        </div>
                                        <button type="button" class="saw-file-clear-btn" title="<?php echo esc_attr($tr('form_cancel_selection', 'Zrušit výběr')); ?>">
                                            <?php if (class_exists('SAW_Icons')): ?>
                                                <?php echo SAW_Icons::get('x'); ?>
                                            <?php else: ?>
                                                <span class="dashicons dashicons-dismiss"></span>
                                            <?php endif; ?>
                                        </button>
                                    </div>
                                    
                                    <p class="saw-help-text">
                                        <?php echo esc_html($tr('form_image_help', 'Nahrajte obrázek ve formátu JPG, PNG, GIF nebo WebP (max 2MB)')); ?>
                                    </p>
                                </div>
                                
                            </div>
                            
                            <!-- Hidden field for marking file removal -->
                            <input type="hidden" name="remove_image" class="saw-file-remove-flag" value="0">
                            
                            <!-- Hidden field for current image path (for backend reference) -->
                            <?php if (!empty($item['image_path'])): ?>
                                <input type="hidden" name="current_image_path" value="<?php echo esc_attr($item['image_path']); ?>">
                            <?php endif; ?>
                        </div>
                        
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- PLATNOST (Pobočky a oddělení) -->
        <!-- ================================================ -->
        <details class="saw-form-section">
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('map-pin'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-location"></span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('section_validity', 'Platnost')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Pobočky -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label">
                            <?php echo esc_html($tr('label_branches', 'Pobočky')); ?>
                            <span id="branch-counter" class="saw-counter">
                                <span id="branch-selected"><?php echo count($selected_branch_ids); ?></span> / <span id="branch-total"><?php echo count($branches); ?></span>
                            </span>
                        </label>
                        <p class="saw-help-text" style="margin-bottom: 12px;">
                            <?php echo esc_html($tr('form_branches_help', 'Vyberte pobočky, pro které platí tento OOPP. Pokud nic nevyberete, platí pro všechny pobočky.')); ?>
                        </p>
                        
                        <div class="saw-selection-controls">
                            <input type="text" id="branch-search" class="saw-input" placeholder="<?php echo esc_attr($tr('form_search_branch', '🔍 Hledat pobočku...')); ?>">
                            <label class="saw-select-all-label">
                                <input type="checkbox" id="select-all-branches">
                                <span><?php echo esc_html($tr('form_select_all', 'Vybrat vše')); ?></span>
                            </label>
                        </div>
                        
                        <div id="branches-list" class="saw-selection-list">
                            <?php if (empty($branches)): ?>
                                <p class="saw-empty-message"><?php echo esc_html($tr('form_no_branches', 'Žádné pobočky k dispozici')); ?></p>
                            <?php else: ?>
                                <?php foreach ($branches as $branch): 
                                    $is_checked = in_array($branch['id'], $selected_branch_ids);
                                ?>
                                    <label class="saw-selection-item" data-name="<?php echo esc_attr(mb_strtolower($branch['name'])); ?>">
                                        <input 
                                            type="checkbox" 
                                            name="branch_ids[]" 
                                            value="<?php echo esc_attr($branch['id']); ?>"
                                            class="branch-checkbox"
                                            <?php checked($is_checked); ?>
                                        >
                                        <span class="saw-item-name"><?php echo esc_html($branch['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Oddělení - seskupená podle poboček -->
                <div class="saw-form-row" style="margin-top: 24px;">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label">
                            <?php echo esc_html($tr('label_departments', 'Oddělení')); ?>
                            <span id="department-counter" class="saw-counter">
                                <span id="department-selected"><?php echo count($selected_department_ids); ?></span> / <span id="department-total"><?php echo $total_departments; ?></span>
                            </span>
                        </label>
                        <p class="saw-help-text" style="margin-bottom: 12px;">
                            <?php echo esc_html($tr('form_departments_help', 'Zobrazují se oddělení pro vybrané pobočky. Pokud není vybraná žádná pobočka, zobrazují se všechna oddělení.')); ?>
                        </p>
                        
                        <div class="saw-selection-controls">
                            <input type="text" id="department-search" class="saw-input" placeholder="<?php echo esc_attr($tr('form_search_department', '🔍 Hledat oddělení...')); ?>">
                            <label class="saw-select-all-label">
                                <input type="checkbox" id="select-all-departments">
                                <span><?php echo esc_html($tr('form_select_all', 'Vybrat vše')); ?></span>
                            </label>
                        </div>
                        
                        <div id="departments-list" class="saw-selection-list saw-grouped-list">
                            <?php if (empty($departments_by_branch)): ?>
                                <p class="saw-empty-message"><?php echo esc_html($tr('form_no_departments', 'Žádná oddělení k dispozici')); ?></p>
                            <?php else: ?>
                                <?php foreach ($departments_by_branch as $branch_id => $group): ?>
                                    <div class="saw-selection-group" data-branch-id="<?php echo esc_attr($branch_id); ?>">
                                        <div class="saw-group-header">
                                            <span class="saw-group-icon">🏢</span>
                                            <span class="saw-group-name"><?php echo esc_html($group['branch_name']); ?></span>
                                            <span class="saw-group-count"><?php echo count($group['departments']); ?> <?php echo esc_html($tr('form_departments_count', 'oddělení')); ?></span>
                                        </div>
                                        <?php foreach ($group['departments'] as $dept): 
                                            $is_checked = in_array($dept['id'], $selected_department_ids);
                                            $search_name = mb_strtolower($dept['name'] . ' ' . $group['branch_name']);
                                        ?>
                                            <label class="saw-selection-item" data-name="<?php echo esc_attr($search_name); ?>" data-branch-id="<?php echo esc_attr($branch_id); ?>">
                                                <input 
                                                    type="checkbox" 
                                                    name="department_ids[]" 
                                                    value="<?php echo esc_attr($dept['id']); ?>"
                                                    class="department-checkbox"
                                                    <?php checked($is_checked); ?>
                                                >
                                                <span class="saw-item-name"><?php echo esc_html($dept['name']); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- PŘEKLADY -->
        <!-- ================================================ -->
        <?php if (!empty($languages)): ?>
        <details class="saw-form-section" open>
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('globe'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-translation"></span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('section_translations', 'Překlady')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <?php
                // Najít index aktivního jazyka podle UX language switcheru
                $active_lang_index = 0;
                $user_lang = 'cs';
                if (class_exists('SAW_Component_Language_Switcher')) {
                    $user_lang = SAW_Component_Language_Switcher::get_user_language();
                }
                
                // Najít index jazyka v seznamu
                foreach ($languages as $idx => $language) {
                    if ($language['code'] === $user_lang) {
                        $active_lang_index = $idx;
                        break;
                    }
                }
                ?>
                
                <!-- Jazykové záložky -->
                <div class="saw-language-tabs-wrapper">
                    <button type="button" class="saw-language-tab-nav saw-language-tab-nav-prev" aria-label="Předchozí jazyky">
                        <?php if (class_exists('SAW_Icons')): ?>
                            <?php echo SAW_Icons::get('chevron-left'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                        <?php endif; ?>
                    </button>
                    <div class="saw-language-tabs">
                        <?php foreach ($languages as $index => $language): ?>
                            <button 
                                type="button" 
                                class="saw-language-tab <?php echo $index === $active_lang_index ? 'active' : ''; ?>" 
                                data-tab="lang-<?php echo esc_attr($language['code']); ?>"
                            >
                                <?php echo !empty($language['flag']) ? esc_html($language['flag']) : '🌐'; ?> 
                                <?php echo esc_html($language['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="saw-language-tab-nav saw-language-tab-nav-next" aria-label="Další jazyky">
                        <?php if (class_exists('SAW_Icons')): ?>
                            <?php echo SAW_Icons::get('chevron-right'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        <?php endif; ?>
                    </button>
                </div>
                
                <!-- Obsah záložek -->
                <div class="saw-language-contents">
                    <?php foreach ($languages as $index => $language): 
                        $lang_code = $language['code'];
                        $lang_trans = $translations[$lang_code] ?? array();
                    ?>
                        <div 
                            class="saw-language-content" 
                            data-tab-content="lang-<?php echo esc_attr($lang_code); ?>"
                            style="<?php echo $index === $active_lang_index ? '' : 'display: none;'; ?>"
                        >
                            
                            <!-- Název -->
                            <div class="saw-form-row">
                                <div class="saw-form-group saw-col-12">
                                    <label for="translation_name_<?php echo esc_attr($lang_code); ?>" class="saw-label <?php echo $index === $active_lang_index ? 'saw-required' : ''; ?>">
                                        <?php echo esc_html($tr('field_name', 'Název')); ?>
                                        <?php if ($index === $active_lang_index): ?>
                                            <span class="saw-required-marker">*</span>
                                        <?php endif; ?>
                                    </label>
                                    <input 
                                        type="text" 
                                        name="translations[<?php echo esc_attr($lang_code); ?>][name]" 
                                        id="translation_name_<?php echo esc_attr($lang_code); ?>" 
                                        class="saw-input" 
                                        value="<?php echo esc_attr($lang_trans['name'] ?? ''); ?>"
                                        placeholder="<?php echo esc_attr($tr('placeholder_name', 'např. Ochranné brýle proti UV záření')); ?>"
                                        <?php echo $index === $active_lang_index ? 'required' : ''; ?>
                                    >
                                </div>
                            </div>
                            
                            <!-- Normy -->
                            <div class="saw-form-row">
                                <div class="saw-form-group saw-col-12">
                                    <label for="translation_standards_<?php echo esc_attr($lang_code); ?>" class="saw-label">
                                        <?php echo esc_html($tr('field_standards', 'Související předpisy / normy')); ?>
                                    </label>
                                    <textarea 
                                        name="translations[<?php echo esc_attr($lang_code); ?>][standards]" 
                                        id="translation_standards_<?php echo esc_attr($lang_code); ?>" 
                                        class="saw-input saw-textarea" 
                                        rows="3"
                                        placeholder="<?php echo esc_attr($tr('placeholder_standards', 'např. ČSN EN 166, EN 172...')); ?>"
                                    ><?php echo esc_textarea($lang_trans['standards'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Rizika -->
                            <div class="saw-form-row">
                                <div class="saw-form-group saw-col-12">
                                    <label for="translation_risk_description_<?php echo esc_attr($lang_code); ?>" class="saw-label">
                                        <?php echo esc_html($tr('field_risks', 'Popis rizik, proti kterým OOPP chrání')); ?>
                                    </label>
                                    <textarea 
                                        name="translations[<?php echo esc_attr($lang_code); ?>][risk_description]" 
                                        id="translation_risk_description_<?php echo esc_attr($lang_code); ?>" 
                                        class="saw-input saw-textarea" 
                                        rows="4"
                                        placeholder="<?php echo esc_attr($tr('placeholder_risks', 'Popište rizika, před kterými tento prostředek chrání...')); ?>"
                                    ><?php echo esc_textarea($lang_trans['risk_description'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Ochranné vlastnosti -->
                            <div class="saw-form-row">
                                <div class="saw-form-group saw-col-12">
                                    <label for="translation_protective_properties_<?php echo esc_attr($lang_code); ?>" class="saw-label">
                                        <?php echo esc_html($tr('field_properties', 'Ochranné vlastnosti')); ?>
                                    </label>
                                    <textarea 
                                        name="translations[<?php echo esc_attr($lang_code); ?>][protective_properties]" 
                                        id="translation_protective_properties_<?php echo esc_attr($lang_code); ?>" 
                                        class="saw-input saw-textarea" 
                                        rows="4"
                                        placeholder="<?php echo esc_attr($tr('placeholder_properties', 'Popište ochranné vlastnosti prostředku...')); ?>"
                                    ><?php echo esc_textarea($lang_trans['protective_properties'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                            <!-- Pokyny pro použití -->
                            <div class="saw-form-row">
                                <div class="saw-form-group saw-col-12">
                                    <label for="translation_usage_instructions_<?php echo esc_attr($lang_code); ?>" class="saw-label">
                                        <?php echo esc_html($tr('field_usage', 'Pokyny pro použití')); ?>
                                    </label>
                                    <textarea 
                                        name="translations[<?php echo esc_attr($lang_code); ?>][usage_instructions]" 
                                        id="translation_usage_instructions_<?php echo esc_attr($lang_code); ?>" 
                                        class="saw-input saw-textarea" 
                                        rows="4"
                                        placeholder="<?php echo esc_attr($tr('placeholder_usage', 'Jak správně používat tento prostředek...')); ?>"
                                    ><?php echo esc_textarea($lang_trans['usage_instructions'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            
                        </div>
                    <?php endforeach; ?>
                </div>
                
            </div>
        </details>
        <?php else: ?>
            <div class="saw-form-section">
                <div class="saw-form-section-content">
                    <p class="saw-help-text" style="color: #d63638;">
                        <?php echo esc_html($tr('error_no_languages', 'Pro vytváření překladů musíte nejprve přidat jazyky v modulu Training Languages.')); ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- ================================================ -->
        <!-- NASTAVENÍ -->
        <!-- ================================================ -->
        <details class="saw-form-section">
            <summary>
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('settings'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-admin-settings"></span>
                <?php endif; ?>
                <strong><?php echo esc_html($tr('section_settings', 'Nastavení')); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Typ použití -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label saw-required">
                            <?php echo esc_html($tr('field_is_global', 'Typ použití')); ?>
                        </label>
                        
                        <div class="saw-radio-cards">
                            <label class="saw-radio-card <?php echo ($item['is_global'] ?? 1) == 1 ? 'selected' : ''; ?>">
                                <input type="radio" 
                                       name="is_global" 
                                       value="1" 
                                       <?php checked($item['is_global'] ?? 1, 1); ?>
                                       required>
                                <div class="saw-radio-card-content">
                                    <span class="saw-radio-card-icon">🌐</span>
                                    <span class="saw-radio-card-title">
                                        <?php echo esc_html($tr('is_global_yes_title', 'Globální')); ?>
                                    </span>
                                    <span class="saw-radio-card-desc">
                                        <?php echo esc_html($tr('is_global_yes_desc', 'Zobrazuje se automaticky všem návštěvníkům dle nastavení poboček a oddělení.')); ?>
                                    </span>
                                </div>
                            </label>
                            
                            <label class="saw-radio-card <?php echo ($item['is_global'] ?? 1) == 0 ? 'selected' : ''; ?>">
                                <input type="radio" 
                                       name="is_global" 
                                       value="0" 
                                       <?php checked($item['is_global'] ?? 1, 0); ?>>
                                <div class="saw-radio-card-content">
                                    <span class="saw-radio-card-icon">🎯</span>
                                    <span class="saw-radio-card-title">
                                        <?php echo esc_html($tr('is_global_no_title', 'Pouze pro konkrétní akce')); ?>
                                    </span>
                                    <span class="saw-radio-card-desc">
                                        <?php echo esc_html($tr('is_global_no_desc', 'Nezobrazuje se automaticky. Přiřadí se ručně při plánování návštěvy.')); ?>
                                    </span>
                                </div>
                            </label>
                        </div>
                        
                        <p class="saw-help-text">
                            <?php echo esc_html($tr('is_global_help', 'Globální OOPP se zobrazují automaticky. OOPP pro akce se zobrazí pouze když jsou přiřazeny ke konkrétní návštěvě.')); ?>
                        </p>
                    </div>
                </div>
                
                <!-- Aktivní -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-checkbox-label">
                            <input 
                                type="checkbox" 
                                name="is_active" 
                                value="1"
                                <?php checked(empty($item) || !empty($item['is_active'])); ?>
                            >
                            <span><?php echo esc_html($tr('field_active', 'Aktivní')); ?></span>
                        </label>
                        <p class="saw-help-text"><?php echo esc_html($tr('form_active_help', 'Neaktivní OOPP se nezobrazí v seznamu pro výběr')); ?></p>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- Tlačítka -->
        <?php 
        // Form actions - only show outside sidebar (sidebar uses FAB save button)
        $in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];
        if (!$in_sidebar): 
        ?>
        <div class="saw-form-actions">
            <button type="submit" class="saw-btn saw-btn-primary">
                <?php if (class_exists('SAW_Icons')): ?>
                    <?php echo SAW_Icons::get('save'); ?>
                <?php else: ?>
                    <span class="dashicons dashicons-saved"></span>
                <?php endif; ?>
                <?php echo $is_edit 
                    ? esc_html($tr('btn_save_changes', 'Uložit změny')) 
                    : esc_html($tr('btn_create', 'Vytvořit OOPP')); ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/oopp/')); ?>" class="saw-btn saw-btn-secondary">
                <?php echo esc_html($tr('btn_cancel', 'Zrušit')); ?>
            </a>
        </div>
        <?php endif; ?>
    </form>
</div>

<style>
/* ============================================
   FILE UPLOAD COMPONENT STYLES (OOPP specific)
   ============================================ */

.saw-file-upload-area {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 24px;
    align-items: start;
}

.saw-file-preview-section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.saw-file-preview-box {
    position: relative;
    width: 200px;
    height: 200px;
    border: 2px solid #dcdcde;
    border-radius: 8px;
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    transition: all 0.2s ease;
}

.saw-file-preview-box:hover {
    border-color: #a7aaad;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.saw-file-preview-box.has-file {
    cursor: pointer;
}

.saw-preview-image {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
    padding: 12px;
}

.saw-file-empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 24px;
    text-align: center;
}

.saw-file-icon-wrapper {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: #f0f0f1;
    display: flex;
    align-items: center;
    justify-content: center;
}

.saw-file-icon-wrapper .dashicons {
    font-size: 32px;
    width: 32px;
    height: 32px;
    color: #a7aaad;
}

.saw-file-empty-text {
    margin: 0;
    font-size: 13px;
    color: #757575;
    font-weight: 500;
}

.saw-file-remove-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(220, 38, 38, 0.95);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.15s ease;
    border: none;
    cursor: pointer;
    border-radius: 6px;
}

.saw-file-preview-box.has-file:hover .saw-file-remove-overlay {
    opacity: 1;
}

.saw-file-remove-overlay .dashicons {
    font-size: 48px;
    width: 48px;
    height: 48px;
    color: #ffffff;
}

.saw-current-file-label {
    margin: 0;
    font-size: 12px;
    color: #757575;
    text-align: center;
}

/* Upload Controls */
.saw-file-upload-controls {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.saw-file-input {
    position: absolute;
    width: 0.1px;
    height: 0.1px;
    opacity: 0;
    overflow: hidden;
    z-index: -1;
}

.saw-file-upload-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    background: #ffffff;
    color: #50575e;
    border: 2px solid #c3c4c7;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.15s ease;
    align-self: flex-start;
}

.saw-file-upload-trigger:hover {
    background: #f6f7f7;
    border-color: #0073aa;
    color: #0073aa;
}

.saw-file-input:focus + .saw-file-upload-trigger {
    outline: 2px solid #0073aa;
    outline-offset: 2px;
}

.saw-file-upload-trigger .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

/* Selected File Info */
.saw-file-selected-info {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #d1fae5;
    border: 1px solid #a7f3d0;
    border-radius: 6px;
    transition: all 0.15s ease;
}

.saw-file-selected-info.hidden {
    display: none;
}

.saw-file-selected-icon {
    flex-shrink: 0;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: #dcfce7;
    display: flex;
    align-items: center;
    justify-content: center;
}

.saw-file-selected-icon .dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
    color: #22c55e;
}

.saw-file-selected-details {
    flex: 1;
    min-width: 0;
}

.saw-file-selected-name {
    display: block;
    font-weight: 600;
    color: #166534;
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.saw-file-selected-meta {
    display: block;
    font-size: 12px;
    color: #15803d;
}

.saw-file-clear-btn {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    border: none;
    background: rgba(22, 101, 52, 0.1);
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s ease;
}

.saw-file-clear-btn:hover {
    background: rgba(220, 38, 38, 0.15);
}

.saw-file-clear-btn .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    color: #166534;
}

.saw-file-clear-btn:hover .dashicons {
    color: #dc2626;
}

/* ============================================
   SELECTION LIST STYLES
   ============================================ */

.saw-counter {
    display: inline-block;
    margin-left: 12px;
    padding: 4px 12px;
    background: #0073aa;
    color: white;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 600;
}

.saw-counter.has-selection {
    background: #00a32a;
}

.saw-counter.full-selection {
    background: #d63638;
}

.saw-selection-controls {
    display: flex;
    margin-bottom: 12px;
    gap: 12px;
    align-items: center;
}

.saw-selection-controls .saw-input {
    flex: 1;
    max-width: 300px;
    height: 36px;
    padding: 0 12px;
    font-size: 14px;
}

.saw-select-all-label {
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    cursor: pointer;
    user-select: none;
}

.saw-select-all-label input {
    margin: 0;
    cursor: pointer;
}

.saw-select-all-label span {
    font-weight: 600;
    color: #2c3338;
}

.saw-selection-list {
    border: 2px solid #dcdcde;
    border-radius: 6px;
    max-height: 280px;
    overflow-y: auto;
    background: #fff;
}

.saw-empty-message {
    padding: 20px;
    margin: 0;
    text-align: center;
    color: #757575;
}

.saw-selection-item {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f1;
    cursor: pointer;
    transition: background 0.15s ease;
    margin: 0;
}

.saw-selection-item:hover {
    background-color: #f6f7f7;
}

.saw-selection-item:last-child {
    border-bottom: none;
}

.saw-selection-item.hidden {
    display: none !important;
}

.saw-selection-item input[type="checkbox"] {
    margin: 0 12px 0 0;
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.saw-item-name {
    font-weight: 500;
    color: #1e1e1e;
}

/* ============================================
   GROUPED LIST STYLES (Oddělení)
   ============================================ */

.saw-grouped-list .saw-selection-group {
    border-bottom: 2px solid #e0e0e0;
}

.saw-grouped-list .saw-selection-group:last-child {
    border-bottom: none;
}

.saw-grouped-list .saw-selection-group.hidden {
    display: none !important;
}

.saw-group-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    font-weight: 600;
    font-size: 13px;
    position: sticky;
    top: 0;
    z-index: 1;
}

.saw-group-icon {
    font-size: 14px;
}

.saw-group-name {
    flex: 1;
}

.saw-group-count {
    font-size: 11px;
    font-weight: 400;
    opacity: 0.85;
    background: rgba(255,255,255,0.2);
    padding: 2px 8px;
    border-radius: 10px;
}

.saw-grouped-list .saw-selection-item {
    padding-left: 24px;
}

/* Textarea fix */
.saw-textarea {
    min-height: auto;
    resize: vertical;
}

/* ============================================
   LANGUAGE TABS STYLES
   ============================================ */

.saw-language-tabs-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 24px;
}

.saw-language-tabs {
    display: flex;
    gap: 8px;
    border-bottom: 2px solid #e0e0e0;
    padding-bottom: 0;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-behavior: smooth;
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge */
    flex: 1;
    min-width: 0;
}

.saw-language-tabs::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
}

.saw-language-tab-nav {
    flex-shrink: 0;
    width: 32px;
    height: 32px;
    padding: 0;
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.saw-language-tab-nav:hover:not(:disabled) {
    background: #0073aa;
    border-color: #0073aa;
    color: white;
}

.saw-language-tab-nav:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.saw-language-tab-nav .dashicons {
    font-size: 18px;
    width: 18px;
    height: 18px;
}

.saw-language-tab {
    padding: 12px 20px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    color: #50575e;
    transition: all 0.2s ease;
    margin-bottom: -2px;
}

.saw-language-tab:hover {
    color: #0073aa;
    background: #f6f7f7;
}

.saw-language-tab.active {
    color: #0073aa;
    border-bottom-color: #0073aa;
    background: #f6f7f7;
}

.saw-language-contents {
    position: relative;
}

.saw-language-content {
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* ============================================
   RESPONSIVE
   ============================================ */

@media (max-width: 600px) {
    .saw-file-upload-area {
        grid-template-columns: 1fr;
    }
    
    .saw-file-preview-box {
        width: 100%;
        height: 180px;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    
    // ========================================
    // GET TRANSLATIONS FROM DATA ATTRIBUTE
    // ========================================
    var $container = $('.saw-form-container');
    var translations = {};
    try {
        translations = JSON.parse($container.attr('data-translations') || '{}');
    } catch(e) {
        console.warn('Failed to parse translations');
    }
    
    function tr(key, fallback) {
        return translations[key] || fallback || key;
    }
    
    // ========================================
    // FILE UPLOAD COMPONENT
    // ========================================
    
    var $component = $('.saw-file-upload-component');
    var $input = $component.find('.saw-file-input');
    var $preview = $component.find('.saw-file-preview-box');
    var $selectedInfo = $component.find('.saw-file-selected-info');
    var $clearBtn = $component.find('.saw-file-clear-btn');
    var $helpText = $component.find('.saw-help-text');
    var $removeFlag = $component.find('.saw-file-remove-flag');
    
    var maxSize = parseInt($input.data('max-size')) || 2097152;
    var allowedTypes = ($input.attr('accept') || '').split(',').map(function(t) { return t.trim(); });
    var originalHelpText = $helpText.text();
    
    // File input change
    $input.on('change', function() {
        var file = this.files[0];
        if (!file) return;
        
        // Validate size
        if (file.size > maxSize) {
            var maxMB = (maxSize / 1024 / 1024).toFixed(1);
            var errorMsg = tr('file_too_large', 'Soubor je příliš velký. Maximální velikost je {size}MB.');
            showError(errorMsg.replace('{size}', maxMB));
            $input.val('');
            return;
        }
        
        // Validate type
        var isValidType = allowedTypes.some(function(type) {
            return file.type.match(type.replace('*', '.*'));
        });
        if (allowedTypes.length > 0 && !isValidType) {
            showError(tr('invalid_file_type', 'Neplatný typ souboru!'));
            $input.val('');
            return;
        }
        
        clearError();
        
        // Show preview for images
        if (file.type.startsWith('image/')) {
            var reader = new FileReader();
            reader.onload = function(e) {
                showPreview(e.target.result);
            };
            reader.readAsDataURL(file);
        }
        
        // Show file info
        showFileInfo(file);
        
        // Clear remove flag (we're uploading new file)
        $removeFlag.val('0');
    });
    
    // Remove overlay click
    $(document).on('click', '.saw-file-remove-overlay', function(e) {
        e.preventDefault();
        e.stopPropagation();
        removeFile();
    });
    
    // Clear button click
    $clearBtn.on('click', function(e) {
        e.preventDefault();
        $input.val('');
        $selectedInfo.addClass('hidden');
        clearError();
    });
    
    // Preview box click (trigger file input)
    $preview.on('click', function(e) {
        if (!$(e.target).closest('.saw-file-remove-overlay').length) {
            $input.trigger('click');
        }
    });
    
    // Drag and drop
    $preview.on('dragover dragenter', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).addClass('drag-over');
    });
    
    $preview.on('dragleave drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        $(this).removeClass('drag-over');
    });
    
    $preview.on('drop', function(e) {
        var files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            $input[0].files = files;
            $input.trigger('change');
        }
    });
    
    function showPreview(src) {
        $preview.html(
            '<img src="' + src + '" alt="Preview" class="saw-preview-image">' +
            '<button type="button" class="saw-file-remove-overlay" title="' + tr('remove', 'Odstranit') + '">' +
            '<span class="dashicons dashicons-no-alt"></span>' +
            '</button>'
        );
        $preview.addClass('has-file');
    }
    
    function showFileInfo(file) {
        var size = formatFileSize(file.size);
        var ext = file.name.split('.').pop().toUpperCase();
        
        $selectedInfo.find('.saw-file-selected-name').text(file.name);
        $selectedInfo.find('.saw-file-selected-meta').text(
            tr('file_size_label', 'Velikost') + ': ' + size + ' • ' + 
            tr('file_type_label', 'Typ') + ': ' + ext
        );
        $selectedInfo.removeClass('hidden');
    }
    
    function removeFile() {
        $input.val('');
        
        $preview.html(
            '<div class="saw-file-empty-state">' +
            '<div class="saw-file-icon-wrapper">' +
            '<span class="dashicons dashicons-format-image"></span>' +
            '</div>' +
            '<p class="saw-file-empty-text">' + tr('no_photo', 'Žádná fotografie') + '</p>' +
            '</div>'
        );
        $preview.removeClass('has-file');
        
        $selectedInfo.addClass('hidden');
        $removeFlag.val('1');
        
        clearError();
    }
    
    function showError(message) {
        $helpText.text(message).addClass('error').css('color', '#d63638');
    }
    
    function clearError() {
        $helpText.text(originalHelpText).removeClass('error').css('color', '');
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
    
    // ========================================
    // POBOČKY - Search, Select All, Counter
    // ========================================
    
    function updateBranchCounter() {
        var total = $('.branch-checkbox').length;
        var selected = $('.branch-checkbox:checked').length;
        $('#branch-selected').text(selected);
        $('#branch-total').text(total);
        
        var $counter = $('#branch-counter');
        $counter.removeClass('has-selection full-selection');
        if (selected > 0 && selected < total) {
            $counter.addClass('has-selection');
        } else if (selected === total && total > 0) {
            $counter.addClass('full-selection');
        }
        
        $('#select-all-branches').prop('checked', selected === total && total > 0);
        $('#select-all-branches').prop('indeterminate', selected > 0 && selected < total);
    }
    
    // Branch search
    $('#branch-search').on('input', function() {
        var query = $(this).val().toLowerCase().trim();
        $('#branches-list .saw-selection-item').each(function() {
            var name = $(this).data('name') || '';
            $(this).toggleClass('hidden', query !== '' && name.indexOf(query) === -1);
        });
    });
    
    // Branch select all
    $('#select-all-branches').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('#branches-list .saw-selection-item:not(.hidden) .branch-checkbox').prop('checked', isChecked);
        updateBranchCounter();
        filterDepartmentsByBranch();
    });
    
    // Branch checkbox change
    $(document).on('change', '.branch-checkbox', function() {
        updateBranchCounter();
        filterDepartmentsByBranch();
    });
    
    // Initial counter update
    updateBranchCounter();
    
    // ========================================
    // ODDĚLENÍ - Filtrování podle poboček
    // ========================================
    
    function filterDepartmentsByBranch() {
        var selectedBranchIds = [];
        $('.branch-checkbox:checked').each(function() {
            selectedBranchIds.push($(this).val());
        });
        
        var $departmentsList = $('#departments-list');
        var $groups = $departmentsList.find('.saw-selection-group');
        
        // Pokud není vybraná žádná pobočka, zobraz všechna oddělení
        if (selectedBranchIds.length === 0) {
            $groups.removeClass('hidden');
            $groups.find('.saw-selection-item').removeClass('hidden');
        } else {
            // Zobraz pouze oddělení pro vybrané pobočky
            $groups.each(function() {
                var branchId = $(this).data('branch-id').toString();
                var isVisible = selectedBranchIds.indexOf(branchId) !== -1;
                $(this).toggleClass('hidden', !isVisible);
            });
        }
        
        updateDepartmentCounter();
    }
    
    function updateDepartmentCounter() {
        // Počítej pouze viditelná oddělení
        var $visibleItems = $('#departments-list .saw-selection-group:not(.hidden) .saw-selection-item:not(.hidden)');
        var total = $visibleItems.length;
        var selected = $visibleItems.find('.department-checkbox:checked').length;
        
        $('#department-selected').text(selected);
        $('#department-total').text(total);
        
        var $counter = $('#department-counter');
        $counter.removeClass('has-selection full-selection');
        if (selected > 0 && selected < total) {
            $counter.addClass('has-selection');
        } else if (selected === total && total > 0) {
            $counter.addClass('full-selection');
        }
        
        $('#select-all-departments').prop('checked', selected === total && total > 0);
        $('#select-all-departments').prop('indeterminate', selected > 0 && selected < total);
    }
    
    // Department search
    $('#department-search').on('input', function() {
        var query = $(this).val().toLowerCase().trim();
        
        $('#departments-list .saw-selection-group:not(.hidden)').each(function() {
            var $group = $(this);
            var hasVisibleItems = false;
            
            $group.find('.saw-selection-item').each(function() {
                var name = $(this).data('name') || '';
                var isMatch = query === '' || name.indexOf(query) !== -1;
                $(this).toggleClass('hidden', !isMatch);
                if (isMatch) hasVisibleItems = true;
            });
            
            // Skryj celou skupinu pokud nemá žádné viditelné položky (při vyhledávání)
            if (query !== '') {
                $group.toggleClass('hidden', !hasVisibleItems);
            }
        });
        
        updateDepartmentCounter();
    });
    
    // Department select all (pouze viditelné)
    $('#select-all-departments').on('change', function() {
        var isChecked = $(this).prop('checked');
        $('#departments-list .saw-selection-group:not(.hidden) .saw-selection-item:not(.hidden) .department-checkbox').prop('checked', isChecked);
        updateDepartmentCounter();
    });
    
    // Department checkbox change
    $(document).on('change', '.department-checkbox', function() {
        updateDepartmentCounter();
    });
    
    // Initial filter and counter update
    filterDepartmentsByBranch();
    
    // ========================================
    // LANGUAGE TABS
    // ========================================
    
    var $tabsContainer = $('.saw-language-tabs');
    var $tabs = $('.saw-language-tab');
    var $prevBtn = $('.saw-language-tab-nav-prev');
    var $nextBtn = $('.saw-language-tab-nav-next');
    
    function updateNavButtons() {
        var scrollLeft = $tabsContainer.scrollLeft();
        var scrollWidth = $tabsContainer[0].scrollWidth;
        var clientWidth = $tabsContainer[0].clientWidth;
        
        $prevBtn.prop('disabled', scrollLeft === 0);
        $nextBtn.prop('disabled', scrollLeft >= scrollWidth - clientWidth - 1);
    }
    
    // Update buttons on scroll
    $tabsContainer.on('scroll', updateNavButtons);
    
    // Update buttons on resize
    $(window).on('resize', updateNavButtons);
    
    // Initial update
    updateNavButtons();
    
    // Navigation buttons
    $prevBtn.on('click', function() {
        if (!$(this).prop('disabled')) {
            $tabsContainer.animate({
                scrollLeft: $tabsContainer.scrollLeft() - 200
            }, 300);
        }
    });
    
    $nextBtn.on('click', function() {
        if (!$(this).prop('disabled')) {
            $tabsContainer.animate({
                scrollLeft: $tabsContainer.scrollLeft() + 200
            }, 300);
        }
    });
    
    // Tab click handler
    $tabs.on('click', function() {
        var $tab = $(this);
        var targetTab = $tab.data('tab');
        
        // Remove active class from all tabs
        $tabs.removeClass('active');
        
        // Add active class to clicked tab
        $tab.addClass('active');
        
        // Hide all content and remove required from all inputs
        $('.saw-language-content').each(function() {
            $(this).hide();
            $(this).find('input[type="text"], textarea').removeAttr('required');
            $(this).find('label').removeClass('saw-required');
            $(this).find('.saw-required-marker').remove();
        });
        
        // Show target content and add required to first input
        var $targetContent = $('.saw-language-content[data-tab-content="' + targetTab + '"]');
        $targetContent.show();
        var $firstInput = $targetContent.find('input[name*="[name]"]').first();
        if ($firstInput.length) {
            $firstInput.attr('required', 'required');
            $firstInput.closest('.saw-form-group').find('label').addClass('saw-required');
            if (!$firstInput.closest('.saw-form-group').find('.saw-required-marker').length) {
                $firstInput.closest('.saw-form-group').find('label').append('<span class="saw-required-marker">*</span>');
            }
        }
        
        // Scroll clicked tab into view
        var tabOffset = $tab.position().left + $tabsContainer.scrollLeft();
        var tabWidth = $tab.outerWidth();
        var containerWidth = $tabsContainer.outerWidth();
        var scrollLeft = $tabsContainer.scrollLeft();
        
        if (tabOffset < scrollLeft) {
            // Tab is to the left, scroll to show it
            $tabsContainer.animate({
                scrollLeft: tabOffset - 20
            }, 300);
        } else if (tabOffset + tabWidth > scrollLeft + containerWidth) {
            // Tab is to the right, scroll to show it
            $tabsContainer.animate({
                scrollLeft: tabOffset - containerWidth + tabWidth + 20
            }, 300);
        }
    });
    
    // ========================================
    // RADIO CARDS - is_global
    // ========================================
    
    $('.saw-radio-card input[type="radio"]').on('change', function() {
        var $card = $(this).closest('.saw-radio-card');
        $('.saw-radio-card').removeClass('selected');
        $card.addClass('selected');
    });
    
    // Initialize selected state
    $('.saw-radio-card input[type="radio"]:checked').each(function() {
        $(this).closest('.saw-radio-card').addClass('selected');
    });
    
    // ========================================
    // ✅ FIX: AJAX SIDEBAR FORM SUBMISSION
    // ========================================
    
    var $form = $('.saw-oopp-form');
    var $sidebar = $form.closest('.saw-sidebar');
    
    // Only handle if form is inside a sidebar
    if ($sidebar.length) {
        
        // Handle form submission via AJAX
        $form.off('submit.sidebar').on('submit.sidebar', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            var formData = new FormData(this);
            
            // Get mode from sidebar
            var mode = $sidebar.data('mode') || 'create';
            
            // Determine AJAX action
            var action = mode === 'edit' ? 'saw_edit_oopp' : 'saw_create_oopp';
            
            // Get AJAX URL and nonce
            var ajaxUrl = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.ajaxurl) 
                ? window.sawGlobal.ajaxurl 
                : '/wp-admin/admin-ajax.php';
            
            var nonce = (typeof window.sawGlobal !== 'undefined' && window.sawGlobal.nonce) 
                ? window.sawGlobal.nonce 
                : '';
            
            if (!nonce) {
                console.error('[OOPP] Missing nonce');
                alert('Chyba: Nelze ověřit požadavek. Zkuste obnovit stránku.');
                return false;
            }
            
            // Add action and nonce to form data
            formData.append('action', action);
            formData.append('nonce', nonce);
            
            // Show loading state
            var $submitBtn = $form.find('button[type="submit"]');
            var originalHtml = $submitBtn.html();
            $submitBtn.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span> Ukládám...');
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        var entityId = response.data.id;
                        var detailUrl = window.location.origin + '/admin/oopp/' + entityId + '/';
                        
                        console.log('[OOPP] Successfully saved, navigating to:', detailUrl);
                        
                        // Show success message
                        if (typeof window.sawShowToast === 'function') {
                            window.sawShowToast(response.data.message || 'OOPP byl úspěšně uložen', 'success');
                        }
                        
                        // Navigate to detail
                        setTimeout(function() {
                            if (window.viewTransition && typeof window.viewTransition.navigateTo === 'function') {
                                window.viewTransition.navigateTo(detailUrl);
                            } else {
                                window.location.href = detailUrl;
                            }
                        }, 300);
                    } else {
                        var errorMsg = response.data?.message || 'Chyba při ukládání';
                        if (typeof window.sawShowToast === 'function') {
                            window.sawShowToast(errorMsg, 'error');
                        } else {
                            alert(errorMsg);
                        }
                        $submitBtn.prop('disabled', false).html(originalHtml);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[OOPP] AJAX Error:', status, error, xhr.responseText);
                    
                    var errorMsg = 'Chyba při ukládání';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        errorMsg = xhr.responseJSON.data.message;
                    }
                    
                    if (typeof window.sawShowToast === 'function') {
                        window.sawShowToast(errorMsg, 'error');
                    } else {
                        alert(errorMsg);
                    }
                    
                    $submitBtn.prop('disabled', false).html(originalHtml);
                }
            });
            
            return false;
        });
        
        console.log('[OOPP] Sidebar AJAX handler initialized');
    }
    
});
</script>