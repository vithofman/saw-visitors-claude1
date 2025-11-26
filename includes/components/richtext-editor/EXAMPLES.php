<?php
/**
 * Rich Text Editor - Example Usage
 * 
 * Příklady použití richtext-editor komponenty v různých contextech.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 */

// ============================================
// PŘÍKLAD 1: Basic Usage (Invitation Module)
// ============================================

class SAW_Invitation_Controller {
    
    public function render() {
        // 1. Include komponenty
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/richtext-editor/richtext-editor.php';
        
        // 2. Initialize hooks (CRITICAL!)
        saw_richtext_editor_init();
        saw_richtext_editor_enqueue_assets();
        
        // 3. Render layout
        $this->render_header();
        $this->render_risks_upload();
        $this->render_footer();
    }
    
    private function render_risks_upload() {
        global $wpdb;
        
        // Get existing content
        $existing_text = $wpdb->get_var($wpdb->prepare(
            "SELECT text_content FROM {$wpdb->prefix}saw_visit_invitation_materials 
             WHERE visit_id = %d AND material_type = 'text'",
            $this->visit_id
        ));
        
        // Include template
        require SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/invitation/steps/2-risks-upload.php';
    }
}

// V template (2-risks-upload.php):
?>
<div class="saw-section-body">
    <?php
    render_saw_richtext_editor('risks_text', $existing_text, array(
        'textarea_name' => 'risks_text',
        'height' => 350,
        'dark_mode' => true,
        'toolbar_preset' => 'basic',
    ));
    ?>
</div>


<?php
// ============================================
// PŘÍKLAD 2: Content Module (Full Editor)
// ============================================

class SAW_Content_Controller {
    
    public function view() {
        // 1. Include komponenty
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/richtext-editor/richtext-editor.php';
        
        // 2. Initialize hooks
        saw_richtext_editor_init();
        saw_richtext_editor_enqueue_assets();
        
        // 3. Render
        $this->render_view();
    }
}

// V template (view.php):
?>
<div class="saw-content-section">
    <h3>Informace o rizicích</h3>
    <?php
    render_saw_richtext_editor(
        'risks_text_' . $language['id'],
        $lang_content['risks_text'] ?? '',
        array(
            'textarea_name' => 'risks_text',
            'height' => 420,
            'dark_mode' => false,  // Light mode pro admin
            'toolbar_preset' => 'full',  // Kompletní toolbar
        )
    );
    ?>
</div>


<?php
// ============================================
// PŘÍKLAD 3: Custom Toolbar
// ============================================
?>
<div class="custom-editor">
    <?php
    render_saw_richtext_editor('custom_field', $content, array(
        'height' => 300,
        'dark_mode' => true,
        'tinymce' => array(
            'toolbar1' => 'bold,italic,underline,bullist,numlist,link',
            'toolbar2' => '',
            'block_formats' => 'Odstavec=p;Nadpis 2=h2;Nadpis 3=h3',
        ),
    ));
    ?>
</div>


<?php
// ============================================
// PŘÍKLAD 4: Minimal Editor (Notes)
// ============================================
?>
<div class="notes-section">
    <label>Poznámky</label>
    <?php
    render_saw_richtext_editor('admin_notes', $visit->admin_notes, array(
        'textarea_name' => 'admin_notes',
        'height' => 200,
        'dark_mode' => false,
        'toolbar_preset' => 'minimal',
    ));
    ?>
</div>


<?php
// ============================================
// PŘÍKLAD 5: Multiple Editors on Same Page
// ============================================

// V controlleru - jeden init pro všechny editory
saw_richtext_editor_init();
saw_richtext_editor_enqueue_assets();
?>

<!-- Editor 1 -->
<div class="editor-1">
    <?php
    render_saw_richtext_editor('intro_text', $intro, array(
        'height' => 200,
        'dark_mode' => true,
        'toolbar_preset' => 'basic',
    ));
    ?>
</div>

<!-- Editor 2 -->
<div class="editor-2">
    <?php
    render_saw_richtext_editor('main_text', $main, array(
        'height' => 400,
        'dark_mode' => true,
        'toolbar_preset' => 'full',
    ));
    ?>
</div>

<!-- Editor 3 -->
<div class="editor-3">
    <?php
    render_saw_richtext_editor('footer_text', $footer, array(
        'height' => 150,
        'dark_mode' => true,
        'toolbar_preset' => 'minimal',
    ));
    ?>
</div>


<?php
// ============================================
// PŘÍKLAD 6: Form Handling
// ============================================

class My_Controller {
    
    private function handle_save() {
        // Get editor content from POST
        $content = wp_kses_post($_POST['risks_text'] ?? '');
        
        // Save to database
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'saw_content',
            array('text_content' => $content),
            array('id' => $content_id)
        );
        
        // Redirect
        wp_redirect($success_url);
        exit;
    }
}
?>


<?php
// ============================================
// PŘÍKLAD 7: Conditional Rendering
// ============================================

// Show editor only if user has permission
if (current_user_can('edit_training_content')) {
    render_saw_richtext_editor('editable_content', $content, array(
        'height' => 350,
        'dark_mode' => true,
    ));
} else {
    // Show read-only content
    echo '<div class="readonly-content">' . wp_kses_post($content) . '</div>';
}
?>


<?php
// ============================================
// COMMON MISTAKES (CHYBY K VYHNUTÍ SE)
// ============================================

// ❌ ŠPATNĚ: Init po render_header
class Bad_Controller {
    public function render() {
        $this->render_header();  // Již načetlo wp_head()
        saw_richtext_editor_init();  // POZDĚ! Hooks se nenačtou
    }
}

// ✅ SPRÁVNĚ: Init před render_header
class Good_Controller {
    public function render() {
        saw_richtext_editor_init();  // VČAS! Před wp_head()
        saw_richtext_editor_enqueue_assets();
        $this->render_header();
    }
}


// ❌ ŠPATNĚ: Zapomenuté include
function my_template() {
    render_saw_richtext_editor('field', $content);  // Fatal error!
}

// ✅ SPRÁVNĚ: Include v controlleru
class Good_Controller {
    public function render() {
        require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/richtext-editor/richtext-editor.php';
        saw_richtext_editor_init();
        // ...
    }
}


// ❌ ŠPATNĚ: Duplicitní editor_id
render_saw_richtext_editor('text', $content1);
render_saw_richtext_editor('text', $content2);  // Konflikt!

// ✅ SPRÁVNĚ: Unikátní ID
render_saw_richtext_editor('text_1', $content1);
render_saw_richtext_editor('text_2', $content2);


// ============================================
// DEBUG CHECKLIST
// ============================================
/*

1. Media buttons se nezobrazují:
   [ ] saw_richtext_editor_init() voláno PŘED render_header()?
   [ ] saw_richtext_editor_enqueue_assets() voláno?
   [ ] Console (F12) - JavaScript errory?
   [ ] wp_enqueue_media() a wp_enqueue_editor() načteny?

2. Dark mode nefunguje:
   [ ] 'dark_mode' => true v args?
   [ ] richtext-editor.css načtené? (Network tab F12)
   [ ] richtext-editor.js načtené?
   [ ] Console errory?

3. TinyMCE se neinicializuje:
   [ ] wp_enqueue_editor() voláno?
   [ ] jQuery načtené?
   [ ] Conflicts s jinými skripty?

4. Content se neuloží:
   [ ] 'textarea_name' správně nastavené?
   [ ] Form má method="post"?
   [ ] wp_kses_post() použito při save?

*/
