<?php
/**
 * OOPP Form Template
 * 
 * Form for creating/editing OOPP (Osobní ochranné pracovní prostředky)
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/OOPP
 * @version     1.0.0
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
    $branches_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM {$wpdb->prefix}saw_branches WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $customer_id
    ), ARRAY_A);
    $branches = $branches_data;
}

// Get departments for multiselect
$departments = array();
if ($customer_id) {
    $departments_data = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name FROM {$wpdb->prefix}saw_departments WHERE customer_id = %d AND is_active = 1 ORDER BY name ASC",
        $customer_id
    ), ARRAY_A);
    $departments = $departments_data;
}

// Selected branch/department IDs
$selected_branch_ids = $item['branch_ids'] ?? array();
$selected_department_ids = $item['department_ids'] ?? array();

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
                
                <!-- Fotografie -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <?php
                        // File upload component
                        $id = 'image';
                        $name = 'image';
                        $current_file_url = !empty($item['image_url']) ? $item['image_url'] : '';
                        $label = 'Nahrát fotografii';
                        $current_label = 'Současná fotografie';
                        $help_text = 'Nahrajte obrázek ve formátu JPG, PNG, GIF nebo WebP (max 2MB)';
                        $accept = 'image/jpeg,image/png,image/gif,image/webp';
                        $show_preview = true;
                        $config = array();
                        
                        require SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php';
                        ?>
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
                        </label>
                        <p class="saw-help-text" style="margin-bottom: 12px;">
                            Vyberte pobočky, pro které platí tento OOPP. Pokud nic nevyberete, platí pro všechny pobočky.
                        </p>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 12px; background: #fff;">
                            <?php if (empty($branches)): ?>
                                <p class="saw-text-muted">Žádné pobočky k dispozici</p>
                            <?php else: ?>
                                <?php foreach ($branches as $branch): ?>
                                    <label class="saw-checkbox-label" style="display: block; margin-bottom: 8px;">
                                        <input 
                                            type="checkbox" 
                                            name="branch_ids[]" 
                                            value="<?php echo esc_attr($branch['id']); ?>"
                                            <?php checked(in_array($branch['id'], $selected_branch_ids)); ?>
                                        >
                                        <span><?php echo esc_html($branch['name']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Oddělení -->
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label class="saw-label">
                            Oddělení
                        </label>
                        <p class="saw-help-text" style="margin-bottom: 12px;">
                            Vyberte oddělení, pro která platí tento OOPP. Pokud nic nevyberete, platí pro všechna oddělení.
                        </p>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px; padding: 12px; background: #fff;">
                            <?php if (empty($departments)): ?>
                                <p class="saw-text-muted">Žádná oddělení k dispozici</p>
                            <?php else: ?>
                                <?php foreach ($departments as $dept): ?>
                                    <label class="saw-checkbox-label" style="display: block; margin-bottom: 8px;">
                                        <input 
                                            type="checkbox" 
                                            name="department_ids[]" 
                                            value="<?php echo esc_attr($dept['id']); ?>"
                                            <?php checked(in_array($dept['id'], $selected_department_ids)); ?>
                                        >
                                        <span><?php echo esc_html($dept['name']); ?></span>
                                    </label>
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
                            class="saw-input" 
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
                            class="saw-input" 
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
                            class="saw-input" 
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
                            class="saw-input" 
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
                            class="saw-input" 
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
                            class="saw-input" 
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

