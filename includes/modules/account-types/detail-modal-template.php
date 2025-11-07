<?php
/**
 * Account Types Detail Modal Template
 * 
 * Displays complete account type information in a modal window:
 * - Color badge (visual identifier)
 * - Display name and internal slug
 * - Active/Inactive status
 * - Monthly price (formatted)
 * - Description text
 * - Features list (bullet points)
 * - Metadata (sort order, color code, timestamps)
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes/Templates
 * @since       1.0.0
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Validate data
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">' . esc_html__('Typ účtu nebyl nalezen', 'saw-visitors') . '</div>';
    return;
}
?>

<div class="saw-detail-header">
    <?php if (!empty($item['color'])): ?>
        <div style="width: 80px; height: 80px; border-radius: var(--saw-border-radius-lg); background-color: <?php echo esc_attr($item['color']); ?>; border: 3px solid #fff; box-shadow: var(--saw-shadow-lg);"></div>
    <?php endif; ?>
    
    <div class="saw-detail-header-info" style="flex: 1;">
        <h2 style="margin: 0 0 10px 0; font-size: 24px; font-weight: 700; color: var(--saw-gray-900);">
            <?php echo esc_html($item['display_name']); ?>
        </h2>
        
        <div style="display: flex; gap: var(--saw-space-md); align-items: center; margin-bottom: var(--saw-space-sm);">
            <?php if (!empty($item['name'])): ?>
                <span class="saw-code-badge"><?php echo esc_html($item['name']); ?></span>
            <?php endif; ?>
            
            <?php if (!empty($item['is_active'])): ?>
                <span class="saw-badge saw-badge-success"><?php echo esc_html__('Aktivní', 'saw-visitors'); ?></span>
            <?php else: ?>
                <span class="saw-badge saw-badge-secondary"><?php echo esc_html__('Neaktivní', 'saw-visitors'); ?></span>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($item['price_formatted'])): ?>
            <div style="font-size: 18px; font-weight: 600; color: var(--saw-success);">
                <?php echo esc_html($item['price_formatted']); ?><?php echo esc_html__('/měsíc', 'saw-visitors'); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="saw-detail-sections">
    
    <!-- Description Section -->
    <?php if (!empty($item['description'])): ?>
    <div class="saw-detail-section">
        <h3 style="margin: 0 0 var(--saw-space-md) 0; font-size: var(--saw-font-size-lg); font-weight: 700; color: var(--saw-gray-900); border-bottom: 2px solid var(--saw-border-color); padding-bottom: var(--saw-space-sm);">
            <?php echo esc_html__('Popis', 'saw-visitors'); ?>
        </h3>
        <p style="margin: 0; color: var(--saw-gray-700); line-height: 1.6;">
            <?php echo nl2br(esc_html($item['description'])); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Features Section -->
    <?php if (!empty($item['features_array']) && is_array($item['features_array'])): ?>
    <div class="saw-detail-section">
        <h3 style="margin: 0 0 var(--saw-space-md) 0; font-size: var(--saw-font-size-lg); font-weight: 700; color: var(--saw-gray-900); border-bottom: 2px solid var(--saw-border-color); padding-bottom: var(--saw-space-sm);">
            <?php echo esc_html__('Funkce', 'saw-visitors'); ?> (<?php echo count($item['features_array']); ?>)
        </h3>
        <ul style="margin: 0; padding-left: 24px; color: var(--saw-gray-700); line-height: 1.8;">
            <?php foreach ($item['features_array'] as $feature): ?>
                <li><?php echo esc_html($feature); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Information Section -->
    <div class="saw-detail-section">
        <h3 style="margin: 0 0 var(--saw-space-md) 0; font-size: var(--saw-font-size-lg); font-weight: 700; color: var(--saw-gray-900); border-bottom: 2px solid var(--saw-border-color); padding-bottom: var(--saw-space-sm);">
            <?php echo esc_html__('Informace', 'saw-visitors'); ?>
        </h3>
        <dl style="margin: 0; display: grid; grid-template-columns: 140px 1fr; gap: var(--saw-space-md) var(--saw-space-xl); font-size: var(--saw-font-size-sm);">
            <?php if (!empty($item['sort_order'])): ?>
                <dt style="font-weight: 700; color: var(--saw-gray-500);"><?php echo esc_html__('Pořadí', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900);"><?php echo esc_html($item['sort_order']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['color'])): ?>
                <dt style="font-weight: 700; color: var(--saw-gray-500);"><?php echo esc_html__('Barva', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); display: flex; align-items: center; gap: 10px;">
                    <span style="display: inline-block; width: 24px; height: 24px; border-radius: var(--saw-border-radius-sm); background-color: <?php echo esc_attr($item['color']); ?>; border: 2px solid var(--saw-gray-200);"></span>
                    <code style="font-family: monospace; font-size: var(--saw-font-size-sm); color: var(--saw-gray-700);"><?php echo esc_html(strtoupper($item['color'])); ?></code>
                </dd>
            <?php endif; ?>
            
            <?php if (!empty($item['created_at_formatted'])): ?>
                <dt style="font-weight: 700; color: var(--saw-gray-500);"><?php echo esc_html__('Vytvořeno', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-family: monospace;"><?php echo esc_html($item['created_at_formatted']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at_formatted'])): ?>
                <dt style="font-weight: 700; color: var(--saw-gray-500);"><?php echo esc_html__('Aktualizováno', 'saw-visitors'); ?></dt>
                <dd style="margin: 0; color: var(--saw-gray-900); font-family: monospace;"><?php echo esc_html($item['updated_at_formatted']); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    
</div>