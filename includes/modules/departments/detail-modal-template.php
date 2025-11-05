<?php
/**
 * Departments Detail Modal Template
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Oddělení nebylo nalezeno</div>';
    return;
}
?>

<div class="saw-detail-header">
    <div class="saw-detail-header-info" style="flex: 1;">
        <h2 style="margin: 0 0 10px 0; font-size: 24px; font-weight: 700; color: #0f172a;">
            <?php echo esc_html($item['name']); ?>
        </h2>
        
        <div style="display: flex; gap: 12px; align-items: center; margin-bottom: 8px;">
            <?php if (!empty($item['department_number'])): ?>
                <span class="saw-code-badge"><?php echo esc_html($item['department_number']); ?></span>
            <?php endif; ?>
            
            <?php if (!empty($item['is_active'])): ?>
                <span class="saw-badge saw-badge-success">Aktivní</span>
            <?php else: ?>
                <span class="saw-badge saw-badge-secondary">Neaktivní</span>
            <?php endif; ?>
            
            <?php if (!empty($item['training_version'])): ?>
                <span class="saw-badge saw-badge-info">Školení v<?php echo esc_html($item['training_version']); ?></span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($item['branch_name'])): ?>
            <div style="font-size: 15px; color: #64748b;">
                <span class="dashicons dashicons-building" style="font-size: 16px; vertical-align: middle;"></span>
                <?php echo esc_html($item['branch_name']); ?>
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
    
    <div class="saw-detail-section">
        <h3 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">
            Informace
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: 12px 20px; font-size: 14px;">
            <?php if (!empty($item['branch_name'])): ?>
                <dt style="font-weight: 700; color: #64748b;">Pobočka</dt>
                <dd style="margin: 0; color: #1e293b;"><?php echo esc_html($item['branch_name']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['department_number'])): ?>
                <dt style="font-weight: 700; color: #64748b;">Číslo oddělení</dt>
                <dd style="margin: 0; color: #1e293b;">
                    <code style="font-family: monospace; font-size: 13px; color: #475569; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;">
                        <?php echo esc_html($item['department_number']); ?>
                    </code>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['training_version'])): ?>
                <dt style="font-weight: 700; color: #64748b;">Verze školení</dt>
                <dd style="margin: 0; color: #1e293b;">v<?php echo esc_html($item['training_version']); ?></dd>
            <?php endif; ?>
            
            <dt style="font-weight: 700; color: #64748b;">Status</dt>
            <dd style="margin: 0;">
                <span class="<?php echo esc_attr($item['is_active_badge_class']); ?>">
                    <?php echo esc_html($item['is_active_label']); ?>
                </span>
            </dd>
            
            <?php if (!empty($item['created_at_formatted'])): ?>
                <dt style="font-weight: 700; color: #64748b;">Vytvořeno</dt>
                <dd style="margin: 0; color: #1e293b; font-family: monospace;"><?php echo esc_html($item['created_at_formatted']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
                <dt style="font-weight: 700; color: #64748b;">Aktualizováno</dt>
                <dd style="margin: 0; color: #1e293b; font-family: monospace;"><?php echo esc_html($item['updated_at_formatted']); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    
</div>
