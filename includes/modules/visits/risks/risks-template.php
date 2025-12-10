<?php
/**
 * Risks Editor Template
 * 
 * EXACT COPY of invitation 2-risks-upload.php
 * Uses render_saw_richtext_editor() for proper dark mode editor.
 * 
 * @package SAW_Visitors
 * @since 5.1.0
 * @version 5.2.2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Display name
$display_name = !empty($visit['company_name']) 
    ? $visit['company_name'] 
    : (!empty($visit['first_visitor_name']) ? $visit['first_visitor_name'] : 'Návštěva #' . $visit_id);

$date_from = !empty($visit['planned_date_from']) ? date_i18n('d.m.Y', strtotime($visit['planned_date_from'])) : '';

// Prepare documents with URLs
$existing_files = [];
if (!empty($existing_docs)) {
    $upload_dir = wp_upload_dir();
    foreach ($existing_docs as $doc) {
        $existing_files[] = [
            'id' => $doc['id'],
            'name' => $doc['file_name'],
            'size' => $doc['file_size'],
            'url' => $upload_dir['baseurl'] . $doc['file_path']
        ];
    }
}

$nonce = wp_create_nonce('saw_edit_visit_risks_' . $visit_id);

$t = [
    'title' => 'Informace o rizicích',
    'subtitle' => $display_name . ($date_from ? ' · ' . $date_from : ''),
    'badge' => 'Editace',
    'text_title' => 'POPIS RIZIK',
    'doc_title' => 'DOKUMENTY',
    'doc_help' => 'PDF, DOC, DOCX • Max 10MB',
    'drag' => 'Přetáhněte soubory',
    'or' => 'nebo',
    'browse' => 'Vyberte soubory',
    'save' => 'Uložit změny',
    'cancel' => 'Zrušit',
];
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Editace rizik - <?php echo esc_html($display_name); ?></title>
    <?php wp_head(); ?>
    <style>
    /* EXACT COPY FROM INVITATION */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    
    html, body { min-height: 100vh; overflow-y: auto !important; }
    
    body.saw-risks-page {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
        background-attachment: fixed;
        color: #e2e8f0;
        padding: 2rem;
    }
    
    #wpadminbar { display: none !important; }
    html { margin-top: 0 !important; }
    .notice, .updated, .error, .is-dismissible, .components-snackbar-list { display: none !important; }
    
    .saw-risks-container { max-width: 1100px; margin: 0 auto; }
    
    .saw-risks-card{width:100%;background:#1a202c;border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);}
    .saw-risks-header{display:flex;align-items:center;gap:1rem;padding:1.5rem 2rem;border-bottom:1px solid #2d3748;}
    
    .saw-header-back {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: #2d3748;
        border: 1px solid #4a5568;
        border-radius: 10px;
        color: #a0aec0;
        text-decoration: none;
        transition: all 0.2s;
    }
    .saw-header-back:hover {
        background: #4a5568;
        color: #fff;
        transform: translateX(-2px);
    }
    
    .saw-risks-icon{width:48px;height:48px;background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);border-radius:14px;display:flex;align-items:center;justify-content:center;}
    .saw-risks-icon svg{width:24px;height:24px;color:#fff;}
    .saw-risks-title-group{flex:1;}
    .saw-risks-title{margin:0 0 0.25rem;font-size:1.5rem;font-weight:700;color:#fff;}
    .saw-risks-subtitle{margin:0;color:#a0aec0;font-size:0.9375rem;}
    .saw-risks-badge{background:rgba(245,158,11,0.15);color:#fbbf24;padding:0.4rem 0.875rem;border-radius:20px;font-size:0.6875rem;font-weight:700;text-transform:uppercase;}

    .saw-risks-form{padding:1.5rem 2rem 2rem;}
    .saw-risks-columns{display:grid;grid-template-columns:1fr 340px;gap:1.5rem;margin-bottom:1.5rem;}
    .saw-risks-section{background:#2d3748;border-radius:14px;display:flex;flex-direction:column;}
    .saw-section-header{display:flex;align-items:center;gap:0.625rem;padding:0.875rem 1rem;border-bottom:1px solid #4a5568;}
    .saw-section-icon{width:20px;height:20px;}
    .saw-section-icon svg{width:100%;height:100%;}
    .saw-section-icon.text-icon svg{color:#f59e0b;}
    .saw-section-icon.docs-icon svg{color:#10b981;}
    .saw-section-title{margin:0;font-size:0.75rem;font-weight:700;letter-spacing:0.1em;}
    .saw-section-title.text-title{color:#f59e0b;}
    .saw-section-title.docs-title{color:#10b981;}
    .saw-section-body{padding:1rem;flex:1;display:flex;flex-direction:column;}

    /* EDITOR STYLES - EXACT FROM INVITATION */
    .saw-section-body .wp-editor-wrap{border:1px solid #4a5568 !important;border-radius:10px;background:#1a202c !important;}
    .saw-section-body .wp-editor-container{border:none !important;background:#1a202c !important;}
    .saw-section-body textarea.wp-editor-area{background:#1a202c !important;color:#e2e8f0 !important;border:none !important;padding:12px !important;}
    .saw-section-body .wp-media-buttons{padding:10px 12px !important;background:#1e2533 !important;border-bottom:1px solid #4a5568 !important;}
    .saw-section-body .wp-media-buttons .button{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%) !important;color:#fff !important;border:none !important;border-radius:8px !important;padding:8px 16px !important;font-weight:600 !important;}
    .saw-section-body .mce-toolbar-grp{background:#1e2533 !important;border-bottom:1px solid #4a5568 !important;}
    .saw-section-body .mce-btn{background:transparent !important;border:none !important;}
    .saw-section-body .mce-btn button{color:#a0aec0 !important;}
    .saw-section-body .mce-btn:hover{background:#4a5568 !important;}
    .saw-section-body .mce-btn:hover button{color:#fff !important;}
    .saw-section-body .mce-btn.mce-active{background:#667eea !important;}
    .saw-section-body .mce-btn.mce-active button{color:#fff !important;}
    .saw-section-body .mce-ico{color:#a0aec0 !important;}
    .saw-section-body .mce-btn:hover .mce-ico,.saw-section-body .mce-btn.mce-active .mce-ico{color:#fff !important;}
    .saw-section-body .mce-content-body{background:#1a202c !important;color:#e2e8f0 !important;padding:12px !important;}
    .saw-section-body .mce-content-body h1,.saw-section-body .mce-content-body h2,.saw-section-body .mce-content-body h3{color:#fff !important;}
    .saw-section-body .mce-edit-area{background:#1a202c !important;}
    .saw-section-body .mce-edit-area iframe{background:#1a202c !important;}
    .saw-section-body .mce-tinymce{border:none !important;}
    .saw-section-body .mce-panel{background:transparent !important;border:none !important;}
    .saw-section-body .mce-menubar{background:#1e2533 !important;border:none !important;}
    .saw-section-body .mce-listbox{background:#2d3748 !important;border:1px solid #4a5568 !important;}
    .saw-section-body .mce-listbox button{color:#e2e8f0 !important;}
    .saw-section-body .wp-switch-editor{background:#2d3748 !important;color:#a0aec0 !important;border-color:#4a5568 !important;}
    .saw-section-body .wp-switch-editor:hover{color:#fff !important;}
    .saw-section-body .html-active .switch-html,.saw-section-body .tmce-active .switch-tmce{background:#4a5568 !important;color:#fff !important;}

    /* UPLOAD ZONE - EXACT FROM INVITATION */
    .saw-upload-zone{position:relative;border:2px dashed #4a5568;border-radius:10px;padding:1.5rem 1rem;text-align:center;background:#1a202c;cursor:pointer;}
    .saw-upload-zone:hover{border-color:#667eea;background:rgba(102,126,234,0.05);}
    .saw-file-input-hidden{position:absolute;width:100%;height:100%;top:0;left:0;opacity:0;cursor:pointer;}
    .saw-upload-content{pointer-events:none;}
    .saw-upload-icon{width:44px;height:44px;margin:0 auto 0.75rem;background:rgba(16,185,129,0.15);border-radius:50%;display:flex;align-items:center;justify-content:center;}
    .saw-upload-icon svg{width:22px;height:22px;color:#10b981;}
    .saw-upload-text{margin:0 0 0.375rem;font-size:0.9375rem;font-weight:600;color:#e2e8f0;}
    .saw-upload-or{display:block;margin:0.375rem 0;font-size:0.75rem;color:#718096;}
    .saw-upload-browse-btn{display:inline-flex;padding:0.5rem 1rem;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border:none;border-radius:8px;font-size:0.8125rem;font-weight:600;cursor:pointer;pointer-events:auto;}
    .saw-upload-help{margin:0.75rem 0 0;font-size:0.6875rem;color:#718096;}

    /* FILE LIST - EXACT FROM INVITATION */
    .saw-file-list{margin-top:0.75rem;display:flex;flex-direction:column;gap:0.5rem;max-height:200px;overflow-y:auto;}
    .saw-file-item{display:flex;align-items:center;gap:0.5rem;padding:0.5rem 0.625rem;background:#1a202c;border:1px solid #4a5568;border-radius:8px;}
    .saw-file-item.deleted{opacity:0.4;background:rgba(239,68,68,0.1);border-color:#ef4444;}
    .saw-file-badge{padding:0.2rem 0.375rem;border-radius:4px;font-size:0.5625rem;font-weight:800;text-transform:uppercase;}
    .saw-file-badge.pdf{background:rgba(239,68,68,0.15);color:#f87171;}
    .saw-file-badge.doc{background:rgba(59,130,246,0.15);color:#60a5fa;}
    .saw-file-badge.new{background:rgba(245,158,11,0.15);color:#fbbf24;}
    .saw-file-info{flex:1;min-width:0;}
    .saw-file-name{display:block;font-weight:600;color:#e2e8f0;font-size:0.75rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .saw-file-size{display:block;font-size:0.625rem;color:#718096;}
    .saw-file-remove{width:24px;height:24px;border:none;background:rgba(239,68,68,0.1);border-radius:5px;cursor:pointer;display:flex;align-items:center;justify-content:center;}
    .saw-file-remove svg{width:12px;height:12px;color:#f87171;}
    .saw-file-remove:hover{background:rgba(239,68,68,0.2);}
    .saw-file-view{width:24px;height:24px;border:none;background:rgba(102,126,234,0.1);border-radius:5px;cursor:pointer;display:flex;align-items:center;justify-content:center;text-decoration:none;}
    .saw-file-view svg{width:12px;height:12px;color:#667eea;}
    
    /* ACTIONS */
    .saw-risks-actions{display:flex;gap:1rem;justify-content:flex-end;padding-top:1.5rem;border-top:1px solid #2d3748;}
    .saw-btn-cancel{padding:0.875rem 1.5rem;background:#2d3748;color:#a0aec0;border:1px solid #4a5568;border-radius:14px;font-size:0.9375rem;font-weight:600;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;}
    .saw-btn-cancel:hover{background:#4a5568;color:#fff;}
    .saw-btn-save{display:inline-flex;align-items:center;gap:0.625rem;padding:0.875rem 1.5rem;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border:none;border-radius:14px;font-size:1rem;font-weight:700;cursor:pointer;box-shadow:0 6px 20px rgba(102,126,234,0.4);}
    .saw-btn-save:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(102,126,234,0.5);}
    .saw-btn-save svg{width:18px;height:18px;}

    /* LOADING */
    .saw-loading{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);backdrop-filter:blur(4px);z-index:10001;display:none;align-items:center;justify-content:center;}
    .saw-loading.active{display:flex;}
    .saw-spinner{width:48px;height:48px;border:4px solid rgba(255,255,255,0.2);border-top-color:#fff;border-radius:50%;animation:spin 0.8s linear infinite;}
    @keyframes spin{to{transform:rotate(360deg);}}

    @media (max-width:900px){.saw-risks-columns{grid-template-columns:1fr;}.saw-risks-actions{flex-direction:column-reverse;}.saw-btn-cancel,.saw-btn-save{width:100%;justify-content:center;}}
    </style>
</head>
<body class="saw-risks-page">

<div class="saw-risks-container">
    <div class="saw-risks-card">
        <div class="saw-risks-header">
            <a href="<?php echo esc_url($return_url); ?>" class="saw-header-back">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </a>
            <div class="saw-risks-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div class="saw-risks-title-group">
                <h1 class="saw-risks-title"><?php echo esc_html($t['title']); ?></h1>
                <p class="saw-risks-subtitle"><?php echo esc_html($t['subtitle']); ?></p>
            </div>
            <span class="saw-risks-badge"><?php echo esc_html($t['badge']); ?></span>
        </div>
        
        <form method="post" enctype="multipart/form-data" class="saw-risks-form" id="risks-form">
            <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="visit_id" value="<?php echo esc_attr($visit_id); ?>">
            
            <div class="saw-risks-columns">
                <div class="saw-risks-section">
                    <div class="saw-section-header">
                        <div class="saw-section-icon text-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14,2 14,8 20,8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10,9 9,9 8,9"/>
                            </svg>
                        </div>
                        <h2 class="saw-section-title text-title"><?php echo esc_html($t['text_title']); ?></h2>
                    </div>
                    <div class="saw-section-body">
                        <?php
                        // Use render_saw_richtext_editor if available, otherwise fallback
                        if (function_exists('render_saw_richtext_editor')) {
                            render_saw_richtext_editor('risks_text', $risks_text, array(
                                'textarea_name' => 'risks_text',
                                'height' => 350,
                                'dark_mode' => true,
                                'toolbar_preset' => 'full',
                            ));
                        } else {
                            // Fallback to wp_editor
                            wp_editor($risks_text, 'risks_text', [
                                'textarea_name' => 'risks_text',
                                'textarea_rows' => 15,
                                'media_buttons' => true,
                                'teeny' => false,
                                'quicktags' => true,
                                'tinymce' => [
                                    'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink,forecolor',
                                    'toolbar2' => '',
                                    'height' => 350,
                                    'content_style' => 'body { background-color: #1a202c; color: #e2e8f0; }',
                                ],
                            ]);
                        }
                        ?>
                    </div>
                </div>
                
                <div class="saw-risks-section">
                    <div class="saw-section-header">
                        <div class="saw-section-icon docs-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17,8 12,3 7,8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                        </div>
                        <h2 class="saw-section-title docs-title"><?php echo esc_html($t['doc_title']); ?></h2>
                    </div>
                    <div class="saw-section-body">
                        <div class="saw-upload-zone">
                            <input type="file" name="risks_documents[]" id="risks_documents" multiple accept=".pdf,.doc,.docx" class="saw-file-input-hidden">
                            <div class="saw-upload-content">
                                <div class="saw-upload-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                        <polyline points="17,8 12,3 7,8"/>
                                        <line x1="12" y1="3" x2="12" y2="15"/>
                                    </svg>
                                </div>
                                <p class="saw-upload-text"><?php echo esc_html($t['drag']); ?></p>
                                <span class="saw-upload-or"><?php echo esc_html($t['or']); ?></span>
                                <button type="button" class="saw-upload-browse-btn" onclick="document.getElementById('risks_documents').click()"><?php echo esc_html($t['browse']); ?></button>
                                <p class="saw-upload-help"><?php echo esc_html($t['doc_help']); ?></p>
                            </div>
                        </div>
                        <div class="saw-file-list" id="file-list">
                            <?php foreach ($existing_files as $file): 
                                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)); 
                                $badge_class = in_array($ext, ['pdf']) ? 'pdf' : 'doc'; 
                            ?>
                            <div class="saw-file-item" data-file-id="<?php echo esc_attr($file['id']); ?>">
                                <span class="saw-file-badge <?php echo $badge_class; ?>"><?php echo strtoupper($ext); ?></span>
                                <div class="saw-file-info">
                                    <span class="saw-file-name"><?php echo esc_html($file['name']); ?></span>
                                    <span class="saw-file-size"><?php echo esc_html(size_format($file['size'])); ?></span>
                                </div>
                                <a href="<?php echo esc_url($file['url']); ?>" target="_blank" class="saw-file-view" title="Zobrazit">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </a>
                                <button type="button" class="saw-file-remove" data-file-id="<?php echo esc_attr($file['id']); ?>">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                    </svg>
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="saw-risks-actions">
                <a href="<?php echo esc_url($return_url); ?>" class="saw-btn-cancel"><?php echo esc_html($t['cancel']); ?></a>
                <button type="submit" class="saw-btn-save">
                    <span><?php echo esc_html($t['save']); ?></span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <polyline points="20,6 9,17 4,12"/>
                    </svg>
                </button>
            </div>
            
            <div id="deleted-inputs"></div>
        </form>
    </div>
</div>

<div class="saw-loading" id="loading">
    <div class="saw-spinner"></div>
</div>

<script>
jQuery(document).ready(function($){
    const form = $('#risks-form');
    const uploadZone = $('.saw-upload-zone');
    const fileInput = $('#risks_documents');
    const fileList = $('#file-list');
    const loading = $('#loading');
    const deletedInputs = $('#deleted-inputs');
    
    let pendingFiles = [];
    
    // Upload zone click
    uploadZone.on('click', function(e) {
        if (!$(e.target).hasClass('saw-upload-browse-btn')) {
            fileInput.click();
        }
    });
    
    fileInput.on('change', function(){ 
        handleFiles(this.files); 
    });
    
    // Drag & drop
    uploadZone.on('dragover', function(e){ 
        e.preventDefault(); 
        e.stopPropagation(); 
        $(this).css('border-color', '#10b981'); 
    });
    uploadZone.on('dragleave', function(e){ 
        e.preventDefault(); 
        e.stopPropagation(); 
        $(this).css('border-color', '#4a5568'); 
    });
    uploadZone.on('drop', function(e){ 
        e.preventDefault(); 
        e.stopPropagation(); 
        $(this).css('border-color', '#4a5568'); 
        handleFiles(e.originalEvent.dataTransfer.files); 
    });
    
    function handleFiles(files){
        Array.from(files).forEach(file => {
            const ext = file.name.split('.').pop().toLowerCase();
            if(!['pdf','doc','docx','xls','xlsx','jpg','jpeg','png'].includes(ext)) {
                alert('Nepodporovaný formát: ' + ext);
                return;
            }
            if(file.size > 10485760) {
                alert('Soubor je příliš velký (max 10MB): ' + file.name);
                return;
            }
            
            pendingFiles.push(file);
            
            const item = $('<div class="saw-file-item new-file">').html(`
                <span class="saw-file-badge new">NOVÝ</span>
                <div class="saw-file-info">
                    <span class="saw-file-name">${$('<div>').text(file.name).html()}</span>
                    <span class="saw-file-size">${(file.size/1024).toFixed(1)} KB</span>
                </div>
                <button type="button" class="saw-file-remove">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            `);
            
            item.find('.saw-file-remove').on('click', function() {
                const idx = pendingFiles.indexOf(file);
                if (idx > -1) pendingFiles.splice(idx, 1);
                item.remove();
            });
            
            fileList.append(item);
        });
    }
    
    // Remove existing files
    $('.saw-file-item .saw-file-remove').on('click', function(){
        const item = $(this).closest('.saw-file-item');
        const fileId = item.data('file-id');
        
        if (fileId) {
            $('<input>').attr({
                type: 'hidden', 
                name: 'delete_files[]', 
                value: fileId
            }).appendTo(deletedInputs);
            
            item.addClass('deleted');
            $(this).prop('disabled', true);
        }
    });
    
    // Form submit
    form.on('submit', function(e){ 
        e.preventDefault();
        
        // Save TinyMCE content
        if(typeof tinyMCE !== 'undefined'){ 
            tinyMCE.triggerSave(); 
        }
        
        loading.addClass('active');
        
        // Create FormData
        const formData = new FormData(this);
        
        // Remove empty file input
        formData.delete('risks_documents[]');
        
        // Add pending files
        pendingFiles.forEach(file => {
            formData.append('risks_documents[]', file);
        });
        
        // Submit
        fetch(form.attr('action') || window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => {
            if (response.redirected) {
                window.location.href = response.url;
            } else {
                window.location.href = '<?php echo esc_url($return_url); ?>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Chyba při ukládání. Zkuste to znovu.');
            loading.removeClass('active');
        });
    });
});
</script>

<?php wp_footer(); ?>
</body>
</html>