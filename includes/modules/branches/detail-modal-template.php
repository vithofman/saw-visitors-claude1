<?php
/**
 * Branches Detail Template
 *
 * REFACTORED v13.1.0 - PRODUCTION READY
 * ✅ Validace dat
 * ✅ Opening hours display
 * ✅ GPS map link
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     13.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Validate data
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">';
    echo '<strong>Chyba:</strong> ';
    echo 'Pobočka nebyla nalezena nebo data nejsou dostupná.';
    echo '</div>';
    return;
}

// Data prepared in controller->format_detail_data()
$opening_hours = $item['opening_hours_array'] ?? array();
$days = array(
    'monday' => 'Pondělí',
    'tuesday' => 'Úterý',
    'wednesday' => 'Středa',
    'thursday' => 'Čtvrtek',
    'friday' => 'Pátek',
    'saturday' => 'Sobota',
    'sunday' => 'Neděle',
);

?>

<div class="saw-detail-header saw-module-branches">
    <?php if (!empty($item['image_url'])): ?>
        <img src="<?php echo esc_url($item['image_url']); ?>" 
             alt="<?php echo esc_attr($item['name']); ?>" 
             class="saw-detail-logo saw-branch-thumbnail"
             style="max-width: 100px; height: auto; border-radius: 8px;">
    <?php else: ?>
        <div class="saw-detail-logo-placeholder" style="width: 100px; height: 100px; background: #f0f0f0; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
            <span class="dashicons dashicons-store" style="font-size: 48px; color: #999;"></span>
        </div>
    <?php endif; ?>
    
    <div class="saw-detail-header-content">
        <h2 class="saw-detail-header-title">
            <?php echo esc_html($item['name']); ?>
        </h2>
        
        <div class="saw-detail-header-badges">
            <?php if (!empty($item['is_headquarters'])): ?>
                <span class="saw-badge saw-badge-primary">
                    Sídlo firmy
                </span>
            <?php endif; ?>
            
            <?php if (!empty($item['code'])): ?>
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
    
    <!-- Kontakt a Adresa -->
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">📞</span>
            Kontakt a Adresa
        </h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['phone'])): ?>
                <div>
                    <dt>Telefon:</dt>
                    <dd>
                        <a href="tel:<?php echo esc_attr($item['phone']); ?>" class="saw-phone-link">
                            <?php echo esc_html($item['phone']); ?>
                        </a>
                    </dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['email'])): ?>
                <div>
                    <dt>Email:</dt>
                    <dd>
                        <a href="mailto:<?php echo esc_attr($item['email']); ?>">
                            <?php echo esc_html($item['email']); ?>
                        </a>
                    </dd>
                </div>
            <?php endif; ?>
        </dl>

        <?php if (!empty($item['full_address'])): ?>
            <div class="saw-detail-address" style="margin-top: 1rem; padding: 1rem; background: #f9f9f9; border-radius: 6px;">
                <?php if (!empty($item['street'])): ?>
                    <div style="font-weight: 500;"><?php echo esc_html($item['street']); ?></div>
                <?php endif; ?>
                <div><?php echo esc_html(trim(($item['postal_code'] ?? '') . ' ' . ($item['city'] ?? ''))); ?></div>
                <?php if (!empty($item['country'])): ?>
                    <div><?php echo esc_html($item['country']); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($item['map_link'])): ?>
            <div class="saw-detail-action" style="margin-top: 1rem;">
                <a href="<?php echo esc_url($item['map_link']); ?>" target="_blank" rel="noopener" class="saw-map-link saw-button saw-button-secondary">
                    <span class="dashicons dashicons-location"></span>
                    Zobrazit na mapě
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Otevírací doba -->
    <?php if (!empty($opening_hours)): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">🕒</span>
            Otevírací doba
        </h3>
        <ul class="saw-opening-hours-list" style="list-style: none; padding: 0; margin: 0;">
            <?php foreach ($days as $day_key => $day_label): ?>
                <?php
                $status = $opening_hours[$day_key]['is_open'] ?? 'closed';
                $from = $opening_hours[$day_key]['open_from'] ?? '';
                $to = $opening_hours[$day_key]['open_to'] ?? '';
                $display_time = '';
                
                if ($status === 'nonstop') {
                    $display_time = '<strong style="color: #0073aa;">Nonstop</strong>';
                } elseif ($status === 'open') {
                    $display_time = '<strong>' . esc_html($from) . ' - ' . esc_html($to) . '</strong>';
                } else {
                    $display_time = '<span class="saw-text-muted" style="color: #999;">Zavřeno</span>';
                }
                ?>
                <li style="padding: 0.5rem 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between;">
                    <span style="font-weight: 500;"><?php echo esc_html($day_label); ?>:</span>
                    <?php echo $display_time; // WPCS: XSS ok. ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Popis -->
    <?php if (!empty($item['description'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="dashicons dashicons-clipboard"></span>
            Popis
        </h3>
        <div class="saw-detail-section-content" style="white-space: pre-wrap;">
            <?php echo nl2br(esc_html($item['description'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Poznámky -->
    <?php if (!empty($item['notes'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="dashicons dashicons-edit-page"></span>
            Poznámky
        </h3>
        <div class="saw-detail-section-content" style="white-space: pre-wrap;">
            <?php echo nl2br(esc_html($item['notes'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Metadata -->
    <div class="saw-detail-section saw-detail-section-metadata">
        <dl class="saw-detail-list">
            <?php if (!empty($item['created_at_formatted'])): ?>
                <div>
                    <dt>Vytvořeno:</dt>
                    <dd><?php echo esc_html($item['created_at_formatted']); ?></dd>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
                <div>
                    <dt>Aktualizováno:</dt>
                    <dd><?php echo esc_html($item['updated_at_formatted']); ?></dd>
                </div>
            <?php endif; ?>
        </dl>
    </div>
    
</div>