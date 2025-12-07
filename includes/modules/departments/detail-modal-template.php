<?php
/**
 * Departments Detail Sidebar Template
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Departments
 * @since       1.0.0
 * @version     3.0.0 - REFACTORED: Industrial sections, translations, related users
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
    ? saw_get_translations($lang, 'admin', 'departments') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// VALIDATION
// ============================================
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">' 
        . esc_html($tr('error_not_found', 'Oddělení nebylo nalezeno')) 
        . '</div>';
    return;
}

// ============================================
// LOAD RELATED DATA
// ============================================
global $wpdb;

// Count assigned users
$users_count = 0;
if (!empty($item['id'])) {
    $users_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_user_departments WHERE department_id = %d",
        intval($item['id'])
    ));
}

// Get assigned users (limit 5)
$assigned_users = array();
if (!empty($item['id']) && $users_count > 0) {
    $assigned_users = $wpdb->get_results($wpdb->prepare(
        "SELECT u.id, u.first_name, u.last_name, u.email, u.is_active
         FROM {$wpdb->prefix}saw_users u
         INNER JOIN {$wpdb->prefix}saw_user_departments ud ON u.id = ud.user_id
         WHERE ud.department_id = %d
         ORDER BY u.last_name ASC, u.first_name ASC
         LIMIT 5",
        intval($item['id'])
    ), ARRAY_A);
}
?>

<!-- Header with image is rendered by detail-sidebar.php -->

<div class="saw-detail-wrapper">
    <div class="saw-detail-stack">
        
        <!-- STATISTICS -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📊 <?php echo esc_html($tr('section_statistics', 'Statistiky')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('stat_users', 'Uživatelů')); ?></span>
                    <span class="saw-info-val"><strong><?php echo $users_count; ?></strong></span>
                </div>
            </div>
        </div>
        
        <!-- BRANCH -->
        <?php if (!empty($item['branch_name'])): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🏢 <?php echo esc_html($tr('section_branch', 'Pobočka')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_branch', 'Pobočka')); ?></span>
                    <span class="saw-info-val">
                        <?php if (!empty($item['branch_code'])): ?>
                            <code><?php echo esc_html($item['branch_code']); ?></code>
                        <?php endif; ?>
                        <?php echo esc_html($item['branch_name']); ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- INFO -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">ℹ️ <?php echo esc_html($tr('section_info', 'Informace')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label">ID</span>
                    <span class="saw-info-val"><code><?php echo intval($item['id']); ?></code></span>
                </div>
                
                <?php if (!empty($item['department_number'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_department_number', 'Číslo oddělení')); ?></span>
                    <span class="saw-info-val"><code><?php echo esc_html($item['department_number']); ?></code></span>
                </div>
                <?php endif; ?>
                
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('col_status', 'Status')); ?></span>
                    <span class="saw-info-val">
                        <?php if (!empty($item['is_active'])): ?>
                            <span class="saw-badge saw-badge-success"><?php echo esc_html($tr('status_active', 'Aktivní')); ?></span>
                        <?php else: ?>
                            <span class="saw-badge saw-badge-secondary"><?php echo esc_html($tr('status_inactive', 'Neaktivní')); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- DESCRIPTION -->
        <?php if (!empty($item['description'])): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">📝 <?php echo esc_html($tr('section_description', 'Popis')); ?></h4>
            </div>
            <div class="saw-section-body">
                <p style="margin: 0; line-height: 1.6;"><?php echo nl2br(esc_html($item['description'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ASSIGNED USERS -->
        <?php if (!empty($assigned_users)): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">
                    👥 <?php echo esc_html($tr('section_users', 'Přiřazení uživatelé')); ?> 
                    <span class="saw-visit-badge-count"><?php echo $users_count; ?></span>
                </h4>
            </div>
            <div class="saw-section-body" style="padding: 0;">
                <?php foreach ($assigned_users as $user): ?>
                <a href="<?php echo esc_url(home_url('/admin/users/' . intval($user['id']) . '/')); ?>" 
                   class="saw-info-row" 
                   style="display: flex; padding: 12px 20px; text-decoration: none; border-bottom: 1px solid #f0f0f0;">
                    <span class="saw-info-label" style="flex-shrink: 0; width: 24px;">
                        <?php echo !empty($user['is_active']) ? '✓' : '⏸️'; ?>
                    </span>
                    <span class="saw-info-val" style="flex: 1;">
                        <strong><?php echo esc_html(trim($user['last_name'] . ' ' . $user['first_name'])); ?></strong>
                        <?php if (!empty($user['email'])): ?>
                            <br><small style="color: #666;"><?php echo esc_html($user['email']); ?></small>
                        <?php endif; ?>
                    </span>
                </a>
                <?php endforeach; ?>
                
                <?php if ($users_count > 5): ?>
                <a href="<?php echo esc_url(home_url('/admin/users/?department_id=' . intval($item['id']))); ?>" 
                   style="display: block; padding: 12px 20px; text-align: center; color: #0077B5; font-weight: 600; text-decoration: none;">
                    → <?php echo esc_html($tr('show_all', 'Zobrazit všechny')); ?> (<?php echo $users_count; ?>)
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- METADATA -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🕐 <?php echo esc_html($tr('section_metadata', 'Metadata')); ?></h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['created_at_formatted'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_created_at', 'Vytvořeno')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html($item['created_at_formatted']); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['updated_at_formatted'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_updated_at', 'Změněno')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html($item['updated_at_formatted']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>