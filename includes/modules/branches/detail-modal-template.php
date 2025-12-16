<?php
/**
 * Branches Detail Sidebar Template
 *
 * SPRÁVNÁ VERZE - stejná struktura jako visitors/companies
 * Header se renderuje externě v detail-sidebar.php
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Branches
 * @version     19.0.0 - ADDED: Translation support
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'branches') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// VALIDATION
// ============================================
if (empty($item)) {
    echo '<div class="sa-alert sa-alert--danger">' . esc_html($tr('error_not_found', 'Pobočka nebyla nalezena')) . '</div>';
    return;
}

// ============================================
// LOAD RELATED DATA
// ============================================
global $wpdb;

$departments_count = 0;
$visits_count = 0;
$visitors_count = 0;
$departments = array();

if (!empty($item['id'])) {
    $branch_id = intval($item['id']);
    
    $departments_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_departments WHERE branch_id = %d",
        $branch_id
    ));
    
    $visits_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE branch_id = %d",
        $branch_id
    ));
    
    $visitors_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT vis.id) 
         FROM {$wpdb->prefix}saw_visitors vis
         INNER JOIN {$wpdb->prefix}saw_visits v ON vis.visit_id = v.id
         WHERE v.branch_id = %d",
        $branch_id
    ));
    
    $departments = $wpdb->get_results($wpdb->prepare(
        "SELECT id, name, department_number, is_active 
         FROM {$wpdb->prefix}saw_departments 
         WHERE branch_id = %d 
         ORDER BY name ASC
         LIMIT 5",
        $branch_id
    ), ARRAY_A) ?: array();
}
?>

<!-- Header with image is rendered by detail-sidebar.php -->

<div class="sa-detail-wrapper">
    <div class="sa-detail-stack">
        
        <!-- STATISTICS -->
        <div class="sa-detail-section">
            <div class="sa-detail-section-head">
                <h4 class="sa-detail-section-title">📊 <?php echo esc_html($tr('section_statistics', 'Statistiky')); ?></h4>
            </div>
            <div class="sa-detail-section-body">
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('stat_departments', 'Oddělení')); ?></span>
                    <span class="sa-detail-field-value"><strong><?php echo $departments_count; ?></strong></span>
                </div>
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('stat_visits', 'Návštěv')); ?></span>
                    <span class="sa-detail-field-value"><strong><?php echo $visits_count; ?></strong></span>
                </div>
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('stat_visitors', 'Návštěvníků')); ?></span>
                    <span class="sa-detail-field-value"><strong><?php echo $visitors_count; ?></strong></span>
                </div>
            </div>
        </div>
        
        <!-- ADDRESS -->
        <?php 
        $has_address = !empty($item['street']) || !empty($item['city']) || !empty($item['postal_code']);
        if ($has_address): 
        ?>
        <div class="sa-detail-section">
            <div class="sa-detail-section-head">
                <h4 class="sa-detail-section-title">📍 <?php echo esc_html($tr('section_address', 'Adresa')); ?></h4>
            </div>
            <div class="sa-detail-section-body">
                <?php if (!empty($item['street'])): ?>
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('field_street', 'Ulice')); ?></span>
                    <span class="sa-detail-field-value"><?php echo esc_html($item['street']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['city']) || !empty($item['postal_code'])): ?>
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('field_city_zip', 'Město a PSČ')); ?></span>
                    <span class="sa-detail-field-value"><?php 
                        $city_parts = array();
                        if (!empty($item['postal_code'])) $city_parts[] = $item['postal_code'];
                        if (!empty($item['city'])) $city_parts[] = $item['city'];
                        echo esc_html(implode(' ', $city_parts));
                    ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['country']) && $item['country'] !== 'CZ'): ?>
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('field_country', 'Země')); ?></span>
                    <span class="sa-detail-field-value"><?php echo esc_html($item['country']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- CONTACT -->
        <?php if (!empty($item['phone']) || !empty($item['email'])): ?>
        <div class="sa-detail-section">
            <div class="sa-detail-section-head">
                <h4 class="sa-detail-section-title">📞 <?php echo esc_html($tr('section_contact', 'Kontakt')); ?></h4>
            </div>
            <div class="sa-detail-section-body">
                <?php if (!empty($item['phone'])): ?>
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('field_phone', 'Telefon')); ?></span>
                    <span class="sa-detail-field-value">
                        <a href="tel:<?php echo esc_attr(str_replace(' ', '', $item['phone'])); ?>" class="sa-link">
                            <?php echo esc_html($item['phone']); ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['email'])): ?>
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('field_email', 'Email')); ?></span>
                    <span class="sa-detail-field-value">
                        <a href="mailto:<?php echo esc_attr($item['email']); ?>" class="sa-link">
                            <?php echo esc_html($item['email']); ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- INFO -->
        <div class="sa-detail-section">
            <div class="sa-detail-section-head">
                <h4 class="sa-detail-section-title">ℹ️ <?php echo esc_html($tr('section_info', 'Informace')); ?></h4>
            </div>
            <div class="sa-detail-section-body">
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label">ID</span>
                    <span class="sa-detail-field-value"><code><?php echo intval($item['id']); ?></code></span>
                </div>
                
                <?php if (!empty($item['code'])): ?>
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('field_code', 'Kód pobočky')); ?></span>
                    <span class="sa-detail-field-value"><code><?php echo esc_html($item['code']); ?></code></span>
                </div>
                <?php endif; ?>
                
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('field_type', 'Typ')); ?></span>
                    <span class="sa-detail-field-value">
                        <?php if (!empty($item['is_headquarters'])): ?>
                            <span class="sa-badge sa-badge--info"><?php echo esc_html($tr('badge_headquarters', 'Sídlo firmy')); ?></span>
                        <?php else: ?>
                            <?php echo esc_html($tr('badge_branch', 'Pobočka')); ?>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('field_status', 'Status')); ?></span>
                    <span class="sa-detail-field-value">
                        <?php if (!empty($item['is_active'])): ?>
                            <span class="sa-badge sa-badge--success"><?php echo esc_html($tr('status_active', 'Aktivní')); ?></span>
                        <?php else: ?>
                            <span class="sa-badge sa-badge--neutral"><?php echo esc_html($tr('status_inactive', 'Neaktivní')); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <?php if (!empty($item['sort_order'])): ?>
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('field_sort_order', 'Pořadí')); ?></span>
                    <span class="sa-detail-field-value"><?php echo intval($item['sort_order']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- DESCRIPTION -->
        <?php if (!empty($item['description'])): ?>
        <div class="sa-detail-section">
            <div class="sa-detail-section-head">
                <h4 class="sa-detail-section-title">📝 <?php echo esc_html($tr('section_description', 'Popis')); ?></h4>
            </div>
            <div class="sa-detail-section-body">
                <p class="sa-detail-field-value"><?php echo nl2br(esc_html($item['description'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- NOTES -->
        <?php if (!empty($item['notes'])): ?>
        <div class="sa-detail-section">
            <div class="sa-detail-section-head">
                <h4 class="sa-detail-section-title">💬 <?php echo esc_html($tr('section_notes', 'Interní poznámky')); ?></h4>
            </div>
            <div class="sa-detail-section-body">
                <p class="sa-detail-field-value sa-text-muted" style="font-style: italic;"><?php echo nl2br(esc_html($item['notes'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- DEPARTMENTS -->
        <?php if (!empty($departments)): ?>
        <div class="sa-detail-section">
            <div class="sa-detail-section-head">
                <h4 class="sa-detail-section-title">🏭 <?php echo esc_html($tr('section_departments', 'Oddělení')); ?> <span class="sa-badge sa-badge--count"><?php echo $departments_count; ?></span></h4>
            </div>
            <div class="sa-detail-section-body sa-p-0">
                <?php foreach ($departments as $dept): ?>
                <a href="<?php echo esc_url(home_url('/admin/departments/' . intval($dept['id']) . '/')); ?>" 
                   class="sa-department-link">
                    <span class="sa-department-icon">
                        <?php echo !empty($dept['is_active']) ? '🏭' : '⏸️'; ?>
                    </span>
                    <span class="sa-department-name">
                        <?php echo esc_html($dept['name']); ?>
                        <?php if (!empty($dept['department_number'])): ?>
                            <span class="sa-department-number">#<?php echo esc_html($dept['department_number']); ?></span>
                        <?php endif; ?>
                    </span>
                </a>
                <?php endforeach; ?>
                
                <?php if ($departments_count > 5): ?>
                <a href="<?php echo esc_url(home_url('/admin/departments/?branch_id=' . intval($item['id']))); ?>" 
                   class="sa-department-show-all">
                    → <?php echo esc_html($tr('show_all', 'Zobrazit všechna')); ?> (<?php echo $departments_count; ?>)
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- METADATA -->
        <div class="sa-detail-section">
            <div class="sa-detail-section-head">
                <h4 class="sa-detail-section-title">🕐 <?php echo esc_html($tr('section_metadata', 'Metadata')); ?></h4>
            </div>
            <div class="sa-detail-section-body">
                <?php if (!empty($item['created_at_formatted'])): ?>
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('field_created_at', 'Vytvořeno')); ?></span>
                    <span class="sa-detail-field-value"><?php echo esc_html($item['created_at_formatted']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['updated_at_formatted'])): ?>
                <div class="sa-detail-field">
                    <span class="sa-detail-field-label"><?php echo esc_html($tr('field_updated_at', 'Změněno')); ?></span>
                    <span class="sa-detail-field-value"><?php echo esc_html($item['updated_at_formatted']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>