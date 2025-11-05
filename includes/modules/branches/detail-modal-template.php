<?php
/**
 * Branches Detail Modal Template
 * 
 * REFACTORED v2.0.0:
 * ✅ No inline styles
 * ✅ Global CSS classes
 * ✅ All values escaped
 * ✅ Fallback for missing data
 * 
 * @package SAW_Visitors
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Pobočka nebyla nalezena</div>';
    return;
}
?>

<div class="saw-detail-header">
    <?php if (!empty($item['image_url'])): ?>
        <img src="<?php echo esc_url($item['image_url']); ?>" 
             alt="<?php echo esc_attr($item['name']); ?>" 
             class="saw-detail-logo">
    <?php else: ?>
        <div class="saw-detail-logo-placeholder">
            <span class="dashicons dashicons-building"></span>
        </div>
    <?php endif; ?>
    
    <div class="saw-detail-header-info">
        <h2>
            <?php echo esc_html($item['name']); ?>
        </h2>
        
        <div class="saw-detail-badges">
            <?php if (!empty($item['code'])): ?>
                <span class="saw-code-badge"><?php echo esc_html($item['code']); ?></span>
            <?php endif; ?>
            
            <?php if (!empty($item['is_headquarters'])): ?>
                <span class="saw-badge saw-badge-info">Hlavní sídlo</span>
            <?php endif; ?>
            
            <span class="<?php echo esc_attr($item['is_active_badge_class']); ?>">
                <?php echo esc_html($item['is_active_label']); ?>
            </span>
        </div>
    </div>
</div>

<div class="saw-detail-sections">
    
    <?php if (!empty($item['full_address'])): ?>
    <div class="saw-detail-section">
        <h3>📍 Adresa</h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['street'])): ?>
                <dt>Ulice</dt>
                <dd><?php echo esc_html($item['street']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['city'])): ?>
                <dt>Město</dt>
                <dd><?php echo esc_html($item['city']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['postal_code'])): ?>
                <dt>PSČ</dt>
                <dd><?php echo esc_html($item['postal_code']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['country_name'])): ?>
                <dt>Země</dt>
                <dd><?php echo esc_html($item['country_name']); ?></dd>
            <?php endif; ?>
        </dl>
        
        <?php if (!empty($item['has_gps'])): ?>
            <div class="saw-detail-action">
                <a href="<?php echo esc_url($item['google_maps_url']); ?>" 
                   target="_blank" 
                   class="saw-map-link">
                    <span class="dashicons dashicons-location"></span>
                    Zobrazit na mapě
                </a>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($item['phone']) || !empty($item['email'])): ?>
    <div class="saw-detail-section">
        <h3>📞 Kontakt</h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['phone'])): ?>
                <dt>Telefon</dt>
                <dd><a href="tel:<?php echo esc_attr($item['phone']); ?>"><?php echo esc_html($item['phone']); ?></a></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['email'])): ?>
                <dt>Email</dt>
                <dd><a href="mailto:<?php echo esc_attr($item['email']); ?>"><?php echo esc_html($item['email']); ?></a></dd>
            <?php endif; ?>
        </dl>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($item['opening_hours_array'])): ?>
    <div class="saw-detail-section">
        <h3>🕐 Provozní doba</h3>
        <ul class="saw-opening-hours-list">
            <?php foreach ($item['opening_hours_array'] as $hours): ?>
                <li><?php echo esc_html($hours); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($item['description'])): ?>
    <div class="saw-detail-section">
        <h3>📄 Popis</h3>
        <p><?php echo nl2br(esc_html($item['description'])); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($item['notes'])): ?>
    <div class="saw-detail-section">
        <h3>🔒 Interní poznámky</h3>
        <p><?php echo nl2br(esc_html($item['notes'])); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($item['has_gps'])): ?>
    <div class="saw-detail-section">
        <h3>🌍 GPS Souřadnice</h3>
        <dl class="saw-detail-list">
            <dt>Zeměpisná šířka</dt>
            <dd><?php echo esc_html($item['latitude']); ?></dd>
            
            <dt>Zeměpisná délka</dt>
            <dd><?php echo esc_html($item['longitude']); ?></dd>
        </dl>
    </div>
    <?php endif; ?>
    
    <div class="saw-detail-section">
        <h3>ℹ️ Informace</h3>
        <dl class="saw-detail-list">
            <dt>Status</dt>
            <dd>
                <span class="<?php echo esc_attr($item['is_active_badge_class']); ?>">
                    <?php echo esc_html($item['is_active_label']); ?>
                </span>
            </dd>
            
            <dt>Hlavní sídlo</dt>
            <dd>
                <span class="<?php echo esc_attr($item['is_headquarters_badge_class']); ?>">
                    <?php echo esc_html($item['is_headquarters_label']); ?>
                </span>
            </dd>
            
            <dt>Pořadí řazení</dt>
            <dd><?php echo esc_html($item['sort_order'] ?? 0); ?></dd>
            
            <?php if (!empty($item['created_at_formatted'])): ?>
                <dt>Vytvořeno</dt>
                <dd><?php echo esc_html($item['created_at_formatted']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
                <dt>Naposledy upraveno</dt>
                <dd><?php echo esc_html($item['updated_at_formatted']); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    
</div>
