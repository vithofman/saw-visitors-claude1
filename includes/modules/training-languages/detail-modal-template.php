<?php
/**
 * Detail Content Template
 * Used inside the sidebar structure
 */

if (!defined('ABSPATH')) {
    exit;
}

$active_branches = $item['active_branches'] ?? [];
$is_protected = ($item['language_code'] === 'cs');
?>

<div class="saw-detail-header">
    <div class="saw-language-flag-large">
        <?php echo esc_html($item['flag_emoji']); ?>
    </div>
    
    <div class="saw-detail-header-info">
        <h2 class="saw-detail-header-title">
            <?php echo esc_html($item['language_name']); ?>
            <?php if ($is_protected): ?>
                <span class="saw-badge saw-badge-info">Povinný</span>
            <?php endif; ?>
        </h2>
        <div class="saw-detail-header-meta">
            <span>Kód: <strong><?php echo esc_html(strtoupper($item['language_code'])); ?></strong></span>
            <span>•</span>
            <span>Pobočky: <strong><?php echo count($active_branches); ?></strong></span>
        </div>
    </div>
</div>

<div class="saw-detail-sections">
    
    <div class="saw-detail-section">
        <div class="saw-detail-section-title">
            <span class="dashicons dashicons-building"></span> Aktivní pobočky
        </div>
        
        <?php if (!empty($active_branches)): ?>
            <div class="saw-branches-detail-list">
                <?php foreach ($active_branches as $branch): ?>
                    <div class="saw-branch-detail-item">
                        <div class="saw-branch-detail-name">
                            <strong><?php echo esc_html($branch['name']); ?></strong>
                            <?php if (!empty($branch['code'])): ?>
                                <span class="saw-branch-code"><?php echo esc_html($branch['code']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="saw-branch-detail-meta">
                            <?php if (!empty($branch['is_default'])): ?>
                                <span class="saw-badge saw-badge-success">Výchozí</span>
                            <?php endif; ?>
                            <?php if (!empty($branch['display_order'])): ?>
                                <span class="saw-badge saw-badge-default">Pořadí: <?php echo esc_html($branch['display_order']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="saw-notice saw-notice-warning">
                <p>Tento jazyk není aktivován pro žádnou pobočku.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="saw-detail-section">
        <div class="saw-detail-section-title">
            <span class="dashicons dashicons-info"></span> Systémové informace
        </div>
        <div class="saw-detail-section-content">
            <dl class="saw-detail-list">
                <dt>Vytvořeno</dt>
                <dd><?php echo $item['created_at_formatted'] ?? '—'; ?></dd>
                
                <dt>Naposledy upraveno</dt>
                <dd><?php echo $item['updated_at_formatted'] ?? '—'; ?></dd>
                
                <dt>ID záznamu</dt>
                <dd>#<?php echo esc_html($item['id']); ?></dd>
            </dl>
        </div>
    </div>
    
</div>

<style>
.saw-language-flag-large {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    font-size: 32px;
    flex-shrink: 0;
}

.saw-branches-detail-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.saw-branch-detail-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 14px;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.saw-branch-detail-name {
    display: flex;
    align-items: center;
    gap: 8px;
}

.saw-branch-detail-name strong {
    font-size: 14px;
    color: #0f172a;
}

.saw-branch-code {
    display: inline-block;
    padding: 1px 6px;
    background: #f1f5f9;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
    color: #64748b;
    font-family: monospace;
}

.saw-branch-detail-meta {
    display: flex;
    gap: 6px;
    align-items: center;
}
</style>