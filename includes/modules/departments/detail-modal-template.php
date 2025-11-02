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
    echo '<p>Oddělení nebylo nalezeno.</p>';
    return;
}

$branch_name = '';
if (!empty($item['branch_id'])) {
    global $wpdb;
    $branch = $wpdb->get_row($wpdb->prepare(
        "SELECT name, code, city FROM {$wpdb->prefix}saw_branches WHERE id = %d",
        $item['branch_id']
    ), ARRAY_A);
    
    if ($branch) {
        $branch_name = $branch['name'];
        if (!empty($branch['code'])) {
            $branch_name .= ' (' . $branch['code'] . ')';
        }
        if (!empty($branch['city'])) {
            $branch_name .= ' - ' . $branch['city'];
        }
    }
}
?>

<div class="saw-detail-header">
    <div class="saw-detail-logo-placeholder">
        <span class="dashicons dashicons-building"></span>
    </div>
    
    <div class="saw-detail-header-info">
        <h2><?php echo esc_html($item['name']); ?></h2>
        <?php if (!empty($item['department_number'])): ?>
            <p style="margin: 4px 0 8px 0; color: #6b7280; font-size: 14px;">
                Číslo: <strong><?php echo esc_html($item['department_number']); ?></strong>
            </p>
        <?php endif; ?>
        <?php if (!empty($item['is_active'])): ?>
            <span class="saw-badge saw-badge-success">Aktivní</span>
        <?php else: ?>
            <span class="saw-badge saw-badge-secondary">Neaktivní</span>
        <?php endif; ?>
    </div>
</div>

<div class="saw-detail-sections">
    
    <div class="saw-detail-section">
        <h3>🏢 Pobočka</h3>
        <dl>
            <?php if ($branch_name): ?>
                <dt>Pobočka</dt>
                <dd><?php echo esc_html($branch_name); ?></dd>
            <?php else: ?>
                <dd><span class="saw-text-muted">Pobočka nebyla nalezena</span></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['department_number'])): ?>
                <dt>Číslo oddělení</dt>
                <dd><span class="saw-code-badge"><?php echo esc_html($item['department_number']); ?></span></dd>
            <?php endif; ?>
        </dl>
    </div>
    
    <?php if (!empty($item['description'])): ?>
    <div class="saw-detail-section">
        <h3>📝 Popis</h3>
        <p><?php echo nl2br(esc_html($item['description'])); ?></p>
    </div>
    <?php endif; ?>
    
    <div class="saw-detail-section">
        <h3>📚 Školení</h3>
        <dl>
            <dt>Verze školení</dt>
            <dd><span class="saw-version-badge-large">v<?php echo esc_html($item['training_version'] ?? 1); ?></span></dd>
        </dl>
    </div>
    
    <div class="saw-detail-section">
        <h3>ℹ️ Informace</h3>
        <dl>
            <dt>Status</dt>
            <dd>
                <?php if (!empty($item['is_active'])): ?>
                    <span class="saw-badge saw-badge-success">Aktivní</span>
                <?php else: ?>
                    <span class="saw-badge saw-badge-secondary">Neaktivní</span>
                <?php endif; ?>
            </dd>
            
            <?php if (!empty($item['created_at'])): ?>
                <dt>Vytvořeno</dt>
                <dd><?php echo esc_html(date_i18n('j. n. Y H:i', strtotime($item['created_at']))); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at'])): ?>
                <dt>Naposledy upraveno</dt>
                <dd><?php echo esc_html(date_i18n('j. n. Y H:i', strtotime($item['updated_at']))); ?></dd>
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

.saw-version-badge-large {
    display: inline-block;
    padding: 8px 16px;
    background: #eff6ff;
    border: 2px solid #bfdbfe;
    border-radius: 8px;
    font-family: 'SF Mono', 'Monaco', 'Courier New', monospace;
    font-size: 16px;
    font-weight: 600;
    color: #1e40af;
}

.saw-code-badge {
    display: inline-block;
    padding: 4px 10px;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    font-family: 'SF Mono', 'Monaco', 'Courier New', monospace;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    letter-spacing: 0.5px;
}

.saw-text-muted {
    color: #9ca3af;
    font-size: 14px;
}
</style>