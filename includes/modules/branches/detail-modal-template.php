<?php
/**
 * Branches Detail Template
 *
 * FINAL v13.4.0 - BEZ OPENING HOURS
 * ✅ Validace dat
 * ✅ Bez GPS
 * ✅ Bez opening hours
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     13.4.0
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