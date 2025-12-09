<?php
/**
 * Visit Risks Edit Template
 * 
 * Full-page editor for visit risk information with richtext and file upload.
 * 
 * @package     SAW_Visitors
 * @subpackage  Modules/Visits/Risks
 * @since       5.1.0
 * 
 * Variables available:
 * @var array $visit Visit data
 * @var int $visit_id Visit ID
 * @var string $risks_text Existing risk text
 * @var array $existing_docs Existing documents
 * @var string $return_url URL to return after save
 * @var SAW_Visit_Risks_Controller $controller Controller instance
 * @var bool $show_success Whether to show success message
 */

if (!defined('ABSPATH')) {
    exit;
}

// Helper function for translations
$tr = function($key, $fallback = '') use ($controller) {
    return $controller->tr($key, $fallback);
};

// Prepare visit display data
$visit_name = !empty($visit['company_name']) 
    ? $visit['company_name'] 
    : (!empty($visit['first_visitor_name']) ? $visit['first_visitor_name'] : 'Návštěva #' . $visit_id);

$visit_type_label = ($visit['visit_type'] ?? '') === 'walk_in' 
    ? $tr('visit_type_walk_in')
    : $tr('visit_type_planned');

$visit_dates = '';
if (!empty($visit['planned_date_from'])) {
    $visit_dates = date_i18n('d.m.Y', strtotime($visit['planned_date_from']));
    if (!empty($visit['planned_date_to']) && $visit['planned_date_to'] !== $visit['planned_date_from']) {
        $visit_dates .= ' - ' . date_i18n('d.m.Y', strtotime($visit['planned_date_to']));
    }
}

$upload_dir = wp_upload_dir();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($tr('page_title')); ?> - <?php echo esc_html($visit_name); ?></title>
    <?php wp_head(); ?>
    
    <style>
    /* ============================================
       BASE STYLES
       ============================================ */
    :root {
        --saw-primary: #6366f1;
        --saw-primary-dark: #4f46e5;
        --saw-primary-light: #818cf8;
        --saw-success: #10b981;
        --saw-warning: #f59e0b;
        --saw-danger: #ef4444;
        --saw-gray-50: #f8fafc;
        --saw-gray-100: #f1f5f9;
        --saw-gray-200: #e2e8f0;
        --saw-gray-300: #cbd5e1;
        --saw-gray-400: #94a3b8;
        --saw-gray-500: #64748b;
        --saw-gray-600: #475569;
        --saw-gray-700: #334155;
        --saw-gray-800: #1e293b;
        --saw-gray-900: #0f172a;
    }
    
    * {
        box-sizing: border-box;
    }
    
    body {
        margin: 0;
        padding: 0;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
    }
    
    .saw-risks-page {
        min-height: 100vh;
        padding: 2rem;
    }
    
    .saw-risks-container {
        max-width: 900px;
        margin: 0 auto;
    }
    
    /* ============================================
       HEADER
       ============================================ */
    .saw-risks-header {
        margin-bottom: 1.5rem;
    }
    
    .saw-risks-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        font-size: 0.875rem;
    }
    
    .saw-risks-breadcrumb a {
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        transition: color 0.2s;
    }
    
    .saw-risks-breadcrumb a:hover {
        color: white;
    }
    
    .saw-risks-breadcrumb span {
        color: rgba(255, 255, 255, 0.4);
    }
    
    .saw-risks-breadcrumb .current {
        color: white;
        font-weight: 600;
    }
    
    .saw-risks-title-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }
    
    .saw-risks-title {
        margin: 0;
        font-size: 1.75rem;
        font-weight: 700;
        color: white;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    
    .saw-risks-title-icon {
        width: 48px;
        height: 48px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    /* ============================================
       VISIT INFO CARD
       ============================================ */
    .saw-visit-info-card {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }
    
    .saw-visit-info-icon {
        width: 40px;
        height: 40px;
        background: rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    
    .saw-visit-info-content {
        flex: 1;
        min-width: 0;
    }
    
    .saw-visit-info-name {
        font-size: 1rem;
        font-weight: 600;
        color: white;
        margin: 0 0 0.25rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .saw-visit-info-meta {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem;
        font-size: 0.8125rem;
        color: rgba(255, 255, 255, 0.7);
    }
    
    .saw-visit-info-badge {
        background: rgba(255, 255, 255, 0.2);
        padding: 0.25rem 0.625rem;
        border-radius: 20px;
        font-size: 0.6875rem;
        font-weight: 600;
        text-transform: uppercase;
        color: white;
    }
    
    .saw-visit-info-badge.walk-in {
        background: rgba(245, 158, 11, 0.3);
        color: #fcd34d;
    }
    
    /* ============================================
       MAIN FORM CARD
       ============================================ */
    .saw-risks-form-card {
        background: white;
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        overflow: hidden;
    }
    
    .saw-risks-form {
        padding: 0;
    }
    
    /* ============================================
       SECTIONS
       ============================================ */
    .saw-risks-section {
        padding: 1.5rem 2rem;
        border-bottom: 1px solid var(--saw-gray-100);
    }
    
    .saw-risks-section:last-of-type {
        border-bottom: none;
    }
    
    .saw-risks-section-header {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        margin-bottom: 1rem;
    }
    
    .saw-risks-section-icon {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.125rem;
        flex-shrink: 0;
    }
    
    .saw-risks-section-icon.docs {
        background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    }
    
    .saw-risks-section-text {
        flex: 1;
    }
    
    .saw-risks-section-title {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: var(--saw-gray-800);
    }
    
    .saw-risks-section-help {
        margin: 0.25rem 0 0;
        font-size: 0.8125rem;
        color: var(--saw-gray-500);
    }
    
    /* ============================================
       RICHTEXT EDITOR
       ============================================ */
    .saw-richtext-wrapper {
        border: 1px solid var(--saw-gray-200);
        border-radius: 12px;
        overflow: hidden;
    }
    
    .saw-richtext-wrapper .wp-editor-wrap {
        border: none !important;
    }
    
    .saw-richtext-wrapper .wp-editor-container {
        border: none !important;
    }
    
    .saw-richtext-wrapper .wp-editor-tabs {
        background: var(--saw-gray-50) !important;
        border-bottom: 1px solid var(--saw-gray-200) !important;
        padding: 4px 8px 0 !important;
    }
    
    .saw-richtext-wrapper .mce-toolbar-grp {
        background: var(--saw-gray-50) !important;
        border-bottom: 1px solid var(--saw-gray-200) !important;
    }
    
    .saw-richtext-wrapper textarea.wp-editor-area {
        border: none !important;
        padding: 1rem !important;
        min-height: 200px;
    }
    
    .saw-richtext-wrapper .mce-edit-area iframe {
        min-height: 200px;
    }
    
    /* ============================================
       FILE UPLOAD
       ============================================ */
    .saw-upload-zone {
        border: 2px dashed var(--saw-gray-300);
        border-radius: 12px;
        padding: 2rem;
        text-align: center;
        background: var(--saw-gray-50);
        transition: all 0.2s ease;
        cursor: pointer;
        position: relative;
    }
    
    .saw-upload-zone:hover {
        border-color: var(--saw-primary);
        background: #eef2ff;
    }
    
    .saw-upload-zone.dragover {
        border-color: var(--saw-primary);
        background: #eef2ff;
        transform: scale(1.01);
    }
    
    .saw-upload-zone input[type="file"] {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
    
    .saw-upload-icon {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, var(--saw-primary) 0%, #8b5cf6 100%);
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        color: white;
        font-size: 1.5rem;
    }
    
    .saw-upload-text {
        font-size: 1rem;
        font-weight: 600;
        color: var(--saw-gray-700);
        margin-bottom: 0.25rem;
    }
    
    .saw-upload-button {
        color: var(--saw-primary);
        font-weight: 600;
        text-decoration: underline;
    }
    
    .saw-upload-help {
        font-size: 0.8125rem;
        color: var(--saw-gray-500);
        margin-top: 0.5rem;
    }
    
    /* ============================================
       FILES LISTS
       ============================================ */
    .saw-files-section {
        margin-top: 1.5rem;
    }
    
    .saw-files-header {
        font-size: 0.8125rem;
        font-weight: 600;
        color: var(--saw-gray-600);
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .saw-files-list {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .saw-file-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        background: var(--saw-gray-50);
        border: 1px solid var(--saw-gray-200);
        border-radius: 10px;
        transition: all 0.2s ease;
    }
    
    .saw-file-item:hover {
        background: var(--saw-gray-100);
    }
    
    .saw-file-item.marked-delete {
        background: #fef2f2;
        border-color: #fecaca;
        opacity: 0.7;
    }
    
    .saw-file-item.marked-delete .saw-file-name {
        text-decoration: line-through;
        color: var(--saw-gray-400);
    }
    
    .saw-file-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #dc2626;
        font-size: 1.125rem;
        flex-shrink: 0;
    }
    
    .saw-file-icon.pdf { background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%); color: #dc2626; }
    .saw-file-icon.doc { background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); color: #2563eb; }
    .saw-file-icon.img { background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #059669; }
    
    .saw-file-info {
        flex: 1;
        min-width: 0;
    }
    
    .saw-file-name {
        font-size: 0.875rem;
        font-weight: 600;
        color: var(--saw-gray-800);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .saw-file-meta {
        font-size: 0.75rem;
        color: var(--saw-gray-500);
        margin-top: 0.125rem;
    }
    
    .saw-file-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .saw-file-btn {
        width: 32px;
        height: 32px;
        border: 1px solid var(--saw-gray-200);
        border-radius: 8px;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        color: var(--saw-gray-500);
        text-decoration: none;
        font-size: 0.875rem;
    }
    
    .saw-file-btn:hover {
        background: var(--saw-gray-100);
        color: var(--saw-gray-700);
    }
    
    .saw-file-btn.delete:hover {
        background: #fef2f2;
        border-color: #fecaca;
        color: #dc2626;
    }
    
    .saw-file-btn.undo {
        background: #dcfce7;
        border-color: #86efac;
        color: #16a34a;
    }
    
    .saw-file-btn.undo:hover {
        background: #bbf7d0;
    }
    
    .saw-no-files {
        text-align: center;
        padding: 2rem;
        color: var(--saw-gray-400);
        font-size: 0.875rem;
    }
    
    /* ============================================
       FORM ACTIONS
       ============================================ */
    .saw-risks-actions {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.5rem 2rem;
        background: var(--saw-gray-50);
        border-top: 1px solid var(--saw-gray-200);
    }
    
    .saw-risks-actions-left {
        display: flex;
        gap: 0.75rem;
    }
    
    .saw-risks-actions-right {
        display: flex;
        gap: 0.75rem;
    }
    
    .saw-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        font-size: 0.9375rem;
        font-weight: 600;
        border-radius: 10px;
        border: none;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        font-family: inherit;
    }
    
    .saw-btn-primary {
        background: linear-gradient(135deg, var(--saw-primary) 0%, #8b5cf6 100%);
        color: white;
        box-shadow: 0 4px 14px rgba(99, 102, 241, 0.4);
    }
    
    .saw-btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(99, 102, 241, 0.5);
        color: white;
    }
    
    .saw-btn-secondary {
        background: white;
        color: var(--saw-gray-700);
        border: 1px solid var(--saw-gray-300);
    }
    
    .saw-btn-secondary:hover {
        background: var(--saw-gray-50);
        border-color: var(--saw-gray-400);
        color: var(--saw-gray-700);
    }
    
    /* ============================================
       SUCCESS MESSAGE
       ============================================ */
    .saw-success-toast {
        position: fixed;
        top: 2rem;
        right: 2rem;
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-weight: 600;
        z-index: 9999;
        animation: slideIn 0.3s ease;
    }
    
    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    /* ============================================
       RESPONSIVE
       ============================================ */
    @media (max-width: 768px) {
        .saw-risks-page {
            padding: 1rem;
        }
        
        .saw-risks-section {
            padding: 1.25rem;
        }
        
        .saw-risks-actions {
            flex-direction: column;
            gap: 1rem;
        }
        
        .saw-risks-actions-left,
        .saw-risks-actions-right {
            width: 100%;
        }
        
        .saw-btn {
            flex: 1;
            justify-content: center;
        }
        
        .saw-visit-info-meta {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }
        
        .saw-risks-section-header {
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .saw-risks-section-icon {
            width: 32px;
            height: 32px;
            font-size: 1rem;
        }
    }
    </style>
</head>
<body>
    
<?php if (!empty($show_success)): ?>
<div class="saw-success-toast" id="successToast">
    <span>✅</span>
    <span><?php echo esc_html($tr('success_saved')); ?></span>
</div>
<script>
setTimeout(function() {
    var toast = document.getElementById('successToast');
    if (toast) toast.style.display = 'none';
}, 3000);
</script>
<?php endif; ?>

<div class="saw-risks-page">
    <div class="saw-risks-container">
        
        <!-- HEADER -->
        <header class="saw-risks-header">
            <nav class="saw-risks-breadcrumb">
                <a href="<?php echo esc_url(home_url('/admin/visits')); ?>">
                    <?php echo esc_html($tr('breadcrumb_visits')); ?>
                </a>
                <span>›</span>
                <a href="<?php echo esc_url($return_url); ?>">
                    <?php echo esc_html($tr('breadcrumb_detail')); ?>
                </a>
                <span>›</span>
                <span class="current"><?php echo esc_html($tr('breadcrumb_risks')); ?></span>
            </nav>
            
            <div class="saw-risks-title-row">
                <h1 class="saw-risks-title">
                    <span class="saw-risks-title-icon">⚠️</span>
                    <span><?php echo esc_html($tr('page_title')); ?></span>
                </h1>
            </div>
        </header>
        
        <!-- VISIT INFO CARD -->
        <div class="saw-visit-info-card">
            <div class="saw-visit-info-icon">🏢</div>
            <div class="saw-visit-info-content">
                <h2 class="saw-visit-info-name"><?php echo esc_html($visit_name); ?></h2>
                <div class="saw-visit-info-meta">
                    <span class="saw-visit-info-badge <?php echo ($visit['visit_type'] ?? '') === 'walk_in' ? 'walk-in' : ''; ?>">
                        <?php echo esc_html($visit_type_label); ?>
                    </span>
                    <?php if ($visit_dates): ?>
                    <span>📅 <?php echo esc_html($visit_dates); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($visit['branch_name'])): ?>
                    <span>📍 <?php echo esc_html($visit['branch_name']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- MAIN FORM -->
        <div class="saw-risks-form-card">
            <form method="POST" enctype="multipart/form-data" class="saw-risks-form" id="risksForm">
                <?php wp_nonce_field('saw_edit_visit_risks_' . $visit_id); ?>
                
                <!-- TEXT SECTION -->
                <div class="saw-risks-section">
                    <div class="saw-risks-section-header">
                        <div class="saw-risks-section-icon">📝</div>
                        <div class="saw-risks-section-text">
                            <h3 class="saw-risks-section-title"><?php echo esc_html($tr('section_text')); ?></h3>
                            <p class="saw-risks-section-help"><?php echo esc_html($tr('section_text_help')); ?></p>
                        </div>
                    </div>
                    
                    <div class="saw-richtext-wrapper">
                        <?php
                        $editor_settings = [
                            'textarea_name' => 'risks_text',
                            'textarea_rows' => 12,
                            'media_buttons' => true,
                            'teeny' => false,
                            'quicktags' => true,
                            'tinymce' => [
                                'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,link,unlink',
                                'toolbar2' => '',
                                'block_formats' => 'Odstavec=p;Nadpis 2=h2;Nadpis 3=h3',
                            ],
                        ];
                        
                        wp_editor($risks_text, 'risks_text_editor', $editor_settings);
                        ?>
                    </div>
                </div>
                
                <!-- DOCUMENTS SECTION -->
                <div class="saw-risks-section">
                    <div class="saw-risks-section-header">
                        <div class="saw-risks-section-icon docs">📎</div>
                        <div class="saw-risks-section-text">
                            <h3 class="saw-risks-section-title"><?php echo esc_html($tr('section_documents')); ?></h3>
                            <p class="saw-risks-section-help"><?php echo esc_html($tr('section_documents_help')); ?></p>
                        </div>
                    </div>
                    
                    <!-- Upload Zone -->
                    <div class="saw-upload-zone" id="uploadZone">
                        <input type="file" name="risks_documents[]" id="fileInput" multiple 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                        <div class="saw-upload-icon">📤</div>
                        <div class="saw-upload-text">
                            <?php echo esc_html($tr('upload_zone_text')); ?>
                            <span class="saw-upload-button"><?php echo esc_html($tr('upload_zone_button')); ?></span>
                        </div>
                        <div class="saw-upload-help"><?php echo esc_html($tr('upload_zone_help')); ?></div>
                    </div>
                    
                    <!-- Selected files preview -->
                    <div class="saw-files-section" id="newFilesPreview" style="display: none;">
                        <div class="saw-files-header">
                            📎 <span><?php echo esc_html($tr('new_files')); ?></span>
                        </div>
                        <div class="saw-files-list" id="newFilesList"></div>
                    </div>
                    
                    <!-- Existing Files -->
                    <?php if (!empty($existing_docs)): ?>
                    <div class="saw-files-section">
                        <div class="saw-files-header">
                            📁 <?php echo esc_html($tr('existing_files')); ?>
                            <span>(<?php echo count($existing_docs); ?>)</span>
                        </div>
                        <div class="saw-files-list" id="existingFilesList">
                            <?php foreach ($existing_docs as $doc): 
                                $file_url = $upload_dir['baseurl'] . $doc['file_path'];
                                $ext = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                $icon_class = 'pdf';
                                $icon = '📄';
                                if (in_array($ext, ['doc', 'docx'])) { $icon_class = 'doc'; $icon = '📝'; }
                                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) { $icon_class = 'img'; $icon = '🖼️'; }
                            ?>
                            <div class="saw-file-item" data-file-id="<?php echo esc_attr($doc['id']); ?>">
                                <div class="saw-file-icon <?php echo esc_attr($icon_class); ?>">
                                    <?php echo $icon; ?>
                                </div>
                                <div class="saw-file-info">
                                    <div class="saw-file-name"><?php echo esc_html($doc['file_name']); ?></div>
                                    <div class="saw-file-meta">
                                        <?php echo esc_html(strtoupper($ext)); ?> • 
                                        <?php echo esc_html(size_format($doc['file_size'])); ?> • 
                                        <?php echo esc_html(date_i18n('d.m.Y', strtotime($doc['uploaded_at']))); ?>
                                    </div>
                                </div>
                                <div class="saw-file-actions">
                                    <a href="<?php echo esc_url($file_url); ?>" target="_blank" class="saw-file-btn" title="Zobrazit">
                                        👁️
                                    </a>
                                    <button type="button" class="saw-file-btn delete" 
                                            onclick="toggleDeleteFile(this, <?php echo esc_attr($doc['id']); ?>)" 
                                            title="<?php echo esc_attr($tr('delete_file')); ?>">
                                        🗑️
                                    </button>
                                </div>
                                <input type="checkbox" name="delete_files[]" value="<?php echo esc_attr($doc['id']); ?>" 
                                       class="delete-checkbox" style="display: none;">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php elseif (empty($existing_docs)): ?>
                    <div class="saw-no-files" id="noFilesMessage">
                        <?php echo esc_html($tr('no_files')); ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- ACTIONS -->
                <div class="saw-risks-actions">
                    <div class="saw-risks-actions-left">
                        <a href="<?php echo esc_url($return_url); ?>" class="saw-btn saw-btn-secondary">
                            ← <?php echo esc_html($tr('btn_cancel')); ?>
                        </a>
                    </div>
                    <div class="saw-risks-actions-right">
                        <button type="submit" class="saw-btn saw-btn-primary">
                            ✓ <?php echo esc_html($tr('btn_save_close')); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
    </div>
</div>

<script>
(function() {
    'use strict';
    
    // Toggle delete file
    window.toggleDeleteFile = function(btn, fileId) {
        var fileItem = btn.closest('.saw-file-item');
        var checkbox = fileItem.querySelector('.delete-checkbox');
        
        if (checkbox.checked) {
            // Undo delete
            checkbox.checked = false;
            fileItem.classList.remove('marked-delete');
            btn.classList.remove('undo');
            btn.innerHTML = '🗑️';
            btn.title = '<?php echo esc_js($tr('delete_file')); ?>';
        } else {
            // Mark for delete
            checkbox.checked = true;
            fileItem.classList.add('marked-delete');
            btn.classList.add('undo');
            btn.innerHTML = '↩️';
            btn.title = '<?php echo esc_js($tr('undo')); ?>';
        }
    };
    
    // File input preview
    var fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            var preview = document.getElementById('newFilesPreview');
            var list = document.getElementById('newFilesList');
            
            if (this.files.length > 0) {
                preview.style.display = 'block';
                list.innerHTML = '';
                
                Array.from(this.files).forEach(function(file) {
                    var ext = file.name.split('.').pop().toLowerCase();
                    var iconClass = 'pdf';
                    var icon = '📄';
                    if (['doc', 'docx'].indexOf(ext) !== -1) { iconClass = 'doc'; icon = '📝'; }
                    if (['jpg', 'jpeg', 'png', 'gif'].indexOf(ext) !== -1) { iconClass = 'img'; icon = '🖼️'; }
                    
                    var item = document.createElement('div');
                    item.className = 'saw-file-item';
                    item.innerHTML = 
                        '<div class="saw-file-icon ' + iconClass + '">' + icon + '</div>' +
                        '<div class="saw-file-info">' +
                            '<div class="saw-file-name">' + file.name + '</div>' +
                            '<div class="saw-file-meta">' + ext.toUpperCase() + ' • ' + formatBytes(file.size) + '</div>' +
                        '</div>';
                    list.appendChild(item);
                });
            } else {
                preview.style.display = 'none';
            }
        });
    }
    
    // Drag and drop
    var uploadZone = document.getElementById('uploadZone');
    if (uploadZone && fileInput) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
            uploadZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(function(eventName) {
            uploadZone.addEventListener(eventName, function() {
                uploadZone.classList.add('dragover');
            }, false);
        });
        
        ['dragleave', 'drop'].forEach(function(eventName) {
            uploadZone.addEventListener(eventName, function() {
                uploadZone.classList.remove('dragover');
            }, false);
        });
        
        uploadZone.addEventListener('drop', function(e) {
            var dt = e.dataTransfer;
            if (dt && dt.files) {
                fileInput.files = dt.files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
    }
    
    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        var k = 1024;
        var sizes = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
})();
</script>

<?php wp_footer(); ?>
</body>
</html>