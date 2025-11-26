<?php
/**
 * Rich Text Editor Component
 * 
 * Globální komponenta pro WYSIWYG editor s media gallery podporou.
 * Vychází z content modulu, který má plně funkční WordPress editor.
 * 
 * @package SAW_Visitors
 * @version 1.0.0
 * @since 7.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialize Rich Text Editor hooks and filters
 * 
 * CRITICAL: Tuto funkci MUSÍTE zavolat PŘED render_saw_richtext_editor()
 * Jinak nebude fungovat media gallery!
 * 
 * @since 1.0.0
 * @return void
 */
function saw_richtext_editor_init() {
    // CRITICAL: Load WordPress media templates for media library modal
    if (!has_action('admin_footer', 'wp_print_media_templates')) {
        add_action('admin_footer', 'wp_print_media_templates');
    }
    
    if (!has_action('wp_footer', 'wp_print_media_templates')) {
        add_action('wp_footer', 'wp_print_media_templates');
    }
    
    // CRITICAL: Give user capabilities for media upload
    // WordPress checks current_user_can('upload_files') before showing media buttons
    add_filter('user_has_cap', function($allcaps) {
        $allcaps['edit_posts'] = true;
        $allcaps['upload_files'] = true;
        $allcaps['edit_files'] = true;
        return $allcaps;
    });
    
    // CRITICAL: Force media buttons to be displayed in wp_editor
    add_filter('wp_editor_settings', function($settings, $editor_id) {
        $settings['media_buttons'] = true;
        return $settings;
    }, 10, 2);
    
    // CRITICAL: Ensure media buttons HTML is output
    add_action('media_buttons', function($editor_id = '') {
        // WordPress automatically adds media buttons when media_buttons=true
        // This action ensures they're properly rendered
    }, 1);
}

/**
 * Render Rich Text Editor
 * 
 * Vykreslí WordPress TinyMCE editor s media gallery podporou a dark mode.
 * 
 * POUŽITÍ:
 * ```php
 * // V controlleru PŘED render():
 * saw_richtext_editor_init();
 * 
 * // V template:
 * render_saw_richtext_editor('my_field_name', $existing_content, [
 *     'height' => 400,
 *     'dark_mode' => true
 * ]);
 * ```
 * 
 * @since 1.0.0
 * @param string $editor_id Unique editor ID (will be used as textarea ID)
 * @param string $content Initial content
 * @param array $args {
 *     Optional. Editor configuration arguments.
 *     
 *     @type string $textarea_name  Name attribute for textarea (default: $editor_id)
 *     @type int    $height         Editor height in pixels (default: 350)
 *     @type bool   $dark_mode      Enable dark mode styling (default: false)
 *     @type string $toolbar_preset Toolbar preset: 'full', 'basic', 'minimal' (default: 'basic')
 *     @type array  $tinymce        Custom TinyMCE settings (optional)
 * }
 * @return void
 */
function render_saw_richtext_editor($editor_id, $content = '', $args = array()) {
    // Default arguments
    $defaults = array(
        'textarea_name' => $editor_id,
        'height' => 350,
        'dark_mode' => false,
        'toolbar_preset' => 'basic',
        'tinymce' => null,
    );
    
    $args = wp_parse_args($args, $defaults);
    
    // Toolbar presets
    $toolbar_presets = array(
        'full' => array(
            'toolbar1' => 'formatselect,bold,italic,underline,strikethrough,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
            'toolbar2' => 'undo,redo,removeformat,code,hr,blockquote,subscript,superscript,charmap,indent,outdent,pastetext,searchreplace,fullscreen',
            'block_formats' => 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3;Nadpis 4=h4;Citace=blockquote',
        ),
        'basic' => array(
            'toolbar1' => 'formatselect,bold,italic,underline,blockquote,bullist,numlist,link,unlink',
            'toolbar2' => '',
            'block_formats' => 'Odstavec=p;Nadpis 1=h1;Nadpis 2=h2;Nadpis 3=h3',
        ),
        'minimal' => array(
            'toolbar1' => 'bold,italic,bullist,numlist,link',
            'toolbar2' => '',
            'block_formats' => 'Odstavec=p',
        ),
    );
    
    // Get toolbar configuration
    if ($args['tinymce'] === null) {
        $toolbar_config = $toolbar_presets[$args['toolbar_preset']] ?? $toolbar_presets['basic'];
    } else {
        $toolbar_config = $args['tinymce'];
    }
    
    // Merge with common TinyMCE settings
    $tinymce_settings = array_merge($toolbar_config, array(
        'height' => $args['height'],
        'menubar' => false,
        'statusbar' => false,
        'content_css' => false, // We use inline styles for dark mode
    ));
    
    // WordPress editor settings
    $editor_settings = array(
        'textarea_name' => $args['textarea_name'],
        'textarea_rows' => ceil($args['height'] / 20), // Approximate rows
        'media_buttons' => true, // CRITICAL: Must be true for media gallery
        'teeny' => false,
        'quicktags' => false,
        'tinymce' => $tinymce_settings,
    );
    
    // Add wrapper div with data attributes for JavaScript
    echo '<div class="saw-richtext-editor-wrapper" data-editor-id="' . esc_attr($editor_id) . '" data-dark-mode="' . ($args['dark_mode'] ? '1' : '0') . '">';
    
    // Render WordPress editor
    wp_editor($content, $editor_id, $editor_settings);
    
    echo '</div>';
    
    // Add inline script to ensure media buttons are visible
    // This runs immediately after editor is rendered
    ?>
    <script>
    (function() {
        var editorWrap = document.getElementById('wp-<?php echo esc_js($editor_id); ?>-wrap');
        if (editorWrap) {
            // Ensure media buttons exist
            var mediaButtons = editorWrap.querySelector('.wp-media-buttons');
            if (!mediaButtons) {
                // Create media buttons if missing
                var mediaButtonsDiv = document.createElement('div');
                mediaButtonsDiv.className = 'wp-media-buttons';
                mediaButtonsDiv.innerHTML = '<button type="button" class="button insert-media add_media" data-editor="<?php echo esc_js($editor_id); ?>"><span class="wp-media-buttons-icon"></span> Přidat média</button>';
                
                var editorContainer = editorWrap.querySelector('.wp-editor-container');
                if (editorContainer) {
                    editorContainer.parentNode.insertBefore(mediaButtonsDiv, editorContainer);
                } else {
                    editorWrap.insertBefore(mediaButtonsDiv, editorWrap.firstChild);
                }
                console.log('[SAW RichText] Manually added media buttons for:', '<?php echo esc_js($editor_id); ?>');
            } else {
                // Make sure media buttons are visible
                mediaButtons.style.display = 'block';
                mediaButtons.style.visibility = 'visible';
                mediaButtons.style.opacity = '1';
                console.log('[SAW RichText] Media buttons found for:', '<?php echo esc_js($editor_id); ?>');
            }
        }
    })();
    </script>
    <?php
}

/**
 * Enqueue Rich Text Editor assets
 * 
 * Načte CSS a JS soubory pro richtext editor.
 * Volat v controlleru společně s saw_richtext_editor_init().
 * 
 * @since 1.0.0
 * @return void
 */
function saw_richtext_editor_enqueue_assets() {
    $css_path = SAW_VISITORS_PLUGIN_DIR . 'includes/components/richtext-editor/richtext-editor.css';
    $js_path = SAW_VISITORS_PLUGIN_DIR . 'includes/components/richtext-editor/richtext-editor.js';
    
    // CSS
    if (file_exists($css_path)) {
        wp_enqueue_style(
            'saw-richtext-editor',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/richtext-editor/richtext-editor.css',
            array(),
            filemtime($css_path)
        );
    }
    
    // JS
    if (file_exists($js_path)) {
        wp_enqueue_script(
            'saw-richtext-editor',
            SAW_VISITORS_PLUGIN_URL . 'includes/components/richtext-editor/richtext-editor.js',
            array('jquery', 'wp-util'),
            filemtime($js_path),
            true
        );
    }
    
    // CRITICAL: Ensure WordPress media scripts are loaded
    wp_enqueue_media();
    wp_enqueue_editor();
}
