<?php
/**
 * Training Languages Detail Sidebar Template
 *
 * Header with flag, code, branches count is rendered by detail-sidebar.php
 * via get_detail_header_meta() in controller.
 *
 * @package    SAW_Visitors
 * @subpackage Modules/TrainingLanguages
 * @version    5.0.0 - REDESIGNED: No hero card (in blue header), no ID
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
    ? saw_get_translations($lang, 'admin', 'training_languages') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// VALIDATION
// ============================================
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">' . esc_html($tr('error_not_found', 'Jazyk nebyl nalezen')) . '</div>';
    return;
}

// ============================================
// PREPARE DATA
// ============================================
$active_branches = $item['active_branches'] ?? array();
$is_protected = ($item['language_code'] === 'cs');
$branches_count = count($active_branches);

// Count default branches
$default_count = 0;
foreach ($active_branches as $b) {
    if (!empty($b['is_default'])) $default_count++;
}
?>

<!-- Header is rendered by detail-sidebar.php using get_detail_header_meta() -->

<div class="saw-detail-wrapper">
    <div class="saw-detail-stack">
        
        <!-- STATISTICS -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title"><?php if (class_exists('SAW_Icons')): ?><?php echo SAW_Icons::get('bar-chart-3', 'saw-icon--sm'); ?><?php else: ?>📊<?php endif; ?> <?php echo esc_html($tr('section_statistics', 'Statistiky')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('stat_active_branches', 'Aktivních poboček')); ?></span>
                    <span class="saw-info-val"><strong><?php echo $branches_count; ?></strong></span>
                </div>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('stat_default_branches', 'Výchozí na pobočkách')); ?></span>
                    <span class="saw-info-val"><strong><?php echo $default_count; ?></strong></span>
                </div>
            </div>
        </div>
        
        <!-- ACTIVE BRANCHES -->
        <?php if (!empty($active_branches)): ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title"><?php if (class_exists('SAW_Icons')): ?><?php echo SAW_Icons::get('building-2', 'saw-icon--sm'); ?><?php else: ?>🏢<?php endif; ?> <?php echo esc_html($tr('section_branches', 'Pobočky')); ?> <span class="saw-visit-badge-count"><?php echo $branches_count; ?></span></h4>
            </div>
            <div class="saw-section-body" style="padding: 0;">
                <?php foreach ($active_branches as $branch): ?>
                <a href="<?php echo esc_url(home_url('/admin/branches/' . intval($branch['id']) . '/')); ?>" 
                   class="saw-info-row" 
                   style="display: flex; padding: 12px 20px; text-decoration: none; border-bottom: 1px solid #f0f0f0;">
                    <span class="saw-info-label" style="flex-shrink: 0;">
                        <?php 
                        if (class_exists('SAW_Icons')) {
                            echo !empty($branch['is_default']) ? SAW_Icons::get('star', 'saw-icon--sm') : SAW_Icons::get('building-2', 'saw-icon--sm');
                        } else {
                            echo !empty($branch['is_default']) ? '⭐' : '🏢';
                        }
                        ?>
                    </span>
                    <span class="saw-info-val" style="flex: 1;">
                        <?php echo esc_html($branch['name']); ?>
                        <?php if (!empty($branch['code'])): ?>
                            <span style="color: #888; font-size: 12px;"><?php echo esc_html($branch['code']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($branch['is_default'])): ?>
                            <span class="saw-badge saw-badge-success" style="margin-left: 8px; font-size: 10px;"><?php echo esc_html($tr('badge_default', 'Výchozí')); ?></span>
                        <?php endif; ?>
                    </span>
                    <?php if (!empty($branch['display_order'])): ?>
                    <span style="color: #888; font-size: 12px;">#<?php echo intval($branch['display_order']); ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title"><?php if (class_exists('SAW_Icons')): ?><?php echo SAW_Icons::get('building-2', 'saw-icon--sm'); ?><?php else: ?>🏢<?php endif; ?> <?php echo esc_html($tr('section_branches', 'Pobočky')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-notice saw-notice-warning" style="margin: 0;">
                    <p style="margin: 0;"><?php echo esc_html($tr('detail_no_branches', 'Tento jazyk není aktivován pro žádnou pobočku.')); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- INFO (without ID) -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title"><?php if (class_exists('SAW_Icons')): ?><?php echo SAW_Icons::get('info', 'saw-icon--sm'); ?><?php else: ?>ℹ️<?php endif; ?> <?php echo esc_html($tr('section_info', 'Informace')); ?></h4>
            </div>
            <div class="saw-section-body">
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('detail_language_code', 'Kód jazyka')); ?></span>
                    <span class="saw-info-val"><code><?php echo esc_html(strtoupper($item['language_code'])); ?></code></span>
                </div>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('detail_language_name', 'Název')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html($item['language_name']); ?></span>
                </div>
                <?php if ($is_protected): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('detail_protection', 'Ochrana')); ?></span>
                    <span class="saw-info-val">
                        <span class="saw-badge saw-badge-info"><?php echo esc_html($tr('badge_system_language', 'Systémový jazyk')); ?></span>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- METADATA -->
        <div class="saw-industrial-section">
            <div class="saw-section-head">
                <h4 class="saw-section-title">🕐 <?php echo esc_html($tr('section_metadata', 'Metadata')); ?></h4>
            </div>
            <div class="saw-section-body">
                <?php if (!empty($item['created_at_formatted'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('detail_created_at', 'Vytvořeno')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html($item['created_at_formatted']); ?></span>
                </div>
                <?php elseif (!empty($item['created_at'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('detail_created_at', 'Vytvořeno')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($item['created_at']))); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($item['updated_at_formatted'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('detail_updated_at', 'Změněno')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html($item['updated_at_formatted']); ?></span>
                </div>
                <?php elseif (!empty($item['updated_at'])): ?>
                <div class="saw-info-row">
                    <span class="saw-info-label"><?php echo esc_html($tr('detail_updated_at', 'Změněno')); ?></span>
                    <span class="saw-info-val"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($item['updated_at']))); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<!-- ============================================
     HEADER FLAG STYLE (for blue header)
     ============================================ -->
<style>
/* Large flag emoji in blue header */
.saw-header-flag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 8px;
    font-size: 28px;
    margin-right: 8px;
    vertical-align: middle;
}

/* Code badge in header */
.saw-badge-code {
    font-family: monospace;
    font-weight: 700;
    font-size: 16px;
    letter-spacing: 1px;
}
</style>
