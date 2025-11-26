<?php
if (!defined('ABSPATH')) exit;

$flow = $this->session->get('invitation_flow');
$lang = $flow['language'] ?? 'cs';
$existing_text = $existing_text ?? '';
$existing_docs = $existing_docs ?? [];

$existing_files = [];
foreach ($existing_docs as $doc) {
    $existing_files[] = ['id' => $doc['id'], 'name' => $doc['file_name'], 'size' => $doc['file_size']];
}

$translations = [
    'cs' => ['title' => 'Informace o rizicích', 'subtitle' => 'Nahrajte dokumenty a popište rizika', 'optional' => 'Volitelné', 'step' => 'Krok', 'text_title' => 'POPIS RIZIK', 'doc_title' => 'DOKUMENTY', 'doc_help' => 'PDF, DOC, DOCX • Max 10MB', 'drag' => 'Přetáhněte soubory', 'or' => 'nebo', 'browse' => 'Vyberte soubory', 'continue' => 'Pokračovat', 'skip' => 'Přeskočit'],
    'en' => ['title' => 'Risk Information', 'subtitle' => 'Upload documents and describe risks', 'optional' => 'Optional', 'step' => 'Step', 'text_title' => 'RISK DESCRIPTION', 'doc_title' => 'DOCUMENTS', 'doc_help' => 'PDF, DOC, DOCX • Max 10MB', 'drag' => 'Drag files', 'or' => 'or', 'browse' => 'Browse', 'continue' => 'Continue', 'skip' => 'Skip'],
];
$t = $translations[$lang] ?? $translations['cs'];
?>

<style>
.saw-progress-container{display:flex;align-items:center;justify-content:center;gap:1.5rem;margin-bottom:3rem;padding:2rem 0;}
.saw-progress-steps{display:flex;align-items:center;gap:0.75rem;}
.saw-progress-step{display:flex;align-items:center;gap:0.375rem;}
.saw-step-circle{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1rem;flex-shrink:0;}
.saw-progress-step.completed .saw-step-circle{background:#10b981;color:#fff;box-shadow:0 0 0 3px rgba(16,185,129,0.2);}
.saw-progress-step.active .saw-step-circle{background:#fff;color:#667eea;box-shadow:0 0 0 3px rgba(255,255,255,0.3);}
.saw-progress-step.upcoming .saw-step-circle{background:rgba(255,255,255,0.15);color:rgba(255,255,255,0.5);}
.saw-step-line{width:30px;height:3px;flex-shrink:0;}
.saw-progress-step.completed .saw-step-line{background:linear-gradient(90deg,#10b981 0%,rgba(16,185,129,0.3) 100%);}
.saw-progress-step.active .saw-step-line{background:linear-gradient(90deg,rgba(255,255,255,0.5) 0%,rgba(255,255,255,0.1) 100%);}
.saw-progress-step.upcoming .saw-step-line{background:rgba(255,255,255,0.1);}
.saw-progress-step:last-child .saw-step-line{display:none;}
.saw-step-label{color:rgba(255,255,255,0.7);font-size:0.875rem;font-weight:600;}

.saw-risks-card{width:100%;max-width:1100px;margin:0 auto;background:#1a202c;border-radius:20px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);}
.saw-risks-header{display:flex;align-items:center;gap:1rem;padding:1.5rem 2rem;border-bottom:1px solid #2d3748;}
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

.saw-section-body .wp-editor-wrap{border:1px solid #4a5568 !important;border-radius:10px;background:#1a202c !important;}
.saw-section-body .wp-editor-container{border:none !important;background:#1a202c !important;}
.saw-section-body textarea.wp-editor-area{background:#1a202c !important;color:#e2e8f0 !important;border:none !important;padding:12px !important;}
.saw-section-body .wp-media-buttons{padding:10px 12px !important;background:#1e2533 !important;border-bottom:1px solid #4a5568 !important;}
.saw-section-body .wp-media-buttons .button{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%) !important;color:#fff !important;border:none !important;border-radius:8px !important;padding:8px 16px !important;font-weight:600 !important;}
.saw-section-body .mce-toolbar-grp{background:#1e2533 !important;border-bottom:1px solid #4a5568 !important;}
.saw-section-body .mce-btn{background:transparent !important;color:#a0aec0 !important;}
.saw-section-body .mce-btn:hover{background:#4a5568 !important;color:#fff !important;}
.saw-section-body .mce-btn.mce-active{background:#667eea !important;color:#fff !important;}
.saw-section-body .mce-content-body{background:#1a202c !important;color:#e2e8f0 !important;padding:12px !important;}
.saw-section-body .mce-content-body h1,.saw-section-body .mce-content-body h2,.saw-section-body .mce-content-body h3{color:#fff !important;}

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

.saw-file-list{margin-top:0.75rem;display:flex;flex-direction:column;gap:0.5rem;max-height:150px;overflow-y:auto;}
.saw-file-item{display:flex;align-items:center;gap:0.5rem;padding:0.5rem 0.625rem;background:#1a202c;border:1px solid #4a5568;border-radius:8px;}
.saw-file-badge{padding:0.2rem 0.375rem;border-radius:4px;font-size:0.5625rem;font-weight:800;text-transform:uppercase;}
.saw-file-badge.pdf{background:rgba(239,68,68,0.15);color:#f87171;}
.saw-file-badge.doc{background:rgba(59,130,246,0.15);color:#60a5fa;}
.saw-file-info{flex:1;min-width:0;}
.saw-file-name{display:block;font-weight:600;color:#e2e8f0;font-size:0.75rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.saw-file-size{display:block;font-size:0.625rem;color:#718096;}
.saw-file-remove{width:24px;height:24px;border:none;background:rgba(239,68,68,0.1);border-radius:5px;cursor:pointer;display:flex;align-items:center;justify-content:center;}
.saw-file-remove svg{width:12px;height:12px;color:#f87171;}

.saw-risks-actions{display:flex;gap:1rem;justify-content:flex-end;}
.saw-btn-skip{padding:0.875rem 1.25rem;background:transparent;color:#a0aec0;border:2px solid #4a5568;border-radius:14px;font-size:0.9375rem;font-weight:600;cursor:pointer;}
.saw-btn-continue{display:inline-flex;align-items:center;gap:0.625rem;padding:0.875rem 1.5rem;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;border:none;border-radius:14px;font-size:1rem;font-weight:700;cursor:pointer;box-shadow:0 6px 20px rgba(102,126,234,0.4);}
.saw-btn-continue svg{width:18px;height:18px;}

@media (max-width:900px){.saw-risks-columns{grid-template-columns:1fr;}.saw-risks-actions{flex-direction:column-reverse;}.saw-btn-skip,.saw-btn-continue{width:100%;justify-content:center;}.saw-progress-steps{gap:0.5rem;}.saw-progress-step{gap:0.25rem;}.saw-step-circle{width:28px;height:28px;font-size:0.875rem;}.saw-step-line{width:24px;}.saw-step-label{font-size:0.8125rem;}}
</style>

<div class="saw-progress-container">
    <div class="saw-progress-steps">
        <div class="saw-progress-step completed">
            <div class="saw-step-circle"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20,6 9,17 4,12"/></svg></div>
            <div class="saw-step-line"></div>
        </div>
        <div class="saw-progress-step active">
            <div class="saw-step-circle">2</div>
            <div class="saw-step-line"></div>
        </div>
        <div class="saw-progress-step upcoming">
            <div class="saw-step-circle">3</div>
            <div class="saw-step-line"></div>
        </div>
        <div class="saw-progress-step upcoming">
            <div class="saw-step-circle">4</div>
        </div>
    </div>
    <span class="saw-step-label"><?php echo esc_html($t['step']); ?> 2/4</span>
</div>

<div class="saw-risks-card">
    <div class="saw-risks-header">
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
        <span class="saw-risks-badge"><?php echo esc_html($t['optional']); ?></span>
    </div>
    
    <form method="post" enctype="multipart/form-data" class="saw-risks-form">
        <?php wp_nonce_field('saw_invitation_step', 'invitation_nonce'); ?>
        <input type="hidden" name="invitation_action" value="save_risks">
        
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
                    // ✅ WYSIWYG editor with image upload support
                    render_saw_richtext_editor('risks_text', $existing_text, array(
                        'textarea_name' => 'risks_text',
                        'height' => 350,
                        'dark_mode' => true,
                        'toolbar_preset' => 'full', // Full toolbar with colors, headings, etc.
                    ));
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
                        <?php foreach ($existing_files as $file): $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)); $badge_class = in_array($ext, ['pdf']) ? 'pdf' : 'doc'; ?>
                            <div class="saw-file-item" data-file-id="<?php echo esc_attr($file['id']); ?>">
                                <span class="saw-file-badge <?php echo $badge_class; ?>"><?php echo strtoupper($ext); ?></span>
                                <div class="saw-file-info">
                                    <span class="saw-file-name"><?php echo esc_html($file['name']); ?></span>
                                    <span class="saw-file-size"><?php echo esc_html(size_format($file['size'])); ?></span>
                                </div>
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
            <button type="submit" name="action" value="skip" class="saw-btn-skip"><?php echo esc_html($t['skip']); ?></button>
            <button type="submit" name="action" value="save" class="saw-btn-continue">
                <span><?php echo esc_html($t['continue']); ?></span>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12,5 19,12 12,19"/>
                </svg>
            </button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($){
    const uploadZone = $('.saw-upload-zone');
    const fileInput = $('#risks_documents');
    const fileList = $('#file-list');
    
    uploadZone.on('click', () => fileInput.click());
    fileInput.on('change', function(){ handleFiles(this.files); });
    
    uploadZone.on('dragover', function(e){ e.preventDefault(); e.stopPropagation(); $(this).css('border-color', '#10b981'); });
    uploadZone.on('dragleave', function(e){ e.preventDefault(); e.stopPropagation(); $(this).css('border-color', '#4a5568'); });
    uploadZone.on('drop', function(e){ e.preventDefault(); e.stopPropagation(); $(this).css('border-color', '#4a5568'); handleFiles(e.originalEvent.dataTransfer.files); });
    
    function handleFiles(files){
        Array.from(files).forEach(file => {
            const ext = file.name.split('.').pop().toLowerCase();
            if(!['pdf','doc','docx'].includes(ext)) return;
            if(file.size > 10485760) return;
            
            const badge_class = ext === 'pdf' ? 'pdf' : 'doc';
            const item = $('<div class="saw-file-item">').html(`
                <span class="saw-file-badge ${badge_class}">${ext.toUpperCase()}</span>
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
            item.find('.saw-file-remove').on('click', () => item.remove());
            fileList.append(item);
        });
    }
    
    $('.saw-file-item .saw-file-remove').on('click', function(){
        const item = $(this).closest('.saw-file-item');
        $('<input>').attr({type:'hidden', name:'delete_files[]', value:item.data('file-id')}).appendTo('form');
        item.remove();
    });
    
    $('form').on('submit', function(){ 
        // Save TinyMCE content to textarea before submit
        if(typeof tinyMCE !== 'undefined'){ 
            tinyMCE.triggerSave(); 
        } 
    });
});
</script>