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
?>

<div class="saw-content-page">
    
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
        $is_manager = ($current_user_role === 'manager');
        ?>
        
        <?php foreach ($languages as $index => $language): ?>
        <div 
            class="saw-tab-content" 
            data-tab-content="lang-<?php echo esc_attr($language['id']); ?>"
            style="<?php echo $index === 0 ? '' : 'display: none;'; ?>"
        >
            
            <form method="post" action="" enctype="multipart/form-data" class="saw-content-form">
                <?php wp_nonce_field('saw_content_save'); ?>
                <input type="hidden" name="language_id" value="<?php echo esc_attr($language['id']); ?>">
                
                <?php if (!$is_manager): ?>
                
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
                            wp_editor('', 'risks_text_' . $language['id'], array(
                                'textarea_name' => 'risks_text',
                                'textarea_rows' => 12,
                                'media_buttons' => true,
                                'teeny' => false,
                                'tinymce' => array(
                                    'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,image',
                                    'toolbar2' => 'undo,redo,removeformat,code,hr,pastetext,searchreplace,fullscreen',
                                    'block_formats' => 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4',
                                ),
                            ));
                            ?>
                        </div>
                        
                        <div class="saw-form-field">
                            <label class="saw-label">Dokumenty o rizicích</label>
                            
                            <div class="saw-documents-list" id="risks-docs-list-<?php echo esc_attr($language['id']); ?>">
                                <div class="saw-document-item">
                                    <input 
                                        type="file" 
                                        name="risks_documents[]" 
                                        accept="application/pdf,.doc,.docx"
                                        class="saw-file-input"
                                    >
                                </div>
                            </div>
                            
                            <button 
                                type="button" 
                                class="saw-btn-secondary saw-add-document"
                                data-target="risks-docs-list-<?php echo esc_attr($language['id']); ?>"
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
                            wp_editor('', 'additional_text_' . $language['id'], array(
                                'textarea_name' => 'additional_text',
                                'textarea_rows' => 12,
                                'media_buttons' => true,
                                'teeny' => false,
                                'tinymce' => array(
                                    'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,image',
                                    'toolbar2' => 'undo,redo,removeformat,code,hr,pastetext,searchreplace,fullscreen',
                                    'block_formats' => 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4',
                                ),
                            ));
                            ?>
                        </div>
                        
                        <div class="saw-form-field">
                            <label class="saw-label">Dokumenty</label>
                            
                            <div class="saw-documents-list" id="additional-docs-list-<?php echo esc_attr($language['id']); ?>">
                                <div class="saw-document-item">
                                    <input 
                                        type="file" 
                                        name="additional_documents[]" 
                                        accept="application/pdf,.doc,.docx"
                                        class="saw-file-input"
                                    >
                                </div>
                            </div>
                            
                            <button 
                                type="button" 
                                class="saw-btn-secondary saw-add-document"
                                data-target="additional-docs-list-<?php echo esc_attr($language['id']); ?>"
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
                    $branch_id = SAW_Context::get_branch_id();
                    
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
                        // Admin/Super admin vidí všechna oddělení pobočky
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
                                        wp_editor('', 'dept_text_' . $dept['id'] . '_' . $language['id'], array(
                                            'textarea_name' => 'department_text[' . $dept['id'] . ']',
                                            'textarea_rows' => 10,
                                            'media_buttons' => true,
                                            'teeny' => false,
                                            'tinymce' => array(
                                                'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,link,unlink',
                                            ),
                                        ));
                                        ?>
                                    </div>
                                    
                                    <div class="saw-form-field">
                                        <label class="saw-label">Dokumenty pro oddělení</label>
                                        
                                        <div class="saw-documents-list" id="dept-docs-list-<?php echo esc_attr($dept['id']); ?>-<?php echo esc_attr($language['id']); ?>">
                                            <div class="saw-document-item">
                                                <input 
                                                    type="file" 
                                                    name="department_documents[<?php echo esc_attr($dept['id']); ?>][]" 
                                                    accept="application/pdf,.doc,.docx"
                                                    class="saw-file-input"
                                                >
                                            </div>
                                        </div>
                                        
                                        <button 
                                            type="button" 
                                            class="saw-btn-secondary saw-add-document"
                                            data-target="dept-docs-list-<?php echo esc_attr($dept['id']); ?>-<?php echo esc_attr($language['id']); ?>"
                                            data-dept-id="<?php echo esc_attr($dept['id']); ?>"
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
