# Rich Text Editor Component

Globální komponenta pro WYSIWYG editor s media gallery podporou v SAW Visitors pluginu.

## 📁 Struktura

```
includes/components/richtext-editor/
├── richtext-editor.php      # PHP funkce pro render editoru
├── richtext-editor.js       # JavaScript inicializace
├── richtext-editor.css      # Dark mode styling
└── README.md               # Tato dokumentace
```

## ✨ Features

- ✅ WordPress TinyMCE editor
- ✅ **Media Gallery** (Přidat média tlačítko)
- ✅ **Dark Mode** styling
- ✅ **Light Mode** podpora
- ✅ Toolbar presets (full, basic, minimal)
- ✅ Konfigurovatelná výška
- ✅ Responzivní design
- ✅ Automatická inicializace media buttons

## 🚀 Použití

### 1. V controlleru (PŘED render)

```php
<?php
// Include component
require_once SAW_VISITORS_PLUGIN_DIR . 'includes/components/richtext-editor/richtext-editor.php';

class My_Controller {
    
    public function render() {
        // CRITICAL: Initialize hooks BEFORE rendering
        saw_richtext_editor_init();
        saw_richtext_editor_enqueue_assets();
        
        // ... your render code
    }
}
```

### 2. V template (kdekoli chcete editor)

```php
<?php
// Basic usage
render_saw_richtext_editor('my_field_name', $existing_content);

// With options
render_saw_richtext_editor('risks_text', $existing_content, array(
    'height' => 400,                  // Výška v px
    'dark_mode' => true,              // Zapnout dark mode
    'toolbar_preset' => 'basic',      // full, basic, minimal
));

// Custom toolbar
render_saw_richtext_editor('custom_editor', $content, array(
    'height' => 500,
    'dark_mode' => true,
    'tinymce' => array(
        'toolbar1' => 'bold,italic,link',
        'toolbar2' => '',
        'block_formats' => 'Odstavec=p;Nadpis 2=h2',
    ),
));
```

## 📋 Parametry

### `render_saw_richtext_editor($editor_id, $content, $args)`

**$editor_id** (string, required)
- Unikátní ID editoru
- Bude použito jako ID textarea
- Příklad: `'risks_text'`, `'description'`

**$content** (string, optional)
- Výchozí obsah editoru
- HTML formát
- Default: `''`

**$args** (array, optional)

| Parametr | Type | Default | Popis |
|----------|------|---------|-------|
| `textarea_name` | string | `$editor_id` | Name atribut textarea (pro POST data) |
| `height` | int | `350` | Výška editoru v pixelech |
| `dark_mode` | bool | `false` | Zapnout dark mode styling |
| `toolbar_preset` | string | `'basic'` | Preset toolbaru: `full`, `basic`, `minimal` |
| `tinymce` | array | `null` | Custom TinyMCE nastavení (přepíše preset) |

## 🎨 Toolbar Presets

### Full
Kompletní editor s všemi funkcemi:
- Format select, bold, italic, underline, strikethrough
- Forecolor, backcolor, lists, align
- Links, undo/redo, code, blockquote
- Special chars, indent, search, fullscreen

### Basic (výchozí)
Základní formátování:
- Format select (Odstavec, Nadpis 1-3)
- Bold, italic, underline, blockquote
- Bullet/numbered lists
- Links

### Minimal
Minimální toolbar:
- Bold, italic
- Lists
- Links

## 💡 Příklady použití

### Content Module (školení)

```php
<?php
// V controlleru
saw_richtext_editor_init();
saw_richtext_editor_enqueue_assets();

// V template
render_saw_richtext_editor(
    'risks_text_' . $language_id,
    $lang_content['risks_text'] ?? '',
    array(
        'textarea_name' => 'risks_text',
        'height' => 420,
        'dark_mode' => false,
        'toolbar_preset' => 'full',
    )
);
```

### Invitation Module (rizika návštěvy)

```php
<?php
// V controlleru
saw_richtext_editor_init();
saw_richtext_editor_enqueue_assets();

// V template
render_saw_richtext_editor(
    'risks_text',
    $existing_text,
    array(
        'textarea_name' => 'risks_text',
        'height' => 350,
        'dark_mode' => true,
        'toolbar_preset' => 'basic',
    )
);
```

### Terminal Module (admin poznámky)

```php
<?php
// V controlleru
saw_richtext_editor_init();
saw_richtext_editor_enqueue_assets();

// V template
render_saw_richtext_editor(
    'admin_notes',
    $visit->admin_notes,
    array(
        'textarea_name' => 'admin_notes',
        'height' => 200,
        'dark_mode' => false,
        'toolbar_preset' => 'minimal',
    )
);
```

## 🔧 Technické detaily

### WordPress Hooks

Komponenta nastavuje tyto WordPress hooks:

```php
// Media templates (pro media library modal)
add_action('admin_footer', 'wp_print_media_templates');
add_action('wp_footer', 'wp_print_media_templates');

// User capabilities (pro upload souborů)
add_filter('user_has_cap', function($allcaps) {
    $allcaps['upload_files'] = true;
    return $allcaps;
});

// Force media buttons
add_filter('wp_editor_settings', function($settings) {
    $settings['media_buttons'] = true;
    return $settings;
});
```

### JavaScript Inicializace

JavaScript automaticky:
1. Čeká na načtení WordPress media library
2. Kontroluje přítomnost media buttons
3. Pokud chybí, přidá je ručně
4. Aplikuje dark mode styling (pokud zapnutý)
5. Připojí event handler pro otevření media library

### CSS Struktura

```css
.saw-richtext-editor-wrapper          /* Wrapper */
  .wp-editor-wrap                      /* WordPress editor wrap */
    .wp-media-buttons                  /* Media buttons bar */
      .button.insert-media             /* Přidat média tlačítko */
    .mce-toolbar-grp                   /* TinyMCE toolbar */
    .wp-editor-container               /* Editor container */
      textarea.wp-editor-area          /* Text mode */
      .mce-edit-area                   /* Visual mode */
        iframe                         /* TinyMCE iframe */
          .mce-content-body            /* Editable content */
```

## 🐛 Troubleshooting

### Media buttons se nezobrazují

**Řešení:**
1. Ujistěte se, že voláte `saw_richtext_editor_init()` PŘED render
2. Zkontrolujte console (F12) pro JavaScript chyby
3. Ujistěte se, že máte `wp_enqueue_media()` a `wp_enqueue_editor()`

```php
// ✅ SPRÁVNĚ
saw_richtext_editor_init();
saw_richtext_editor_enqueue_assets();
$this->render_header();

// ❌ ŠPATNĚ
$this->render_header();
saw_richtext_editor_init();  // Pozdě!
```

### Editor má bílé pozadí (dark mode nefunguje)

**Řešení:**
1. Zkontrolujte, že `dark_mode => true` v args
2. Zkontrolujte console - JavaScript může hlásit chybu
3. Ujistěte se, že CSS soubor je načtený (Network tab v F12)

### TinyMCE se neinicializuje

**Řešení:**
1. Zkontrolujte, že `wp_enqueue_editor()` je voláno
2. Zkontrolujte conflicts s jinými skripty
3. Ujistěte se, že máte jQuery

## 📝 Changelog

### Version 1.0.0 (2025-01-XX)
- ✨ První verze
- ✅ WordPress TinyMCE integration
- ✅ Media gallery podpora
- ✅ Dark mode styling
- ✅ Toolbar presets
- ✅ Automatická inicializace

## 🎯 Budoucí vylepšení

- [ ] Autosave funkce
- [ ] Drag & drop pro obrázky
- [ ] Paste from Word cleaning
- [ ] Spell checker
- [ ] Custom color picker
- [ ] Table support
- [ ] Code syntax highlighting

## 📄 License

Part of SAW Visitors WordPress Plugin
