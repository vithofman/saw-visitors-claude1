# SAW Visitors - Překladový systém

## Přehled

Systém používá hierarchické načítání překladů z databázové tabulky `cwp_saw_ui_translations`. Překlady se načítají ve třech vrstvách - od obecných po specifické.

---

## 1. Hierarchie načítání

```
saw_get_translations('cs', 'admin', 'visits')
         ↓
┌─────────────────────────────────────────────┐
│ 1. common (section=NULL)     → Globální UI  │
│ 2. admin (section=NULL)      → Admin-wide   │
│ 3. admin/visits              → Modul-specific│
└─────────────────────────────────────────────┘
         ↓
Pozdější hodnoty přepisují dřívější
```

**Příklad:** Klíč `btn_save` definovaný v `common` se použije všude. Pokud ho definuješ i v `admin/visits`, přepíše se pouze pro modul visits.

---

## 2. Databázová struktura

**Tabulka:** `cwp_saw_ui_translations`

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `translation_key` | VARCHAR(100) | Jedinečný klíč překladu |
| `language_code` | VARCHAR(10) | `cs`, `en`, `es` |
| `context` | VARCHAR(50) | `common`, `admin`, `terminal`, `invitation` |
| `section` | VARCHAR(50) | `NULL` pro obecné, nebo název modulu (`visits`, `visitors`, `companies`) |
| `translation_text` | TEXT | Přeložený text |

**Unikátní klíč:** `(translation_key, language_code, context, section)`

---

## 3. Kontexty a sekce

| Context | Section | Použití |
|---------|---------|---------|
| `common` | `NULL` | Globální UI (tlačítka, labely, sidebar) |
| `admin` | `NULL` | Admin-wide texty |
| `admin` | `visits` | Modul návštěv |
| `admin` | `visitors` | Modul návštěvníků |
| `admin` | `companies` | Modul firem |
| `terminal` | `NULL` | Terminálový flow |
| `invitation` | `NULL` | Pozvánkový flow |

---

## 4. Common překlady (globální)

Tyto klíče patří do `context='common', section=NULL` a jsou dostupné **všude**:

### Sidebar navigace
```
sidebar_previous, sidebar_next, sidebar_close
```

### Akční tlačítka
```
btn_edit, btn_delete, btn_save, btn_cancel, btn_create, btn_back
```

### Související záznamy
```
related_records     → "Související záznamy"
record_singular     → "záznam" (1)
record_few          → "záznamy" (2-4)
record_many         → "záznamů" (5+)
no_records, view_detail
```

### Obecné labely
```
loading, error, success, warning, confirm, yes, no
```

---

## 5. Konvence pojmenování klíčů

### Prefixy podle typu

| Prefix | Použití | Příklad |
|--------|---------|---------|
| `form_` | Formulářové prvky | `form_branch`, `form_select_company` |
| `btn_` | Tlačítka | `btn_save`, `btn_cancel` |
| `col_` | Sloupce tabulky | `col_visitor`, `col_status` |
| `tab_` | Záložky/taby | `tab_all`, `tab_pending` |
| `filter_` | Filtry | `filter_status`, `filter_all_types` |
| `status_` | Stavy | `status_draft`, `status_confirmed` |
| `type_` | Typy | `type_planned`, `type_walk_in` |
| `section_` | Sekce v detailu | `section_timeline`, `section_info` |
| `field_` | Pole v detailu | `field_started`, `field_duration` |
| `alert_` | Alertní zprávy | `alert_error`, `alert_pin_generated` |
| `pin_` | PIN sekce | `pin_label`, `pin_expired` |
| `relation_` | Související záznamy | `relation_visitors` |
| `config_` | Konfigurace entity | `config_singular`, `config_plural` |

### Speciální sufixy

| Suffix | Použití | Příklad |
|--------|---------|---------|
| `_placeholder` | Placeholder texty | `form_purpose_placeholder` |
| `_hint` | Nápovědy | `form_schedule_hint` |
| `_title` | Title atributy | `risks_missing_title` |
| `_singular` | Jednotné číslo | `record_singular` |
| `_few` | 2-4 (čeština) | `record_few` |
| `_many` | 5+ (čeština) | `record_many` |

---

## 6. Česká gramatika (pluralizace)

Čeština má 3 tvary pro počítání:

| Počet | Tvar | Příklad klíče |
|-------|------|---------------|
| 1 | singular | `record_singular` → "záznam" |
| 2-4 | few | `record_few` → "záznamy" |
| 5+ | many | `record_many` → "záznamů" |

**PHP helper:**
```php
$record_label = function($count) use ($tr) {
    if ($count === 1) return $tr('record_singular', 'záznam');
    elseif ($count >= 2 && $count <= 4) return $tr('record_few', 'záznamy');
    else return $tr('record_many', 'záznamů');
};

echo $count . ' ' . $record_label($count);
// 1 záznam, 3 záznamy, 7 záznamů
```

---

## 7. Použití v PHP šablonách

### Inicializace (na začátku souboru)
```php
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'visits') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};
```

### Použití v HTML
```php
// Běžný text
<?php echo $tr('form_branch', 'Pobočka'); ?>

// V atributech (s escapováním)
placeholder="<?php echo esc_attr($tr('form_purpose_placeholder', 'Popis...')); ?>"
title="<?php echo esc_attr($tr('btn_edit', 'Upravit')); ?>"

// Podmíněný text
<?php echo $is_edit ? $tr('btn_save_changes', 'Uložit změny') : $tr('btn_create', 'Vytvořit'); ?>
```

---

## 8. SQL šablona pro nové překlady

```sql
-- ============================================
-- SAW Visitors - PŘEKLADY PRO [NÁZEV]
-- context: admin, section: [modul]
-- ============================================

INSERT INTO `cwp_saw_ui_translations` 
(`translation_key`, `language_code`, `context`, `section`, `translation_text`) VALUES
-- Sekce 1
('klíč_1', 'cs', 'admin', 'visits', 'Český text'),
('klíč_1', 'en', 'admin', 'visits', 'English text'),

-- Sekce 2
('klíč_2', 'cs', 'admin', 'visits', 'Český text'),
('klíč_2', 'en', 'admin', 'visits', 'English text');

-- Vymazat cache (DŮLEŽITÉ!)
DELETE FROM cwp_options WHERE option_name LIKE '%saw_t_%';
```

---

## 9. Postup přidání překladů do nového souboru

### Krok 1: Přidat inicializaci
Na začátek PHP souboru (za `if (!defined('ABSPATH')) exit;`):
```php
$lang = 'cs';
if (class_exists('SAW_Component_Language_Switcher')) {
    $lang = SAW_Component_Language_Switcher::get_user_language();
}
$t = function_exists('saw_get_translations') 
    ? saw_get_translations($lang, 'admin', 'MODUL') 
    : [];

$tr = function($key, $fallback = null) use ($t) {
    return $t[$key] ?? $fallback ?? $key;
};
```

### Krok 2: Nahradit hardcoded texty
```php
// PŘED
<label>Pobočka</label>

// PO
<label><?php echo $tr('form_branch', 'Pobočka'); ?></label>
```

### Krok 3: Vytvořit SQL s překlady
Vypiš všechny klíče, které jsi použil, a vytvoř INSERT.

### Krok 4: Spustit SQL + vymazat cache
```sql
DELETE FROM cwp_options WHERE option_name LIKE '%saw_t_%';
```

---

## 10. Kontrola duplikátů

Před vložením nových překladů zkontroluj, zda klíč již neexistuje:

```sql
SELECT * FROM cwp_saw_ui_translations 
WHERE translation_key = 'tvuj_klic' 
AND context = 'admin' 
AND (section = 'visits' OR section IS NULL);
```

**Pravidlo:** Pokud klíč existuje v `common`, nemusíš ho přidávat do modulu (automaticky se načte).

---

## 11. Přehled existujících překladů visits

### List view (tabulka)
```
title, search_placeholder, add_new, empty_message
filter_status, filter_all_statuses, filter_visit_type, filter_all_types
col_visitor, col_branch, col_type, col_count, col_risks, col_status, col_created_at
tab_all, tab_draft, tab_pending, tab_confirmed, tab_in_progress, tab_completed, tab_cancelled
```

### Statusy a typy
```
status_draft, status_pending, status_confirmed, status_in_progress, status_completed, status_cancelled
type_planned, type_walk_in
visitor_company, visitor_physical, visitor_physical_short
```

### Detail view
```
section_timeline, section_schedule, section_info, section_visitor, section_risks
field_started, field_completed, field_duration, field_days, field_hours, field_minutes, field_branch
day_mon, day_tue, day_wed, day_thu, day_fri, day_sat, day_sun
person_singular, person_few, person_many
```

### PIN sekce
```
pin_label, pin_copy, pin_copied, pin_status, pin_expiration
pin_unlimited, pin_permanent, pin_expired, pin_expired_ago, pin_remaining
pin_extend_24h, pin_extend_48h, pin_extend_7d, pin_extend_manual
pin_set_expiry, pin_save, pin_back, pin_not_generated, pin_generate
```

### Formulář
```
form_title_edit, form_title_create, btn_back_to_list
form_section_basic, form_branch, form_select_branch
form_visitor_type, form_legal_person, form_physical_person
form_company, form_select_company, form_new_company
form_visit_type, form_status, form_schedule_days
form_date, form_time_from, form_time_to, form_note, form_note_placeholder
btn_remove_day, btn_add_day, form_schedule_hint
form_invitation_email, form_purpose, form_purpose_placeholder
form_hosts, form_hosts_search, form_select_all, form_select_branch_first
btn_save_changes, btn_create_visit
```

---

## 12. Debugování

### Ověření načtených překladů
```php
$t = saw_get_translations('cs', 'admin', 'visits');
echo '<pre>' . print_r($t, true) . '</pre>';
```

### Kontrola v databázi
```sql
SELECT translation_key, language_code, translation_text 
FROM cwp_saw_ui_translations 
WHERE context = 'admin' AND section = 'visits'
ORDER BY translation_key;
```

### Vymazání cache
```sql
DELETE FROM cwp_options WHERE option_name LIKE '%saw_t_%';
```

---

## 13. Checklist pro nový modul

- [ ] Přidat translation loading na začátek šablony
- [ ] Nahradit všechny hardcoded texty za `$tr()`
- [ ] Vždy uvádět fallback: `$tr('key', 'Fallback text')`
- [ ] Použít správné prefixy (`form_`, `btn_`, `col_`, atd.)
- [ ] Vytvořit SQL pro CS i EN
- [ ] Ověřit, že klíče nejsou v `common` (nemusíš duplikovat)
- [ ] Spustit SQL
- [ ] Vymazat cache
- [ ] Otestovat přepnutí jazyka

---

## 14. Důležité poznámky

1. **Fallbacky jsou povinné** - vždy uváděj český text jako fallback pro případ, že překlad neexistuje
2. **Cache** - po každé změně v DB vymaž cache transientů
3. **Common first** - tlačítka a obecné UI dej do `common`, ne do modulu
4. **Nepřepisuj co funguje** - při přidávání překladů do existujícího souboru měň POUZE texty, ne strukturu
5. **Testuj oba jazyky** - přepni na EN a ověř, že vše funguje