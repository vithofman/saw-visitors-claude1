<?php
/**
 * Account Types Detail Sidebar Template
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     1.0.0 - NEW: Sidebar format
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Typ účtu nebyl nalezen</div>';
    return;
}
?>

<div class="saw-detail-sidebar-content">
    
    <!-- Header -->
    <div class="saw-detail-header" style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px;">
        <?php if (!empty($item['color'])): ?>
            <div style="width: 60px; height: 60px; border-radius: 12px; background-color: <?php echo esc_attr($item['color']); ?>; border: 3px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.1); flex-shrink: 0;"></div>
        <?php endif; ?>
        
        <div style="flex: 1; min-width: 0;">
            <h3 style="margin: 0 0 8px 0; font-size: 20px; font-weight: 700; color: #1f2937;">
                <?php echo esc_html($item['display_name']); ?>
            </h3>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <span class="saw-badge saw-badge-secondary" style="font-family: monospace;">
                    <?php echo esc_html($item['name']); ?>
                </span>
                <?php if (!empty($item['is_active'])): ?>
                    <span class="saw-badge saw-badge-success">Aktivní</span>
                <?php else: ?>
                    <span class="saw-badge saw-badge-secondary">Neaktivní</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Price -->
    <?php if (!empty($item['price_formatted'])): ?>
    <div class="saw-detail-section">
        <h4 class="saw-detail-section-title">Cena</h4>
        <div style="font-size: 24px; font-weight: 700; color: #059669;">
            <?php echo esc_html($item['price_formatted']); ?>/měsíc
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Features -->
    <?php if (!empty($item['features_array']) && is_array($item['features_array'])): ?>
    <div class="saw-detail-section">
        <h4 class="saw-detail-section-title">Funkce (<?php echo count($item['features_array']); ?>)</h4>
        <ul style="margin: 0; padding-left: 24px; color: #6b7280; line-height: 1.8;">
            <?php foreach ($item['features_array'] as $feature): ?>
                <li><?php echo esc_html($feature); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Metadata -->
    <div class="saw-detail-section">
        <h4 class="saw-detail-section-title">Informace</h4>
        <dl class="saw-detail-meta">
            <dt>Pořadí</dt>
            <dd><?php echo esc_html($item['sort_order'] ?? 0); ?></dd>
            
            <?php if (!empty($item['color'])): ?>
            <dt>Barva</dt>
            <dd>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="display: inline-block; width: 24px; height: 24px; border-radius: 4px; background-color: <?php echo esc_attr($item['color']); ?>; border: 2px solid #e5e7eb;"></span>
                    <code style="font-family: monospace; font-size: 13px; color: #6b7280;"><?php echo esc_html(strtoupper($item['color'])); ?></code>
                </div>
            </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['created_at_formatted'])): ?>
            <dt>Vytvořeno</dt>
            <dd style="font-family: monospace; font-size: 13px;"><?php echo esc_html($item['created_at_formatted']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
            <dt>Aktualizováno</dt>
            <dd style="font-family: monospace; font-size: 13px;"><?php echo esc_html($item['updated_at_formatted']); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    
</div>