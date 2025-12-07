<?php
/**
 * Content Module View
 *
 * @package SAW_Visitors
 * @version 3.0.0 - ADDED: Multi-language translation support
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
    ? saw_get_translations($lang, 'admin', 'content') 
    : array();

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};

// ============================================
// LOAD FILE UPLOAD COMPONENT
// ============================================
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/file-upload/file-upload-input.php';

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
        <?php echo esc_html($tr('msg_saved', 'Obsah byl úspěšně uložen')); ?>
    </div>
    <?php endif; ?>
    
    <div class="saw-page-header">
        <h1 class="saw-page-title">
            <span class="saw-page-icon"><?php echo esc_html($icon); ?></span>
            <?php echo esc_html($tr('page_title', 'Správa obsahu školení')); ?>
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
                <?php wp_nonce_field('saw_content_action', 'saw_content_nonce'); ?>
                <input type="hidden" name="language_id" value="<?php echo esc_attr($language['id']); ?>">
                
                <?php if ($show_main_sections): ?>
                
                <!-- ============================================
                     SECTION 1: Video
                     ============================================ -->
                <div class="saw-collapsible-section">
                    <button type="button" class="saw-section-header">
                        <span class="saw-section-icon">▶</span>
                        <span class="saw-section-title">📹 <?php echo esc_html($tr('section_video', 'Video (YouTube / Vimeo)')); ?></span>
                    </button>
                    <div class="saw-section-content">
                        <div class="saw-form-field">
                            <label class="saw-label"><?php echo esc_html($tr('label_video_url', 'URL adresa videa')); ?></label>
                            <input 
                                type="url" 
                                name="video_url" 
                                class="saw-input"
                                placeholder="https://www.youtube.com/watch?v=..."
                                value="<?php echo esc_attr($lang_content['video_url'] ?? ''); ?>"
                            >
                            <span class="saw-hint"><?php echo esc_html($tr('hint_video_url', 'Vložte odkaz na YouTube nebo Vimeo video')); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- ============================================
                     SECTION 2: PDF Map
                     ============================================ -->
                <div class="saw-collapsible-section">
                    <button type="button" class="saw-section-header">
                        <span class="saw-section-icon">▶</span>
                        <span class="saw-section-title">🗺️ <?php echo esc_html($tr('section_pdf_map', 'PDF Mapa')); ?></span>
                    </button>
                    <div class="saw-section-content">
                        <div class="saw-form-field">
                            <label class="saw-label"><?php echo esc_html($tr('label_upload_pdf', 'Nahrát PDF mapu')); ?></label>
                            <?php
                            // Prepare existing PDF map file
                            $existing_pdf_map = array();
                            if (!empty($lang_content['pdf_map_path'])) {
                                $upload_dir = wp_upload_dir();
                                $existing_pdf_map = array(
                                    array(
                                        'id' => 'pdf_map_' . $language['id'],
                                        'url' => $upload_dir['baseurl'] . $lang_content['pdf_map_path'],
                                        'path' => $lang_content['pdf_map_path'],
                                        'name' => basename($lang_content['pdf_map_path']),
                                        'size' => file_exists($upload_dir['basedir'] . $lang_content['pdf_map_path']) ? filesize($upload_dir['basedir'] . $lang_content['pdf_map_path']) : 0,
                                        'type' => 'application/pdf',
                                        'extension' => 'pdf',
                                    )
                                );
                            }
                            
                            saw_file_upload_input(array(
                                'name' => 'pdf_map',
                                'id' => 'pdf-map-input-' . $language['id'],
                                'multiple' => false,
                                'accept' => 'application/pdf,.pdf',
                                'max_size' => 10485760, // 10MB
                                'max_files' => 1, // Only one PDF map allowed
                                'context' => 'content_pdf_map',
                                'existing_files' => $existing_pdf_map,
                            ));
                            ?>
                            <span class="saw-hint"><?php echo esc_html($tr('hint_pdf_map', 'Nahrajte PDF soubor s mapou objektu')); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- ============================================
                     SECTION 3: Risk Information
                     ============================================ -->
                <div class="saw-collapsible-section">
                    <button type="button" class="saw-section-header">
                        <span class="saw-section-icon">▶</span>
                        <span class="saw-section-title">⚠️ <?php echo esc_html($tr('section_risks', 'Informace o rizicích')); ?></span>
                    </button>
                    <div class="saw-section-content">
                        <div class="saw-form-field">
                            <label class="saw-label"><?php echo esc_html($tr('label_text_content', 'Textový obsah')); ?></label>
                            <?php
                            // CRITICAL: Ensure media buttons are shown by checking user capabilities first
                            $editor_id = 'risks_text_' . $language['id'];
                            $editor_settings = array(
                                'textarea_name' => 'risks_text',
                                'textarea_rows' => 36,
                                'media_buttons' => true, // CRITICAL: Must be true
                                'teeny' => false,
                                'tinymce' => array(
                                    'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                                    'toolbar2' => 'undo,redo,removeformat,code,hr,blockquote,subscript,superscript,charmap,indent,outdent,pastetext,searchreplace,fullscreen',
                                    'block_formats' => $tr('editor_formats', 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote'),
                                ),
                            );
                            
                            // Render editor
                            wp_editor($lang_content['risks_text'] ?? '', $editor_id, $editor_settings);
                            
                            // CRITICAL: Manually ensure media buttons are rendered if WordPress didn't add them
                            $editor_wrap_id = 'wp-' . $editor_id . '-wrap';
                            $add_media_text = esc_js($tr('btn_add_media', 'Přidat média'));
                            ?>
                            <script>
                            (function() {
                                // Ensure media buttons are visible after editor is rendered
                                var editorWrap = document.getElementById('<?php echo esc_js($editor_wrap_id); ?>');
                                if (editorWrap) {
                                    var mediaButtons = editorWrap.querySelector('.wp-media-buttons');
                                    if (!mediaButtons) {
                                        // Media buttons missing - add them manually
                                        var mediaButtonsDiv = document.createElement('div');
                                        mediaButtonsDiv.className = 'wp-media-buttons';
                                        mediaButtonsDiv.innerHTML = '<button type="button" class="button insert-media add_media" data-editor="<?php echo esc_js($editor_id); ?>"><span class="wp-media-buttons-icon"></span> <?php echo $add_media_text; ?></button>';
                                        var editorContainer = editorWrap.querySelector('.wp-editor-container');
                                        if (editorContainer) {
                                            editorWrap.insertBefore(mediaButtonsDiv, editorContainer);
                                        }
                                    }
                                }
                            })();
                            </script>
                        </div>
                        
                        <div class="saw-form-field">
                            <label class="saw-label"><?php echo esc_html($tr('label_risks_documents', 'Dokumenty o rizicích')); ?></label>
                            
                            <?php
                            // Load existing documents
                            $existing_risks_docs = array();
                            if (isset($lang_content['id'])) {
                                $risks_docs = $model->get_documents('risks', $lang_content['id']);
                                if (!empty($risks_docs)) {
                                    $upload_dir = wp_upload_dir();
                                    foreach ($risks_docs as $doc) {
                                        $doc_type_name = '';
                                        foreach ($document_types as $dt) {
                                            if ($dt['id'] == $doc['document_type_id']) {
                                                $doc_type_name = $dt['name'];
                                                break;
                                            }
                                        }
                                        
                                        $existing_risks_docs[] = array(
                                            'id' => $doc['id'],
                                            'url' => $upload_dir['baseurl'] . $doc['file_path'],
                                            'path' => $doc['file_path'],
                                            'name' => $doc['file_name'],
                                            'size' => $doc['file_size'],
                                            'type' => $doc['mime_type'] ?? 'application/octet-stream',
                                            'extension' => strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION)),
                                            'category' => $doc['document_type_id'],
                                            'category_name' => $doc_type_name,
                                        );
                                    }
                                }
                            }
                            ?>
                            
                            <div class="saw-documents-list" id="risks-docs-list-<?php echo esc_attr($language['id']); ?>">
                                <div class="saw-document-item">
                                    <?php
                                    saw_file_upload_input(array(
                                        'name' => 'risks_documents[]',
                                        'id' => 'risks-doc-input-' . $language['id'] . '-0',
                                        'multiple' => true,
                                        'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.pages,.numbers,.key,.txt,.rtf',
                                        'max_size' => 10485760, // 10MB
                                        'context' => 'content_documents',
                                        'class' => 'saw-document-upload',
                                        'existing_files' => $existing_risks_docs,
                                        'category_config' => array(
                                            'enabled' => true,
                                            'source' => 'config',
                                            'required' => false,
                                            'label' => $tr('label_document_type', 'Typ dokumentu'),
                                            'multiple' => false, // Single select
                                            'options' => $document_types, // Pass directly
                                        ),
                                    ));
                                    ?>
                                    <button type="button" class="saw-remove-document" style="display: none;">🗑️</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ============================================
                     SECTION 4: Additional Information
                     ============================================ -->
                <div class="saw-collapsible-section">
                    <button type="button" class="saw-section-header">
                        <span class="saw-section-icon">▶</span>
                        <span class="saw-section-title">📄 <?php echo esc_html($tr('section_additional', 'Další informace')); ?></span>
                    </button>
                    <div class="saw-section-content">
                        <div class="saw-form-field">
                            <label class="saw-label"><?php echo esc_html($tr('label_text_content', 'Textový obsah')); ?></label>
                            <?php
                            wp_editor($lang_content['additional_text'] ?? '', 'additional_text_' . $language['id'], array(
                                'textarea_name' => 'additional_text',
                                'textarea_rows' => 36,
                                'media_buttons' => true,
                                'teeny' => false,
                                'tinymce' => array(
                                    'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                                    'toolbar2' => 'undo,redo,removeformat,code,hr,blockquote,subscript,superscript,charmap,indent,outdent,pastetext,searchreplace,fullscreen',
                                    'block_formats' => $tr('editor_formats', 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote'),
                                ),
                            ));
                            ?>
                        </div>
                        
                        <div class="saw-form-field">
                            <label class="saw-label"><?php echo esc_html($tr('label_documents', 'Dokumenty')); ?></label>
                            
                            <?php
                            // Load existing documents
                            $existing_additional_docs = array();
                            if (isset($lang_content['id'])) {
                                $additional_docs = $model->get_documents('additional', $lang_content['id']);
                                if (!empty($additional_docs)) {
                                    $upload_dir = wp_upload_dir();
                                    foreach ($additional_docs as $doc) {
                                        $doc_type_name = '';
                                        foreach ($document_types as $dt) {
                                            if ($dt['id'] == $doc['document_type_id']) {
                                                $doc_type_name = $dt['name'];
                                                break;
                                            }
                                        }
                                        
                                        $existing_additional_docs[] = array(
                                            'id' => $doc['id'],
                                            'url' => $upload_dir['baseurl'] . $doc['file_path'],
                                            'path' => $doc['file_path'],
                                            'name' => $doc['file_name'],
                                            'size' => $doc['file_size'],
                                            'type' => $doc['mime_type'] ?? 'application/octet-stream',
                                            'extension' => strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION)),
                                            'category' => $doc['document_type_id'],
                                            'category_name' => $doc_type_name,
                                        );
                                    }
                                }
                            }
                            ?>
                            
                            <div class="saw-documents-list" id="additional-docs-list-<?php echo esc_attr($language['id']); ?>">
                                <div class="saw-document-item">
                                    <?php
                                    saw_file_upload_input(array(
                                        'name' => 'additional_documents[]',
                                        'id' => 'additional-doc-input-' . $language['id'] . '-0',
                                        'multiple' => true,
                                        'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.pages,.numbers,.key,.txt,.rtf',
                                        'max_size' => 10485760, // 10MB
                                        'context' => 'content_documents',
                                        'class' => 'saw-document-upload',
                                        'existing_files' => $existing_additional_docs,
                                        'category_config' => array(
                                            'enabled' => true,
                                            'source' => 'config',
                                            'required' => true,
                                            'label' => $tr('label_document_type', 'Typ dokumentu'),
                                            'multiple' => false, // Single select
                                            'options' => $document_types,
                                        ),
                                    ));
                                    ?>
                                    <button type="button" class="saw-remove-document" style="display: none;">🗑️</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php endif; ?>
                
                <!-- ============================================
                     SECTION 5: Department-Specific Information
                     ============================================ -->
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
                            <span class="saw-section-title">🏢 <?php echo esc_html($tr('section_departments', 'Specifické informace pracovišť')); ?></span>
                            <span class="saw-section-badge"><?php echo count($departments); ?> <?php echo esc_html($tr('badge_departments', 'oddělení')); ?></span>
                        </button>
                        <div class="saw-section-content">
                            
                            <div class="saw-departments-intro">
                                <p><?php echo esc_html($tr('departments_intro', 'Každé oddělení může mít své specifické instrukce a dokumenty pro školení návštěvníků.')); ?></p>
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
                                        <label class="saw-label"><?php echo esc_html($tr('label_dept_text', 'Textové informace pro oddělení')); ?></label>
                                        <?php
                                        $dept_text = isset($lang_content['id']) ? $model->get_department_content($lang_content['id'], $dept['id']) : '';
                                        $editor_id = 'dept_text_' . $dept['id'] . '_' . $language['id'];
                                        $editor_settings = array(
                                            'textarea_name' => 'department_text[' . $dept['id'] . ']',
                                            'textarea_rows' => 30,
                                            'media_buttons' => true,
                                            'teeny' => false,
                                            'tinymce' => array(
                                                'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                                                'toolbar2' => 'undo,redo,removeformat,code,hr,blockquote,subscript,superscript,charmap,indent,outdent,pastetext,fullscreen',
                                                'block_formats' => $tr('editor_formats', 'Odstavec=p;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote'),
                                            ),
                                        );
                                        wp_editor($dept_text, $editor_id, $editor_settings);
                                        $editor_wrap_id = 'wp-' . $editor_id . '-wrap';
                                        $add_media_text = esc_js($tr('btn_add_media', 'Přidat média'));
                                        ?>
                                        <script>
                                        (function() {
                                            var editorWrap = document.getElementById('<?php echo esc_js($editor_wrap_id); ?>');
                                            if (editorWrap) {
                                                var mediaButtons = editorWrap.querySelector('.wp-media-buttons');
                                                if (!mediaButtons) {
                                                    var mediaButtonsDiv = document.createElement('div');
                                                    mediaButtonsDiv.className = 'wp-media-buttons';
                                                    mediaButtonsDiv.innerHTML = '<button type="button" class="button insert-media add_media" data-editor="<?php echo esc_js($editor_id); ?>"><span class="wp-media-buttons-icon"></span> <?php echo $add_media_text; ?></button>';
                                                    var editorContainer = editorWrap.querySelector('.wp-editor-container');
                                                    if (editorContainer) {
                                                        editorWrap.insertBefore(mediaButtonsDiv, editorContainer);
                                                    }
                                                }
                                            }
                                        })();
                                        </script>
                                    </div>
                                    
                                    <div class="saw-form-field">
                                        <label class="saw-label"><?php echo esc_html($tr('label_dept_documents', 'Dokumenty pro oddělení')); ?></label>
                                        
                                <?php
                                // Prepare existing department documents
                                $existing_dept_docs = array();
                                if (isset($lang_content['id'])) {
                                    $dept_content_id = $model->get_department_content_id($lang_content['id'], $dept['id']);
                                    if ($dept_content_id) {
                                        $dept_docs = $model->get_documents('department', $dept_content_id);
                                        if (!empty($dept_docs)) {
                                            $upload_dir = wp_upload_dir();
                                            foreach ($dept_docs as $doc) {
                                                $doc_type_name = '';
                                                foreach ($document_types as $dt) {
                                                    if ($dt['id'] == $doc['document_type_id']) {
                                                        $doc_type_name = $dt['name'];
                                                        break;
                                                    }
                                                }
                                                
                                                $file_path = $doc['file_path'] ?? '';
                                                $existing_dept_docs[] = array(
                                                    'id' => $doc['id'],
                                                    'url' => !empty($file_path) ? $upload_dir['baseurl'] . $file_path : '',
                                                    'path' => $file_path,
                                                    'name' => $doc['file_name'],
                                                    'size' => $doc['file_size'],
                                                    'type' => $doc['file_type'] ?? 'application/octet-stream',
                                                    'extension' => strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION)),
                                                    'category' => $doc['document_type_id'],
                                                    'category_name' => $doc_type_name,
                                                );
                                            }
                                        }
                                    }
                                }
                                ?>
                                
                                <div class="saw-documents-list" id="dept-docs-list-<?php echo esc_attr($dept['id']); ?>-<?php echo esc_attr($language['id']); ?>">
                                    <div class="saw-document-item">
                                        <?php
                                        saw_file_upload_input(array(
                                            'name' => 'department_documents[' . $dept['id'] . '][]',
                                            'id' => 'dept-doc-input-' . $dept['id'] . '-' . $language['id'] . '-0',
                                            'multiple' => true,
                                            'accept' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.odp,.pages,.numbers,.key,.txt,.rtf',
                                            'max_size' => 10485760, // 10MB
                                            'context' => 'content_documents',
                                            'class' => 'saw-document-upload',
                                            'existing_files' => $existing_dept_docs,
                                            'category_config' => array(
                                                'enabled' => true,
                                                'source' => 'config',
                                                'required' => true,
                                                'label' => $tr('label_document_type', 'Typ dokumentu'),
                                                'multiple' => false, // Single select
                                                'options' => $document_types,
                                            ),
                                        ));
                                        ?>
                                        <button type="button" class="saw-remove-document" style="display: none;">🗑️</button>
                                    </div>
                                </div>
                                        
                                    </div>
                                    
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                        </div>
                    </div>
                    <?php endif; ?>
                
                <?php endif; ?>
                
                <!-- ============================================
                     FORM ACTIONS
                     ============================================ -->
                <div class="saw-form-actions">
                    <button type="submit" class="saw-btn-primary">
                        💾 <?php echo esc_html($tr('btn_save', 'Uložit obsah')); ?>
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