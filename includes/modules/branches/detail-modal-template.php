<?php
/**
 * Branches Detail Modal Template
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($item)) {
    echo '<p>Pobočka nebyla nalezena.</p>';
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
            <?php if (!empty($item['is_headquarters'])): ?>
                <span class="saw-badge saw-badge-info">Hlavní sídlo</span>
            <?php endif; ?>
        </h2>
        <?php if (!empty($item['code'])): ?>
            <p>Kód: <strong><?php echo esc_html($item['code']); ?></strong></p>
        <?php endif; ?>
    </div>
</div>

<div class="saw-detail-sections">
    
    <?php if (!empty($item['full_address'])): ?>
    <div class="saw-detail-section">
        <h3>📍 Adresa</h3>
        <dl>
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
        
        <?php if ($item['has_gps']): ?>
            <div style="margin-top: 16px;">
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
        <dl>
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
        <h3>📝 Popis</h3>
        <p><?php echo nl2br(esc_html($item['description'])); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($item['notes'])): ?>
    <div class="saw-detail-section">
        <h3>🔒 Interní poznámky</h3>
        <p><?php echo nl2br(esc_html($item['notes'])); ?></p>
    </div>
    <?php endif; ?>
    
    <?php if ($item['has_gps']): ?>
    <div class="saw-detail-section">
        <h3>🌍 GPS Souřadnice</h3>
        <dl>
            <dt>Zeměpisná šířka</dt>
            <dd><?php echo esc_html($item['latitude']); ?></dd>
            
            <dt>Zeměpisná délka</dt>
            <dd><?php echo esc_html($item['longitude']); ?></dd>
        </dl>
    </div>
    <?php endif; ?>
    
    <div class="saw-detail-section">
        <h3>ℹ️ Informace</h3>
        <dl>
            <dt>Status</dt>
            <dd>
                <span class="saw-badge <?php echo esc_attr($item['is_active_badge_class']); ?>">
                    <?php echo esc_html($item['is_active_label']); ?>
                </span>
            </dd>
            
            <dt>Hlavní sídlo</dt>
            <dd>
                <span class="saw-badge <?php echo esc_attr($item['is_headquarters_badge_class']); ?>">
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

<style>
.saw-detail-logo-placeholder {
    width: 88px;
    height: 88px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 14px;
    border: 2px solid #e2e8f0;
}

.saw-detail-logo-placeholder .dashicons {
    font-size: 44px;
    width: 44px;
    height: 44px;
    color: #ffffff;
}

.saw-opening-hours-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.saw-opening-hours-list li {
    padding: 10px 14px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    font-size: 14px;
    color: #1e293b;
}
</style>