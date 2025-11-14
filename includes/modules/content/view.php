<?php
/**
 * Content Module View
 *
 * @package SAW_Visitors
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get file icon CSS class based on extension
 */
function saw_get_file_icon($filename) {
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if ($extension === 'pdf') {
        return '<span class="saw-file-icon saw-icon-pdf"></span>';
    } elseif (in_array($extension, array('doc', 'docx', 'odt', 'pages', 'rtf'))) {
        return '<span class="saw-file-icon saw-icon-word"></span>';
    } elseif (in_array($extension, array('xls', 'xlsx', 'ods', 'numbers'))) {
        return '<span class="saw-file-icon saw-icon-excel"></span>';
    } elseif (in_array($extension, array('ppt', 'pptx', 'odp', 'key'))) {
        return '<span class="saw-file-icon saw-icon-powerpoint"></span>';
    } elseif ($extension === 'txt') {
        return '<span class="saw-file-icon saw-icon-text"></span>';
    }
    
    return '<span class="saw-file-icon saw-icon-document"></span>';
}
?>

<div class="saw-content-page">
    
    <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
    <div class="saw-success-notification">
        Obsah byl úspěšně uložen
    </div>
    <?php endif; ?>
    
    <div class="saw-page-header">
        <h1 class="saw-page-title">
            <span class="saw-page-icon"><?php echo esc_html($icon); ?></span>
            Správa obsahu školení
        </h1>
    </div>
    
    <div class="saw-content-tabs">
        <?php foreach ($languages as $index => $language): ?>
            <button 
                type="button" 
                class="saw-tab-btn <?php echo $index === 0 ? 'active' : ''; ?>" 
                data-tab="lang-<?php echo esc_attr($language['id']); ?>"
            >
                <?php echo !empty($language['flag_emoji']) ? $language['flag_emoji'] : '🌐'; ?> 
                <?php echo esc_html($language['name']); ?> 
                (<?php echo esc_html(strtoupper($language['code'])); ?>)
            </button>
        <?php endforeach; ?>
    </div>
    
    <div class="saw-content-main">
        
        <?php 
        $current_user_role = $this->get_current_role();
        // Manager vidí POUZE sekci oddělení
        // Super_manager vidí VŠE
        // Admin vidí VŠE
        // Super_admin vidí VŠE
        $show_main_sections = !($current_user_role === 'manager');
        ?>
        
        <?php foreach ($languages as $index => $language): ?>
        <div 
            class="saw-tab-content" 
            data-tab-content="lang-<?php echo esc_attr($language['id']); ?>"
            style="<?php echo $index === 0 ? '' : 'display: none;'; ?>"
        >
            
            <?php
            // Load saved content for this specific language
            $lang_content = $model->get_content($customer_id, $branch_id, $language['id']);
            ?>
            
            <form method="post" action="" enctype="multipart/form-data" class="saw-content-form">
                <?php wp_nonce_field('saw_content_save_action', 'saw_content_nonce'); ?>
                <input type="hidden" name="language_id" value="<?php echo esc_attr($language['id']); ?>">
                
                <?php if ($show_main_sections): ?>
                
                <!-- Sekce 1: Video -->
                <div class="saw-collapsible-section">
                    <button type="button" class="saw-section-header">
                        <span class="saw-section-icon">▶</span>
                        <span class="saw-section-title">📹 Video (YouTube / Vimeo)</span>
                    </button>
                    <div class="saw-section-content">
                        <div class="saw-form-field">
                            <label class="saw-label">URL adresa videa</label>
                            <input 
                                type="url" 
                                name="video_url" 
                                class="saw-input"
                                placeholder="https://www.youtube.com/watch?v=..."
                                value="<?php echo esc_attr($lang_content['video_url'] ?? ''); ?>"
                            >
                            <span class="saw-hint">Vložte odkaz na YouTube nebo Vimeo video</span>
                        </div>
                    </div>
                </div>
                
                <!-- Sekce 2: PDF Mapa -->
                <div class="saw-collapsible-section">
                    <button type="button" class="saw-section-header">
                        <span class="saw-section-icon">▶</span>
                        <span class="saw-section-title">🗺️ PDF Mapa</span>
                    </button>
                    <div class="saw-section-content">
                        
                        <?php if (!empty($lang_content['pdf_map_path'])): ?>
                        <div class="saw-existing-documents">
                            <h4 class="saw-docs-title">📎 Nahraná mapa:</h4>
                            <div class="saw-existing-doc">
                                <span class="saw-doc-badge">PDF Mapa</span>
                                <span class="saw-doc-name"><?php echo saw_get_file_icon($lang_content['pdf_map_path']); ?> <?php echo esc_html(basename($lang_content['pdf_map_path'])); ?></span>
                                <a href="<?php echo esc_url(wp_upload_dir()['baseurl'] . $lang_content['pdf_map_path']); ?>" 
                                   target="_blank" 
                                   class="saw-doc-view">👁️ Zobrazit</a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="saw-form-field">
                            <label class="saw-label">Nahrát PDF mapu</label>
                            <input 
                                type="file" 
                                name="pdf_map" 
                                accept="application/pdf"
                                class="saw-file-input"
                            >
                            <span class="saw-hint">Nahrajte PDF soubor s mapou objektu</span>
                        </div>
                    </div>
                </div>
                
                <!-- Sekce 3: Informace o rizicích -->
                <div class="saw-collapsible-section">
                    <button type="button" class="saw-section-header">
                        <span class="saw-section-icon">▶</span>
                        <span class="saw-section-title">⚠️ Informace o rizicích</span>
                    </button>
                    <div class="saw-section-content">
                        <div class="saw-form-field">
                            <label class="saw-label">Textový obsah</label>
                            <?php
                            wp_editor($lang_content['risks_text'] ?? '', 'risks_text_' . $language['id'], array(
                                'textarea_name' => 'risks_text',
                                'textarea_rows' => 36,
                                'media_buttons' => true,
                                'teeny' => false,
                                'tinymce' => array(
                                    'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                                    'toolbar2' => 'undo,redo,removeformat,code,hr,blockquote,subscript,superscript,charmap,indent,outdent,pastetext,searchreplace,fullscreen',
                                    'block_formats' => 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote',
                                ),
                            ));
                            ?>
                        </div>
                        
                        <div class="saw-form-field">
                            <label class="saw-label">Dokumenty o rizicích</label>
                            
                            <?php
                            // Load existing documents
                            if (isset($lang_content['id'])) {
                                $risks_docs = $model->get_documents('risks', $lang_content['id']);
                                if (!empty($risks_docs)) {
                                    echo '<div class="saw-existing-documents">';
                                    echo '<h4 class="saw-docs-title">📎 Nahrané dokumenty:</h4>';
                                    foreach ($risks_docs as $doc) {
                                        $doc_type_name = '';
                                        foreach ($document_types as $dt) {
                                            if ($dt['id'] == $doc['document_type_id']) {
                                                $doc_type_name = $dt['name'];
                                                break;
                                            }
                                        }
                                        echo '<div class="saw-existing-doc">';
                                        echo '<span class="saw-doc-badge">' . esc_html($doc_type_name) . '</span>';
                                        echo '<span class="saw-doc-name">' . saw_get_file_icon($doc['file_name']) . ' ' . esc_html($doc['file_name']) . '</span>';
                                        echo '<span class="saw-doc-size">' . size_format($doc['file_size']) . '</span>';
                                        echo '<button type="button" class="saw-doc-delete" data-doc-id="' . $doc['id'] . '" onclick="if(confirm(\'Opravdu smazat tento dokument?\')) { this.closest(\'.saw-existing-doc\').style.display=\'none\'; var input = document.createElement(\'input\'); input.type=\'hidden\'; input.name=\'delete_document[]\'; input.value=\'' . $doc['id'] . '\'; this.closest(\'form\').appendChild(input); }">🗑️</button>';
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }
                            }
                            ?>
                            
                            <div class="saw-documents-list" id="risks-docs-list-<?php echo esc_attr($language['id']); ?>">
                                <div class="saw-document-item">
                                    <div class="saw-doc-type-select">
                                        <select name="risks_doc_type[]" class="saw-select">
                                            <option value="">-- Vyberte typ dokumentu --</option>
                                            <?php foreach ($document_types as $doc_type): ?>
                                                <option value="<?php echo esc_attr($doc_type['id']); ?>">
                                                    <?php echo esc_html($doc_type['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <input 
                                        type="file" 
                                        name="risks_documents[]" 
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.pages,.numbers,.key,.txt,.rtf"
                                        class="saw-file-input"
                                        data-requires-type="true"
                                    >
                                </div>
                            </div>
                            
                            <button 
                                type="button" 
                                class="saw-btn-secondary saw-add-document"
                                data-target="risks-docs-list-<?php echo esc_attr($language['id']); ?>"
                                data-doc-type="risks"
                            >
                                ➕ Přidat další dokument
                            </button>
                            
                            <span class="saw-hint">PDF, DOC nebo DOCX</span>
                        </div>
                    </div>
                </div>
                
                <!-- Sekce 4: Další informace -->
                <div class="saw-collapsible-section">
                    <button type="button" class="saw-section-header">
                        <span class="saw-section-icon">▶</span>
                        <span class="saw-section-title">📄 Další informace</span>
                    </button>
                    <div class="saw-section-content">
                        <div class="saw-form-field">
                            <label class="saw-label">Textový obsah</label>
                            <?php
                            wp_editor($lang_content['additional_text'] ?? '', 'additional_text_' . $language['id'], array(
                                'textarea_name' => 'additional_text',
                                'textarea_rows' => 36,
                                'media_buttons' => true,
                                'teeny' => false,
                                'tinymce' => array(
                                    'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                                    'toolbar2' => 'undo,redo,removeformat,code,hr,blockquote,subscript,superscript,charmap,indent,outdent,pastetext,searchreplace,fullscreen',
                                    'block_formats' => 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote',
                                ),
                            ));
                            ?>
                        </div>
                        
                        <div class="saw-form-field">
                            <label class="saw-label">Dokumenty</label>
                            
                            <?php
                            // Load existing documents
                            if (isset($lang_content['id'])) {
                                $additional_docs = $model->get_documents('additional', $lang_content['id']);
                                if (!empty($additional_docs)) {
                                    echo '<div class="saw-existing-documents">';
                                    echo '<h4 class="saw-docs-title">📎 Nahrané dokumenty:</h4>';
                                    foreach ($additional_docs as $doc) {
                                        $doc_type_name = '';
                                        foreach ($document_types as $dt) {
                                            if ($dt['id'] == $doc['document_type_id']) {
                                                $doc_type_name = $dt['name'];
                                                break;
                                            }
                                        }
                                        echo '<div class="saw-existing-doc">';
                                        echo '<span class="saw-doc-badge">' . esc_html($doc_type_name) . '</span>';
                                        echo '<span class="saw-doc-name">' . saw_get_file_icon($doc['file_name']) . ' ' . esc_html($doc['file_name']) . '</span>';
                                        echo '<span class="saw-doc-size">' . size_format($doc['file_size']) . '</span>';
                                        echo '<button type="button" class="saw-doc-delete" data-doc-id="' . $doc['id'] . '" onclick="if(confirm(\'Opravdu smazat tento dokument?\')) { this.closest(\'.saw-existing-doc\').style.display=\'none\'; var input = document.createElement(\'input\'); input.type=\'hidden\'; input.name=\'delete_document[]\'; input.value=\'' . $doc['id'] . '\'; this.closest(\'form\').appendChild(input); }">🗑️</button>';
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }
                            }
                            ?>
                            
                            <div class="saw-documents-list" id="additional-docs-list-<?php echo esc_attr($language['id']); ?>">
                                <div class="saw-document-item">
                                    <div class="saw-doc-type-select">
                                        <select name="additional_doc_type[]" class="saw-select">
                                            <option value="">-- Vyberte typ dokumentu --</option>
                                            <?php foreach ($document_types as $doc_type): ?>
                                                <option value="<?php echo esc_attr($doc_type['id']); ?>">
                                                    <?php echo esc_html($doc_type['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <input 
                                        type="file" 
                                        name="additional_documents[]" 
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.pages,.numbers,.key,.txt,.rtf"
                                        class="saw-file-input"
                                        data-requires-type="true"
                                    >
                                </div>
                            </div>
                            
                            <button 
                                type="button" 
                                class="saw-btn-secondary saw-add-document"
                                data-target="additional-docs-list-<?php echo esc_attr($language['id']); ?>"
                                data-doc-type="additional"
                            >
                                ➕ Přidat další dokument
                            </button>
                            
                            <span class="saw-hint">PDF, DOC nebo DOCX</span>
                        </div>
                    </div>
                </div>
                
                <?php endif; ?>
                
                <!-- Sekce 5: Specifické informace pracovišť (pro všechny kromě terminalu) -->
                <?php if ($current_user_role !== 'terminal'): ?>
                
                    <?php
                    // Načti oddělení podle role
                    global $wpdb;
                    $branch_id = $branch_id; // Already loaded in controller
                    
                    if ($current_user_role === 'manager') {
                        // Manager vidí POUZE svá přiřazená oddělení
                        $current_user_id = get_current_user_id();
                        
                        // Najdi SAW user ID
                        $saw_user = $wpdb->get_row($wpdb->prepare(
                            "SELECT id FROM %i WHERE wp_user_id = %d",
                            $wpdb->prefix . 'saw_users',
                            $current_user_id
                        ));
                        
                        if ($saw_user) {
                            $departments = $wpdb->get_results($wpdb->prepare(
                                "SELECT d.id, d.name, d.department_number 
                                 FROM %i ud
                                 INNER JOIN %i d ON ud.department_id = d.id
                                 WHERE ud.user_id = %d AND d.is_active = 1
                                 ORDER BY d.department_number ASC, d.name ASC",
                                $wpdb->prefix . 'saw_user_departments',
                                $wpdb->prefix . 'saw_departments',
                                $saw_user->id
                            ), ARRAY_A);
                        } else {
                            $departments = array();
                        }
                    } else {
                        // Super_manager, Admin, Super_admin vidí všechna oddělení pobočky
                        $departments = $wpdb->get_results($wpdb->prepare(
                            "SELECT id, name, department_number FROM %i WHERE branch_id = %d AND is_active = 1 ORDER BY department_number ASC, name ASC",
                            $wpdb->prefix . 'saw_departments',
                            $branch_id
                        ), ARRAY_A);
                    }
                    ?>
                    
                    <?php if (!empty($departments)): ?>
                    <div class="saw-collapsible-section saw-section-departments">
                        <button type="button" class="saw-section-header">
                            <span class="saw-section-icon">▶</span>
                            <span class="saw-section-title">🏢 Specifické informace pracovišť</span>
                            <span class="saw-section-badge"><?php echo count($departments); ?> oddělení</span>
                        </button>
                        <div class="saw-section-content">
                            
                            <div class="saw-departments-intro">
                                <p>Každé oddělení může mít své specifické instrukce a dokumenty pro školení návštěvníků.</p>
                            </div>
                            
                            <?php foreach ($departments as $dept): ?>
                            <div class="saw-department-subsection">
                                <button type="button" class="saw-department-header">
                                    <span class="saw-dept-icon">▶</span>
                                    <span class="saw-dept-name">
                                        <?php if (!empty($dept['department_number'])): ?>
                                            <span class="saw-dept-number"><?php echo esc_html($dept['department_number']); ?></span>
                                        <?php endif; ?>
                                        <?php echo esc_html($dept['name']); ?>
                                    </span>
                                </button>
                                <div class="saw-department-content">
                                    
                                    <div class="saw-form-field">
                                        <label class="saw-label">Textové informace pro oddělení</label>
                                        <?php
                                        $dept_text = isset($lang_content['id']) ? $model->get_department_content($lang_content['id'], $dept['id']) : '';
                                        wp_editor($dept_text, 'dept_text_' . $dept['id'] . '_' . $language['id'], array(
                                            'textarea_name' => 'department_text[' . $dept['id'] . ']',
                                            'textarea_rows' => 30,
                                            'media_buttons' => true,
                                            'teeny' => false,
                                            'tinymce' => array(
                                                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                                                'toolbar2' => 'undo,redo,removeformat,code,hr,blockquote,subscript,superscript,charmap,indent,outdent,pastetext,fullscreen',
                                                'block_formats' => 'Odstavec=p;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote',
                                            ),
                                        ));
                                        ?>
                                    </div>
                                    
                                    <div class="saw-form-field">
                                        <label class="saw-label">Dokumenty pro oddělení</label>
                                        
                                        <?php
                                        // Load existing department documents
                                        if (isset($lang_content['id'])) {
                                            $dept_content_id = $model->get_department_content_id($lang_content['id'], $dept['id']);
                                            if ($dept_content_id) {
                                                $dept_docs = $model->get_documents('department', $dept_content_id);
                                                if (!empty($dept_docs)) {
                                                    echo '<div class="saw-existing-documents">';
                                                    echo '<h4 class="saw-docs-title">📎 Nahrané dokumenty:</h4>';
                                                    foreach ($dept_docs as $doc) {
                                                        $doc_type_name = '';
                                                        foreach ($document_types as $dt) {
                                                            if ($dt['id'] == $doc['document_type_id']) {
                                                                $doc_type_name = $dt['name'];
                                                                break;
                                                            }
                                                        }
                                                        echo '<div class="saw-existing-doc">';
                                                        echo '<span class="saw-doc-badge">' . esc_html($doc_type_name) . '</span>';
                                                        echo '<span class="saw-doc-name">' . saw_get_file_icon($doc['file_name']) . ' ' . esc_html($doc['file_name']) . '</span>';
                                                        echo '<span class="saw-doc-size">' . size_format($doc['file_size']) . '</span>';
                                                        echo '<button type="button" class="saw-doc-delete" data-doc-id="' . $doc['id'] . '" onclick="if(confirm(\'Opravdu smazat tento dokument?\')) { this.closest(\'.saw-existing-doc\').style.display=\'none\'; var input = document.createElement(\'input\'); input.type=\'hidden\'; input.name=\'delete_document[]\'; input.value=\'' . $doc['id'] . '\'; this.closest(\'form\').appendChild(input); }">🗑️</button>';
                                                        echo '</div>';
                                                    }
                                                    echo '</div>';
                                                }
                                            }
                                        }
                                        ?>
                                        
                                        <div class="saw-documents-list" id="dept-docs-list-<?php echo esc_attr($dept['id']); ?>-<?php echo esc_attr($language['id']); ?>">
                                            <div class="saw-document-item">
                                                <div class="saw-doc-type-select">
                                                    <select name="department_doc_type[<?php echo esc_attr($dept['id']); ?>][]" class="saw-select">
                                                        <option value="">-- Vyberte typ dokumentu --</option>
                                                        <?php foreach ($document_types as $doc_type): ?>
                                                            <option value="<?php echo esc_attr($doc_type['id']); ?>">
                                                                <?php echo esc_html($doc_type['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <input 
                                                    type="file" 
                                                    name="department_documents[<?php echo esc_attr($dept['id']); ?>][]" 
                                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.pages,.numbers,.key,.txt,.rtf"
                                                    class="saw-file-input"
                                                    data-requires-type="true"
                                                >
                                            </div>
                                        </div>
                                        
                                        <button 
                                            type="button" 
                                            class="saw-btn-secondary saw-add-document"
                                            data-target="dept-docs-list-<?php echo esc_attr($dept['id']); ?>-<?php echo esc_attr($language['id']); ?>"
                                            data-dept-id="<?php echo esc_attr($dept['id']); ?>"
                                            data-doc-type="department"
                                        >
                                            ➕ Přidat další dokument
                                        </button>
                                        
                                        <span class="saw-hint">Dokumenty specifické pro toto oddělení</span>
                                    </div>
                                    
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                        </div>
                    </div>
                    <?php endif; ?>
                
                <?php endif; ?>
                
                <div class="saw-form-actions">
                    <button type="submit" class="saw-btn-primary">
                        💾 Uložit obsah
                    </button>
                </div>
                
            </form>
            
        </div>
        <?php endforeach; ?>
        
    </div>
    
</div>

<?php
// CRITICAL: Load WordPress media templates for media library
wp_print_media_templates();
?>

<script>
// Document types for JS
var sawDocumentTypes = <?php echo json_encode($document_types); ?>;
</script>
