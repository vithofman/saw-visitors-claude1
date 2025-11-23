<?php
/**
 * Invitation Step - Risks Upload (volitelné)
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

// Load file upload component
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php';

// Get variables from template context
$lang = $lang ?? 'cs';
$visit_id = $visit_id ?? null;
$existing_text = $existing_text ?? null;
$existing_docs = $existing_docs ?? [];

// Prepare existing files for file-upload component
$existing_files = [];
foreach ($existing_docs as $doc) {
    $existing_files[] = [
        'id' => $doc['id'],
        'name' => $doc['file_name'],
        'size' => $doc['file_size'],
        'url' => wp_upload_dir()['baseurl'] . $doc['file_path'],
        'path' => $doc['file_path'],
    ];
}

$translations = [
    'cs' => [
        'title' => 'Informace o rizicích',
        'subtitle' => 'Nahrajte informace o rizicích při práci na vašem pracovišti (volitelné)',
        'text_label' => 'Textový popis rizik:',
        'doc_label' => 'Dokumenty o rizicích:',
        'doc_help' => 'Povolené formáty: PDF, DOC, DOCX, XLS, XLSX (max 10MB na soubor, max 10 souborů)',
        'continue' => 'Pokračovat',
        'skip' => 'Přeskočit',
        'uploaded_docs' => 'Nahrané dokumenty:',
    ],
    'en' => [
        'title' => 'Risk Information',
        'subtitle' => 'Upload information about workplace risks (optional)',
        'text_label' => 'Text description of risks:',
        'doc_label' => 'Risk documents:',
        'doc_help' => 'Allowed formats: PDF, DOC, DOCX, XLS, XLSX (max 10MB per file, max 10 files)',
        'continue' => 'Continue',
        'skip' => 'Skip',
        'uploaded_docs' => 'Uploaded documents:',
    ],
];

$t = $translations[$lang] ?? $translations['cs'];
?>
<div class="saw-invitation-risks-upload">
    
    <header class="saw-risks-header">
        <div class="saw-risks-icon">📄</div>
        <div class="saw-risks-header-text">
            <h1 class="saw-risks-title"><?= esc_html($t['title']) ?></h1>
            <p class="saw-risks-subtitle"><?= esc_html($t['subtitle']) ?></p>
        </div>
    </header>
    
    <form method="POST" enctype="multipart/form-data" class="saw-risks-form">
        
        <div class="saw-risks-two-columns">
            <!-- Left Column: Rich Text Editor -->
            <div class="saw-risks-column saw-risks-column-left">
                <div class="saw-form-group saw-text-editor-group">
                    <label><?= esc_html($t['text_label']) ?></label>
                    <?php
                    // Ensure user can upload files for media buttons
                    $current_user_can_upload = current_user_can('upload_files');
                    if (!$current_user_can_upload) {
                        // Temporarily add capability for editor
                        $user = wp_get_current_user();
                        if ($user && $user->ID === 0) {
                            // Guest user - add temporary capability
                            add_filter('user_has_cap', function($caps, $cap, $user_id, $args) {
                                if (isset($args[0]) && $args[0] === 'upload_files') {
                                    $caps['upload_files'] = true;
                                }
                                return $caps;
                            }, 10, 4);
                        }
                    }
                    
                    wp_editor($existing_text ?? '', 'risks_text', [
                        'textarea_name' => 'risks_text',
                        'textarea_rows' => 15,
                        'media_buttons' => false,
                        'teeny' => false,
                        'quicktags' => true,
                        'tinymce' => [
                            'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink',
                            'toolbar2' => '',
                            'height' => 400,
                            'content_css' => false, // Use default WordPress styles
                            'skin' => false, // Use default WordPress skin
                            'wp_autoresize_on' => true,
                        ],
                    ]);
                    ?>
                </div>
            </div>
            
            <!-- Right Column: Document Upload -->
            <div class="saw-risks-column saw-risks-column-right">
                <div class="saw-form-group">
                    <label><?= esc_html($t['doc_label']) ?></label>
                    <small class="saw-form-help"><?= esc_html($t['doc_help']) ?></small>
                    
                    <?php
                    saw_file_upload_input([
                        'name' => 'risks_documents[]',
                        'id' => 'risks-documents-input',
                        'multiple' => true,
                        'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.odt',
                        'max_size' => 10485760, // 10MB
                        'max_files' => 10,
                        'context' => 'invitation_risks',
                        'class' => 'saw-risks-document-upload',
                        'existing_files' => $existing_files,
                    ]);
                    ?>
                </div>
            </div>
        </div>
        
        <!-- Buttons -->
        <div class="saw-form-actions">
            <button type="submit" name="action" value="save" class="saw-btn-primary">
                <?= esc_html($t['continue']) ?> →
            </button>
            <button type="submit" name="action" value="skip" class="saw-btn-secondary">
                <?= esc_html($t['skip']) ?>
            </button>
        </div>
        
        <input type="hidden" name="terminal_action" value="save_invitation_risks">
        <input type="hidden" name="visit_id" value="<?= $visit_id ?>">
        <?php wp_nonce_field('saw_invitation_risks', 'risks_nonce'); ?>
        <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
    </form>
    
</div>

<style>
/* Override terminal wrapper for invitation risks to allow scrolling */
body:has(.saw-invitation-risks-upload) .saw-terminal-wrapper {
    min-height: 100vh !important;
    align-items: flex-start !important;
    justify-content: flex-start !important;
    overflow-y: auto !important;
    padding-top: 1rem !important;
    padding-bottom: 1rem !important;
}

body:has(.saw-invitation-risks-upload) .saw-terminal-content {
    max-width: 1400px !important;
    width: 100% !important;
    height: auto !important;
    margin: 0 auto !important;
}

.saw-invitation-risks-upload {
    max-width: 100%;
    margin: 0 auto;
    padding: 1rem;
    width: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    min-height: 0; /* Allow flex child to shrink */
}

.saw-risks-header {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding: 2rem;
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.saw-risks-icon {
    width: 4rem;
    height: 4rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    border-radius: 18px;
    box-shadow: 0 10px 30px rgba(251, 191, 36, 0.3);
}

.saw-risks-title {
    font-size: 2rem;
    font-weight: 700;
    color: #f9fafb;
    margin: 0 0 0.5rem 0;
}

.saw-risks-subtitle {
    font-size: 1rem;
    color: rgba(203, 213, 225, 0.7);
    margin: 0;
}

.saw-risks-form {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    padding: 2rem;
    width: 100%;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0; /* Allow flex child to shrink */
    overflow-y: auto; /* Enable scrolling */
}

.saw-risks-two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    align-items: start;
    flex: 1;
    min-height: 0; /* Allow grid to shrink */
}

@media (max-width: 1024px) {
    .saw-risks-two-columns {
        grid-template-columns: 1fr;
    }
}

.saw-risks-column {
    display: flex;
    flex-direction: column;
}

.saw-risks-column-left {
    /* Left column styles */
}

.saw-risks-column-right {
    /* Right column styles */
}

.saw-form-group {
    margin-bottom: 2rem;
}

.saw-form-group label {
    display: block;
    font-size: 1rem;
    font-weight: 600;
    color: #f9fafb;
    margin-bottom: 0.75rem;
}

.saw-text-editor-group .wp-editor-wrap {
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}

.saw-text-editor-group .wp-editor-container {
    background: #fff;
}

.saw-text-editor-group .mce-tinymce {
    border: 1px solid #ddd;
    border-radius: 4px;
}

.saw-text-editor-group .wp-editor-area {
    background: #fff;
    color: #333;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

.saw-existing-documents {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.saw-existing-documents ul {
    list-style: none;
    padding: 0;
    margin: 0.5rem 0 0 0;
}

.saw-existing-documents li {
    padding: 0.5rem;
    color: #cbd5e1;
}

input[type="file"] {
    display: block;
    width: 100%;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border: 2px dashed rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    color: #f9fafb;
    cursor: pointer;
    transition: all 0.3s;
}

input[type="file"]:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: rgba(102, 126, 234, 0.5);
}

.saw-form-help {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: rgba(203, 213, 225, 0.6);
}

.saw-form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
    flex-shrink: 0; /* Prevent buttons from shrinking */
    padding-top: 1rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.saw-btn-primary,
.saw-btn-secondary {
    flex: 1;
    padding: 1.25rem 2rem;
    font-size: 1.125rem;
    font-weight: 600;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
}

.saw-btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

.saw-btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(102, 126, 234, 0.6);
}

.saw-btn-secondary {
    background: rgba(255, 255, 255, 0.05);
    color: rgba(203, 213, 225, 0.9);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.saw-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.08);
}
</style>

<script>
jQuery(document).ready(function($) {
    let formIsSubmitting = false;
    
    // Remove beforeunload warning when form is submitted
    $('.saw-risks-form').on('submit', function(e) {
        formIsSubmitting = true;
        
        // Remove beforeunload listener to prevent warning
        window.removeEventListener('beforeunload', handleBeforeUnload);
        
        // Save TinyMCE content
        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
        
        // Collect uploaded files from file-upload component
        const uploadedFilesData = {};
        
        // Get files from risks documents upload component
        const $risksUpload = $('#risks-documents-input').closest('.saw-file-upload-modern-container');
        if ($risksUpload.length) {
            const uploadInstance = $risksUpload.data('saw-file-upload-instance');
            if (uploadInstance && typeof uploadInstance.getUploadedFiles === 'function') {
                const files = uploadInstance.getUploadedFiles();
                if (files.length > 0) {
                    uploadedFilesData['risks_documents[]'] = files.map(function(file) {
                        return {
                            file: {
                                id: file.id || null,
                                name: file.name || file.fileName || '',
                                size: file.size || 0,
                                url: file.url || '',
                                path: file.path || file.relativePath || '',
                                type: file.type || file.mimeType || 'application/octet-stream',
                            }
                        };
                    });
                }
            }
        }
        
        // Add uploaded files data to form as hidden input
        if (Object.keys(uploadedFilesData).length > 0) {
            // Remove existing hidden input if any
            $(this).find('input[name="uploaded_files"]').remove();
            
            // Add new hidden input with JSON data
            $('<input>').attr({
                type: 'hidden',
                name: 'uploaded_files',
                value: JSON.stringify(uploadedFilesData)
            }).appendTo(this);
        }
    });
    
    // Handle beforeunload only if form is not submitting
    function handleBeforeUnload(e) {
        if (!formIsSubmitting) {
            // Only show warning if there are unsaved changes
            const hasText = $('#risks_text').val() && $('#risks_text').val().trim() !== '';
            const hasFiles = $('#risks-documents-input').closest('.saw-file-upload-modern-container').find('.saw-file-item').length > 0;
            
            if (hasText || hasFiles) {
                e.preventDefault();
                e.returnValue = 'Máte neuložené změny. Opravdu chcete opustit stránku?';
                return e.returnValue;
            }
        }
    }
    
    // Add beforeunload listener
    window.addEventListener('beforeunload', handleBeforeUnload);
    
    // Initialize TinyMCE editor properly (similar to content module)
    function initTinyMCE() {
        if (typeof wp !== 'undefined' && wp.editor && wp.editor.initialize) {
            const editorId = 'risks_text';
            const $textarea = $('#' + editorId);
            
            if ($textarea.length && !tinymce.get(editorId)) {
                // Wait for WordPress editor to be ready
                setTimeout(function() {
                    if (typeof tinymce !== 'undefined' && !tinymce.get(editorId)) {
                        wp.editor.initialize(editorId, {
                            tinymce: {
                                toolbar1: 'formatselect,bold,italic,bullist,numlist,link,unlink',
                                toolbar2: '',
                                height: 400,
                                content_css: false, // Use default WordPress styles
                                skin: false, // Use default WordPress skin
                            },
                            quicktags: true,
                            media_buttons: false,
                        });
                    }
                }, 100);
            }
        }
    }
    
    // Initialize file upload component after page load
    if (typeof SAWModernFileUpload !== 'undefined') {
        $('.saw-file-upload-modern-container').each(function() {
            const $container = $(this);
            if (!$container.data('saw-file-upload-instance')) {
                const options = $container.data('options');
                if (options) {
                    try {
                        const parsedOptions = typeof options === 'string' ? JSON.parse(options) : options;
                        const instance = new SAWModernFileUpload($container, parsedOptions);
                        $container.data('saw-file-upload-instance', instance);
                    } catch (e) {
                        console.error('Error initializing file upload:', e);
                    }
                }
            }
        });
    }
    
    // Initialize TinyMCE when WordPress editor is ready
    if (typeof wp !== 'undefined' && wp.editor) {
        initTinyMCE();
    } else {
        // Wait for WordPress editor to load
        $(window).on('load', function() {
            setTimeout(initTinyMCE, 500);
        });
    }
});
</script>

