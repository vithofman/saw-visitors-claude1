<?php
if (!defined('ABSPATH')) exit;

if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Návštěva nebyla nalezena</div>';
    return;
}
?>

<div class="saw-detail-header">
    <div class="saw-detail-header-info">
        <h2 class="saw-detail-title">
            #<?php echo esc_html($item['id']); ?> 
            <?php if (!empty($item['company_name'])): ?>
                <?php echo esc_html($item['company_name']); ?>
            <?php else: ?>
                Návštěva
            <?php endif; ?>
        </h2>
        <div class="saw-detail-badges">
            <?php
            $status_labels = array(
                'draft' => 'Koncept',
                'pending' => 'Čekající',
                'confirmed' => 'Potvrzená',
                'in_progress' => 'Probíhající',
                'completed' => 'Dokončená',
                'cancelled' => 'Zrušená',
            );
            $status_classes = array(
                'draft' => 'saw-badge-secondary',
                'pending' => 'saw-badge-warning',
                'confirmed' => 'saw-badge-info',
                'in_progress' => 'saw-badge-primary',
                'completed' => 'saw-badge-success',
                'cancelled' => 'saw-badge-danger',
            );
            $type_labels = array(
                'planned' => 'Plánovaná',
                'walk_in' => 'Walk-in',
            );
            ?>
            <?php if (!empty($item['visit_type'])): ?>
            <span class="saw-badge saw-badge-info">
                <?php echo esc_html($type_labels[$item['visit_type']] ?? $item['visit_type']); ?>
            </span>
            <?php endif; ?>
            <?php if (!empty($item['status'])): ?>
            <span class="saw-badge <?php echo esc_attr($status_classes[$item['status']] ?? 'saw-badge-secondary'); ?>">
                <?php echo esc_html($status_labels[$item['status']] ?? $item['status']); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="saw-detail-sections">
    <div class="saw-detail-section">
        <h3 class="saw-detail-section-title">
            <span class="dashicons dashicons-info"></span>
            Informace o návštěvě
        </h3>
        <dl class="saw-detail-list">
            <?php if (!empty($item['planned_date_from'])): ?>
            <dt class="saw-detail-label">Datum od</dt>
            <dd class="saw-detail-value"><?php echo esc_html($item['planned_date_from']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['planned_date_to'])): ?>
            <dt class="saw-detail-label">Datum do</dt>
            <dd class="saw-detail-value"><?php echo esc_html($item['planned_date_to']); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['invitation_email'])): ?>
            <dt class="saw-detail-label">Email pro pozvánku</dt>
            <dd class="saw-detail-value"><?php echo esc_html($item['invitation_email']); ?></dd>
            <?php endif; ?>
            
            <dt class="saw-detail-label">Účel návštěvy</dt>
            <dd class="saw-detail-value"><?php echo !empty($item['purpose']) ? nl2br(esc_html($item['purpose'])) : '-'; ?></dd>
            
            <?php if (!empty($item['hosts'])): ?>
            <dt class="saw-detail-label">Koho navštěvují</dt>
            <dd class="saw-detail-value">
                <?php foreach ($item['hosts'] as $host): ?>
                    <div style="margin-bottom: 4px;">
                        👤 <strong><?php echo esc_html($host['first_name'] . ' ' . $host['last_name']); ?></strong>
                        <?php if (!empty($host['email'])): ?>
                            <span style="color: #666;"> (<?php echo esc_html($host['email']); ?>)</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </dd>
            <?php endif; ?>
        </dl>
    </div>
</div>
