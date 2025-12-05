<?php
/**
 * OOPP Form Template
 * 
 * Form for creating/editing OOPP (Osobní ochranné pracovní prostředky)
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/OOPP
 * @version     1.4.0 - FIXED: image_path vs image_url, proper URL generation
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if we're in sidebar mode
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Determine if this is edit mode
$is_edit = !empty($item);
$item = $item ?? array();

// Get current customer context
$customer_id = class_exists('SAW_Context') ? SAW_Context::get_customer_id() : 0;

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
                'branch_name' => $dept['branch_name'] ?: 'Bez pobočky',
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
?>

<?php if (!$in_sidebar): ?>
<!-- Page Header (only when NOT in sidebar) -->
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? 'Upravit OOPP' : 'Nový OOPP'; ?>
        </h1>
        <a href="<?php echo esc_url(home_url('/admin/oopp/')); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            Zpět na seznam
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Form Container -->
<div class="saw-form-container saw-module-oopp">
    <form method="POST" action="<?php echo esc_url($form_action); ?>" enctype="multipart/form-data" class="saw-oopp-form">
        <?php 
        $nonce_action = $is_edit ? 'saw_edit_oopp' : 'saw_create_oopp';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
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
                <span class="dashicons dashicons-admin-generic"></span>
                <strong>Základní informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Skupina OOPP -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="group_id" class="saw-label saw-required">
                            Skupina OOPP
                        </label>
                        <select 
                            name="group_id" 
                            id="group_id" 
                            class="saw-input" 
                            required
                        >
                            <option value="">-- Vyberte skupinu --</option>
                            <?php foreach ($oopp_groups as $group): ?>
                                <option 
                                    value="<?php echo esc_attr($group['id']); ?>"
                                    <?php selected($item['group_id'] ?? '', $group['id']); ?>
                                >
                                    <?php echo esc_html($group['code'] . '. ' . $group['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="saw-help-text">Vyberte skupinu, do které OOPP patří</p>
                    </div>
                </div>
                
                <!-- Název -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="name" class="saw-label saw-required">
                            Název
                        </label>
                        <input 
                            type="text" 
                            name="name" 
                            id="name" 
                            class="saw-input" 
                            value="<?php echo esc_attr($item['name'] ?? ''); ?>"
                            placeholder="např. Ochranné brýle proti UV záření"
                            required
                        >
                    </div>
                </div>
                
                <!-- ================================================ -->
                <!-- FOTOGRAFIE - FILE UPLOAD COMPONENT -->
                <!-- ================================================ -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label">Fotografie</label>
                        
                        <div class="saw-file-upload-component" data-context="oopp">
                            <div class="saw-file-upload-area">
                                
                                <!-- Preview Section -->
                                <div class="saw-file-preview-section">
                                    <div class="saw-file-preview-box<?php echo $has_image ? ' has-file' : ''; ?>">
                                        <?php if ($has_image): ?>
                                            <img src="<?php echo esc_url($current_image_url); ?>" alt="Aktuální fotografie" class="saw-preview-image">
                                            <button type="button" class="saw-file-remove-overlay" title="Odstranit fotografii">
                                                <span class="dashicons dashicons-no-alt"></span>
                                            </button>
                                        <?php else: ?>
                                            <div class="saw-file-empty-state">
                                                <div class="saw-file-icon-wrapper">
                                                    <span class="dashicons dashicons-format-image"></span>
                                                </div>
                                                <p class="saw-file-empty-text">Žádná fotografie</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($has_image): ?>
                                        <p class="saw-current-file-label">Současná fotografie</p>
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
                                        <span class="dashicons dashicons-upload"></span>
                                        Nahrát fotografii
                                    </label>
                                    
                                    <!-- Selected File Info (hidden by default) -->
                                    <div class="saw-file-selected-info hidden">
                                        <div class="saw-file-selected-icon">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                        </div>
                                        <div class="saw-file-selected-details">
                                            <span class="saw-file-selected-name"></span>
                                            <span class="saw-file-selected-meta"></span>
                                        </div>
                                        <button type="button" class="saw-file-clear-btn" title="Zrušit výběr">
                                            <span class="dashicons dashicons-dismiss"></span>
                                        </button>
                                    </div>
                                    
                                    <p class="saw-help-text">
                                        Nahrajte obrázek ve formátu JPG, PNG, GIF nebo WebP (max 2MB)
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
                <span class="dashicons dashicons-location"></span>
                <strong>Platnost</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Pobočky -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label">
                            Pobočky
                            <span id="branch-counter" class="saw-counter">
                                <span id="branch-selected"><?php echo count($selected_branch_ids); ?></span> / <span id="branch-total"><?php echo count($branches); ?></span>
                            </span>
                        </label>
                        <p class="saw-help-text" style="margin-bottom: 12px;">
                            Vyberte pobočky, pro které platí tento OOPP. Pokud nic nevyberete, platí pro všechny pobočky.
                        </p>
                        
                        <div class="saw-selection-controls">
                            <input type="text" id="branch-search" class="saw-input" placeholder="🔍 Hledat pobočku...">
                            <label class="saw-select-all-label">
                                <input type="checkbox" id="select-all-branches">
                                <span>Vybrat vše</span>
                            </label>
                        </div>
                        
                        <div id="branches-list" class="saw-selection-list">
                            <?php if (empty($branches)): ?>
                                <p class="saw-empty-message">Žádné pobočky k dispozici</p>
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
                            Oddělení
                            <span id="department-counter" class="saw-counter">
                                <span id="department-selected"><?php echo count($selected_department_ids); ?></span> / <span id="department-total"><?php echo $total_departments; ?></span>
                            </span>
                        </label>
                        <p class="saw-help-text" style="margin-bottom: 12px;">
                            Zobrazují se oddělení pro vybrané pobočky. Pokud není vybraná žádná pobočka, zobrazují se všechna oddělení.
                        </p>
                        
                        <div class="saw-selection-controls">
                            <input type="text" id="department-search" class="saw-input" placeholder="🔍 Hledat oddělení...">
                            <label class="saw-select-all-label">
                                <input type="checkbox" id="select-all-departments">
                                <span>Vybrat vše</span>
                            </label>
                        </div>
                        
                        <div id="departments-list" class="saw-selection-list saw-grouped-list">
                            <?php if (empty($departments_by_branch)): ?>
                                <p class="saw-empty-message">Žádná oddělení k dispozici</p>
                            <?php else: ?>
                                <?php foreach ($departments_by_branch as $branch_id => $group): ?>
                                    <div class="saw-selection-group" data-branch-id="<?php echo esc_attr($branch_id); ?>">
                                        <div class="saw-group-header">
                                            <span class="saw-group-icon">🏢</span>
                                            <span class="saw-group-name"><?php echo esc_html($group['branch_name']); ?></span>
                                            <span class="saw-group-count"><?php echo count($group['departments']); ?> oddělení</span>
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
        <!-- TECHNICKÉ INFORMACE -->
        <!-- ================================================ -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-admin-settings"></span>
                <strong>Technické informace</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Normy -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="standards" class="saw-label">
                            Související předpisy / normy
                        </label>
                        <textarea 
                            name="standards" 
                            id="standards" 
                            class="saw-input saw-textarea" 
                            rows="3"
                            placeholder="např. ČSN EN 166, EN 172..."
                        ><?php echo esc_textarea($item['standards'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Rizika -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="risk_description" class="saw-label">
                            Popis rizik, proti kterým OOPP chrání
                        </label>
                        <textarea 
                            name="risk_description" 
                            id="risk_description" 
                            class="saw-input saw-textarea" 
                            rows="4"
                            placeholder="Popište rizika, před kterými tento prostředek chrání..."
                        ><?php echo esc_textarea($item['risk_description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Ochranné vlastnosti -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="protective_properties" class="saw-label">
                            Ochranné vlastnosti
                        </label>
                        <textarea 
                            name="protective_properties" 
                            id="protective_properties" 
                            class="saw-input saw-textarea" 
                            rows="4"
                            placeholder="Popište ochranné vlastnosti prostředku..."
                        ><?php echo esc_textarea($item['protective_properties'] ?? ''); ?></textarea>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- POKYNY -->
        <!-- ================================================ -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-info"></span>
                <strong>Pokyny</strong>
            </summary>
            <div class="saw-form-section-content">
                
                <!-- Použití -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="usage_instructions" class="saw-label">
                            Pokyny pro použití
                        </label>
                        <textarea 
                            name="usage_instructions" 
                            id="usage_instructions" 
                            class="saw-input saw-textarea" 
                            rows="4"
                            placeholder="Jak správně používat tento prostředek..."
                        ><?php echo esc_textarea($item['usage_instructions'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Údržba -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="maintenance_instructions" class="saw-label">
                            Pokyny pro údržbu
                        </label>
                        <textarea 
                            name="maintenance_instructions" 
                            id="maintenance_instructions" 
                            class="saw-input saw-textarea" 
                            rows="3"
                            placeholder="Jak správně udržovat a čistit prostředek..."
                        ><?php echo esc_textarea($item['maintenance_instructions'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <!-- Skladování -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="storage_instructions" class="saw-label">
                            Pokyny pro skladování
                        </label>
                        <textarea 
                            name="storage_instructions" 
                            id="storage_instructions" 
                            class="saw-input saw-textarea" 
                            rows="3"
                            placeholder="Jak správně skladovat prostředek..."
                        ><?php echo esc_textarea($item['storage_instructions'] ?? ''); ?></textarea>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- ================================================ -->
        <!-- NASTAVENÍ -->
        <!-- ================================================ -->
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-admin-settings"></span>
                <strong>Nastavení</strong>
            </summary>
            <div class="saw-form-section-content">
                
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
                            <span>Aktivní</span>
                        </label>
                        <p class="saw-help-text">Neaktivní OOPP se nezobrazí v seznamu pro výběr</p>
                    </div>
                </div>
                
                <!-- Pořadí zobrazení -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="display_order" class="saw-label">
                            Pořadí zobrazení
                        </label>
                        <input 
                            type="number" 
                            name="display_order" 
                            id="display_order" 
                            class="saw-input" 
                            value="<?php echo esc_attr($item['display_order'] ?? 0); ?>"
                            min="0"
                        >
                        <p class="saw-help-text">Nižší číslo = výše v seznamu</p>
                    </div>
                </div>
                
            </div>
        </details>
        
        <!-- Tlačítka -->
        <div class="saw-form-actions">
            <button type="submit" class="saw-btn saw-btn-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php echo $is_edit ? 'Uložit změny' : 'Vytvořit OOPP'; ?>
            </button>
            <a href="<?php echo esc_url(home_url('/admin/oopp/')); ?>" class="saw-btn saw-btn-secondary">
                Zrušit
            </a>
        </div>
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
            showError('Soubor je příliš velký. Maximální velikost je ' + maxMB + 'MB.');
            $input.val('');
            return;
        }
        
        // Validate type
        var isValidType = allowedTypes.some(function(type) {
            return file.type.match(type.replace('*', '.*'));
        });
        if (allowedTypes.length > 0 && !isValidType) {
            showError('Neplatný typ souboru!');
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
            '<button type="button" class="saw-file-remove-overlay" title="Odstranit">' +
            '<span class="dashicons dashicons-no-alt"></span>' +
            '</button>'
        );
        $preview.addClass('has-file');
    }
    
    function showFileInfo(file) {
        var size = formatFileSize(file.size);
        var ext = file.name.split('.').pop().toUpperCase();
        
        $selectedInfo.find('.saw-file-selected-name').text(file.name);
        $selectedInfo.find('.saw-file-selected-meta').text('Velikost: ' + size + ' • Typ: ' + ext);
        $selectedInfo.removeClass('hidden');
    }
    
    function removeFile() {
        $input.val('');
        
        $preview.html(
            '<div class="saw-file-empty-state">' +
            '<div class="saw-file-icon-wrapper">' +
            '<span class="dashicons dashicons-format-image"></span>' +
            '</div>' +
            '<p class="saw-file-empty-text">Žádná fotografie</p>' +
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
    
});
</script>