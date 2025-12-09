<?php
/**
 * Visitor Info Portal - Summary View
 * 
 * Features:
 * - Sections as accordion (only one open at a time)
 * - First section open, rest collapsed
 * - PDF viewer with navigation, fullscreen, download
 * - OOPP as accordion list with details
 * - Departments as accordion
 * - All styles inline
 * 
 * @package SAW_Visitors
 * @since 3.3.0
 * @version 3.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$visitor = $this->visitor;

// Language flags
$language_flags = array(
    'cs' => '🇨🇿', 'en' => '🇬🇧', 'sk' => '🇸🇰', 'uk' => '🇺🇦',
    'de' => '🇩🇪', 'pl' => '🇵🇱', 'hu' => '🇭🇺', 'ro' => '🇷🇴',
    'vi' => '🇻🇳', 'ru' => '🇷🇺',
);

$training_completed = !empty($visitor['training_completed_at']);

// Build PDF URL if available
$pdf_url = '';
if (!empty($content['pdf_map_path'])) {
    if (is_numeric($content['pdf_map_path'])) {
        $pdf_url = wp_get_attachment_url((int) $content['pdf_map_path']);
    } elseif (strpos($content['pdf_map_path'], 'http') === 0) {
        $pdf_url = $content['pdf_map_path'];
    } else {
        $upload_dir = wp_upload_dir();
        $pdf_url = $upload_dir['baseurl'] . '/' . ltrim($content['pdf_map_path'], '/');
    }
}

// Track section index for accordion
$section_index = 0;
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($this->language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1a202c">
    <title><?php echo esc_html($t['page_title']); ?> - <?php echo esc_html($visitor['customer_name']); ?></title>
    
    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    
    <style>
    /* ============================================
       RESET & BASE
       ============================================ */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    
    html {
        overflow: auto !important;
        height: auto !important;
        font-size: 16px;
        -webkit-font-smoothing: antialiased;
    }
    
    body {
        overflow: auto !important;
        height: auto !important;
        min-height: 100vh;
        margin: 0 !important;
        padding: 0 !important;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        font-size: 16px;
        line-height: 1.5;
        color: #f9fafb;
        background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
        background-attachment: fixed;
    }
    
    #wpadminbar { display: none !important; }
    
    /* ============================================
       LAYOUT - Same max-width for header and content
       ============================================ */
    .page-wrapper {
        max-width: 900px;
        margin: 0 auto;
        padding: 0;
    }
    
    /* ============================================
       HEADER
       ============================================ */
    .sum-header {
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(20px);
        border-bottom: 1px solid rgba(148, 163, 184, 0.15);
        padding: 1.5rem;
        position: sticky;
        top: 0;
        z-index: 100;
    }
    
    .sum-company { font-size: 1.5rem; font-weight: 700; color: #f9fafb; margin: 0 0 0.25rem; }
    .sum-subtitle { font-size: 0.875rem; color: rgba(148, 163, 184, 0.8); margin: 0; }
    
    .sum-visitor-card {
        margin-top: 1rem;
        padding: 1rem 1.25rem;
        background: rgba(30, 41, 59, 0.6);
        border: 1px solid rgba(148, 163, 184, 0.15);
        border-radius: 12px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 1rem;
    }
    
    .sum-visitor-name { display: flex; align-items: center; gap: 0.5rem; font-weight: 600; color: #f9fafb; }
    .sum-visitor-validity { display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: rgba(203, 213, 225, 0.8); }
    
    .sum-badge {
        display: inline-flex; align-items: center; gap: 0.375rem;
        padding: 0.375rem 0.75rem; border-radius: 6px;
        font-size: 0.8125rem; font-weight: 600;
    }
    .sum-badge-ok { background: rgba(34, 197, 94, 0.2); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.3); }
    
    .sum-lang-switcher { margin-left: auto; }
    .sum-lang-switcher select {
        padding: 0.5rem 2.5rem 0.5rem 1rem;
        background: rgba(30, 41, 59, 0.8);
        border: 1px solid rgba(148, 163, 184, 0.25);
        border-radius: 8px;
        color: #f9fafb;
        font-size: 0.9rem;
        font-family: inherit;
        cursor: pointer;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23cbd5e1' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
    }
    .sum-lang-switcher select:focus { outline: none; border-color: rgba(102, 126, 234, 0.6); }
    .sum-lang-switcher select option { background: #1e293b; color: #f9fafb; }
    
    /* ============================================
       CONTENT
       ============================================ */
    .sum-content { padding: 1.5rem; padding-bottom: 4rem; }
    
    /* ============================================
       MAIN SECTIONS ACCORDION
       ============================================ */
    .sum-sections { display: flex; flex-direction: column; gap: 1rem; }
    
    .sum-section {
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(148, 163, 184, 0.12);
        border-radius: 16px;
        overflow: hidden;
        transition: border-color 0.3s;
    }
    
    .sum-section.expanded { border-color: rgba(102, 126, 234, 0.3); }
    
    .sum-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 1rem 1.25rem;
        background: rgba(30, 41, 59, 0.5);
        border: none;
        cursor: pointer;
        text-align: left;
        font-family: inherit;
        color: #f9fafb;
        transition: background 0.2s;
    }
    
    .sum-section-header:hover { background: rgba(30, 41, 59, 0.7); }
    
    .sum-section-title-wrap {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .sum-section-icon { font-size: 1.25rem; }
    .sum-section-title { font-size: 1rem; font-weight: 600; margin: 0; }
    
    .sum-section-chevron {
        width: 1.25rem; height: 1.25rem;
        color: #94a3b8;
        transition: transform 0.3s;
        flex-shrink: 0;
    }
    
    .sum-section.expanded .sum-section-chevron { transform: rotate(90deg); }
    
    .sum-section-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .sum-section.expanded .sum-section-content { max-height: 5000px; }
    
    .sum-section-body {
        padding: 1.25rem;
        border-top: 1px solid rgba(148, 163, 184, 0.1);
    }
    
    /* ============================================
       VIDEO
       ============================================ */
    .sum-video-wrapper {
        position: relative;
        padding-bottom: 56.25%;
        height: 0;
        border-radius: 12px;
        overflow: hidden;
        background: #0f172a;
    }
    .sum-video-wrapper iframe {
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        border: none;
    }
    
    /* ============================================
       PDF VIEWER
       ============================================ */
    .pdf-container {
        background: #0f172a;
        border-radius: 12px;
        overflow: hidden;
        position: relative;
    }
    
    .pdf-canvas-wrapper {
        min-height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        overflow: auto;
    }
    
    #pdf-canvas {
        max-width: 100%;
        height: auto;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        border-radius: 4px;
    }
    
    .pdf-loading {
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        color: #94a3b8;
    }
    
    .pdf-spinner {
        width: 3rem; height: 3rem;
        border: 3px solid rgba(255, 255, 255, 0.1);
        border-top-color: #818cf8;
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin: 0 auto 1rem;
    }
    
    @keyframes spin { to { transform: rotate(360deg); } }
    
    .pdf-controls {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 1rem;
        background: rgba(30, 41, 59, 0.8);
        border-top: 1px solid rgba(148, 163, 184, 0.1);
        flex-wrap: wrap;
    }
    
    .pdf-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1rem;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 8px;
        color: #f9fafb;
        font-size: 0.875rem;
        font-weight: 500;
        font-family: inherit;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
    }
    
    .pdf-btn:hover { background: rgba(255, 255, 255, 0.2); }
    .pdf-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .pdf-btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-color: transparent; }
    .pdf-btn-primary:hover { box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4); }
    
    .pdf-page-info {
        padding: 0 1rem;
        color: #94a3b8;
        font-size: 0.875rem;
        font-weight: 600;
    }
    
    /* Fullscreen */
    .pdf-fullscreen {
        position: fixed !important;
        top: 0; left: 0; right: 0; bottom: 0;
        z-index: 9999;
        background: #0f172a;
        border-radius: 0;
    }
    
    .pdf-fullscreen .pdf-canvas-wrapper {
        height: calc(100vh - 70px);
        min-height: auto;
    }
    
    .pdf-fullscreen .pdf-controls {
        position: absolute;
        bottom: 0; left: 0; right: 0;
    }
    
    /* ============================================
       INNER ACCORDION (Departments & OOPP items)
       ============================================ */
    .inner-accordion { display: flex; flex-direction: column; gap: 0.75rem; }
    
    .inner-accordion-item {
        background: rgba(30, 41, 59, 0.5);
        border: 1px solid rgba(148, 163, 184, 0.12);
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s;
    }
    
    .inner-accordion-item.expanded { border-color: rgba(102, 126, 234, 0.3); }
    
    .inner-accordion-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        padding: 1rem 1.25rem;
        background: transparent;
        border: none;
        cursor: pointer;
        text-align: left;
        font-family: inherit;
        color: #f9fafb;
        transition: background 0.2s;
    }
    
    .inner-accordion-header:hover { background: rgba(255, 255, 255, 0.05); }
    
    .inner-accordion-title-wrap {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex: 1;
    }
    
    .inner-accordion-thumb {
        width: 48px; height: 48px;
        border-radius: 10px;
        overflow: hidden;
        flex-shrink: 0;
        background: rgba(255, 255, 255, 0.05);
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .inner-accordion-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .inner-accordion-thumb-icon { font-size: 1.5rem; }
    
    .inner-accordion-chevron {
        width: 1.5rem; height: 1.5rem;
        color: #94a3b8;
        transition: transform 0.3s;
        flex-shrink: 0;
    }
    
    .inner-accordion-item.expanded .inner-accordion-chevron { transform: rotate(90deg); }
    
    .inner-accordion-title { font-size: 1rem; font-weight: 600; margin: 0; }
    .inner-accordion-badge {
        padding: 0.25rem 0.625rem;
        background: rgba(102, 126, 234, 0.15);
        border: 1px solid rgba(102, 126, 234, 0.3);
        border-radius: 999px;
        color: #818cf8;
        font-size: 0.75rem;
        font-weight: 600;
        margin-left: 0.5rem;
    }
    
    .inner-accordion-content {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .inner-accordion-item.expanded .inner-accordion-content { max-height: 2000px; }
    
    .inner-accordion-body {
        padding: 1.25rem;
        border-top: 1px solid rgba(148, 163, 184, 0.1);
    }
    
    /* OOPP Detail Grid */
    .oopp-detail {
        display: grid;
        grid-template-columns: 200px 1fr;
        gap: 1.5rem;
        align-items: start;
    }
    
    @media (max-width: 600px) {
        .oopp-detail { grid-template-columns: 1fr; }
        .oopp-image { max-width: 200px; margin: 0 auto 1rem; }
    }
    
    .oopp-image {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .oopp-image img { width: 100%; height: auto; display: block; }
    .oopp-image-placeholder {
        aspect-ratio: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 4rem;
    }
    
    .oopp-info h4 {
        font-size: 0.75rem;
        font-weight: 700;
        color: #818cf8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin: 0 0 0.5rem;
    }
    
    .oopp-info p {
        font-size: 0.9375rem;
        line-height: 1.6;
        color: rgba(226, 232, 240, 0.9);
        margin: 0 0 1rem;
    }
    
    .oopp-info p:last-child { margin-bottom: 0; }
    
    /* ============================================
       TEXT CONTENT
       ============================================ */
    .sum-text {
        font-size: 0.9375rem;
        line-height: 1.7;
        color: rgba(226, 232, 240, 0.9);
    }
    
    .sum-text p { margin: 0 0 1rem; }
    .sum-text p:last-child { margin-bottom: 0; }
    .sum-text h1, .sum-text h2, .sum-text h3, .sum-text h4, .sum-text h5, .sum-text h6 {
        color: #f9fafb;
        margin: 1.5rem 0 0.75rem;
    }
    .sum-text h1:first-child, .sum-text h2:first-child, .sum-text h3:first-child { margin-top: 0; }
    .sum-text ul, .sum-text ol { margin: 0 0 1rem 1.5rem; }
    .sum-text li { margin-bottom: 0.5rem; }
    
    /* ============================================
       DOCUMENTS
       ============================================ */
    .sum-docs { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem; }
    .sum-doc-link {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.75rem 1rem;
        background: rgba(30, 41, 59, 0.5);
        border: 1px solid rgba(148, 163, 184, 0.15);
        border-radius: 8px;
        color: #a5b4fc;
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        transition: all 0.2s;
    }
    .sum-doc-link:hover { background: rgba(30, 41, 59, 0.7); border-color: rgba(102, 126, 234, 0.4); color: #c7d2fe; }
    
    /* ============================================
       NO CONTENT
       ============================================ */
    .no-content {
        text-align: center;
        padding: 3rem 2rem;
        color: rgba(148, 163, 184, 0.6);
        font-style: italic;
    }
    
    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 640px) {
        .sum-header { padding: 1rem; }
        .sum-content { padding: 1rem; }
        .sum-visitor-card { flex-direction: column; align-items: flex-start; }
        .sum-lang-switcher { margin-left: 0; width: 100%; }
        .sum-lang-switcher select { width: 100%; }
        .sum-company { font-size: 1.25rem; }
        .pdf-controls { gap: 0.375rem; }
        .pdf-btn { padding: 0.5rem 0.75rem; font-size: 0.8125rem; }
        .pdf-btn span.btn-text { display: none; }
    }
    </style>
</head>
<body>

<div class="page-wrapper">
    
    <!-- Header -->
    <header class="sum-header">
        <h1 class="sum-company"><?php echo esc_html($visitor['customer_name']); ?></h1>
        <p class="sum-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
        
        <div class="sum-visitor-card">
            <div class="sum-visitor-name">
                <span>👤</span>
                <?php echo esc_html($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
            </div>
            
            <?php if ($valid_until): ?>
            <div class="sum-visitor-validity">
                <span>📅</span>
                <?php echo esc_html($t['valid_until']); ?>: <?php echo esc_html($valid_until); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($training_completed): ?>
            <div class="sum-badge sum-badge-ok">✓ <?php echo esc_html($t['training_complete']); ?></div>
            <?php endif; ?>
            
            <?php if (count($languages) > 1): ?>
            <div class="sum-lang-switcher">
                <select onchange="changeLanguage(this.value)">
                    <?php foreach ($languages as $lang): 
                        $flag = $language_flags[$lang['language_code']] ?? '🌐';
                    ?>
                    <option value="<?php echo esc_attr($lang['language_code']); ?>" <?php selected($this->language, $lang['language_code']); ?>>
                        <?php echo $flag; ?> <?php echo esc_html($lang['language_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </header>
    
    <!-- Content -->
    <main class="sum-content">
        
        <?php if (empty($content) || empty($available_steps)): ?>
        <div class="no-content">
            <p><?php echo esc_html($t['no_content']); ?></p>
        </div>
        <?php else: ?>
        
        <div class="sum-sections">
        <?php 
        $first_section = true;
        foreach ($available_steps as $step): 
            switch ($step['type']):
        
        // ==================== VIDEO ====================
        case 'video':
            if (!empty($content['video_embed_url'])):
                $is_first = $first_section; $first_section = false;
        ?>
        <section class="sum-section<?php echo $is_first ? ' expanded' : ''; ?>" data-section="video">
            <button type="button" class="sum-section-header" onclick="toggleSection(this)">
                <div class="sum-section-title-wrap">
                    <span class="sum-section-icon">🎥</span>
                    <h2 class="sum-section-title"><?php echo esc_html($t['section_video']); ?></h2>
                </div>
                <svg class="sum-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="sum-section-content">
                <div class="sum-section-body">
                    <div class="sum-video-wrapper">
                        <iframe src="<?php echo esc_url($content['video_embed_url']); ?>" 
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; break;
        
        // ==================== MAP (PDF VIEWER) ====================
        case 'map':
            if ($pdf_url):
                $is_first = $first_section; $first_section = false;
        ?>
        <section class="sum-section<?php echo $is_first ? ' expanded' : ''; ?>" data-section="map">
            <button type="button" class="sum-section-header" onclick="toggleSection(this)">
                <div class="sum-section-title-wrap">
                    <span class="sum-section-icon">🗺️</span>
                    <h2 class="sum-section-title"><?php echo esc_html($t['section_map']); ?></h2>
                </div>
                <svg class="sum-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="sum-section-content">
                <div class="sum-section-body" style="padding: 0;">
                    <div class="pdf-container" id="pdf-container">
                        <div class="pdf-canvas-wrapper">
                            <div class="pdf-loading" id="pdf-loading">
                                <div class="pdf-spinner"></div>
                                <div>Načítání mapy...</div>
                            </div>
                            <canvas id="pdf-canvas" style="display: none;"></canvas>
                        </div>
                        <div class="pdf-controls">
                            <button type="button" class="pdf-btn" id="pdf-prev" disabled>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>
                                <span class="btn-text">Předchozí</span>
                            </button>
                            <span class="pdf-page-info" id="pdf-page-info">1 / 1</span>
                            <button type="button" class="pdf-btn" id="pdf-next" disabled>
                                <span class="btn-text">Další</span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>
                            </button>
                            <button type="button" class="pdf-btn" id="pdf-fullscreen" title="Fullscreen">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"></path></svg>
                            </button>
                            <a href="<?php echo esc_url($pdf_url); ?>" download class="pdf-btn pdf-btn-primary" title="Stáhnout">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                <span class="btn-text">Stáhnout</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; break;
        
        // ==================== RISKS ====================
        case 'risks':
            if (!empty($content['risks_text'])):
                $is_first = $first_section; $first_section = false;
        ?>
        <section class="sum-section<?php echo $is_first ? ' expanded' : ''; ?>" data-section="risks">
            <button type="button" class="sum-section-header" onclick="toggleSection(this)">
                <div class="sum-section-title-wrap">
                    <span class="sum-section-icon">⚠️</span>
                    <h2 class="sum-section-title"><?php echo esc_html($t['section_risks']); ?></h2>
                </div>
                <svg class="sum-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="sum-section-content">
                <div class="sum-section-body">
                    <div class="sum-text"><?php echo wp_kses_post(wpautop($content['risks_text'])); ?></div>
                </div>
            </div>
        </section>
        <?php endif; break;
        
        // ==================== DEPARTMENTS ====================
        case 'department':
            if (!empty($content['departments'])):
                $is_first = $first_section; $first_section = false;
        ?>
        <section class="sum-section<?php echo $is_first ? ' expanded' : ''; ?>" data-section="departments">
            <button type="button" class="sum-section-header" onclick="toggleSection(this)">
                <div class="sum-section-title-wrap">
                    <span class="sum-section-icon">🏢</span>
                    <h2 class="sum-section-title"><?php echo esc_html($t['section_departments']); ?></h2>
                </div>
                <svg class="sum-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="sum-section-content">
                <div class="sum-section-body">
                    <div class="inner-accordion" data-accordion="departments">
                        <?php foreach ($content['departments'] as $i => $dept): ?>
                        <div class="inner-accordion-item<?php echo $i === 0 ? ' expanded' : ''; ?>">
                            <button type="button" class="inner-accordion-header" onclick="toggleInnerAccordion(this, 'departments')">
                                <div class="inner-accordion-title-wrap">
                                    <div class="inner-accordion-thumb">
                                        <span class="inner-accordion-thumb-icon">🏢</span>
                                    </div>
                                    <svg class="inner-accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6"></polyline>
                                    </svg>
                                    <h3 class="inner-accordion-title"><?php echo esc_html($dept['department_name']); ?></h3>
                                </div>
                            </button>
                            <div class="inner-accordion-content">
                                <div class="inner-accordion-body">
                                    <div class="sum-text"><?php echo wp_kses_post(wpautop($dept['text_content'])); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; break;
        
        // ==================== OOPP ====================
        case 'oopp':
            if (!empty($content['oopp_items'])):
                $is_first = $first_section; $first_section = false;
        ?>
        <section class="sum-section<?php echo $is_first ? ' expanded' : ''; ?>" data-section="oopp">
            <button type="button" class="sum-section-header" onclick="toggleSection(this)">
                <div class="sum-section-title-wrap">
                    <span class="sum-section-icon">🦺</span>
                    <h2 class="sum-section-title"><?php echo esc_html($t['section_oopp']); ?></h2>
                </div>
                <svg class="sum-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="sum-section-content">
                <div class="sum-section-body">
                    <div class="inner-accordion" data-accordion="oopp">
                        <?php foreach ($content['oopp_items'] as $i => $item): ?>
                        <div class="inner-accordion-item<?php echo $i === 0 ? ' expanded' : ''; ?>">
                            <button type="button" class="inner-accordion-header" onclick="toggleInnerAccordion(this, 'oopp')">
                                <div class="inner-accordion-title-wrap">
                                    <div class="inner-accordion-thumb">
                                        <?php if (!empty($item['image_url'])): ?>
                                        <img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr($item['name']); ?>">
                                        <?php else: ?>
                                        <span class="inner-accordion-thumb-icon">🦺</span>
                                        <?php endif; ?>
                                    </div>
                                    <svg class="inner-accordion-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6"></polyline>
                                    </svg>
                                    <h3 class="inner-accordion-title"><?php echo esc_html($item['name']); ?></h3>
                                    <?php if (!empty($item['group_code'])): ?>
                                    <span class="inner-accordion-badge"><?php echo esc_html($item['group_code']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </button>
                            <div class="inner-accordion-content">
                                <div class="inner-accordion-body">
                                    <div class="oopp-detail">
                                        <!-- Image -->
                                        <div class="oopp-image">
                                            <?php if (!empty($item['image_url'])): ?>
                                            <img src="<?php echo esc_url($item['image_url']); ?>" alt="<?php echo esc_attr($item['name']); ?>">
                                            <?php else: ?>
                                            <div class="oopp-image-placeholder">🦺</div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Info -->
                                        <div class="oopp-info">
                                            <?php if (!empty($item['description'])): ?>
                                            <h4>Popis</h4>
                                            <p><?php echo esc_html($item['description']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($item['risk_description'])): ?>
                                            <h4>Rizika</h4>
                                            <p><?php echo esc_html($item['risk_description']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($item['usage_instructions'])): ?>
                                            <h4>Použití</h4>
                                            <p><?php echo esc_html($item['usage_instructions']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($item['standards'])): ?>
                                            <h4>Normy</h4>
                                            <p><?php echo esc_html($item['standards']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($item['group_name'])): ?>
                                            <h4>Skupina</h4>
                                            <p><?php echo esc_html($item['group_name']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if (empty($item['description']) && empty($item['risk_description']) && empty($item['usage_instructions']) && empty($item['standards']) && empty($item['group_name'])): ?>
                                            <p style="color: #64748b; font-style: italic;">Žádné další informace.</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; break;
        
        // ==================== ADDITIONAL ====================
        case 'additional':
            if (!empty($content['additional_text']) || !empty($content['documents'])):
                $is_first = $first_section; $first_section = false;
        ?>
        <section class="sum-section<?php echo $is_first ? ' expanded' : ''; ?>" data-section="additional">
            <button type="button" class="sum-section-header" onclick="toggleSection(this)">
                <div class="sum-section-title-wrap">
                    <span class="sum-section-icon">ℹ️</span>
                    <h2 class="sum-section-title"><?php echo esc_html($t['section_additional']); ?></h2>
                </div>
                <svg class="sum-section-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"></polyline>
                </svg>
            </button>
            <div class="sum-section-content">
                <div class="sum-section-body">
                    <?php if (!empty($content['additional_text'])): ?>
                    <div class="sum-text"><?php echo wp_kses_post(wpautop($content['additional_text'])); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($content['documents'])): ?>
                    <div class="sum-docs">
                        <?php foreach ($content['documents'] as $doc): 
                            $upload_dir = wp_upload_dir();
                            $doc_url = $upload_dir['baseurl'] . '/' . ltrim($doc['file_path'], '/');
                        ?>
                        <a href="<?php echo esc_url($doc_url); ?>" target="_blank" class="sum-doc-link">
                            📄 <?php echo esc_html($doc['file_name']); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php endif; break;
        
        endswitch; 
        endforeach; 
        ?>
        </div>
        
        <?php endif; ?>
        
    </main>
    
</div>

<script>
// Language switcher
function changeLanguage(lang) {
    window.location.href = '<?php echo esc_url($this->get_url('summary')); ?>?lang=' + lang;
}

// Main sections accordion - only one open at a time
function toggleSection(btn) {
    var section = btn.closest('.sum-section');
    var isExpanded = section.classList.contains('expanded');
    
    // Close all sections
    document.querySelectorAll('.sum-section.expanded').forEach(function(el) {
        el.classList.remove('expanded');
    });
    
    // Toggle current if was closed
    if (!isExpanded) {
        section.classList.add('expanded');
        
        // Scroll to section
        setTimeout(function() {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);
    }
}

// Inner accordion (departments, OOPP) - only one open within each group
function toggleInnerAccordion(btn, groupName) {
    var item = btn.closest('.inner-accordion-item');
    var accordion = item.closest('.inner-accordion');
    var isExpanded = item.classList.contains('expanded');
    
    // Close all in same accordion
    accordion.querySelectorAll('.inner-accordion-item.expanded').forEach(function(el) {
        el.classList.remove('expanded');
    });
    
    // Toggle current if was closed
    if (!isExpanded) {
        item.classList.add('expanded');
    }
}

// PDF Viewer
<?php if ($pdf_url): ?>
(function() {
    var pdfUrl = '<?php echo esc_url($pdf_url); ?>';
    var pdfDoc = null;
    var currentPage = 1;
    var totalPages = 0;
    var rendering = false;
    var isFullscreen = false;
    
    // Elements
    var container = document.getElementById('pdf-container');
    var canvas = document.getElementById('pdf-canvas');
    var ctx = canvas.getContext('2d');
    var loading = document.getElementById('pdf-loading');
    var pageInfo = document.getElementById('pdf-page-info');
    var prevBtn = document.getElementById('pdf-prev');
    var nextBtn = document.getElementById('pdf-next');
    var fullscreenBtn = document.getElementById('pdf-fullscreen');
    
    // Set worker
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    
    // Load PDF
    pdfjsLib.getDocument(pdfUrl).promise.then(function(pdf) {
        pdfDoc = pdf;
        totalPages = pdf.numPages;
        loading.style.display = 'none';
        canvas.style.display = 'block';
        updateButtons();
        renderPage(1);
    }).catch(function(err) {
        loading.innerHTML = '<div style="color: #ef4444;">Chyba při načítání PDF</div>';
        console.error(err);
    });
    
    function renderPage(num) {
        if (rendering) return;
        rendering = true;
        
        pdfDoc.getPage(num).then(function(page) {
            var containerWidth = container.clientWidth - 32;
            var viewport = page.getViewport({ scale: 1 });
            var scale = Math.min(containerWidth / viewport.width, 2);
            var scaledViewport = page.getViewport({ scale: scale });
            
            canvas.width = scaledViewport.width;
            canvas.height = scaledViewport.height;
            
            page.render({
                canvasContext: ctx,
                viewport: scaledViewport
            }).promise.then(function() {
                rendering = false;
                currentPage = num;
                updateButtons();
            });
        });
    }
    
    function updateButtons() {
        pageInfo.textContent = currentPage + ' / ' + totalPages;
        prevBtn.disabled = currentPage <= 1;
        nextBtn.disabled = currentPage >= totalPages;
    }
    
    // Navigation
    prevBtn.onclick = function() {
        if (currentPage > 1) renderPage(currentPage - 1);
    };
    
    nextBtn.onclick = function() {
        if (currentPage < totalPages) renderPage(currentPage + 1);
    };
    
    // Keyboard
    document.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft') { prevBtn.click(); e.preventDefault(); }
        if (e.key === 'ArrowRight') { nextBtn.click(); e.preventDefault(); }
        if (e.key === 'Escape' && isFullscreen) { toggleFullscreen(); }
    });
    
    // Fullscreen
    fullscreenBtn.onclick = toggleFullscreen;
    
    function toggleFullscreen() {
        isFullscreen = !isFullscreen;
        container.classList.toggle('pdf-fullscreen', isFullscreen);
        document.body.style.overflow = isFullscreen ? 'hidden' : '';
        
        // Re-render for new size
        setTimeout(function() {
            renderPage(currentPage);
        }, 100);
    }
})();
<?php endif; ?>
</script>

</body>
</html>