<?php
/**
 * Branches Form Template - REFACTORED
 * * UPDATED to match 'schema-branches.php' column names.
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches/Templates
 * @since       9.0.0 (Refactored)
 * @version     12.0.1 (Schema-Fix)
 */

if (!defined('ABSPATH')) {
    exit;
}

$is_edit = !empty($item);
$item = $item ?? array();
$in_sidebar = isset($GLOBALS['saw_sidebar_form']) && $GLOBALS['saw_sidebar_form'];

// Decode opening hours JSON for the form
$opening_hours = array();
if (!empty($item['opening_hours'])) {
    $opening_hours = json_decode($item['opening_hours'], true);
}
if (!is_array($opening_hours)) {
    $opening_hours = array();
}

$days = array(
    'monday' => __('Pondělí', 'saw-visitors'),
    'tuesday' => __('Úterý', 'saw-visitors'),
    'wednesday' => __('Středa', 'saw-visitors'),
    'thursday' => __('Čtvrtek', 'saw-visitors'),
    'friday' => __('Pátek', 'saw-visitors'),
    'saturday' => __('Sobota', 'saw-visitors'),
    'sunday' => __('Neděle', 'saw-visitors'),
);

?>

<?php if (!$in_sidebar): ?>
<div class="saw-page-header">
    <div class="saw-page-header-content">
        <h1 class="saw-page-title">
            <?php echo $is_edit ? esc_html__('Upravit pobočku', 'saw-visitors') : esc_html__('Nová pobočka', 'saw-visitors'); ?>
        </h1>
        <?php
        $back_url = $is_edit 
            ? home_url('/admin/branches/' . ($item['id'] ?? '') . '/') 
            : home_url('/admin/branches/');
        ?>
        <a href="<?php echo esc_url($back_url); ?>" class="saw-back-button">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
            <?php echo esc_html__('Zpět na seznam', 'saw-visitors'); ?>
        </a>
    </div>
</div>
<?php endif; ?>

<div class="saw-form-container saw-module-branches">
    <form method="post" action="" enctype="multipart/form-data" class="saw-branch-form">
        <?php
        $nonce_action = $is_edit ? 'saw_edit_branches' : 'saw_create_branches';
        wp_nonce_field($nonce_action, '_wpnonce', false);
        ?>
        
        <?php if ($is_edit): ?>
            <input type="hidden" name="id" id="branch_id" value="<?php echo esc_attr($item['id']); ?>">
        <?php endif; ?>
        
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-admin-generic"></span>
                <strong><?php echo esc_html__('Základní informace', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-8">
                        <label for="name" class="saw-label saw-required"><?php echo esc_html__('Název pobočky', 'saw-visitors'); ?></label>
                        <input type="text" id="name" name="name" class="saw-input"
                               value="<?php echo esc_attr($item['name'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="saw-form-group saw-col-4">
                        <label for="code" class="saw-label"><?php echo esc_html__('Kód pobočky', 'saw-visitors'); ?></label>
                        <input type="text" id="code" name="code" class="saw-input"
                               value="<?php echo esc_attr($item['code'] ?? ''); ?>" placeholder=" např. P01">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-4">
                        <div class="saw-checkbox-group">
                            <input type="checkbox" id="is_headquarters" name="is_headquarters" value="1"
                                   <?php checked($item['is_headquarters'] ?? 0, 1); ?>>
                            <label for="is_headquarters"><?php echo esc_html__('Toto je sídlo firmy', 'saw-visitors'); ?></label>
                        </div>
                    </div>
                    <div class="saw-form-group saw-col-4">
                         <label for="is_active" class="saw-label"><?php echo esc_html__('Status', 'saw-visitors'); ?></label>
                        <select id="is_active" name="is_active" class="saw-input">
                            <option value="1" <?php selected($item['is_active'] ?? 1, 1); ?>><?php echo esc_html__('Aktivní', 'saw-visitors'); ?></option>
                            <option value="0" <?php selected($item['is_active'] ?? 1, 0); ?>><?php echo esc_html__('Neaktivní', 'saw-visitors'); ?></option>
                        </select>
                    </div>
                    <div class="saw-form-group saw-col-4">
                        <label for="sort_order" class="saw-label"><?php echo esc_html__('Pořadí', 'saw-visitors'); ?></label>
                        <input type="number" id="sort_order" name="sort_order" class="saw-input"
                               value="<?php echo esc_attr($item['sort_order'] ?? 10); ?>">
                    </div>
                </div>
            </div>
        </details>
        
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-format-image"></span>
                <strong><?php echo esc_html__('Obrázek pobočky', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <?php
                        $id = 'image_url';
                        $name = 'image_url';
                        $current_file_url = $item['image_url'] ?? '';
                        $label = __('Nahrát obrázek', 'saw-visitors');
                        $current_label = __('Současný obrázek', 'saw-visitors');
                        $help_text = __('JPG, PNG, SVG nebo WebP (max 2MB)', 'saw-visitors');
                        $accept = 'image/jpeg,image/png,image/svg+xml,image/webp';
                        $show_preview = true;
                        $config = array('preview_class' => 'saw-branch-thumbnail-preview');
                        
                        // Use global component
                        require SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php';
                        ?>
                    </div>
                </div>
            </div>
        </details>
        
        <details class="saw-form-section" open>
            <summary>
                <span class="dashicons dashicons-location"></span>
                <strong><?php echo esc_html__('Adresa a GPS', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="street" class="saw-label"><?php echo esc_html__('Ulice a č.p.', 'saw-visitors'); ?></label>
                        <input type="text" id="street" name="street" class="saw-input"
                               value="<?php echo esc_attr($item['street'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="city" class="saw-label"><?php echo esc_html__('Město', 'saw-visitors'); ?></label>
                        <input type="text" id="city" name="city" class="saw-input"
                               value="<?php echo esc_attr($item['city'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-3">
                        <label for="postal_code" class="saw-label"><?php echo esc_html__('PSČ', 'saw-visitors'); ?></label>
                        <input type="text" id="postal_code" name="postal_code" class="saw-input"
                               value="<?php echo esc_attr($item['postal_code'] ?? ''); ?>">
                    </div>

                    <div class="saw-form-group saw-col-3">
                        <label for="country" class="saw-label"><?php echo esc_html__('Země (kód)', 'saw-visitors'); ?></label>
                        <input type="text" id="country" name="country" class="saw-input"
                               value="<?php echo esc_attr($item['country'] ?? 'CZ'); ?>" maxlength="2">
                    </div>
                </div>
                
                <hr class="saw-form-divider">
                
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="latitude" class="saw-label"><?php echo esc_html__('GPS Latitude', 'saw-visitors'); ?></label>
                        <input type="text" id="latitude" name="latitude" class="saw-input"
                               value="<?php echo esc_attr($item['latitude'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="longitude" class="saw-label"><?php echo esc_html__('GPS Longitude', 'saw-visitors'); ?></label>
                        <input type="text" id="longitude" name="longitude" class="saw-input"
                               value="<?php echo esc_attr($item['longitude'] ?? ''); ?>">
                    </div>
                </div>
                <button type="button" id="saw-get-gps-btn" class="saw-button saw-button-secondary saw-get-gps-btn">
                    <span class="dashicons dashicons-location"></span>
                    <?php echo esc_html__('Načíst souřadnice z adresy', 'saw-visitors'); ?>
                </button>
            </div>
        </details>
        
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-clock"></span>
                <strong><?php echo esc_html__('Otevírací doba', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content" id="saw-opening-hours-editor">
                
                <div class="saw-opening-hours-templates">
                    <button type="button" class="saw-template-btn" id="btn-template-nonstop"><?php echo esc_html__('Nonstop', 'saw-visitors'); ?></button>
                    <button type="button" class="saw-template-btn" id="btn-template-workdays"><?php echo esc_html__('Po-Pá (8-17)', 'saw-visitors'); ?></button>
                    <button type="button" class="saw-template-btn" id="btn-template-closed"><?php echo esc_html__('Zavřeno', 'saw-visitors'); ?></button>
                </div>
                
                <?php foreach ($days as $day_key => $day_label): ?>
                    <?php
                    $is_open = $opening_hours[$day_key]['is_open'] ?? 'closed';
                    $open_from = $opening_hours[$day_key]['open_from'] ?? '';
                    $open_to = $opening_hours[$day_key]['open_to'] ?? '';
                    ?>
                    <div class="saw-form-row saw-opening-hours-row">
                        <div class="saw-form-group saw-col-4">
                            <label class="saw-label"><?php echo esc_html($day_label); ?></label>
                        </div>
                        <div class="saw-form-group saw-col-3">
                            <select name="opening_hours[<?php echo esc_attr($day_key); ?>][is_open]" class="saw-input oh-status">
                                <option value="open" <?php selected($is_open, 'open'); ?>><?php echo esc_html__('Otevřeno', 'saw-visitors'); ?></option>
                                <option value="closed" <?php selected($is_open, 'closed'); ?>><?php echo esc_html__('Zavřeno', 'saw-visitors'); ?></option>
                                <option value="nonstop" <?php selected($is_open, 'nonstop'); ?>><?php echo esc_html__('Nonstop', 'saw-visitors'); ?></option>
                            </select>
                        </div>
                        <div class="saw-form-group saw-col-5 oh-times <?php echo $is_open !== 'open' ? 'saw-hidden' : ''; ?>">
                            <input type="time" name="opening_hours[<?php echo esc_attr($day_key); ?>][open_from]" class="saw-input" value="<?php echo esc_attr($open_from); ?>">
                            <span>-</span>
                            <input type="time" name="opening_hours[<?php echo esc_attr($day_key); ?>][open_to]" class="saw-input" value="<?php echo esc_attr($open_to); ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>
        
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-email"></span>
                <strong><?php echo esc_html__('Kontaktní údaje', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-6">
                        <label for="email" class="saw-label"><?php echo esc_html__('E-mail', 'saw-visitors'); ?></label>
                        <input type="email" id="email" name="email" class="saw-input"
                               value="<?php echo esc_attr($item['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="saw-form-group saw-col-6">
                        <label for="phone" class="saw-label"><?php echo esc_html__('Telefon', 'saw-visitors'); ?></label>
                        <input type="text" id="phone" name="phone" class="saw-input"
                               value="<?php echo esc_attr($item['phone'] ?? ''); ?>">
                    </div>
                </div>
            </div>
        </details>
        
        <details class="saw-form-section">
            <summary>
                <span class="dashicons dashicons-edit-page"></span>
                <strong><?php echo esc_html__('Poznámky a Popis', 'saw-visitors'); ?></strong>
            </summary>
            <div class="saw-form-section-content">
                <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="description" class="saw-label"><?php echo esc_html__('Popis', 'saw-visitors'); ?></label>
                        <textarea id="description" name="description" class="saw-input" rows="3"><?php echo esc_textarea($item['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                 <div class="saw-form-row">
                    <div class="saw-form-group saw-col-12">
                        <label for="notes" class="saw-label"><?php echo esc_html__('Interní poznámky', 'saw-visitors'); ?></label>
                        <textarea id="notes" name="notes" class="saw-input" rows="3"><?php echo esc_textarea($item['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </details>
        
        <div class="saw-form-actions">
            <button type="submit" class="saw-button saw-button-primary">
                <span class="dashicons dashicons-yes"></span>
                <?php echo $is_edit ? esc_html__('Uložit změny', 'saw-visitors') : esc_html__('Vytvořit pobočku', 'saw-visitors'); ?>
            </button>
            
            <button type="button" class="saw-button saw-button-secondary saw-form-cancel-btn">
                <span class="dashicons dashicons-dismiss"></span>
                <?php echo esc_html__('Zrušit', 'saw-visitors'); ?>
            </button>
        </div>
    </form>
</div>