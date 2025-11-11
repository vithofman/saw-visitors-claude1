<?php
/**
 * Branches Detail Template - STYLED LIKE CUSTOMERS
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     14.0.0
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
?>

<!-- HEADER WITH IMAGE -->
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
    
    <div class="saw-detail-header-content">
        <h2 class="saw-detail-header-title">
            <?php echo esc_html($item['name']); ?>
        </h2>
        
        <div class="saw-detail-header-badges">
            <?php if (!empty($item['id'])): ?>
                <span class="saw-badge saw-badge-light">
                    ID: <?php echo esc_html($item['id']); ?>
                </span>
            <?php endif; ?>
            
            <?php if (!empty($item['code'])): ?>
                <span class="saw-badge saw-badge-light">
                    Kód: <?php echo esc_html($item['code']); ?>
                </span>
            <?php endif; ?>
            
            <?php if (!empty($item['is_headquarters'])): ?>
                <span class="saw-badge saw-badge-primary">
                    <?php echo esc_html__('Sídlo firmy', 'saw-visitors'); ?>
                </span>
            <?php endif; ?>
            
            <?php if (!empty($item['is_active'])): ?>
                <span class="saw-badge saw-badge-success">
                    <?php echo esc_html__('Aktivní', 'saw-visitors'); ?>
                </span>
            <?php else: ?>
                <span class="saw-badge saw-badge-secondary">
                    <?php echo esc_html__('Neaktivní', 'saw-visitors'); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- DETAIL SECTIONS -->
<div class="saw-detail-sections">
    
    <!-- BASIC INFO -->
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">📋</span>
            <?php echo esc_html__('Základní informace', 'saw-visitors'); ?>
        </h3>
        <dl class="saw-detail-list">
            <div>
                <dt><?php echo esc_html__('Název pobočky:', 'saw-visitors'); ?></dt>
                <dd><strong><?php echo esc_html($item['name']); ?></strong></dd>
            </div>
            
            <?php if (!empty($item['code'])): ?>
            <div>
                <dt><?php echo esc_html__('Kód:', 'saw-visitors'); ?></dt>
                <dd><code><?php echo esc_html($item['code']); ?></code></dd>
            </div>
            <?php endif; ?>
            
            <div>
                <dt><?php echo esc_html__('Sídlo firmy:', 'saw-visitors'); ?></dt>
                <dd><?php echo esc_html($item['is_headquarters_label']); ?></dd>
            </div>
            
            <div>
                <dt><?php echo esc_html__('Status:', 'saw-visitors'); ?></dt>
                <dd>
                    <?php if (!empty($item['is_active'])): ?>
                        <span class="saw-badge saw-badge-success">
                            <?php echo esc_html__('Aktivní', 'saw-visitors'); ?>
                        </span>
                    <?php else: ?>
                        <span class="saw-badge saw-badge-secondary">
                            <?php echo esc_html__('Neaktivní', 'saw-visitors'); ?>
                        </span>
                    <?php endif; ?>
                </dd>
            </div>
            
            <?php if (!empty($item['sort_order'])): ?>
            <div>
                <dt><?php echo esc_html__('Pořadí:', 'saw-visitors'); ?></dt>
                <dd><?php echo esc_html($item['sort_order']); ?></dd>
            </div>
            <?php endif; ?>
        </dl>
    </div>
    
    <!-- ADDRESS -->
    <?php 
    $has_address = !empty($item['street']) || !empty($item['city']) || !empty($item['postal_code']);
    ?>
    <?php if ($has_address): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">📍</span>
            <?php echo esc_html__('Adresa', 'saw-visitors'); ?>
        </h3>
        <div class="saw-detail-section-content">
            <?php if (!empty($item['street'])): ?>
                <div><?php echo esc_html($item['street']); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($item['city']) || !empty($item['postal_code'])): ?>
                <div>
                    <?php 
                    $address_line = array();
                    if (!empty($item['postal_code'])) $address_line[] = $item['postal_code'];
                    if (!empty($item['city'])) $address_line[] = $item['city'];
                    echo esc_html(implode(' ', $address_line));
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($item['country']) && $item['country'] !== 'CZ'): ?>
                <div><?php echo esc_html($item['country']); ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- CONTACT INFORMATION -->
    <?php 
    $has_contact = !empty($item['phone']) || !empty($item['email']);
    ?>
    <?php if ($has_contact): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">📞</span>
            <?php echo esc_html__('Kontaktní údaje', 'saw-visitors'); ?>
        </h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['phone'])): ?>
            <div>
                <dt><?php echo esc_html__('Telefon:', 'saw-visitors'); ?></dt>
                <dd>
                    <a href="tel:<?php echo esc_attr($item['phone']); ?>">
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
    </div>
    <?php endif; ?>
    
    <!-- DESCRIPTION -->
    <?php if (!empty($item['description'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">📝</span>
            <?php echo esc_html__('Popis', 'saw-visitors'); ?>
        </h3>
        <div class="saw-detail-section-content saw-detail-section-content-preformatted">
            <?php echo nl2br(esc_html($item['description'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- NOTES -->
    <?php if (!empty($item['notes'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="saw-detail-section-icon">💬</span>
            <?php echo esc_html__('Poznámky', 'saw-visitors'); ?>
        </h3>
        <div class="saw-detail-section-content saw-detail-section-content-preformatted">
            <?php echo nl2br(esc_html($item['notes'])); ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- METADATA -->
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