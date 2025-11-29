<?php
/**
 * OOPP Detail Modal Template
 * 
 * Displays detailed information about an OOPP in sidebar.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/OOPP
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if item data exists
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">OOPP nebyl nalezen</div>';
    return;
}
?>

<!-- Detail Header -->
<div class="saw-detail-header">
    <?php if (!empty($item['image_url'])): ?>
        <img src="<?php echo esc_url($item['image_url']); ?>" 
             alt="<?php echo esc_attr($item['name']); ?>" 
             class="saw-detail-logo">
    <?php else: ?>
        <div class="saw-detail-logo-placeholder">
            <span style="font-size: 48px;">🦺</span>
        </div>
    <?php endif; ?>
    
    <div class="saw-detail-header-content">
        <h2 class="saw-detail-title">
            <?php echo esc_html($item['name']); ?>
        </h2>
        
        <!-- Badges Row -->
        <div class="saw-detail-badges">
            <?php if (!empty($item['group_display'])): ?>
                <span class="saw-badge saw-badge-info"><?php echo esc_html($item['group_display']); ?></span>
            <?php endif; ?>
            
            <?php if (!empty($item['is_active'])): ?>
                <span class="saw-badge saw-badge-success">✅ Aktivní</span>
            <?php else: ?>
                <span class="saw-badge saw-badge-secondary">❌ Neaktivní</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Detail Sections -->
<div class="saw-detail-sections">
    
    <!-- Základní informace -->
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">Základní informace</h3>
        <dl class="saw-detail-list">
            
            <!-- Skupina -->
            <?php if (!empty($item['group_display'])): ?>
                <dt class="saw-detail-label">Skupina OOPP</dt>
                <dd class="saw-detail-value"><?php echo esc_html($item['group_display']); ?></dd>
            <?php endif; ?>
            
            <!-- Status -->
            <dt class="saw-detail-label">Status</dt>
            <dd class="saw-detail-value">
                <?php if (!empty($item['is_active'])): ?>
                    <span class="saw-badge saw-badge-success">✅ Aktivní</span>
                <?php else: ?>
                    <span class="saw-badge saw-badge-secondary">❌ Neaktivní</span>
                <?php endif; ?>
            </dd>
            
            <!-- Pořadí -->
            <?php if (isset($item['display_order'])): ?>
                <dt class="saw-detail-label">Pořadí zobrazení</dt>
                <dd class="saw-detail-value"><?php echo esc_html($item['display_order']); ?></dd>
            <?php endif; ?>
            
            <!-- Created At -->
            <?php if (!empty($item['created_at_formatted'])): ?>
                <dt class="saw-detail-label">Vytvořeno</dt>
                <dd class="saw-detail-value saw-detail-date">
                    <?php echo esc_html($item['created_at_formatted']); ?>
                </dd>
            <?php endif; ?>
            
            <!-- Updated At -->
            <?php if (!empty($item['updated_at_formatted'])): ?>
                <dt class="saw-detail-label">Aktualizováno</dt>
                <dd class="saw-detail-value saw-detail-date">
                    <?php echo esc_html($item['updated_at_formatted']); ?>
                </dd>
            <?php endif; ?>
            
        </dl>
    </div>
    
    <!-- Platnost -->
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">Platnost</h3>
        <dl class="saw-detail-list">
            
            <!-- Pobočky -->
            <dt class="saw-detail-label">Pobočky</dt>
            <dd class="saw-detail-value">
                <?php if (!empty($item['branches_all'])): ?>
                    <span class="saw-badge saw-badge-success">Všechny pobočky</span>
                <?php elseif (!empty($item['branches'])): ?>
                    <?php foreach ($item['branches'] as $branch): ?>
                        <span class="saw-badge saw-badge-info" style="margin-right: 4px;">
                            <?php echo esc_html($branch['name']); ?>
                        </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="saw-text-muted">—</span>
                <?php endif; ?>
            </dd>
            
            <!-- Oddělení -->
            <dt class="saw-detail-label">Oddělení</dt>
            <dd class="saw-detail-value">
                <?php if (!empty($item['departments_all'])): ?>
                    <span class="saw-badge saw-badge-success">Všechna oddělení</span>
                <?php elseif (!empty($item['departments'])): ?>
                    <?php foreach ($item['departments'] as $dept): ?>
                        <span class="saw-badge saw-badge-warning" style="margin-right: 4px;">
                            <?php echo esc_html($dept['name']); ?>
                        </span>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="saw-text-muted">—</span>
                <?php endif; ?>
            </dd>
            
        </dl>
    </div>
    
    <!-- Technické informace -->
    <?php if (!empty($item['standards']) || !empty($item['risk_description']) || !empty($item['protective_properties'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">Technické informace</h3>
        
        <!-- Normy -->
        <?php if (!empty($item['standards'])): ?>
            <div style="margin-bottom: 20px;">
                <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #1d2327;">Související předpisy / normy</h4>
                <p class="saw-detail-text"><?php echo nl2br(esc_html($item['standards'])); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Rizika -->
        <?php if (!empty($item['risk_description'])): ?>
            <div style="margin-bottom: 20px;">
                <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #1d2327;">Popis rizik, proti kterým OOPP chrání</h4>
                <p class="saw-detail-text"><?php echo nl2br(esc_html($item['risk_description'])); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Ochranné vlastnosti -->
        <?php if (!empty($item['protective_properties'])): ?>
            <div style="margin-bottom: 20px;">
                <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #1d2327;">Ochranné vlastnosti</h4>
                <p class="saw-detail-text"><?php echo nl2br(esc_html($item['protective_properties'])); ?></p>
            </div>
        <?php endif; ?>
        
    </div>
    <?php endif; ?>
    
    <!-- Pokyny -->
    <?php if (!empty($item['usage_instructions']) || !empty($item['maintenance_instructions']) || !empty($item['storage_instructions'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">Pokyny</h3>
        
        <!-- Použití -->
        <?php if (!empty($item['usage_instructions'])): ?>
            <div style="margin-bottom: 20px;">
                <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #1d2327;">Pokyny pro použití</h4>
                <p class="saw-detail-text"><?php echo nl2br(esc_html($item['usage_instructions'])); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Údržba -->
        <?php if (!empty($item['maintenance_instructions'])): ?>
            <div style="margin-bottom: 20px;">
                <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #1d2327;">Pokyny pro údržbu</h4>
                <p class="saw-detail-text"><?php echo nl2br(esc_html($item['maintenance_instructions'])); ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Skladování -->
        <?php if (!empty($item['storage_instructions'])): ?>
            <div style="margin-bottom: 20px;">
                <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 8px; color: #1d2327;">Pokyny pro skladování</h4>
                <p class="saw-detail-text"><?php echo nl2br(esc_html($item['storage_instructions'])); ?></p>
            </div>
        <?php endif; ?>
        
    </div>
    <?php endif; ?>
    
</div>

