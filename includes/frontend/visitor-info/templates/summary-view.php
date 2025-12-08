<?php
/**
 * Visitor Info Portal - Summary View Template
 * 
 * Displays all training content in a summary format after completion.
 * Includes language switcher for viewing content in different languages.
 * 
 * Available variables (from controller):
 * - $content (array) - Training content from get_training_content()
 * - $available_steps (array) - Steps that have content
 * - $languages (array) - Available languages
 * - $t (array) - Translations
 * - $valid_until (string) - Validity date
 * - $this->visitor (array) - Visitor data
 * - $this->language (string) - Current language
 * 
 * @package SAW_Visitors
 * @since 3.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$visitor = $this->visitor;

// Language flags mapping
$language_flags = array(
    'cs' => '🇨🇿',
    'en' => '🇬🇧',
    'sk' => '🇸🇰',
    'uk' => '🇺🇦',
    'de' => '🇩🇪',
    'pl' => '🇵🇱',
    'hu' => '🇭🇺',
    'ro' => '🇷🇴',
);

// Check training status
$training_completed = !empty($visitor['training_completed_at']);
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($this->language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#667eea">
    <title><?php echo esc_html($t['page_title']); ?> - <?php echo esc_html($visitor['customer_name']); ?></title>
    <?php wp_head(); ?>
</head>
<body class="saw-visitor-info-page saw-summary-view">

<div class="saw-info-container">
    
    <!-- ==================== HEADER ==================== -->
    <header class="saw-info-header">
        <div class="saw-info-header-content">
            <h1 class="saw-info-company"><?php echo esc_html($visitor['customer_name']); ?></h1>
            <p class="saw-info-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
        </div>
        
        <!-- Visitor Info Card -->
        <div class="saw-info-visitor-card">
            <div class="saw-visitor-name">
                <span class="saw-visitor-icon">👤</span>
                <?php echo esc_html($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
            </div>
            
            <?php if ($valid_until): ?>
            <div class="saw-visitor-validity">
                <span class="saw-validity-icon">📅</span>
                <?php echo esc_html($t['valid_until']); ?>: <?php echo esc_html($valid_until); ?>
            </div>
            <?php endif; ?>
            
            <!-- Training Status Badge -->
            <?php if ($training_completed): ?>
            <div class="saw-training-badge saw-badge-completed">
                ✓ <?php echo esc_html($t['training_complete']); ?>
            </div>
            <?php else: ?>
            <div class="saw-training-badge saw-badge-pending">
                ⏳ <?php echo esc_html($t['training_pending'] ?? 'Školení nebylo dokončeno'); ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Language Switcher -->
        <?php if (count($languages) > 1): ?>
        <div class="saw-language-switcher">
            <select id="language-select" onchange="sawChangeLanguage(this.value)">
                <?php foreach ($languages as $lang): 
                    $flag = isset($language_flags[$lang['language_code']]) 
                        ? $language_flags[$lang['language_code']] 
                        : '🌐';
                ?>
                <option value="<?php echo esc_attr($lang['language_code']); ?>"
                        <?php selected($this->language, $lang['language_code']); ?>>
                    <?php echo $flag; ?> <?php echo esc_html($lang['language_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </header>
    
    <!-- ==================== CONTENT ==================== -->
    <main class="saw-info-content">
        
        <?php if (empty($content) || empty($available_steps)): ?>
        
        <!-- No Content Available -->
        <div class="saw-no-content">
            <div class="saw-no-content-icon">📭</div>
            <p><?php echo esc_html($t['no_content']); ?></p>
        </div>
        
        <?php else: ?>
        
        <?php 
        // Display sections based on available steps
        foreach ($available_steps as $step):
            switch ($step['type']):
                
                // ==================== VIDEO ====================
                case 'video':
                    if (!empty($content['video_embed_url'])):
        ?>
        <section class="saw-info-section">
            <h2 class="saw-section-title">
                <span class="saw-section-icon">🎥</span>
                <?php echo esc_html($t['section_video']); ?>
            </h2>
            <div class="saw-section-body">
                <div class="saw-video-wrapper-summary">
                    <iframe src="<?php echo esc_url($content['video_embed_url']); ?>" 
                            frameborder="0" 
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen></iframe>
                </div>
            </div>
        </section>
        <?php 
                    endif;
                    break;
                
                // ==================== MAP ====================
                case 'map':
                    if (!empty($content['pdf_map_path'])):
                        $upload_dir = wp_upload_dir();
                        // Check if it's an attachment ID or path
                        if (is_numeric($content['pdf_map_path'])) {
                            $map_url = wp_get_attachment_url($content['pdf_map_path']);
                        } else {
                            $map_url = $upload_dir['baseurl'] . '/' . ltrim($content['pdf_map_path'], '/');
                        }
        ?>
        <section class="saw-info-section">
            <h2 class="saw-section-title">
                <span class="saw-section-icon">🗺️</span>
                <?php echo esc_html($t['section_map']); ?>
            </h2>
            <div class="saw-section-body">
                <div class="saw-pdf-container">
                    <a href="<?php echo esc_url($map_url); ?>" 
                       target="_blank" 
                       rel="noopener"
                       class="saw-pdf-link">
                        <span class="saw-pdf-icon">📄</span>
                        <span><?php echo esc_html($t['view_pdf']); ?></span>
                    </a>
                </div>
            </div>
        </section>
        <?php 
                    endif;
                    break;
                
                // ==================== RISKS ====================
                case 'risks':
                    if (!empty($content['risks_text'])):
        ?>
        <section class="saw-info-section">
            <h2 class="saw-section-title">
                <span class="saw-section-icon">⚠️</span>
                <?php echo esc_html($t['section_risks']); ?>
            </h2>
            <div class="saw-section-body">
                <div class="saw-text-content">
                    <?php echo wp_kses_post(wpautop($content['risks_text'])); ?>
                </div>
            </div>
        </section>
        <?php 
                    endif;
                    break;
                
                // ==================== DEPARTMENTS ====================
                case 'department':
                    if (!empty($content['departments'])):
        ?>
        <section class="saw-info-section">
            <h2 class="saw-section-title">
                <span class="saw-section-icon">🏢</span>
                <?php echo esc_html($t['section_departments']); ?>
            </h2>
            <div class="saw-section-body">
                <div class="saw-accordion">
                    <?php foreach ($content['departments'] as $dept): ?>
                    <div class="saw-accordion-item">
                        <button class="saw-accordion-header" type="button" onclick="sawToggleAccordion(this)">
                            <span><?php echo esc_html($dept['department_name']); ?></span>
                            <span class="saw-accordion-icon">▼</span>
                        </button>
                        <div class="saw-accordion-content">
                            <?php echo wp_kses_post(wpautop($dept['text_content'])); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php 
                    endif;
                    break;
                
                // ==================== OOPP ====================
                case 'oopp':
                    if (!empty($content['oopp_items'])):
        ?>
        <section class="saw-info-section">
            <h2 class="saw-section-title">
                <span class="saw-section-icon">🦺</span>
                <?php echo esc_html($t['section_oopp']); ?>
            </h2>
            <div class="saw-section-body">
                <div class="saw-oopp-grid">
                    <?php foreach ($content['oopp_items'] as $item): ?>
                    <div class="saw-oopp-card">
                        <div class="saw-oopp-image">
                            <?php if (!empty($item['image_url'])): ?>
                            <img src="<?php echo esc_url($item['image_url']); ?>" 
                                 alt="<?php echo esc_attr($item['name']); ?>">
                            <?php else: ?>
                            <span class="saw-oopp-image-placeholder">🦺</span>
                            <?php endif; ?>
                        </div>
                        <div class="saw-oopp-info">
                            <h3><?php echo esc_html($item['name']); ?></h3>
                            <?php if (!empty($item['description'])): ?>
                            <p><?php echo esc_html($item['description']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php 
                    endif;
                    break;
                
                // ==================== ADDITIONAL ====================
                case 'additional':
                    if (!empty($content['additional_text']) || !empty($content['documents'])):
        ?>
        <section class="saw-info-section">
            <h2 class="saw-section-title">
                <span class="saw-section-icon">ℹ️</span>
                <?php echo esc_html($t['section_additional']); ?>
            </h2>
            <div class="saw-section-body">
                <?php if (!empty($content['additional_text'])): ?>
                <div class="saw-text-content">
                    <?php echo wp_kses_post(wpautop($content['additional_text'])); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($content['documents'])): ?>
                <div class="saw-documents-list">
                    <?php foreach ($content['documents'] as $doc): 
                        $upload_dir = wp_upload_dir();
                        $doc_url = $upload_dir['baseurl'] . '/' . ltrim($doc['file_path'], '/');
                        $file_ext = strtoupper(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                    ?>
                    <a href="<?php echo esc_url($doc_url); ?>" 
                       target="_blank"
                       rel="noopener" 
                       class="saw-document-link">
                        <span class="saw-document-icon">📄</span>
                        <span><?php echo esc_html($doc['file_name']); ?></span>
                        <span class="saw-doc-badge"><?php echo esc_html($file_ext); ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php 
                    endif;
                    break;
                    
            endswitch;
        endforeach;
        ?>
        
        <?php endif; ?>
        
    </main>
    
</div>

<!-- JavaScript -->
<script>
/**
 * Change language - redirect with lang parameter
 */
function sawChangeLanguage(lang) {
    var url = '<?php echo esc_url($this->get_url('summary')); ?>';
    window.location.href = url + '?lang=' + encodeURIComponent(lang);
}

/**
 * Toggle accordion item
 */
function sawToggleAccordion(btn) {
    var item = btn.parentElement;
    var isOpen = item.classList.contains('open');
    
    // Close all other items
    var allItems = document.querySelectorAll('.saw-accordion-item.open');
    for (var i = 0; i < allItems.length; i++) {
        allItems[i].classList.remove('open');
    }
    
    // Toggle current item
    if (!isOpen) {
        item.classList.add('open');
    }
}

/**
 * Open first accordion item by default
 */
(function() {
    'use strict';
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAccordion);
    } else {
        initAccordion();
    }
    
    function initAccordion() {
        var firstItem = document.querySelector('.saw-accordion-item');
        if (firstItem) {
            firstItem.classList.add('open');
        }
    }
})();
</script>

<?php wp_footer(); ?>
</body>
</html>