<?php
if (!defined('ABSPATH')) {
    exit;
}

if (empty($item)) {
    echo '<p>Jazyk nebyl nalezen.</p>';
    return;
}

global $wpdb;

$active_branches = $wpdb->get_results($wpdb->prepare(
    "SELECT b.id, b.name, b.code, b.city,
            lb.is_default, lb.display_order
     FROM {$wpdb->prefix}saw_branches b
     INNER JOIN {$wpdb->prefix}saw_training_language_branches lb ON b.id = lb.branch_id
     WHERE lb.language_id = %d AND lb.is_active = 1
     ORDER BY lb.display_order ASC, b.name ASC",
    $item['id']
), ARRAY_A);

$is_protected = ($item['language_code'] === 'cs');
?>

<div class="saw-detail-header">
    <div class="saw-language-flag-large">
        <?php echo esc_html($item['flag_emoji']); ?>
    </div>
    
    <div class="saw-detail-header-info">
        <h2>
            <?php echo esc_html($item['language_name']); ?>
            <?php if ($is_protected): ?>
                <span class="saw-badge saw-badge-info">Povinný</span>
            <?php endif; ?>
        </h2>
        <p>Kód: <strong><?php echo esc_html($item['language_code']); ?></strong></p>
    </div>
</div>

<div class="saw-detail-sections">
    
    <?php if (!empty($active_branches)): ?>
    <div class="saw-detail-section">
        <h3>🏢 Aktivní pobočky</h3>
        <div class="saw-branches-detail-list">
            <?php foreach ($active_branches as $branch): ?>
                <div class="saw-branch-detail-item">
                    <div class="saw-branch-detail-name">
                        <strong><?php echo esc_html($branch['name']); ?></strong>
                        <?php if (!empty($branch['code'])): ?>
                            <span class="saw-branch-code"><?php echo esc_html($branch['code']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($branch['city'])): ?>
                            <span class="saw-branch-city"><?php echo esc_html($branch['city']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="saw-branch-detail-meta">
                        <?php if (!empty($branch['is_default'])): ?>
                            <span class="saw-badge saw-badge-success">Výchozí</span>
                        <?php endif; ?>
                        <span class="saw-badge saw-badge-secondary">Pořadí: <?php echo esc_html($branch['display_order']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="saw-detail-section">
        <h3>🏢 Aktivní pobočky</h3>
        <div class="saw-notice saw-notice-warning">
            <p>Tento jazyk není aktivován pro žádnou pobočku.</p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="saw-detail-section">
        <h3>ℹ️ Informace</h3>
        <dl>
            <dt>Kód jazyka</dt>
            <dd><span class="saw-code-badge"><?php echo esc_html($item['language_code']); ?></span></dd>
            
            <dt>Název</dt>
            <dd><?php echo esc_html($item['language_name']); ?></dd>
            
            <dt>Vlajka</dt>
            <dd><span style="font-size: 32px;"><?php echo esc_html($item['flag_emoji']); ?></span></dd>
            
            <dt>Počet aktivních poboček</dt>
            <dd><?php echo count($active_branches); ?></dd>
            
            <?php if ($is_protected): ?>
                <dt>Ochrana</dt>
                <dd><span class="saw-badge saw-badge-warning">Čeština nemůže být smazána</span></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['created_at'])): ?>
                <dt>Vytvořeno</dt>
                <dd><?php echo date('d.m.Y H:i', strtotime($item['created_at'])); ?></dd>
            <?php endif; ?>
            
            <?php if (!empty($item['updated_at'])): ?>
                <dt>Naposledy upraveno</dt>
                <dd><?php echo date('d.m.Y H:i', strtotime($item['updated_at'])); ?></dd>
            <?php endif; ?>
        </dl>
    </div>
    
</div>

<style>
.saw-language-flag-large {
    width: 88px;
    height: 88px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 14px;
    border: 2px solid #e2e8f0;
    font-size: 48px;
}

.saw-branches-detail-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.saw-branch-detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 16px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.saw-branch-detail-name {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.saw-branch-detail-name strong {
    font-size: 15px;
    color: #111827;
}

.saw-branch-code {
    display: inline-block;
    padding: 2px 8px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    color: #6b7280;
    font-family: monospace;
    margin-right: 8px;
}

.saw-branch-city {
    font-size: 13px;
    color: #6b7280;
}

.saw-branch-detail-meta {
    display: flex;
    gap: 8px;
    align-items: center;
}

@media (max-width: 768px) {
    .saw-branch-detail-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}
</style>
