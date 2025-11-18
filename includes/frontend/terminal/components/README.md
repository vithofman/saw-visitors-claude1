# 🧩 Fáze 2 - PHP Komponenty

## ✅ Co je v balíčku

4 reusable PHP komponenty pro training systém:

```
components/
├── training-header.php          # Header (icon + title + subtitle)
├── training-checkbox.php        # Touch-friendly checkbox
├── training-button.php          # Action button (4 variants)
└── training-content-viewer.php  # Collapsible sections
```

---

## 📦 Instalace

### Krok 1: Nahrát soubory

Zkopíruj všechny 4 soubory do:
```
/includes/frontend/terminal/components/
```

### Krok 2: Ověřit strukturu

```
includes/frontend/terminal/
├── assets/css/
│   ├── terminal-base.css
│   ├── terminal-layout.css
│   ├── terminal-components.css
│   └── terminal-training.css
└── components/
    ├── training-header.php
    ├── training-checkbox.php
    ├── training-button.php
    └── training-content-viewer.php
```

---

## 📖 Použití komponent

### 1. training-header.php

**Parametry:**
- `icon` (string) - Emoji ikona
- `title` (string, required) - Titulek
- `subtitle` (string, optional) - Podtitulek

**Příklad:**
```php
get_template_part('components/training-header', null, [
    'icon' => '🎬',
    'title' => 'Školící video',
    'subtitle' => 'Sledujte celé video do konce'
]);
```

---

### 2. training-checkbox.php

**Parametry:**
- `id` (string, required) - Unikátní ID checkboxu
- `name` (string) - Input name (default: 'confirmed')
- `text` (string, required) - Text labelu
- `checked` (bool) - Je zaškrtnutý (default: false)
- `disabled` (bool) - Je disabled (default: false)
- `required` (bool) - Je povinný (default: true)
- `value` (string) - Hodnota (default: '1')

**Příklad:**
```php
get_template_part('components/training-checkbox', null, [
    'id' => 'video-confirmed',
    'name' => 'video_confirmed',
    'text' => 'Potvrzuji, že jsem shlédl celé video',
    'disabled' => true  // Povolí se až po doshlédnutí
]);
```

**JavaScript API:**
```javascript
// Povolit checkbox programově
const checkbox = document.getElementById('video-confirmed');
checkbox.disabled = false;
checkbox.closest('.saw-training-confirm-box').classList.remove('saw-training-btn-disabled');
```

---

### 3. training-button.php

**Parametry:**
- `text` (string, required) - Text tlačítka
- `type` (string) - 'submit', 'button', 'link' (default: 'submit')
- `variant` (string) - 'primary', 'success', 'danger', 'secondary' (default: 'success')
- `disabled` (bool) - Je disabled (default: false)
- `icon` (string) - Ikona/emoji (default: '→')
- `href` (string) - URL pro type='link'
- `attributes` (array) - Další HTML atributy
- `id` (string) - ID tlačítka
- `full_width` (bool) - Celá šířka (default: true)

**Příklady:**

```php
// Success button (zelený, default)
get_template_part('components/training-button', null, [
    'text' => 'Pokračovat',
    'variant' => 'success'
]);

// Primary button (fialový)
get_template_part('components/training-button', null, [
    'text' => 'Začít školení',
    'variant' => 'primary',
    'icon' => '▶️'
]);

// Disabled button
get_template_part('components/training-button', null, [
    'text' => 'Pokračovat',
    'disabled' => true,
    'attributes' => ['id' => 'continue-btn']
]);

// Link button
get_template_part('components/training-button', null, [
    'text' => 'Zpět',
    'type' => 'link',
    'href' => '/terminal/',
    'variant' => 'secondary',
    'icon' => '←'
]);
```

---

### 4. training-content-viewer.php

**Parametry:**
- `sections` (array, required) - Pole sekcí
- `scrollable` (bool) - Scrollable container (default: true)
- `max_height` (string) - Max výška (default: '60vh')

**Struktura sekce:**
```php
[
    'title' => 'Název sekce',           // Required
    'content' => '<p>HTML obsah</p>',   // Optional
    'documents' => [                     // Optional
        [
            'name' => 'Dokument.pdf',
            'url' => '/path/to/file.pdf',
            'icon' => '📄'               // Optional
        ]
    ],
    'collapsed' => false                 // Optional (default: false)
]
```

**Příklad - Single section (Risks):**
```php
$sections = [[
    'title' => 'Bezpečnostní rizika',
    'content' => $risks_text,
    'documents' => $documents,
    'collapsed' => false
]];

get_template_part('components/training-content-viewer', null, [
    'sections' => $sections
]);
```

**Příklad - Multiple sections (Departments):**
```php
$sections = [];
foreach ($departments as $dept) {
    $sections[] = [
        'title' => $dept['department_name'],
        'content' => $dept['text_content'],
        'documents' => $dept['documents'] ?? [],
        'collapsed' => true  // Všechny zavřené
    ];
}

get_template_part('components/training-content-viewer', null, [
    'sections' => $sections,
    'max_height' => '65vh'
]);
```

---

## 🎯 Kompletní příklad - Video step

```php
<?php
// video.php - Refactored
?>
<div class="saw-training-fullscreen">
    <!-- Home button -->
    <a href="/terminal/" class="saw-terminal-home-btn">🏠</a>
    
    <div class="saw-training-container">
        <!-- Header component -->
        <?php 
        get_template_part('components/training-header', null, [
            'icon' => '🎬',
            'title' => $t['title'],
            'subtitle' => $t['subtitle']
        ]); 
        ?>
        
        <!-- Content card -->
        <div class="saw-training-card">
            <!-- Video viewer -->
            <div class="saw-training-viewer">
                <div class="saw-video-viewer">
                    <div class="saw-video-player-container">
                        <iframe id="training-video" 
                                src="<?php echo esc_url($video_url); ?>" 
                                frameborder="0" 
                                allowfullscreen>
                        </iframe>
                    </div>
                </div>
            </div>
            
            <!-- Footer with checkbox + button -->
            <div class="saw-training-footer">
                <!-- Checkbox component -->
                <?php 
                get_template_part('components/training-checkbox', null, [
                    'id' => 'video-confirmed',
                    'name' => 'video_confirmed',
                    'text' => $t['confirm_watched'],
                    'disabled' => true  // Enabled at 90% progress
                ]); 
                ?>
                
                <!-- Button component -->
                <?php 
                get_template_part('components/training-button', null, [
                    'text' => $t['continue'],
                    'variant' => 'success',
                    'disabled' => true,
                    'attributes' => ['id' => 'continue-btn']
                ]); 
                ?>
            </div>
        </div>
    </div>
</div>

<script>
// Enable checkbox when video is 90% watched
// Enable button when checkbox is checked
</script>
```

---

## 🎯 Kompletní příklad - Risks step

```php
<?php
// risks.php - Refactored
?>
<div class="saw-training-fullscreen">
    <a href="/terminal/" class="saw-terminal-home-btn">🏠</a>
    
    <div class="saw-training-container">
        <!-- Header -->
        <?php 
        get_template_part('components/training-header', null, [
            'icon' => '⚠️',
            'title' => $t['title'],
            'subtitle' => $t['subtitle']
        ]); 
        ?>
        
        <!-- Card -->
        <div class="saw-training-card">
            <!-- Content viewer -->
            <?php 
            $sections = [[
                'title' => 'Informace o rizicích',
                'content' => $risks_text,
                'documents' => $documents,
                'collapsed' => false
            ]];
            
            get_template_part('components/training-content-viewer', null, [
                'sections' => $sections
            ]); 
            ?>
            
            <!-- Footer -->
            <div class="saw-training-footer">
                <?php 
                get_template_part('components/training-checkbox', null, [
                    'id' => 'risks-confirmed',
                    'text' => $t['confirm_read']
                ]); 
                ?>
                
                <?php 
                get_template_part('components/training-button', null, [
                    'text' => $t['continue']
                ]); 
                ?>
            </div>
        </div>
    </div>
</div>
```

---

## 🎯 Kompletní příklad - Department step

```php
<?php
// department.php - Refactored
?>
<div class="saw-training-fullscreen">
    <a href="/terminal/" class="saw-terminal-home-btn">🏠</a>
    
    <div class="saw-training-container">
        <!-- Header -->
        <?php 
        get_template_part('components/training-header', null, [
            'icon' => '🏭',
            'title' => $t['title'],
            'subtitle' => $t['subtitle']
        ]); 
        ?>
        
        <!-- Card -->
        <div class="saw-training-card">
            <!-- Multi-section viewer -->
            <?php 
            $sections = [];
            foreach ($departments as $dept) {
                $sections[] = [
                    'title' => $dept['department_name'],
                    'content' => $dept['text_content'],
                    'documents' => $dept['documents'] ?? [],
                    'collapsed' => true  // All closed by default
                ];
            }
            
            get_template_part('components/training-content-viewer', null, [
                'sections' => $sections,
                'max_height' => '65vh'
            ]); 
            ?>
            
            <!-- Footer -->
            <div class="saw-training-footer">
                <?php 
                get_template_part('components/training-checkbox', null, [
                    'id' => 'department-confirmed',
                    'text' => $t['confirm_read']
                ]); 
                ?>
                
                <?php 
                get_template_part('components/training-button', null, [
                    'text' => $t['continue']
                ]); 
                ?>
            </div>
        </div>
    </div>
</div>
```

---

## ✅ Výhody komponent

### Před (bez komponent):
- ❌ Duplicitní kód v každém kroku
- ❌ Nekonzistentní UX
- ❌ Těžká údržba
- ❌ Změna = editovat všechny soubory

### Po (s komponenty):
- ✅ Znovupoužitelný kód
- ✅ Konzistentní UX
- ✅ Snadná údržba
- ✅ Změna na 1 místě = všude

---

## 🔧 Debugging

**Logování:**
Všechny komponenty logují chyby do PHP error logu:
```php
// Příklad
error_log('[SAW Training Header] Warning: Title is required');
```

**JavaScript Console:**
```javascript
// Content viewer
console.log('[SAW Content Viewer] Initialized with X sections');

// Checkbox
console.error('[SAW Training Checkbox] Checkbox or wrapper not found');
```

---

## 🚀 Next Steps - Fáze 3

**Co bude následovat:**
1. PDF Viewer s PDF.js (`assets/js/pdf-viewer.js`)
2. Touch Gestures (`assets/js/touch-gestures.js`)
3. Refactor `map.php` s novým viewerem

---

## 📊 Statistiky

**Soubory:** 4 PHP komponenty  
**Řádky kódu:** ~550 řádků  
**Použití:** 5 training kroků (video, map, risks, additional, department)  
**Úspora kódu:** ~70% (díky reusability)

---

## 📞 Support

Pro otázky nebo problémy vytvoř issue nebo kontaktuj vývojáře.

**Verze:** 3.0.0  
**Datum:** Listopad 2024  
**Autor:** Claude (Anthropic)
