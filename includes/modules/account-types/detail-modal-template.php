<?php
/**
 * Account Types Detail Sidebar Template
 * 
 * FALLBACK: This template is used until SAW Table is fully implemented.
 * Once SAW Table detail rendering is complete, this file can be deleted.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/AccountTypes
 * @version     4.0.0 - SAW Table integration (fallback)
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// VALIDATION
// ============================================
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Typ účtu nebyl nalezen</div>';
    return;
}

// ============================================
// PREPARE DATA
// ============================================
// Features
$features_array = array();
if (!empty($item['features'])) {
    $features = json_decode($item['features'], true);
    $features_array = is_array($features) ? $features : array();
}
$features_count = count($features_array);

// Price
$price = floatval($item['price'] ?? 0);
$price_display = $price > 0 
    ? number_format($price, 0, ',', ' ') . ' Kč' 
    : 'Zdarma';

// Customers count
$customers_count = 0;
if (!empty($item['id'])) {
    global $wpdb;
    $customers_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_customers WHERE account_type_id = %d",
        $item['id']
    ));
}
?>

<!-- Header is rendered by detail-sidebar.php using get_detail_header_meta() -->

<div class="saw-detail-wrapper">
    <div class="saw-detail-stack">
        
        <!-- STATISTICS -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📊 Statistiky</h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label">Zákazníků</span>
                    <span class="saw-info-val"><strong><?php echo $customers_count; ?></strong></span>
                </div>
                <div class="saw-info-row">
                    <span class="saw-info-label">Funkcí</span>
                    <span class="saw-info-val"><strong><?php echo $features_count; ?></strong></span>
                </div>
            </div>
        </div>
        
        <!-- PRICE -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">💰 Cena</h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label">Měsíční cena</span>
                    <span class="saw-info-val">
                        <strong style="font-size: 18px; color: <?php echo $price > 0 ? '#059669' : '#6b7280'; ?>;">
                            <?php echo esc_html($price_display); ?>
                        </strong>
                        <?php if ($price > 0): ?>
                            <small style="color: #6b7280;">/měsíc</small>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- FEATURES -->
        <?php if (!empty($features_array)): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">
                    ✨ Funkce 
                    <span class="saw-visit-badge-count"><?php echo $features_count; ?></span>
                </h4>
            </div>
            <div class="saw-section-body" style="padding: 0;">
                <?php foreach ($features_array as $feature): ?>
                <div class="saw-info-row" style="padding: 10px 20px; border-bottom: 1px solid #f0f0f0;">
                    <span class="saw-info-label" style="flex-shrink: 0; width: 24px; color: #10b981;">✓</span>
                    <span class="saw-info-val" style="flex: 1;"><?php echo esc_html($feature); ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- SETTINGS -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">⚙️ Nastavení</h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label">Barva</span>
                    <span class="saw-info-val">
                        <?php if (!empty($item['color'])): ?>
                            <span style="display: inline-flex; align-items: center; gap: 8px;">
                                <span style="display: inline-block; width: 20px; height: 20px; border-radius: 4px; background-color: <?php echo esc_attr($item['color']); ?>; border: 2px solid #e5e7eb;"></span>
                                <code style="font-family: monospace; font-size: 12px; color: #64748b;"><?php echo esc_html(strtoupper($item['color'])); ?></code>
                            </span>
                        <?php else: ?>
                            <span class="saw-text-muted">—</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="saw-info-row">
                    <span class="saw-info-label">Pořadí</span>
                    <span class="saw-info-val"><?php echo esc_html($item['sort_order'] ?? 0); ?></span>
                </div>
            </div>
        </div>
        
        <!-- METADATA -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🕐 Metadata</h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['created_at'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Vytvořeno</span>
                    <span class="saw-info-val"><?php echo esc_html(date_i18n('j. n. Y H:i', strtotime($item['created_at']))); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['updated_at'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Aktualizováno</span>
                    <span class="saw-info-val"><?php echo esc_html(date_i18n('j. n. Y H:i', strtotime($item['updated_at']))); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>
