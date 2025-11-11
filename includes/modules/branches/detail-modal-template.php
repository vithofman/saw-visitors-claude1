<?php
/**
 * Branches Detail Template - REFACTORED
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

// Validate data
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">';
    echo '<strong>' . esc_html__('Chyba:', 'saw-visitors') . '</strong> ';
    echo esc_html__('Pobočka nebyla nalezena nebo data nejsou dostupná.', 'saw-visitors');
    echo '</div>';
    return;
}

// Data prepared in controller->format_detail_data()
$opening_hours = $item['opening_hours_array'] ?? array();
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

<div class="saw-detail-header saw-module-branches">
    <?php if (!empty($item['image_url'])): // Use 'image_url' ?>
        <img src="<?php echo esc_url($item['image_url']); ?>" 
             alt="<?php echo esc_attr($item['name']); ?>" 
             class="saw-detail-logo saw-branch-thumbnail">
    <?php else: ?>
        <div class="saw-detail-logo-placeholder">
            <span class="dashicons dashicons-store"></span>
        </div>
    <?php endif; ?>
    
    <div class="saw-detail-header-content">
        <h2 class="saw-detail-header-title">
            <?php echo esc_html($item['name']); ?>
        </h2>
        
        <div class="saw-detail-header-badges">
            <?php if (!empty($item['is_headquarters'])): ?>
                <span class="saw-badge saw-badge-primary">
                    <?php echo esc_html__('Sídlo firmy', 'saw-visitors'); ?>
                </span>
            <?php endif; ?>
            
            <?php if (!empty($item['code'])): // Use 'code' ?>
                <span class="saw-badge saw-badge-light">
                    Kód: <?php echo esc_html($item['code']); ?>
                </span>
            <?php endif; ?>

            <span class="saw-badge saw-badge-light">
                ID: <?php echo esc_html($item['id']); ?>
            </span>
        </div>
    </div>
</div>

<div class="saw-detail-sections saw-module-branches">
    
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">📞</span>
            <?php echo esc_html__('Kontakt a Adresa', 'saw-visitors'); ?>
        </h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['phone'])): ?>
                <div>
                    <dt><?php echo esc_html__('Telefon:', 'saw-visitors'); ?></dt>
                    <dd>
                        <a href="tel:<?php echo esc_attr($item['phone']); ?>" class="saw-phone-link">
                            <?php echo esc_html($item['phone']); ?>
                        </a>
                    </dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['email'])): ?>
                <div>
                    <dt><?php echo esc_html__('Email:', 'saw-visitors'); ?></dt>
                    <dd>
                        <a href="mailto:<?php echo esc_attr($item['email']); ?>">
                            <?php echo esc_html($item['email']); ?>
                        </a>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>

        <?php if (!empty($item['full_address'])): ?>
            <div class="saw-detail-address">
                <?php if (!empty($item['street'])): // Use 'street' ?>
                    <div><?php echo esc_html($item['street']); ?></div>
                <?php endif; ?>
                <div><?php echo esc_html(trim(($item['postal_code'] ?? '') . ' ' . ($item['city'] ?? ''))); // Use 'postal_code' and 'city' ?></div>
                <?php if (!empty($item['country'])): ?>
                    <div><?php echo esc_html($item['country']); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($item['map_link'])): ?>
            <div class="saw-detail-action">
                <a href="<?php echo esc_url($item['map_link']); ?>" target="_blank" rel="noopener" class="saw-map-link">
                    <span class="dashicons dashicons-location"></span>
                    <?php echo esc_html__('Zobrazit na mapě', 'saw-visitors'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($opening_hours)): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">🕒</span>
            <?php echo esc_html__('Otevírací doba', 'saw-visitors'); ?>
        </h3>
        <ul class="saw-opening-hours-list">
            <?php foreach ($days as $day_key => $day_label): ?>
                <?php
                $status = $opening_hours[$day_key]['is_open'] ?? 'closed';
                $from = $opening_hours[$day_key]['open_from'] ?? '';
                $to = $opening_hours[$day_key]['open_to'] ?? '';
                $display_time = '';
                
                if ($status === 'nonstop') {
                    $display_time = '<strong>' . __('Nonstop', 'saw-visitors') . '</strong>';
                } elseif ($status === 'open') {
                    $display_time = '<strong>' . esc_html($from) . ' - ' . esc_html($to) . '</strong>';
                } else {
                    $display_time = '<span class="saw-text-muted">' . __('Zavřeno', 'saw-visitors') . '</span>';
                }
                ?>
                <li>
                    <span><?php echo esc_html($day_label); ?>:</span>
                    <?php echo $display_time; // WPCS: XSS ok. ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($item['description'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="dashicons dashicons-clipboard"></span>
            <?php echo esc_html__('Popis', 'saw-visitors'); ?>
        </h3>
        <div class="saw-detail-section-content saw-detail-section-content-preformatted">
            <?php echo nl2br(esc_html($item['description'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($item['notes'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="dashicons dashicons-edit-page"></span>
            <?php echo esc_html__('Poznámky', 'saw-visitors'); ?>
        </h3>
        <div class="saw-detail-section-content saw-detail-section-content-preformatted">
            <?php echo nl2br(esc_html($item['notes'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="saw-detail-section saw-detail-section-metadata">
        <dl class="saw-detail-list">
            <?php if (!empty($item['created_at_formatted'])): ?>
                <div>
                    <dt><?php echo esc_html__('Vytvořeno:', 'saw-visitors'); ?></dt>
                    <dd><?php echo esc_html($item['created_at_formatted']); ?></dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
                <div>
                    <dt><?php echo esc_html__('Aktualizováno:', 'saw-visitors'); ?></dt>
                    <dd><?php echo esc_html($item['updated_at_formatted']); ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>
    
</div>