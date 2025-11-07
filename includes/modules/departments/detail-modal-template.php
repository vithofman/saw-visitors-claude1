<?php
/**
 * Departments Detail Modal Template
 * 
 * Displays detailed information about a department in a modal window.
 * Loaded via AJAX when user clicks on the view icon in the list.
 * 
 * Available Variables:
 * @var array $item Department data with the following keys:
 *  - id: Department ID
 *  - name: Department name
 *  - department_number: Internal department code (optional)
 *  - description: Department description (optional)
 *  - training_version: Training version number
 *  - is_active: Active status (1/0)
 *  - is_active_label: Formatted status label
 *  - is_active_badge_class: CSS class for status badge
 *  - branch_name: Name of parent branch
 *  - created_at_formatted: Formatted creation date
 *  - updated_at_formatted: Formatted update date
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @author      SAW Visitors Dev Team
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if item data exists
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Oddělení nebylo nalezeno</div>';
    return;
}
?>

<!-- Detail Header -->
<div class="saw-detail-header">
    <div class="saw-detail-header-info">
        <h2 class="saw-detail-title">
            <?php echo esc_html($item['name']); ?>
        </h2>
        
        <!-- Badges Row -->
        <div class="saw-detail-badges">
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
        
        <!-- Branch Info -->
        <?php if (!empty($item['branch_name'])): ?>
            <div class="saw-detail-subtitle">
                <span class="dashicons dashicons-building"></span>
                <?php echo esc_html($item['branch_name']); ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Detail Sections -->
<div class="saw-detail-sections">
    
    <!-- Description Section -->
    <?php if (!empty($item['description'])): ?>
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">Popis</h3>
        <p class="saw-detail-text">
            <?php echo nl2br(esc_html($item['description'])); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Information Section -->
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">Informace</h3>
        <dl class="saw-detail-list">
            
            <!-- Branch -->
            <?php if (!empty($item['branch_name'])): ?>
                <dt class="saw-detail-label">Pobočka</dt>
                <dd class="saw-detail-value"><?php echo esc_html($item['branch_name']); ?></dd>
            <?php endif; ?>
            
            <!-- Department Number -->
            <?php if (!empty($item['department_number'])): ?>
                <dt class="saw-detail-label">Číslo oddělení</dt>
                <dd class="saw-detail-value">
                    <code class="saw-code"><?php echo esc_html($item['department_number']); ?></code>
                </dd>
            <?php endif; ?>
            
            <!-- Training Version -->
            <?php if (!empty($item['training_version'])): ?>
                <dt class="saw-detail-label">Verze školení</dt>
                <dd class="saw-detail-value">v<?php echo esc_html($item['training_version']); ?></dd>
            <?php endif; ?>
            
            <!-- Status -->
            <dt class="saw-detail-label">Status</dt>
            <dd class="saw-detail-value">
                <span class="<?php echo esc_attr($item['is_active_badge_class']); ?>">
                    <?php echo esc_html($item['is_active_label']); ?>
                </span>
            </dd>
            
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
    
</div>