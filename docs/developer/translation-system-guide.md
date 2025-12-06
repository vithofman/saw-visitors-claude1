# 🌐 Translation System Guide

**SAW Visitors Plugin - Developer Manual**  
**Version:** 1.0.0  
**Last Updated:** 6. prosince 2024  
**Target Audience:** Plugin Developers

---

## 📋 Obsah

1. [Co je Translation System a proč ho používat](#1-co-je-translation-system-a-proč-ho-používat)
2. [Architektura systému](#2-architektura-systému)
3. [Databázová struktura](#3-databázová-struktura)
4. [Použití v kódu](#4-použití-v-kódu)
5. [Přidávání překladů](#5-přidávání-překladů)
6. [Quick Reference Card](#6-quick-reference-card)
7. [Testování & Debugging](#7-testování--debugging)

---

## 1. Co je Translation System a proč ho používat

### 1.1 Definice

**SAW Translation System** = vlastní systém pro správu UI překladů uložených v databázi.

**Proč vlastní systém (ne WordPress .pot/.po)?**
- ✅ Překlady v databázi - snadná správa bez přístupu k souborům
- ✅ Budoucí export do Flutter aplikace (JSON/ARB formát)
- ✅ Hierarchická struktura (context → section → key)
- ✅ Různé překlady pro různé stránky (tlačítko "Pokračovat" může mít jiný text na různých místech)

### 1.2 Kde se používá

| Oblast | Context | Příklad |
|--------|---------|---------|
| **Terminál** | `terminal` | Check-in/out obrazovky |
| **Pozvánky** | `invitation` | Invitation system |
| **Admin** | `admin` | Administrace, sidebar, moduly |
| **Sdílené** | `common` | Ano, Ne, Načítání... |

### 1.3 Jak to funguje

```
┌─────────────────────────────────────────────────────────┐
│ 1. UŽIVATEL VYBERE JAZYK                                │
│    Language Switcher → uloží do saw_users.language      │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│ 2. STRÁNKA NAČTE PŘEKLADY                               │
│    $t = saw_get_translations('en', 'admin', 'sidebar'); │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│ 3. HIERARCHICKÉ NAČTENÍ Z DB                            │
│    1. common (section=NULL)      → yes, no, loading     │
│    2. admin (section=NULL)       → save, cancel, delete │
│    3. admin/sidebar (section)    → dashboard, visits    │
│                                                          │
│    Pozdější přepíše dřívější (specifické > obecné)      │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│ 4. VÝSTUP V ŠABLONĚ                                     │
│    echo $t['dashboard'];  // "Dashboard"                │
└─────────────────────────────────────────────────────────┘
```

### 1.4 Fallback logika

Pokud překlad neexistuje v požadovaném jazyce:

```
Požadovaný jazyk (de) → Fallback jazyk (en) → Default jazyk (cs) → Klíč
```

**Příklad:**
```php
saw_t('title', 'de', 'terminal', 'video');
// 1. Hledá: de + terminal + video → nenalezeno
// 2. Hledá: en + terminal + video → nalezeno "Training Video"
// 3. Vrací: "Training Video"
```

---

## 2. Architektura systému

### 2.1 Soubory

```
includes/core/
├── class-saw-translations.php    # Hlavní singleton třída
└── translations-helpers.php       # Globální helper funkce (saw_t, saw_te, ...)

includes/components/language-switcher/
└── class-saw-component-language-switcher.php  # UI komponenta + AJAX
```

### 2.2 Třída SAW_Translations

```php
// Singleton - získání instance
$translations = SAW_Translations::instance();

// Hlavní metody
$translations->get($key, $lang, $context, $section);      // Jeden překlad
$translations->get_for_page($lang, $context, $section);   // Všechny pro stránku
$translations->get_available_languages();                  // Dostupné jazyky
$translations->clear_cache();                              // Vymazat cache
```

### 2.3 Helper funkce

| Funkce | Popis | Použití |
|--------|-------|---------|
| `saw_t()` | Vrátí překlad | `$text = saw_t('title', $lang, 'terminal', 'video');` |
| `saw_te()` | Vypíše překlad (escaped) | `<?php saw_te('title', $lang, 'terminal', 'video'); ?>` |
| `saw_te_html()` | Vypíše s HTML | `<?php saw_te_html('message', $lang, 'admin', null); ?>` |
| `saw_get_translations()` | Všechny pro stránku | `$t = saw_get_translations($lang, 'terminal', 'video');` |
| `saw_translations_js()` | Pro JavaScript | `<?php saw_translations_js($lang, 'terminal', 'video'); ?>` |

---

## 3. Databázová struktura

### 3.1 Tabulka `saw_ui_languages`

Systémové jazyky pro UI (ne training content).

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | BIGINT | PK |
| `language_code` | VARCHAR(10) | `cs`, `en`, `de` |
| `language_name` | VARCHAR(100) | Český název |
| `native_name` | VARCHAR(100) | Název v jazyce |
| `flag_emoji` | VARCHAR(10) | 🇨🇿, 🇬🇧 |
| `is_default` | TINYINT | Default jazyk (cs=1) |
| `is_fallback` | TINYINT | Fallback jazyk (en=1) |
| `is_active` | TINYINT | Je aktivní |

### 3.2 Tabulka `saw_ui_translations`

Samotné překlady.

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | BIGINT | PK |
| `translation_key` | VARCHAR(100) | Klíč: `title`, `confirm`, `continue` |
| `language_code` | VARCHAR(10) | `cs`, `en` |
| `context` | ENUM | `terminal`, `invitation`, `admin`, `common` |
| `section` | VARCHAR(50) | `video`, `sidebar`, `NULL` pro obecné |
| `translation_text` | TEXT | Samotný překlad |
| `description` | VARCHAR(255) | Popis pro admina |

### 3.3 Struktura klíčů

```
context     section     key              příklad hodnoty
─────────   ─────────   ──────────────   ─────────────────────
terminal    NULL        continue         "Pokračovat"
terminal    NULL        back             "Zpět"
terminal    video       title            "Školící video"
terminal    video       confirm          "Potvrzuji zhlédnutí"
terminal    success     checkin_title    "Úspěšně přihlášeno!"
admin       NULL        save             "Uložit"
admin       sidebar     dashboard        "Dashboard"
admin       sidebar     section_visits   "Návštěvy"
common      NULL        yes              "Ano"
common      NULL        loading          "Načítání..."
```

---

## 4. Použití v kódu

### 4.1 V PHP šablonách (doporučený způsob)

```php
<?php
// Na začátku souboru - načti všechny překlady pro stránku
$lang = $this->get_user_language();  // nebo jiný způsob získání jazyka
$t = saw_get_translations($lang, 'terminal', 'video');
?>

<!-- V HTML -->
<h1><?php echo esc_html($t['title']); ?></h1>
<p><?php echo esc_html($t['subtitle']); ?></p>

<label>
    <input type="checkbox" name="confirmed">
    <?php echo esc_html($t['confirm']); ?>
</label>

<!-- Tlačítko "Pokračovat" z terminal/general -->
<button><?php echo esc_html($t['continue']); ?></button>
```

### 4.2 Jednotlivé překlady (alternativa)

```php
<?php
// Pro jednotlivé překlady
$lang = 'cs';
?>

<h1><?php saw_te('title', $lang, 'terminal', 'video'); ?></h1>
<button><?php saw_te('continue', $lang, 'terminal', null); ?></button>
```

### 4.3 S placeholders

```php
// V databázi: "Přesměrování za {seconds} sekund..."
$text = saw_t('auto_redirect', $lang, 'terminal', 'success', ['seconds' => 5]);
// Výsledek: "Přesměrování za 5 sekund..."
```

### 4.4 V třídách (sidebar příklad)

```php
class SAW_App_Sidebar {
    
    private $lang;
    
    public function __construct() {
        // Načti jazyk uživatele
        $this->lang = SAW_Component_Language_Switcher::get_user_language();
    }
    
    // Helper metoda pro překlady
    private function t($key) {
        return saw_t($key, $this->lang, 'admin', 'sidebar');
    }
    
    private function get_menu_items() {
        return [
            [
                'id' => 'dashboard',
                'label' => $this->t('dashboard'),  // Přeloženo
                'url' => '/admin/dashboard',
                'icon' => '📊'
            ],
            // ...
        ];
    }
}
```

### 4.5 V JavaScriptu

```php
<!-- V PHP šabloně (header/footer) -->
<?php saw_translations_js($lang, 'terminal', 'video'); ?>
```

```javascript
// V JS souboru
const title = sawGetText('title');           // "Školící video"
const confirm = sawGetText('confirm');       // "Potvrzuji zhlédnutí"

// S placeholders
const msg = sawGetText('auto_redirect', { seconds: 5 });
// "Přesměrování za 5 sekund..."
```

---

## 5. Přidávání překladů

### 5.1 SQL (rychlé pro development)

```sql
INSERT INTO `cwp_saw_ui_translations` 
(`translation_key`, `language_code`, `context`, `section`, `translation_text`, `description`) 
VALUES
('new_key', 'cs', 'admin', 'sidebar', 'Nový text', 'Popis'),
('new_key', 'en', 'admin', 'sidebar', 'New text', 'Description');
```

### 5.2 PHP (pro migrace/seedy)

```php
$translations = [
    ['key' => 'new_key', 'lang' => 'cs', 'context' => 'admin', 'section' => 'sidebar', 'text' => 'Nový text'],
    ['key' => 'new_key', 'lang' => 'en', 'context' => 'admin', 'section' => 'sidebar', 'text' => 'New text'],
];

saw_import_translations($translations);
```

### 5.3 Přidání nového jazyka

```sql
-- 1. Přidat jazyk do saw_ui_languages
INSERT INTO `cwp_saw_ui_languages` 
(`language_code`, `language_name`, `native_name`, `flag_emoji`, `is_active`, `sort_order`) 
VALUES 
('de', 'Němčina', 'Deutsch', '🇩🇪', 1, 3);

-- 2. Přidat překlady pro nový jazyk
INSERT INTO `cwp_saw_ui_translations` 
(`translation_key`, `language_code`, `context`, `section`, `translation_text`) 
VALUES
('dashboard', 'de', 'admin', 'sidebar', 'Übersicht'),
('visits', 'de', 'admin', 'sidebar', 'Besuche'),
-- ... další překlady
```

### 5.4 Konvence pojmenování klíčů

| Pravidlo | Příklad |
|----------|---------|
| Lowercase | `dashboard`, `checkin_title` |
| Podtržítka pro více slov | `section_visits`, `auto_redirect` |
| Popisné názvy | `confirm_video_viewed` místo `cv` |
| Sekce pro nadpisy | `section_organization` |

---

## 6. Quick Reference Card

### Helper funkce

```php
// Získat jeden překlad
saw_t($key, $lang, $context, $section, $replacements);

// Vypsat překlad (HTML escaped)
saw_te($key, $lang, $context, $section);

// Vypsat s povoleným HTML
saw_te_html($key, $lang, $context, $section);

// Všechny překlady pro stránku
$t = saw_get_translations($lang, $context, $section);

// Pro JavaScript
saw_translations_js($lang, $context, $section);

// Dostupné jazyky
$languages = saw_get_ui_languages();

// Normalizovat jazyk
$lang = saw_normalize_language('cz');  // → 'cs'

// Vymazat cache
saw_clear_translations_cache();
```

### Parametry

| Parametr | Typ | Příklad |
|----------|-----|---------|
| `$key` | string | `'title'`, `'confirm'` |
| `$lang` | string | `'cs'`, `'en'` |
| `$context` | string | `'terminal'`, `'admin'`, `'common'` |
| `$section` | string\|null | `'video'`, `'sidebar'`, `null` |
| `$replacements` | array | `['name' => 'Jan', 'count' => 5]` |

### Contexts a jejich sections

```
terminal
├── NULL (obecné: continue, back, error)
├── language
├── action
├── type
├── pin
├── register
├── video
├── risks
├── oopp
├── map
├── department
├── additional
├── success
└── checkout

invitation
├── NULL (obecné)
├── welcome
├── form
└── complete

admin
├── NULL (obecné: save, cancel, delete)
├── sidebar
└── [názvy modulů]

common
└── NULL (yes, no, loading, error)
```

---

## 7. Testování & Debugging

### 7.1 Ověření funkčnosti

```php
// Dočasně přidat do šablony
echo '<pre>';
echo 'Class exists: ' . (class_exists('SAW_Translations') ? 'YES' : 'NO') . "\n";
echo 'Helper exists: ' . (function_exists('saw_t') ? 'YES' : 'NO') . "\n";

$test = saw_t('dashboard', 'en', 'admin', 'sidebar');
echo "Translation: {$test}\n";

$all = saw_get_translations('en', 'admin', 'sidebar');
print_r($all);
echo '</pre>';
```

### 7.2 Kontrola databáze

```sql
-- Počet překladů per jazyk
SELECT language_code, COUNT(*) as count 
FROM cwp_saw_ui_translations 
GROUP BY language_code;

-- Překlady pro konkrétní context/section
SELECT * FROM cwp_saw_ui_translations 
WHERE context = 'admin' AND section = 'sidebar'
ORDER BY language_code, translation_key;

-- Chybějící překlady (existuje cs, ale ne en)
SELECT cs.translation_key, cs.context, cs.section
FROM cwp_saw_ui_translations cs
LEFT JOIN cwp_saw_ui_translations en 
    ON cs.translation_key = en.translation_key 
    AND cs.context = en.context 
    AND (cs.section = en.section OR (cs.section IS NULL AND en.section IS NULL))
    AND en.language_code = 'en'
WHERE cs.language_code = 'cs' AND en.id IS NULL;
```

### 7.3 Debug log

Pokud překlad chybí, zobrazí se v error logu (při WP_DEBUG=true):

```
[SAW_Translations] Missing: admin/sidebar/unknown_key [en]
```

### 7.4 Cache

Překlady se cachují. Po změně v DB:

```php
// Vymazat cache
saw_clear_translations_cache();

// Nebo v SQL
DELETE FROM wp_options WHERE option_name LIKE '_transient_saw_t_%';
```

---

## 8. Získání jazyka uživatele

### V admin části (přihlášený uživatel)

```php
// Preferovaný způsob - přes Language Switcher komponentu
$lang = SAW_Component_Language_Switcher::get_user_language();

// Fallback - přes user meta
$lang = get_user_meta(get_current_user_id(), 'saw_current_language', true) ?: 'cs';
```

### V terminal/invitation (session)

```php
// Z flow session
$flow = $this->session->get('terminal_flow');
$lang = $flow['language'] ?? 'cs';
```

### Kam se jazyk ukládá

| Kontext | Úložiště | Klíč |
|---------|----------|------|
| Admin (přihlášený) | `saw_users.language` | Sloupec v tabulce |
| Admin (backup) | `wp_usermeta` | `saw_current_language` |
| Terminal/Invitation | Session | `$_SESSION['saw_current_language']` |

---

## 9. Best Practices

### ✅ DO

1. **Používej `saw_get_translations()`** pro celou stránku (1 DB dotaz místo N)
2. **Vždy escapuj výstup** - `esc_html($t['key'])` nebo `saw_te()`
3. **Používej popisné klíče** - `confirm_video_viewed` ne `cv`
4. **Přidávej description** - pomůže při budoucí správě
5. **Testuj oba jazyky** - cs i en

### ❌ DON'T

1. **Nehardcoduj texty** - vždy používej překlady
2. **Nemíchej kontexty** - každá oblast má svůj context
3. **Nepoužívej stejný klíč pro různé významy** - raději `button_save` a `title_save`
4. **Nezapomeň na fallback** - systém ho řeší automaticky, ale měj překlady v EN

---

## 10. Shrnutí

| Co | Jak |
|---|---|
| Získat překlad | `saw_t('key', $lang, 'context', 'section')` |
| Vypsat překlad | `saw_te('key', $lang, 'context', 'section')` |
| Všechny pro stránku | `saw_get_translations($lang, 'context', 'section')` |
| Jazyk uživatele | `SAW_Component_Language_Switcher::get_user_language()` |
| Přidat překlad | SQL INSERT do `saw_ui_translations` |
| Vymazat cache | `saw_clear_translations_cache()` |

---

**Happy Translating! 🌍**

*This document is maintained by the SAW Visitors development team.*  
*Last updated: December 6, 2024*