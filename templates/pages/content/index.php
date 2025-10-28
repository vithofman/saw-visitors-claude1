<?php
/**
 * Template: Správa školícího obsahu
 * 
 * @package SAW_Visitors
 * @version 4.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="saw-page-header">
    <h1>📚 Správa školícího obsahu</h1>
    <p class="saw-page-subtitle">Správa videí, dokumentů a textových informací pro školení návštěvníků</p>
</div>

<?php if (isset($_GET['saved'])): ?>
    <div class="saw-alert saw-alert-success">
        <strong>✅ Úspěch!</strong> Obsah byl úspěšně uložen.
    </div>
<?php endif; ?>

<?php if (isset($_GET['deleted'])): ?>
    <div class="saw-alert saw-alert-success">
        <strong>✅ Smazáno!</strong> Položka byla úspěšně odstraněna.
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="saw-alert saw-alert-danger">
        <strong>❌ Chyba!</strong> <?php echo esc_html($_GET['error']); ?>
    </div>
<?php endif; ?>

<div class="saw-language-tabs">
    <?php foreach (SAW_Controller_Content::LANGUAGES as $lang_code => $lang_name): ?>
        <button type="button" 
                class="saw-language-tab <?php echo $lang_code === 'cs' ? 'active' : ''; ?>" 
                data-lang="<?php echo esc_attr($lang_code); ?>">
            <?php echo esc_html($lang_name); ?>
        </button>
    <?php endforeach; ?>
</div>

<form method="post" enctype="multipart/form-data" id="saw-content-form" class="saw-content-form">
    <?php wp_nonce_field('saw_save_content'); ?>
    
    <?php foreach (SAW_Controller_Content::LANGUAGES as $lang_code => $lang_name): ?>
        <div class="saw-language-content" 
             data-lang="<?php echo esc_attr($lang_code); ?>" 
             style="<?php echo $lang_code !== 'cs' ? 'display: none;' : ''; ?>">
            
            <div class="saw-card">
                <div class="saw-card-header">
                    <h2>🎬 Hlavní instruktážní video</h2>
                </div>
                <div class="saw-card-body">
                    <p class="saw-help-text">Zadejte URL adresu videa z YouTube nebo Vimeo</p>
                    
                    <?php 
                    $video = $controller->get_material($materials, 'video', $lang_code);
                    $video_url = $video && $video->file_url ? $video->file_url : '';
                    ?>
                    
                    <div class="saw-form-group">
                        <label for="video_url_<?php echo esc_attr($lang_code); ?>" class="saw-form-label">
                            URL adresa videa
                        </label>
                        <input type="url" 
                               id="video_url_<?php echo esc_attr($lang_code); ?>"
                               name="video_url_<?php echo esc_attr($lang_code); ?>" 
                               class="saw-form-input"
                               placeholder="https://www.youtube.com/watch?v=... nebo https://vimeo.com/..."
                               value="<?php echo esc_attr($video_url); ?>">
                    </div>
                    
                    <?php if ($video_url): ?>
                        <div class="saw-video-preview">
                            <div class="saw-preview-header">
                                <span class="saw-badge saw-badge-success">✅ Aktivní video</span>
                                <a href="<?php echo esc_url($video_url); ?>" target="_blank" class="saw-link">
                                    Otevřít video →
                                </a>
                                <a href="<?php echo esc_url(wp_nonce_url('/admin/settings/content?delete_material=' . $video->id, 'delete_material_' . $video->id)); ?>" 
                                   class="saw-button saw-button-small saw-button-danger"
                                   onclick="return confirm('Opravdu smazat toto video?');">
                                    🗑️ Odstranit
                                </a>
                            </div>
                            
                            <?php 
                            $embed_html = $controller->get_video_embed($video_url);
                            if ($embed_html): ?>
                                <div class="saw-video-embed">
                                    <?php echo $embed_html; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="saw-card">
                <div class="saw-card-header">
                    <h2>📋 Schematický plán areálu / objektů</h2>
                </div>
                <div class="saw-card-body">
                    <p class="saw-help-text">Nahrajte PDF soubor se schematickým plánem (jeden soubor)</p>
                    
                    <?php 
                    $pdf = $controller->get_material($materials, 'pdf', $lang_code);
                    ?>
                    
                    <?php if ($pdf && $pdf->file_url): ?>
                        <div class="saw-file-preview">
                            <div class="saw-file-info">
                                <span class="saw-file-icon">📄</span>
                                <div class="saw-file-details">
                                    <strong><?php echo esc_html($pdf->filename); ?></strong>
                                    <span class="saw-file-meta">
                                        <?php echo $controller->get_file_size($pdf->file_url); ?>
                                    </span>
                                </div>
                                <div class="saw-file-actions">
                                    <a href="<?php echo esc_url($pdf->file_url); ?>" target="_blank" class="saw-button saw-button-small">
                                        👁️ Zobrazit
                                    </a>
                                    <a href="<?php echo esc_url(wp_nonce_url('/admin/settings/content?delete_material=' . $pdf->id, 'delete_material_' . $pdf->id)); ?>" 
                                       class="saw-button saw-button-small saw-button-danger"
                                       onclick="return confirm('Opravdu smazat tento soubor?');">
                                        🗑️ Smazat
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="saw-form-group">
                        <label for="pdf_file_<?php echo esc_attr($lang_code); ?>" class="saw-form-label">
                            Nahrát nový PDF soubor
                        </label>
                        <input type="file" 
                               id="pdf_file_<?php echo esc_attr($lang_code); ?>"
                               name="pdf_file_<?php echo esc_attr($lang_code); ?>" 
                               class="saw-form-input-file"
                               accept=".pdf">
                    </div>
                </div>
            </div>
            
            <div class="saw-card">
                <div class="saw-card-header">
                    <h2>⚠️ Informace o rizicích a o přijatých opatřeních</h2>
                </div>
                <div class="saw-card-body">
                    <p class="saw-help-text">
                        Zde zadejte písemně informace o rizicích a o přijatých opatřeních, 
                        dle odst. 3, § 101, zákona č. 262/2006 Sb., Zákoníku práce v účinném znění.
                    </p>
                    
                    <?php 
                    $risks_wysiwyg = $controller->get_material($materials, 'risks_wysiwyg', $lang_code);
                    $risks_content = $risks_wysiwyg && $risks_wysiwyg->wysiwyg_content ? $risks_wysiwyg->wysiwyg_content : '';
                    ?>
                    
                    <div class="saw-form-group">
                        <label class="saw-form-label">Textové informace</label>
                        <?php
                        wp_editor($risks_content, 'risks_wysiwyg_' . $lang_code, array(
                            'textarea_name' => 'risks_wysiwyg_' . $lang_code,
                            'textarea_rows' => 15,
                            'media_buttons' => true,
                            'teeny' => false,
                            'tinymce' => array(
                                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,alignleft,aligncenter,alignright,|,link,unlink,image,|,forecolor,backcolor,|,removeformat,code,fullscreen',
                                'toolbar2' => 'undo,redo,|,table,|,pastetext,|,charmap,hr,|,outdent,indent',
                                'block_formats' => 'Odstavec=p;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Předformátovaný=pre',
                            ),
                            'quicktags' => array(
                                'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close'
                            ),
                        ));
                        ?>
                    </div>
                    
                    <?php 
                    $risks_docs = $controller->get_documents_by_category($documents, 'risks', $lang_code);
                    if (!empty($risks_docs)): ?>
                        <div class="saw-documents-list">
                            <h4>Nahrané dokumenty:</h4>
                            <?php foreach ($risks_docs as $doc): ?>
                                <div class="saw-document-item">
                                    <span class="saw-doc-icon">📎</span>
                                    <span class="saw-doc-name"><?php echo esc_html($doc->filename); ?></span>
                                    <span class="saw-doc-size"><?php echo $controller->get_file_size($doc->file_url); ?></span>
                                    <a href="<?php echo esc_url($doc->file_url); ?>" target="_blank" class="saw-button saw-button-small">Zobrazit</a>
                                    <a href="<?php echo esc_url(wp_nonce_url('/admin/settings/content?delete_doc=' . $doc->id, 'delete_doc_' . $doc->id)); ?>" 
                                       class="saw-button saw-button-small saw-button-danger"
                                       onclick="return confirm('Opravdu smazat?');">Smazat</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="saw-form-group">
                        <label class="saw-form-label">Nahrát další dokumenty (PDF, DOC, DOCX)</label>
                        <div class="saw-files-wrapper" id="risks_docs_wrapper_<?php echo esc_attr($lang_code); ?>">
                            <div class="saw-file-upload-row">
                                <input type="file" 
                                       name="risks_docs_<?php echo esc_attr($lang_code); ?>[]" 
                                       class="saw-form-input-file"
                                       accept=".pdf,.doc,.docx">
                                <select name="risks_docs_category_<?php echo esc_attr($lang_code); ?>[]" class="saw-form-select">
                                    <option value="risks">Rizika</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" 
                                class="saw-button saw-button-secondary saw-add-file-btn" 
                                data-target="risks_docs_wrapper_<?php echo esc_attr($lang_code); ?>"
                                data-name-prefix="risks_docs_<?php echo esc_attr($lang_code); ?>">
                            ➕ Přidat další soubor
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="saw-card">
                <div class="saw-card-header">
                    <h2>📚 Další povinné školení / osnovy</h2>
                </div>
                <div class="saw-card-body">
                    <p class="saw-help-text">
                        Zde zadejte další povinné školení (např. požární ochrana, první pomoc, BOZP)
                    </p>
                    
                    <?php 
                    $training_wysiwyg = $controller->get_material($materials, 'training_wysiwyg', $lang_code);
                    $training_content = $training_wysiwyg && $training_wysiwyg->wysiwyg_content ? $training_wysiwyg->wysiwyg_content : '';
                    ?>
                    
                    <div class="saw-form-group">
                        <label class="saw-form-label">Textové informace</label>
                        <?php
                        wp_editor($training_content, 'training_wysiwyg_' . $lang_code, array(
                            'textarea_name' => 'training_wysiwyg_' . $lang_code,
                            'textarea_rows' => 15,
                            'media_buttons' => true,
                            'teeny' => false,
                            'tinymce' => array(
                                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,alignleft,aligncenter,alignright,|,link,unlink,image,|,forecolor,backcolor,|,removeformat,code,fullscreen',
                                'toolbar2' => 'undo,redo,|,table,|,pastetext,|,charmap,hr,|,outdent,indent',
                                'block_formats' => 'Odstavec=p;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Předformátovaný=pre',
                            ),
                            'quicktags' => array(
                                'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close'
                            ),
                        ));
                        ?>
                    </div>
                    
                    <?php 
                    $training_docs = $controller->get_documents_by_category($documents, 'training', $lang_code);
                    if (!empty($training_docs)): ?>
                        <div class="saw-documents-list">
                            <h4>Nahrané dokumenty:</h4>
                            <?php foreach ($training_docs as $doc): ?>
                                <div class="saw-document-item">
                                    <span class="saw-doc-icon">📎</span>
                                    <span class="saw-doc-name"><?php echo esc_html($doc->filename); ?></span>
                                    <span class="saw-doc-size"><?php echo $controller->get_file_size($doc->file_url); ?></span>
                                    <a href="<?php echo esc_url($doc->file_url); ?>" target="_blank" class="saw-button saw-button-small">Zobrazit</a>
                                    <a href="<?php echo esc_url(wp_nonce_url('/admin/settings/content?delete_doc=' . $doc->id, 'delete_doc_' . $doc->id)); ?>" 
                                       class="saw-button saw-button-small saw-button-danger"
                                       onclick="return confirm('Opravdu smazat?');">Smazat</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="saw-form-group">
                        <label class="saw-form-label">Nahrát další dokumenty (PDF, DOC, DOCX)</label>
                        <div class="saw-files-wrapper" id="training_docs_wrapper_<?php echo esc_attr($lang_code); ?>">
                            <div class="saw-file-upload-row">
                                <input type="file" 
                                       name="training_docs_<?php echo esc_attr($lang_code); ?>[]" 
                                       class="saw-form-input-file"
                                       accept=".pdf,.doc,.docx">
                                <select name="training_docs_category_<?php echo esc_attr($lang_code); ?>[]" class="saw-form-select">
                                    <option value="training">Školení</option>
                                </select>
                            </div>
                        </div>
                        <button type="button" 
                                class="saw-button saw-button-secondary saw-add-file-btn" 
                                data-target="training_docs_wrapper_<?php echo esc_attr($lang_code); ?>"
                                data-name-prefix="training_docs_<?php echo esc_attr($lang_code); ?>">
                            ➕ Přidat další soubor
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="saw-card">
                <div class="saw-card-header">
                    <h2>🏢 Specifické informace dle oddělení</h2>
                </div>
                <div class="saw-card-body">
                    <p class="saw-help-text">
                        Přidejte specifické informace pro jednotlivá oddělení, pokud je to potřeba.
                    </p>
                    
                    <?php if (!empty($departments)): ?>
                        <?php foreach ($departments as $dept): ?>
                            <div class="saw-department-section">
                                <h4>📍 <?php echo esc_html($dept->name); ?></h4>
                                
                                <?php 
                                $dept_material = null;
                                if (isset($dept_materials[$dept->id])) {
                                    foreach ($dept_materials[$dept->id] as $mat) {
                                        if ($mat->language === $lang_code) {
                                            $dept_material = $mat;
                                            break;
                                        }
                                    }
                                }
                                $dept_content = $dept_material && $dept_material->wysiwyg_content ? $dept_material->wysiwyg_content : '';
                                ?>
                                
                                <div class="saw-form-group">
                                    <label class="saw-form-label">Specifické informace</label>
                                    <?php
                                    wp_editor($dept_content, 'dept_wysiwyg_' . $dept->id . '_' . $lang_code, array(
                                        'textarea_name' => 'dept_wysiwyg_' . $dept->id . '_' . $lang_code,
                                        'textarea_rows' => 10,
                                        'media_buttons' => true,
                                        'teeny' => false,
                                        'tinymce' => array(
                                            'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,alignleft,aligncenter,alignright,|,link,unlink,image,|,forecolor,backcolor,|,removeformat,code,fullscreen',
                                            'toolbar2' => 'undo,redo,|,table,|,pastetext,|,charmap,hr,|,outdent,indent',
                                            'block_formats' => 'Odstavec=p;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Předformátovaný=pre',
                                        ),
                                        'quicktags' => array(
                                            'buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,close'
                                        ),
                                    ));
                                    ?>
                                </div>
                                
                                <?php 
                                $dept_docs = $controller->get_documents_by_category($documents, 'department_' . $dept->id, $lang_code);
                                if (!empty($dept_docs)): ?>
                                    <div class="saw-documents-list">
                                        <h4>Nahrané dokumenty:</h4>
                                        <?php foreach ($dept_docs as $doc): ?>
                                            <div class="saw-document-item">
                                                <span class="saw-doc-icon">📎</span>
                                                <span class="saw-doc-name"><?php echo esc_html($doc->filename); ?></span>
                                                <span class="saw-doc-size"><?php echo $controller->get_file_size($doc->file_url); ?></span>
                                                <a href="<?php echo esc_url($doc->file_url); ?>" target="_blank" class="saw-button saw-button-small">Zobrazit</a>
                                                <a href="<?php echo esc_url(wp_nonce_url('/admin/settings/content?delete_doc=' . $doc->id, 'delete_doc_' . $doc->id)); ?>" 
                                                   class="saw-button saw-button-small saw-button-danger"
                                                   onclick="return confirm('Opravdu smazat?');">Smazat</a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="saw-form-group">
                                    <label class="saw-form-label">Nahrát další dokumenty (PDF, DOC, DOCX)</label>
                                    <div class="saw-files-wrapper" id="dept_<?php echo $dept->id; ?>_docs_wrapper_<?php echo esc_attr($lang_code); ?>">
                                        <div class="saw-file-upload-row">
                                            <input type="file" 
                                                   name="dept_<?php echo $dept->id; ?>_docs_<?php echo esc_attr($lang_code); ?>[]" 
                                                   class="saw-form-input-file"
                                                   accept=".pdf,.doc,.docx">
                                            <select name="dept_<?php echo $dept->id; ?>_docs_category_<?php echo esc_attr($lang_code); ?>[]" class="saw-form-select">
                                                <option value="department_<?php echo $dept->id; ?>">Oddělení: <?php echo esc_html($dept->name); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="button" 
                                            class="saw-button saw-button-secondary saw-add-file-btn" 
                                            data-target="dept_<?php echo $dept->id; ?>_docs_wrapper_<?php echo esc_attr($lang_code); ?>"
                                            data-name-prefix="dept_<?php echo $dept->id; ?>_docs_<?php echo esc_attr($lang_code); ?>">
                                        ➕ Přidat další soubor
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="saw-help-text">
                            Nejsou definována žádná oddělení. 
                            <a href="<?php echo esc_url(home_url('/admin/settings/departments/')); ?>">Vytvořit oddělení</a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div class="saw-form-actions">
        <button type="submit" name="save_content" class="saw-button saw-button-primary saw-button-large">
            💾 Uložit veškerý obsah
        </button>
    </div>
</form>

<style>
.saw-language-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 2px solid #e5e7eb;
}

.saw-language-tab {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    cursor: pointer;
    font-weight: 500;
    color: #6b7280;
    transition: all 0.2s;
}

.saw-language-tab:hover {
    color: #111827;
}

.saw-language-tab.active {
    color: #1e40af;
    border-bottom-color: #1e40af;
}

.saw-card {
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 24px;
    overflow: hidden;
}

.saw-card-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e5e7eb;
    background: #f9fafb;
}

.saw-card-header h2 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #111827;
}

.saw-card-body {
    padding: 24px;
}

.saw-form-group {
    margin-bottom: 20px;
}

.saw-form-label {
    display: block;
    font-weight: 500;
    color: #374151;
    margin-bottom: 8px;
}

.saw-form-input,
.saw-form-select,
.saw-form-input-file {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.saw-help-text {
    display: block;
    font-size: 13px;
    color: #6b7280;
    margin-top: 6px;
}

.saw-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}

.saw-badge-success {
    background: #d1fae5;
    color: #065f46;
}

.saw-button {
    display: inline-block;
    padding: 8px 16px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    background: white;
    color: #374151;
    text-decoration: none;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}

.saw-button:hover {
    background: #f9fafb;
}

.saw-button-primary {
    background: #1e40af;
    color: white;
    border-color: #1e40af;
}

.saw-button-primary:hover {
    background: #1e3a8a;
}

.saw-button-secondary {
    background: #f3f4f6;
    border-color: #d1d5db;
}

.saw-button-danger {
    background: #fee2e2;
    color: #991b1b;
    border-color: #fecaca;
}

.saw-button-danger:hover {
    background: #fecaca;
}

.saw-button-small {
    padding: 6px 12px;
    font-size: 13px;
}

.saw-link {
    color: #1e40af;
    text-decoration: none;
    font-weight: 500;
}

.saw-link:hover {
    text-decoration: underline;
}

.saw-files-wrapper {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 12px;
}

.saw-file-upload-row {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 12px;
    align-items: center;
}

.saw-add-file-btn {
    margin-top: 8px;
}

.saw-video-preview {
    margin-top: 16px;
    padding: 16px;
    background: #f9fafb;
    border-radius: 8px;
}

.saw-preview-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.saw-video-embed {
    position: relative;
    padding-bottom: 56.25%;
    height: 0;
    overflow: hidden;
}

.saw-video-embed iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border-radius: 8px;
}

.saw-file-preview {
    margin-bottom: 16px;
}

.saw-file-info {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.saw-file-icon {
    font-size: 24px;
}

.saw-file-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.saw-file-meta {
    font-size: 12px;
    color: #6b7280;
}

.saw-file-actions {
    display: flex;
    gap: 8px;
}

.saw-documents-list {
    margin: 16px 0;
}

.saw-documents-list h4 {
    font-size: 14px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 12px;
}

.saw-document-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    margin-bottom: 8px;
}

.saw-doc-icon {
    font-size: 20px;
}

.saw-doc-name {
    flex: 1;
    font-size: 14px;
    color: #111827;
}

.saw-doc-size {
    font-size: 12px;
    color: #6b7280;
}

.saw-department-section {
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 1px solid #e5e7eb;
}

.saw-department-section:last-child {
    border-bottom: none;
}

.saw-department-section h4 {
    font-size: 16px;
    font-weight: 600;
    color: #111827;
    margin-bottom: 12px;
}

.saw-form-actions {
    display: flex;
    gap: 12px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 2px solid #e5e7eb;
}

.saw-button-large {
    padding: 14px 32px;
    font-size: 16px;
}
</style>

<script>
(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        const tabs = document.querySelectorAll('.saw-language-tab');
        const contents = document.querySelectorAll('.saw-language-content');
        
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                const lang = this.dataset.lang;
                
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                contents.forEach(function(content) {
                    if (content.dataset.lang === lang) {
                        content.style.display = 'block';
                    } else {
                        content.style.display = 'none';
                    }
                });
            });
        });
        
        document.querySelectorAll('.saw-add-file-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const namePrefix = this.dataset.namePrefix;
                const wrapper = document.getElementById(targetId);
                
                if (wrapper) {
                    const newRow = document.createElement('div');
                    newRow.className = 'saw-file-upload-row';
                    newRow.innerHTML = wrapper.querySelector('.saw-file-upload-row').innerHTML;
                    
                    const fileInput = newRow.querySelector('input[type="file"]');
                    const selectInput = newRow.querySelector('select');
                    fileInput.name = namePrefix + '[]';
                    selectInput.name = namePrefix.replace('_docs_', '_docs_category_') + '[]';
                    
                    wrapper.appendChild(newRow);
                }
            });
        });
    });
})();
</script>