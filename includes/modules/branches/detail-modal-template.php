<?php
/**
 * Branches Detail Sidebar Template
 *
 * SPRÁVNÁ VERZE - stejná struktura jako visitors/companies
 * Header se renderuje externě v detail-sidebar.php
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     18.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// VALIDATION
// ============================================
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">Pobočka nebyla nalezena</div>';
    return;
}

// ============================================
// LOAD RELATED DATA
// ============================================
global $wpdb;

// Departments count
$departments_count = 0;
if (!empty($item['id'])) {
    $departments_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_departments WHERE branch_id = %d",
        intval($item['id'])
    ));
}

// Visits count
$visits_count = 0;
if (!empty($item['id'])) {
    $visits_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE branch_id = %d",
        intval($item['id'])
    ));
}

// Visitors count (unique)
$visitors_count = 0;
if (!empty($item['id'])) {
    $visitors_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT vis.id) 
         FROM {$wpdb->prefix}saw_visitors vis
         INNER JOIN {$wpdb->prefix}saw_visits v ON vis.visit_id = v.id
         WHERE v.branch_id = %d",
        intval($item['id'])
    ));
}

// Load departments (for related section)
$departments = array();
if (!empty($item['id'])) {
    $departments = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, department_number, is_active 
         FROM {$wpdb->prefix}saw_departments 
         WHERE branch_id = %d 
         ORDER BY name ASC
         LIMIT 5",
        intval($item['id'])
    ), ARRAY_A) ?: array();
}
?>

<!-- Header with image is rendered by detail-sidebar.php -->

<div class="saw-detail-wrapper">
    <div class="saw-detail-stack">
        
        <!-- STATISTICS -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📊 Statistiky</h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label">Oddělení</span>
                    <span class="saw-info-val"><strong><?php echo $departments_count; ?></strong></span>
                </div>
                <div class="saw-info-row">
                    <span class="saw-info-label">Návštěv</span>
                    <span class="saw-info-val"><strong><?php echo $visits_count; ?></strong></span>
                </div>
                <div class="saw-info-row">
                    <span class="saw-info-label">Návštěvníků</span>
                    <span class="saw-info-val"><strong><?php echo $visitors_count; ?></strong></span>
                </div>
            </div>
        </div>
        
        <!-- ADDRESS -->
        <?php 
        $has_address = !empty($item['street']) || !empty($item['city']) || !empty($item['postal_code']);
        if ($has_address): 
        ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📍 Adresa</h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['street'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Ulice</span>
                    <span class="saw-info-val"><?php echo esc_html($item['street']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['city']) || !empty($item['postal_code'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Město a PSČ</span>
                    <span class="saw-info-val"><?php 
                        $city_parts = array();
                        if (!empty($item['postal_code'])) $city_parts[] = $item['postal_code'];
                        if (!empty($item['city'])) $city_parts[] = $item['city'];
                        echo esc_html(implode(' ', $city_parts));
                    ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['country']) && $item['country'] !== 'CZ'): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Země</span>
                    <span class="saw-info-val"><?php echo esc_html($item['country']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- CONTACT -->
        <?php if (!empty($item['phone']) || !empty($item['email'])): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📞 Kontakt</h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['phone'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Telefon</span>
                    <span class="saw-info-val">
                        <a href="tel:<?php echo esc_attr(str_replace(' ', '', $item['phone'])); ?>" class="saw-link">
                            <?php echo esc_html($item['phone']); ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['email'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Email</span>
                    <span class="saw-info-val">
                        <a href="mailto:<?php echo esc_attr($item['email']); ?>" class="saw-link">
                            <?php echo esc_html($item['email']); ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- INFO -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">ℹ️ Informace</h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label">ID</span>
                    <span class="saw-info-val"><code><?php echo intval($item['id']); ?></code></span>
                </div>
                
                <?php if (!empty($item['code'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Kód pobočky</span>
                    <span class="saw-info-val"><code><?php echo esc_html($item['code']); ?></code></span>
                </div>
                <?php endif; ?>
                
                <div class="saw-info-row">
                    <span class="saw-info-label">Typ</span>
                    <span class="saw-info-val">
                        <?php if (!empty($item['is_headquarters'])): ?>
                            <span class="saw-badge saw-badge-primary">Sídlo firmy</span>
                        <?php else: ?>
                            Pobočka
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="saw-info-row">
                    <span class="saw-info-label">Status</span>
                    <span class="saw-info-val">
                        <?php if (!empty($item['is_active'])): ?>
                            <span class="saw-badge saw-badge-success">Aktivní</span>
                        <?php else: ?>
                            <span class="saw-badge saw-badge-secondary">Neaktivní</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <?php if (!empty($item['sort_order'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Pořadí</span>
                    <span class="saw-info-val"><?php echo intval($item['sort_order']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- DESCRIPTION -->
        <?php if (!empty($item['description'])): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📝 Popis</h4>
            </div>
            <div class="saw-section-body">
                <p style="margin: 0; line-height: 1.6;"><?php echo nl2br(esc_html($item['description'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- NOTES -->
        <?php if (!empty($item['notes'])): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">💬 Interní poznámky</h4>
            </div>
            <div class="saw-section-body">
                <p style="margin: 0; line-height: 1.6; color: #666; font-style: italic;"><?php echo nl2br(esc_html($item['notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- DEPARTMENTS -->
        <?php if (!empty($departments)): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🏭 Oddělení <span class="saw-visit-badge-count"><?php echo $departments_count; ?></span></h4>
            </div>
            <div class="saw-section-body" style="padding: 0;">
                <?php foreach ($departments as $dept): ?>
                <a href="<?php echo esc_url(home_url('/admin/departments/' . intval($dept['id']) . '/')); ?>" 
                   class="saw-info-row" 
                   style="display: flex; padding: 12px 20px; text-decoration: none; border-bottom: 1px solid #f0f0f0;">
                    <span class="saw-info-label" style="flex-shrink: 0;">
                        <?php echo !empty($dept['is_active']) ? '🏭' : '⏸️'; ?>
                    </span>
                    <span class="saw-info-val" style="flex: 1;">
                        <?php echo esc_html($dept['name']); ?>
                        <?php if (!empty($dept['department_number'])): ?>
                            <span style="color: #888; font-size: 12px;">#<?php echo esc_html($dept['department_number']); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
                <?php endforeach; ?>
                
                <?php if ($departments_count > 5): ?>
                <a href="<?php echo esc_url(home_url('/admin/departments/?branch_id=' . intval($item['id']))); ?>" 
                   style="display: block; padding: 12px 20px; text-align: center; color: #0077B5; font-weight: 600; text-decoration: none;">
                    → Zobrazit všechna (<?php echo $departments_count; ?>)
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- METADATA -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🕐 Metadata</h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['created_at_formatted'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Vytvořeno</span>
                    <span class="saw-info-val"><?php echo esc_html($item['created_at_formatted']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['updated_at_formatted'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label">Změněno</span>
                    <span class="saw-info-val"><?php echo esc_html($item['updated_at_formatted']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>