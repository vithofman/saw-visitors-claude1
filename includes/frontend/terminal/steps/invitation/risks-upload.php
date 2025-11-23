<?php
/**
 * Invitation Step - Risks Upload (volitelné)
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

if (!defined('ABSPATH')) exit;

error_log("[RISKS-UPLOAD.PHP] Template loaded - file: " . __FILE__);

// Load file upload component
$file_upload_path = SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php';
if (!file_exists($file_upload_path)) {
    error_log("[RISKS-UPLOAD.PHP] ERROR: File upload component not found: {$file_upload_path}");
    wp_die("File upload component not found: {$file_upload_path}");
}
require_once $file_upload_path;
error_log("[RISKS-UPLOAD.PHP] File upload component loaded");

// Get variables from template context
$lang = $lang ?? 'cs';
$visit_id = $visit_id ?? null;
$existing_text = $existing_text ?? null;
$existing_docs = $existing_docs ?? [];

error_log("[RISKS-UPLOAD.PHP] Variables: visit_id={$visit_id}, lang={$lang}, existing_text=" . ($existing_text ? 'yes' : 'no') . ", existing_docs=" . count($existing_docs));

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
<style>
/* === UNIFIED STYLE === */
:root {
    --theme-color: #667eea;
    --theme-color-hover: #764ba2;
    --bg-dark: #1a202c;
    --bg-dark-medium: #2d3748;
    --bg-glass: rgba(15, 23, 42, 0.6);
    --bg-glass-light: rgba(255, 255, 255, 0.08);
    --border-glass: rgba(148, 163, 184, 0.12);
    --text-primary: #FFFFFF;
    --text-secondary: #e5e7eb;
    --text-muted: #9ca3af;
}

*,
*::before,
*::after {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

.saw-terminal-footer {
    display: none !important;
}

/* Override terminal wrapper for invitation risks */
.saw-terminal-wrapper.has-invitation-risks {
    min-height: 100vh !important;
    align-items: flex-start !important;
    justify-content: flex-start !important;
    overflow-y: auto !important;
    padding: 2rem !important;
    height: auto !important;
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%) !important;
}

.saw-terminal-wrapper.has-invitation-risks .saw-terminal-content {
    max-width: 1400px !important;
    width: 100% !important;
    height: auto !important;
    margin: 0 auto !important;
    padding: 0 !important;
}

.saw-risks-aurora {
    position: relative;
    width: 100%;
    min-height: calc(100vh - 4rem);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    color: var(--text-secondary);
    background: transparent;
    padding: 0;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
}

.saw-risks-content {
    max-width: 1400px;
    width: 100%;
    margin: 0 auto;
    animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    flex-direction: column;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Header */
.saw-risks-header {
    text-align: center;
    margin-bottom: 3rem;
}

.saw-risks-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    animation: scaleIn 0.5s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
}

@keyframes scaleIn {
    from { opacity: 0; transform: scale(0.8); }
    to { opacity: 1; transform: scale(1); }
}

.saw-risks-title {
    font-size: 2rem;
    font-weight: 700;
    background: linear-gradient(135deg, #f9fafb 0%, #cbd5e1 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    letter-spacing: -0.02em;
    margin-bottom: 0.75rem;
    line-height: 1.3;
}

.saw-risks-subtitle {
    font-size: 1rem;
    color: rgba(203, 213, 225, 0.8);
    font-weight: 500;
}

/* Form */
.saw-risks-form {
    background: var(--bg-glass);
    backdrop-filter: blur(20px) saturate(180%);
    border-radius: 20px;
    border: 1px solid var(--border-glass);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    padding: 2rem;
    margin-bottom: 2rem;
    display: flex;
    flex-direction: column;
    min-height: 0;
    width: 100%;
    box-sizing: border-box;
}

/* Two Column Layout */
.saw-risks-two-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    align-items: start;
    margin-bottom: 2rem;
    width: 100%;
    box-sizing: border-box;
}

@media (max-width: 1024px) {
    .saw-risks-two-columns {
        grid-template-columns: 1fr;
    }
}

.saw-risks-column {
    display: flex !important;
    flex-direction: column;
    width: 100%;
    box-sizing: border-box;
    min-width: 0;
    visibility: visible !important;
    opacity: 1 !important;
}

.saw-risks-two-columns {
    display: grid !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.saw-form-group {
    margin-bottom: 2rem;
}

.saw-form-group label {
    display: block;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 0.75rem;
}

.saw-form-help {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: var(--text-muted);
}

/* WYSIWYG Editor - white background for readability */
.saw-text-editor-group {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    width: 100%;
}

.saw-text-editor-group .wp-editor-wrap {
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    border: 1px solid rgba(255, 255, 255, 0.2);
    display: block !important;
    visibility: visible !important;
    width: 100%;
    min-height: 400px;
}

.saw-text-editor-group .wp-editor-container {
    background: #fff;
    display: block !important;
    visibility: visible !important;
}

.saw-text-editor-group .mce-tinymce {
    border: 1px solid #ddd;
    border-radius: 4px;
    display: block !important;
    visibility: visible !important;
}

.saw-text-editor-group .wp-editor-area {
    background: #fff;
    color: #333;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    display: block !important;
    visibility: visible !important;
}

/* Buttons */
.saw-form-actions {
    display: flex !important;
    gap: 1rem;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border-glass);
    flex-shrink: 0;
    width: 100%;
    box-sizing: border-box;
    visibility: visible !important;
    opacity: 1 !important;
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
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
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
    background: var(--bg-glass-light);
    color: var(--text-secondary);
    border: 1px solid var(--border-glass);
}

.saw-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.12);
    border-color: rgba(148, 163, 184, 0.2);
}

@media (max-width: 768px) {
    .saw-risks-aurora {
        padding: 1rem;
    }
    
    .saw-risks-title {
        font-size: 1.75rem;
    }
    
    .saw-form-actions {
        flex-direction: column;
    }
}
</style>

<div class="saw-risks-aurora">
    <div class="saw-risks-content">
        
        <header class="saw-risks-header">
            <div class="saw-risks-icon">📄</div>
            <h1 class="saw-risks-title"><?= esc_html($t['title']) ?></h1>
            <p class="saw-risks-subtitle"><?= esc_html($t['subtitle']) ?></p>
        </header>
        
        <form method="POST" enctype="multipart/form-data" class="saw-risks-form" id="invitation-risks-form">
            
            <div class="saw-risks-two-columns">
                <!-- Left Column: Rich Text Editor -->
                <div class="saw-risks-column">
                    <div class="saw-form-group saw-text-editor-group">
                        <label><?= esc_html($t['text_label']) ?></label>
                        <?php
                        error_log("[RISKS-UPLOAD.PHP] About to render wp_editor");
                        
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
                        
                        // Check if wp_editor function exists
                        if (!function_exists('wp_editor')) {
                            error_log("[RISKS-UPLOAD.PHP] ERROR: wp_editor function does not exist!");
                            echo '<textarea name="risks_text" id="risks_text" rows="15" style="width: 100%;">' . esc_textarea($existing_text ?? '') . '</textarea>';
                            echo '<p style="color: red;">ERROR: WordPress editor not available. Using fallback textarea.</p>';
                        } else {
                            error_log("[RISKS-UPLOAD.PHP] Calling wp_editor");
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
                            error_log("[RISKS-UPLOAD.PHP] wp_editor called");
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Right Column: Document Upload -->
                <div class="saw-risks-column">
                    <div class="saw-form-group">
                        <label><?= esc_html($t['doc_label']) ?></label>
                        <small class="saw-form-help"><?= esc_html($t['doc_help']) ?></small>
                        
                        <?php
                        error_log("[RISKS-UPLOAD.PHP] About to render file upload - existing_files count: " . count($existing_files));
                        
                        // Check if function exists
                        if (!function_exists('saw_file_upload_input')) {
                            error_log("[RISKS-UPLOAD.PHP] ERROR: saw_file_upload_input function does not exist!");
                            echo '<p style="color: red;">ERROR: File upload component not available.</p>';
                            echo '<input type="file" name="risks_documents[]" id="risks-documents-input" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.odt">';
                        } else {
                            error_log("[RISKS-UPLOAD.PHP] Calling saw_file_upload_input");
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
                            error_log("[RISKS-UPLOAD.PHP] saw_file_upload_input called");
                        }
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
            <input type="hidden" name="uploaded_files" id="uploaded-files-data">
            <?php wp_nonce_field('saw_invitation_risks', 'risks_nonce'); ?>
            <?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
        </form>
        
    </div>
</div>


<script>
jQuery(document).ready(function($) {
    let formIsSubmitting = false;
    let beforeUnloadHandler = null;
    
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
    
    // Store handler reference
    beforeUnloadHandler = handleBeforeUnload;
    
    // Add beforeunload listener
    window.addEventListener('beforeunload', beforeUnloadHandler);
    
    // Remove beforeunload warning when form is submitted
    $('#invitation-risks-form').on('submit', function(e) {
        formIsSubmitting = true;
        
        // Remove beforeunload listener to prevent warning
        if (beforeUnloadHandler) {
            window.removeEventListener('beforeunload', beforeUnloadHandler);
        }
        
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
    
    // Add class to wrapper for CSS
    $('.saw-terminal-wrapper').addClass('has-invitation-risks');
    
    // Initialize TinyMCE editor properly (similar to content module)
    function initTinyMCE() {
        const editorId = 'risks_text';
        const $textarea = $('#' + editorId);
        
        if (!$textarea.length) {
            return;
        }
        
        // Check if editor already exists
        if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
            return;
        }
        
        // Wait for WordPress editor API
        if (typeof wp === 'undefined' || typeof wp.editor === 'undefined') {
            setTimeout(initTinyMCE, 100);
            return;
        }
        
        // Initialize editor
        try {
            wp.editor.initialize(editorId, {
                tinymce: {
                    toolbar1: 'formatselect,bold,italic,bullist,numlist,link,unlink',
                    toolbar2: '',
                    height: 400,
                    content_css: false,
                    skin: false,
                    wp_autoresize_on: true,
                },
                quicktags: true,
                media_buttons: false,
            });
            
            console.log('[Risks Upload] TinyMCE editor initialized');
        } catch (e) {
            console.error('[Risks Upload] Error initializing TinyMCE:', e);
        }
    }
    
    // Initialize file upload component after page load
    function initFileUpload() {
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
    }
    
    // Initialize TinyMCE when WordPress editor is ready
    if (typeof wp !== 'undefined' && wp.editor) {
        setTimeout(initTinyMCE, 200);
    } else {
        // Wait for WordPress editor to load
        $(window).on('load', function() {
            setTimeout(initTinyMCE, 500);
        });
    }
    
    // Initialize file upload
    initFileUpload();
});
</script>

