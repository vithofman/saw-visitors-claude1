<?php
/**
 * Users Detail Modal Template
 *
 * @package     SAW_Visitors
 * @subpackage  Modules/Users
 * @version     6.3.0 - USES GLOBAL CSS: saw-industrial-section, saw-info-row
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// LOAD TRANSLATIONS
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'users') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return isset($t[$key]) ? $t[$key] : ($fallback !== null ? $fallback : $key);
};

// ============================================
// VALIDATE DATA
// ============================================
if (empty($item)) {
    echo '<p>' . esc_html($tr('detail_not_found', 'Uživatel nebyl nalezen.')) . '</p>';
    return;
}

// ============================================
// PREPARE DATA
// ============================================

// Format last login with relative time
$last_login_display = $tr('status_never_logged_in', 'Nikdy');
if (!empty($item['last_login'])) {
    $timestamp = strtotime($item['last_login']);
    $diff = time() - $timestamp;
    
    if ($diff < 3600) {
        $last_login_display = '🟢 ' . $tr('just_now', 'právě teď');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        $last_login_display = '🟢 ' . sprintf($tr('hours_ago', 'před %d h'), $hours);
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        $last_login_display = '🟡 ' . sprintf($tr('days_ago', 'před %d dny'), $days);
    } else {
        $last_login_display = date_i18n('j. n. Y H:i', $timestamp);
    }
}
?>

<!-- Header is rendered by detail-sidebar.php -->

<div class="saw-detail-wrapper">
    <div class="saw-detail-stack">
        
        <!-- TERMINAL SECTION (only for terminal role) -->
        <?php if ($item['role'] === 'terminal'): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🖥️ <?php echo esc_html($tr('section_terminal', 'Terminál')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_pin', 'PIN')); ?></span>
                    <span class="saw-info-val">
                        <?php if (!empty($item['pin'])): ?>
                            <span class="saw-badge saw-badge-success"><?php echo esc_html($tr('pin_set', 'Nastaven')); ?></span>
                        <?php else: ?>
                            <span class="saw-badge saw-badge-warning"><?php echo esc_html($tr('pin_not_set', 'Nenastaven')); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- INFORMATION SECTION -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">ℹ️ <?php echo esc_html($tr('section_info', 'Informace')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('col_last_login', 'Poslední přihlášení')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html($last_login_display); ?></span>
                </div>
                
                <?php if (!empty($item['created_at'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_created_at', 'Vytvořeno')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html(date_i18n('j. n. Y H:i', strtotime($item['created_at']))); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['updated_at'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('field_updated_at', 'Změněno')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html(date_i18n('j. n. Y H:i', strtotime($item['updated_at']))); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>