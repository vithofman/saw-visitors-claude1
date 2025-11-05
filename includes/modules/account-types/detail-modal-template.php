<?php
/**
 * Account Types Detail Modal Template
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Typ uctu nebyl nalezen</div>';
    return;
}
?>

<div class="saw-detail-header">
    <?php if (!empty($item['color'])): ?>
        <div style="width: 80px; height: 80px; border-radius: 16px; background-color: <?php echo esc_attr($item['color']); ?>; border: 3px solid #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.15);"></div>
    <?php endif; ?>
    
    <div class="saw-detail-header-info" style="flex: 1;">
        <h2 style="margin: 0 0 10px 0; font-size: 24px; font-weight: 700; color: #0f172a;">
            <?php echo esc_html($item['display_name']); ?>
        </h2>
        
        <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 8px;">
            <?php if (!empty($item['name'])): ?>
                <span class="saw-code-badge"><?php echo esc_html($item['name']); ?></span>
            <?php endif; ?>
            
            <?php if (!empty($item['is_active'])): ?>
                <span class="saw-badge saw-badge-success">Aktivni</span>
            <?php else: ?>
                <span class="saw-badge saw-badge-secondary">Neaktivni</span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($item['price_formatted'])): ?>
            <div style="font-size: 18px; font-weight: 600; color: #059669;">
                <?php echo esc_html($item['price_formatted']); ?>/mesic
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="saw-detail-sections">
    
    <?php if (!empty($item['description'])): ?>
    <div class="saw-detail-section">
        <h3 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
            Popis
        </h3>
        <p style="margin: 0; color: #475569; line-height: 1.6;">
            <?php echo nl2br(esc_html($item['description'])); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($item['features_array']) && is_array($item['features_array'])): ?>
    <div class="saw-detail-section">
        <h3 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
            Funkce (<?php echo count($item['features_array']); ?>)
        </h3>
        <ul style="margin: 0; padding-left: 24px; color: #475569; line-height: 1.8;">
            <?php foreach ($item['features_array'] as $feature): ?>
                <li><?php echo esc_html($feature); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="saw-detail-section">
        <h3 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
            Informace
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: 12px 20px; font-size: 14px;">
            <?php if (!empty($item['sort_order'])): ?>
                <dt style="font-weight: 700; color: #64748b;">Poradi</dt>
                <dd style="margin: 0; color: #1e293b;"><?php echo esc_html($item['sort_order']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['color'])): ?>
                <dt style="font-weight: 700; color: #64748b;">Barva</dt>
                <dd style="margin: 0; color: #1e293b; display: flex; align-items: center; gap: 10px;">
                    <span style="display: inline-block; width: 24px; height: 24px; border-radius: 6px; background-color: <?php echo esc_attr($item['color']); ?>; border: 2px solid #cbd5e1;"></span>
                    <code style="font-family: monospace; font-size: 13px; color: #475569;"><?php echo esc_html(strtoupper($item['color'])); ?></code>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['created_at_formatted'])): ?>
                <dt style="font-weight: 700; color: #64748b;">Vytvoreno</dt>
                <dd style="margin: 0; color: #1e293b; font-family: monospace;"><?php echo esc_html($item['created_at_formatted']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
                <dt style="font-weight: 700; color: #64748b;">Aktualizovano</dt>
                <dd style="margin: 0; color: #1e293b; font-family: monospace;"><?php echo esc_html($item['updated_at_formatted']); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    
</div>