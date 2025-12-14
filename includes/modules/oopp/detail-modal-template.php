<?php
/**
 * OOPP Detail Modal Template
 * 
 * Displays detailed information about an OOPP in sidebar.
 * Modern card-based design matching visits module style.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/OOPP
 * @version     3.0.0 - ADDED: Translation support
 */

if (!defined('ABSPATH')) {
    exit;
}

// ============================================
// TRANSLATIONS SETUP
// ============================================
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}

$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'oopp') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// CHECK DATA
// ============================================
if (empty($item)) {
    echo '<div class="saw-alert saw-alert-danger">' . esc_html($tr('error_not_found', 'OOPP nebyl nalezen')) . '</div>';
    return;
}

// Get languages for detail view
$languages = $item['detail_languages'] ?? array();

// Get translations from item
$translations = $item['translations'] ?? array();
$default_lang = !empty($languages) ? $languages[0]['code'] : 'cs';

// Determine current language (first available or default)
$current_lang = $default_lang;
if (!empty($translations) && !empty($languages)) {
    // Use first language that has translation, or default
    foreach ($languages as $lang) {
        if (isset($translations[$lang['code']])) {
            $current_lang = $lang['code'];
            break;
        }
    }
}

// Get current translation data
$current_trans = $translations[$current_lang] ?? array();

// Prepare data (use translation if available)
$has_image = !empty($item['image_url']);
$is_active = !empty($item['is_active']);
$group_code = $item['group_code'] ?? '';
$group_name = $item['group_name'] ?? '';

// Use translated name or fallback
$display_name = !empty($current_trans['name']) ? $current_trans['name'] : ($item['name'] ?? '');

// Count branches and departments
$branches_count = !empty($item['branches']) ? count($item['branches']) : 0;
$departments_count = !empty($item['departments']) ? count($item['departments']) : 0;
$branches_all = !empty($item['branches_all']);
$departments_all = !empty($item['departments_all']);

// Check for technical info (from translations)
$has_technical = !empty($current_trans['standards']) || !empty($current_trans['risk_description']) || !empty($current_trans['protective_properties']);
$has_instructions = !empty($current_trans['usage_instructions']);
?>

<div class="saw-oopp-detail" data-oopp-id="<?php echo esc_attr($item['id']); ?>">

    <!-- ================================================
         LANGUAGE SWITCHER (if multiple languages)
         ================================================ -->
    <?php if (!empty($languages) && count($languages) > 1): ?>
    <div class="saw-oopp-language-switcher-wrapper">
        <button type="button" class="saw-oopp-lang-nav saw-oopp-lang-nav-prev" aria-label="Předchozí jazyky">
            <span class="dashicons dashicons-arrow-left-alt2"></span>
        </button>
        <div class="saw-oopp-language-switcher">
            <?php foreach ($languages as $lang): 
                $lang_code = $lang['code'];
                $has_translation = isset($translations[$lang_code]);
            ?>
                <button 
                    type="button" 
                    class="saw-oopp-lang-btn <?php echo $current_lang === $lang_code ? 'active' : ''; ?> <?php echo !$has_translation ? 'no-translation' : ''; ?>" 
                    data-lang="<?php echo esc_attr($lang_code); ?>"
                    title="<?php echo $has_translation ? '' : esc_attr($tr('no_translation', 'Chybí překlad')); ?>"
                >
                    <?php echo !empty($lang['flag']) ? esc_html($lang['flag']) : '🌐'; ?>
                    <span class="saw-oopp-lang-name"><?php echo esc_html($lang['name']); ?></span>
                    <?php if (!$has_translation): ?>
                        <span class="saw-oopp-lang-missing">⚠️</span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
        </div>
        <button type="button" class="saw-oopp-lang-nav saw-oopp-lang-nav-next" aria-label="Další jazyky">
            <span class="dashicons dashicons-arrow-right-alt2"></span>
        </button>
    </div>
    <?php endif; ?>

    <!-- ================================================
         HEADER CARD - Image + Basic Info
         ================================================ -->
    <div class="saw-oopp-header-card">
        <div class="saw-oopp-header-inner">
            <!-- Image Section -->
            <div class="saw-oopp-image-section">
                <?php if ($has_image): ?>
                    <img src="<?php echo esc_url($item['image_url']); ?>" 
                         alt="<?php echo esc_attr($display_name); ?>" 
                         class="saw-oopp-image">
                <?php else: ?>
                    <div class="saw-oopp-image-placeholder">
                        <span>🦺</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Info Section -->
            <div class="saw-oopp-header-info">
                <h2 class="saw-oopp-title" data-translate="name"><?php echo esc_html($display_name); ?></h2>
                
                <div class="saw-oopp-badges">
                    <!-- Group Badge -->
                    <?php if (!empty($group_code)): ?>
                    <div class="saw-oopp-group-badge">
                        <span class="saw-oopp-group-code"><?php echo esc_html($group_code); ?></span>
                        <span class="saw-oopp-group-name"><?php echo esc_html($group_name); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Status Badge -->
                    <div class="saw-oopp-status-badge <?php echo $is_active ? 'saw-status-active' : 'saw-status-inactive'; ?>">
                        <?php if ($is_active): ?>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <?php echo esc_html($tr('status_active', 'Aktivní')); ?>
                        <?php else: ?>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <line x1="15" y1="9" x2="9" y2="15"/>
                                <line x1="9" y1="9" x2="15" y2="15"/>
                            </svg>
                            <?php echo esc_html($tr('status_inactive', 'Neaktivní')); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================
         VALIDITY CARD - Branches & Departments
         ================================================ -->
    <div class="saw-oopp-validity-card">
        <div class="saw-oopp-validity-inner">
            <div class="saw-oopp-validity-header">
                <div class="saw-oopp-validity-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <div class="saw-oopp-validity-title">
                    <span class="saw-oopp-validity-label"><?php echo esc_html($tr('validity_title', 'Platnost OOPP')); ?></span>
                    <span class="saw-oopp-validity-subtitle"><?php echo esc_html($tr('validity_subtitle', 'Kde se tento OOPP vyžaduje')); ?></span>
                </div>
            </div>
            
            <div class="saw-oopp-validity-body">
                <!-- Branches -->
                <div class="saw-oopp-validity-row">
                    <div class="saw-oopp-validity-row-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 21h18"/>
                            <path d="M5 21V7l8-4v18"/>
                            <path d="M19 21V11l-6-4"/>
                            <path d="M9 9v.01"/><path d="M9 12v.01"/><path d="M9 15v.01"/><path d="M9 18v.01"/>
                        </svg>
                    </div>
                    <div class="saw-oopp-validity-row-content">
                        <span class="saw-oopp-validity-row-label"><?php echo esc_html($tr('label_branches', 'Pobočky')); ?></span>
                        <div class="saw-oopp-validity-row-value">
                            <?php if ($branches_all): ?>
                                <span class="saw-oopp-tag saw-oopp-tag-success">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <?php echo esc_html($tr('all_branches', 'Všechny pobočky')); ?>
                                </span>
                            <?php elseif (!empty($item['branches'])): ?>
                                <?php foreach ($item['branches'] as $branch): ?>
                                    <span class="saw-oopp-tag saw-oopp-tag-blue">
                                        <?php echo esc_html($branch['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="saw-oopp-tag saw-oopp-tag-muted"><?php echo esc_html($tr('not_set', 'Nenastaveno')); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Departments -->
                <div class="saw-oopp-validity-row">
                    <div class="saw-oopp-validity-row-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                        </svg>
                    </div>
                    <div class="saw-oopp-validity-row-content">
                        <span class="saw-oopp-validity-row-label"><?php echo esc_html($tr('label_departments', 'Oddělení')); ?></span>
                        <div class="saw-oopp-validity-row-value">
                            <?php if ($departments_all): ?>
                                <span class="saw-oopp-tag saw-oopp-tag-success">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    <?php echo esc_html($tr('all_departments', 'Všechna oddělení')); ?>
                                </span>
                            <?php elseif (!empty($item['departments'])): ?>
                                <?php foreach ($item['departments'] as $dept): ?>
                                    <span class="saw-oopp-tag saw-oopp-tag-amber">
                                        <?php echo esc_html($dept['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span class="saw-oopp-tag saw-oopp-tag-muted"><?php echo esc_html($tr('not_set', 'Nenastaveno')); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================
         STANDARDS CARD - Normy a předpisy
         ================================================ -->
    <div class="saw-oopp-info-card saw-oopp-card-standards" data-translate-section="standards" <?php if (empty($current_trans['standards'])): ?>style="display: none;"<?php endif; ?>>
        <div class="saw-oopp-info-inner">
            <div class="saw-oopp-info-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/>
                    <line x1="16" y1="17" x2="8" y2="17"/>
                    <line x1="10" y1="9" x2="8" y2="9"/>
                </svg>
            </div>
            <div class="saw-oopp-info-content">
                <div class="saw-oopp-info-label"><?php echo esc_html($tr('label_standards', 'Související předpisy / normy')); ?></div>
                <div class="saw-oopp-info-text" data-translate="standards"><?php echo nl2br(esc_html($current_trans['standards'] ?? '')); ?></div>
            </div>
        </div>
    </div>

    <!-- ================================================
         RISKS CARD - Popis rizik
         ================================================ -->
    <div class="saw-oopp-info-card saw-oopp-card-risks" data-translate-section="risk_description" <?php if (empty($current_trans['risk_description'])): ?>style="display: none;"<?php endif; ?>>
        <div class="saw-oopp-info-inner">
            <div class="saw-oopp-info-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div class="saw-oopp-info-content">
                <div class="saw-oopp-info-label"><?php echo esc_html($tr('label_risks', 'Popis rizik, proti kterým OOPP chrání')); ?></div>
                <div class="saw-oopp-info-text" data-translate="risk_description"><?php echo nl2br(esc_html($current_trans['risk_description'] ?? '')); ?></div>
            </div>
        </div>
    </div>

    <!-- ================================================
         PROPERTIES CARD - Ochranné vlastnosti
         ================================================ -->
    <div class="saw-oopp-info-card saw-oopp-card-properties" data-translate-section="protective_properties" <?php if (empty($current_trans['protective_properties'])): ?>style="display: none;"<?php endif; ?>>
        <div class="saw-oopp-info-inner">
            <div class="saw-oopp-info-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <polyline points="9 12 11 14 15 10"/>
                </svg>
            </div>
            <div class="saw-oopp-info-content">
                <div class="saw-oopp-info-label"><?php echo esc_html($tr('label_properties', 'Ochranné vlastnosti')); ?></div>
                <div class="saw-oopp-info-text" data-translate="protective_properties"><?php echo nl2br(esc_html($current_trans['protective_properties'] ?? '')); ?></div>
            </div>
        </div>
    </div>

    <!-- ================================================
         INSTRUCTIONS SECTION
         ================================================ -->
    <div class="saw-oopp-instructions-section" data-translate-section="usage_instructions" <?php if (empty($current_trans['usage_instructions'])): ?>style="display: none;"<?php endif; ?>>
        <div class="saw-oopp-section-header">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <path d="M12 16v-4"/>
                <path d="M12 8h.01"/>
            </svg>
            <span><?php echo esc_html($tr('section_instructions', 'Pokyny pro používání')); ?></span>
        </div>
        
        <!-- Usage Instructions -->
        <div class="saw-oopp-instruction-card saw-oopp-instruction-usage">
            <div class="saw-oopp-instruction-header">
                <div class="saw-oopp-instruction-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
                    </svg>
                </div>
                <span class="saw-oopp-instruction-title"><?php echo esc_html($tr('label_usage', 'Pokyny pro použití')); ?></span>
            </div>
            <div class="saw-oopp-instruction-body" data-translate="usage_instructions">
                <?php echo nl2br(esc_html($current_trans['usage_instructions'] ?? '')); ?>
            </div>
        </div>
    </div>

    <?php
    // Include audit history component (includes metadata dates)
    require SAW_VISITORS_PLUGIN_DIR . 'includes/components/detail-audit-history.php';
    ?>

</div>

<script>
jQuery(document).ready(function($) {
    var $detail = $('.saw-oopp-detail');
    var ooppId = $detail.data('oopp-id');
    
    // Připrav translations data pro JavaScript
    var translationsData = <?php echo json_encode($translations); ?>;
    var languages = <?php echo json_encode($languages); ?>;
    var currentLang = '<?php echo esc_js($current_lang); ?>';
    
    // Jazykový přepínač - navigation
    var $langSwitcher = $('.saw-oopp-language-switcher');
    var $prevBtn = $('.saw-oopp-lang-nav-prev');
    var $nextBtn = $('.saw-oopp-lang-nav-next');
    
    function updateLangNavButtons() {
        if ($langSwitcher.length === 0) return;
        
        var scrollLeft = $langSwitcher.scrollLeft();
        var scrollWidth = $langSwitcher[0].scrollWidth;
        var clientWidth = $langSwitcher[0].clientWidth;
        
        $prevBtn.prop('disabled', scrollLeft === 0);
        $nextBtn.prop('disabled', scrollLeft >= scrollWidth - clientWidth - 1);
    }
    
    if ($langSwitcher.length > 0) {
        $langSwitcher.on('scroll', updateLangNavButtons);
        $(window).on('resize', updateLangNavButtons);
        updateLangNavButtons();
        
        // Navigation buttons
        $prevBtn.on('click', function() {
            if (!$(this).prop('disabled')) {
                $langSwitcher.animate({
                    scrollLeft: $langSwitcher.scrollLeft() - 150
                }, 300);
            }
        });
        
        $nextBtn.on('click', function() {
            if (!$(this).prop('disabled')) {
                $langSwitcher.animate({
                    scrollLeft: $langSwitcher.scrollLeft() + 150
                }, 300);
            }
        });
    }
    
    // Přepínání jazyků
    $(document).on('click', '.saw-oopp-lang-btn', function() {
        var $btn = $(this);
        var langCode = $btn.data('lang');
        
        if (langCode === currentLang) return;
        
        // Zkontroluj zda má překlad
        if (!translationsData[langCode]) {
            return; // Nezobrazovat alert, jen nic neudělat
        }
        
        var trans = translationsData[langCode];
        
        // Update active button
        $('.saw-oopp-lang-btn').removeClass('active');
        $btn.addClass('active');
        
        // Update content
        $('[data-translate="name"]').text(trans.name || '');
        
        // Update sections
        updateTranslateSection('standards', trans.standards);
        updateTranslateSection('risk_description', trans.risk_description);
        updateTranslateSection('protective_properties', trans.protective_properties);
        updateTranslateSection('usage_instructions', trans.usage_instructions);
        
        // Scroll clicked button into view (only if switcher exists)
        if ($langSwitcher.length > 0) {
            var btnOffset = $btn.position().left + $langSwitcher.scrollLeft();
            var btnWidth = $btn.outerWidth();
            var containerWidth = $langSwitcher.outerWidth();
            var scrollLeft = $langSwitcher.scrollLeft();
            
            if (btnOffset < scrollLeft) {
                $langSwitcher.animate({
                    scrollLeft: btnOffset - 20
                }, 300);
            } else if (btnOffset + btnWidth > scrollLeft + containerWidth) {
                $langSwitcher.animate({
                    scrollLeft: btnOffset - containerWidth + btnWidth + 20
                }, 300);
            }
        }
        
        currentLang = langCode;
    });
    
    function updateTranslateSection(field, value) {
        var $section = $('[data-translate-section="' + field + '"]');
        var $text = $section.find('[data-translate="' + field + '"]');
        
        if ($text.length) {
            if (value && value.trim()) {
                // Escape HTML a pak převeď newlines na <br>
                var escaped = $('<div>').text(value).html();
                $text.html(escaped.replace(/\n/g, '<br>'));
                $section.show();
            } else {
                $text.html('');
                $section.hide();
            }
        }
    }
});
</script>

<style>
/* ================================================
   OOPP DETAIL MODAL - Modern Card Design
   Matching visits module style
   ================================================ */

.saw-oopp-detail {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 8px 0;
}

/* ================================================
   LANGUAGE SWITCHER
   ================================================ */
.saw-oopp-language-switcher-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.saw-oopp-language-switcher {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-behavior: smooth;
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE/Edge */
    flex: 1;
    min-width: 0;
    padding: 4px 0;
}

.saw-oopp-language-switcher::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
}

.saw-oopp-lang-nav {
    flex-shrink: 0;
    width: 28px;
    height: 28px;
    padding: 0;
    background: #f6f7f7;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.saw-oopp-lang-nav:hover:not(:disabled) {
    background: #0073aa;
    border-color: #0073aa;
    color: white;
}

.saw-oopp-lang-nav:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.saw-oopp-lang-nav .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.saw-oopp-lang-btn {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: transparent;
    border: 2px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    color: #50575e;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.saw-oopp-lang-btn:hover {
    background: #f6f7f7;
    border-color: #c3c4c7;
}

.saw-oopp-lang-btn.active {
    background: #0073aa;
    border-color: #0073aa;
    color: white;
}

.saw-oopp-lang-btn.no-translation {
    opacity: 0.6;
    position: relative;
}

.saw-oopp-lang-btn.no-translation:hover {
    opacity: 1;
}

.saw-oopp-lang-name {
    font-weight: 500;
}

.saw-oopp-lang-missing {
    font-size: 12px;
    margin-left: 2px;
}

/* ================================================
   HEADER CARD
   ================================================ */
.saw-oopp-header-card {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    border-radius: 16px;
    padding: 3px;
}

.saw-oopp-header-inner {
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    border-radius: 14px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.saw-oopp-image-section {
    flex-shrink: 0;
}

.saw-oopp-image {
    width: 120px;
    height: 120px;
    object-fit: cover;
    border-radius: 12px;
    border: 3px solid rgba(255,255,255,0.2);
}

.saw-oopp-image-placeholder {
    width: 120px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #475569 0%, #64748b 100%);
    border-radius: 12px;
    border: 3px solid rgba(255,255,255,0.1);
}

.saw-oopp-image-placeholder span {
    font-size: 56px;
}

.saw-oopp-header-info {
    flex: 1;
    min-width: 0;
}

.saw-oopp-title {
    font-size: 22px;
    font-weight: 700;
    color: #ffffff;
    margin: 0 0 12px 0;
    line-height: 1.3;
}

.saw-oopp-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.saw-oopp-group-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 20px;
    backdrop-filter: blur(10px);
}

.saw-oopp-group-code {
    font-size: 13px;
    font-weight: 700;
    color: #fbbf24;
}

.saw-oopp-group-name {
    font-size: 13px;
    font-weight: 500;
    color: #e2e8f0;
}

.saw-oopp-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.saw-oopp-status-badge.saw-status-active {
    background: rgba(34, 197, 94, 0.2);
    color: #4ade80;
}

.saw-oopp-status-badge.saw-status-inactive {
    background: rgba(239, 68, 68, 0.2);
    color: #f87171;
}

/* ================================================
   VALIDITY CARD
   ================================================ */
.saw-oopp-validity-card {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-radius: 16px;
    padding: 3px;
}

.saw-oopp-validity-inner {
    background: #ffffff;
    border-radius: 14px;
    overflow: hidden;
}

.saw-oopp-validity-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-bottom: 1px solid #bfdbfe;
}

.saw-oopp-validity-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

.saw-oopp-validity-title {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.saw-oopp-validity-label {
    font-size: 16px;
    font-weight: 700;
    color: #1e40af;
}

.saw-oopp-validity-subtitle {
    font-size: 13px;
    color: #3b82f6;
}

.saw-oopp-validity-body {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.saw-oopp-validity-row {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.saw-oopp-validity-row-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    color: #64748b;
    flex-shrink: 0;
}

.saw-oopp-validity-row-content {
    flex: 1;
    min-width: 0;
}

.saw-oopp-validity-row-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.saw-oopp-validity-row-value {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.saw-oopp-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 10px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
}

.saw-oopp-tag-success {
    background: #dcfce7;
    color: #166534;
}

.saw-oopp-tag-blue {
    background: #dbeafe;
    color: #1e40af;
}

.saw-oopp-tag-amber {
    background: #fef3c7;
    color: #92400e;
}

.saw-oopp-tag-muted {
    background: #f1f5f9;
    color: #64748b;
}

/* ================================================
   INFO CARDS
   ================================================ */
.saw-oopp-info-card {
    border-radius: 16px;
    padding: 3px;
}

.saw-oopp-card-standards {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
}

.saw-oopp-card-risks {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.saw-oopp-card-properties {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.saw-oopp-info-inner {
    background: #ffffff;
    border-radius: 14px;
    padding: 16px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
}

.saw-oopp-info-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.saw-oopp-card-standards .saw-oopp-info-icon {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
}

.saw-oopp-card-risks .saw-oopp-info-icon {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.saw-oopp-card-properties .saw-oopp-info-icon {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.saw-oopp-info-content {
    flex: 1;
    min-width: 0;
}

.saw-oopp-info-label {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.saw-oopp-info-text {
    font-size: 14px;
    line-height: 1.7;
    color: #374151;
}

/* ================================================
   INSTRUCTIONS SECTION
   ================================================ */
.saw-oopp-instructions-section {
    background: #f8fafc;
    border-radius: 16px;
    padding: 16px;
}

.saw-oopp-section-header {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 700;
    color: #475569;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #e2e8f0;
}

.saw-oopp-section-header svg {
    color: #64748b;
}

.saw-oopp-instruction-card {
    background: white;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    overflow: hidden;
    margin-bottom: 12px;
}

.saw-oopp-instruction-card:last-child {
    margin-bottom: 0;
}

.saw-oopp-instruction-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
}

.saw-oopp-instruction-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.saw-oopp-instruction-usage .saw-oopp-instruction-icon {
    background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
}

.saw-oopp-instruction-maintenance .saw-oopp-instruction-icon {
    background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}

.saw-oopp-instruction-storage .saw-oopp-instruction-icon {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.saw-oopp-instruction-title {
    font-size: 14px;
    font-weight: 600;
    color: #1e293b;
}

.saw-oopp-instruction-body {
    padding: 16px;
    font-size: 14px;
    line-height: 1.7;
    color: #374151;
}

/* ================================================
   META CARD
   ================================================ */
.saw-oopp-meta-card {
    background: #f1f5f9;
    border-radius: 12px;
    padding: 2px;
}

.saw-oopp-meta-inner {
    background: white;
    border-radius: 10px;
    padding: 12px 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
}

.saw-oopp-meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.saw-oopp-meta-item svg {
    color: #94a3b8;
    flex-shrink: 0;
}

.saw-oopp-meta-label {
    color: #64748b;
}

.saw-oopp-meta-value {
    font-weight: 600;
    color: #1e293b;
}

/* ================================================
   RESPONSIVE
   ================================================ */
@media (max-width: 768px) {
    /* Add margin for whole detail sidebar on mobile */
    .saw-oopp-detail {
        padding: 8px 12px;
    }
    
    .saw-oopp-language-switcher-wrapper {
        margin-bottom: 12px;
    }
    
    .saw-oopp-lang-btn {
        padding: 7px 12px;
        font-size: 12px;
    }
    
    .saw-oopp-header-inner {
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 16px;
    }
    
    .saw-oopp-header-info {
        width: 100%;
    }
    
    .saw-oopp-badges {
        justify-content: center;
    }
    
    .saw-oopp-image,
    .saw-oopp-image-placeholder {
        width: 100px;
        height: 100px;
    }
    
    .saw-oopp-image-placeholder span {
        font-size: 48px;
    }
    
    .saw-oopp-title {
        font-size: 20px;
    }
    
    .saw-oopp-validity-header {
        padding: 14px;
    }
    
    .saw-oopp-validity-row {
        flex-direction: column;
        gap: 8px;
    }
    
    .saw-oopp-validity-row-icon {
        width: 32px;
        height: 32px;
    }
    
    .saw-oopp-info-inner {
        flex-direction: column;
        gap: 12px;
    }
    
    .saw-oopp-info-icon {
        width: 40px;
        height: 40px;
    }
    
    .saw-oopp-instructions-section {
        padding: 14px;
    }
    
    .saw-oopp-instruction-card {
        margin-bottom: 10px;
    }
    
    .saw-oopp-instruction-header {
        padding: 10px 14px;
    }
    
    .saw-oopp-instruction-body {
        padding: 14px;
    }
    
    .saw-oopp-meta-inner {
        flex-direction: column;
        gap: 10px;
        padding: 12px;
    }
    
    .saw-oopp-meta-item {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .saw-oopp-language-switcher-wrapper {
        gap: 6px;
    }
    
    .saw-oopp-lang-nav {
        width: 24px;
        height: 24px;
    }
    
    .saw-oopp-lang-nav .dashicons {
        font-size: 14px;
        width: 14px;
        height: 14px;
    }
    
    .saw-oopp-lang-btn {
        padding: 6px 10px;
        font-size: 11px;
    }
    
    .saw-oopp-header-inner {
        padding: 16px;
    }
    
    .saw-oopp-image,
    .saw-oopp-image-placeholder {
        width: 80px;
        height: 80px;
    }
    
    .saw-oopp-image-placeholder span {
        font-size: 40px;
    }
    
    .saw-oopp-title {
        font-size: 18px;
        margin-bottom: 10px;
    }
    
    .saw-oopp-validity-inner,
    .saw-oopp-info-inner {
        padding: 12px;
    }
    
    .saw-oopp-validity-header {
        padding: 12px;
    }
    
    .saw-oopp-validity-icon {
        width: 36px;
        height: 36px;
    }
    
    .saw-oopp-info-icon {
        width: 36px;
        height: 36px;
    }
    
    .saw-oopp-instructions-section {
        padding: 12px;
    }
}

/* ================================================
   DARK MODE SUPPORT (if needed)
   ================================================ */
[data-theme="dark"] .saw-oopp-detail {
    /* Add dark mode overrides here if needed */
}
</style>