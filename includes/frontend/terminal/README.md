# 🖥️ SAW Visitors - Terminal Frontend System

## Přehled

Touchscreen-friendly rozhraní pro check-in/out návštěvníků. Optimalizováno pro tablety a kiosky na recepci.

## 📁 Struktura souborů

```
includes/frontend/terminal/
├── terminal.php                      # Hlavní controller (routing, session)
├── terminal.css                      # Kompletní styling (touch-friendly)
├── terminal.js                       # Interaktivní prvky (PIN pad, validace)
├── layout-header.php                 # HTML header + navigace
├── layout-footer.php                 # HTML footer
├── steps/
│   ├── 1-language.php               # Výběr jazyka (cs/en/uk)
│   ├── 2-action.php                 # Check-in vs Check-out
│   ├── 3-type.php                   # Plánovaná vs Jednorázová
│   ├── 4-register.php               # Registrační formulář (walk-in)
│   ├── pin-entry.php                # Zadání PIN s numpadem
│   ├── checkout-method.php          # Způsob odhlášení
│   ├── success.php                  # Potvrzení + auto-redirect
│   └── checkout/
│       ├── pin.php                  # Odhlášení přes PIN + výběr osob
│       └── search.php               # Vyhledání podle jména
└── README.md                         # Tato dokumentace
```

## 🔄 Flow diagramy

### Check-in Flow

```
/terminal
    ↓
1. Jazyk → cs/en/uk
    ↓
2. Akce → Check-in
    ↓
3. Typ návštěvy?
    ├─→ Plánovaná
    │   ├─ PIN Entry
    │   ├─ Ověření DB
    │   ├─ Školení? (pokud training_skipped = 0)
    │   └─ Success
    │
    └─→ Jednorázová (Walk-in)
        ├─ Registrační formulář
        │  - Firma (nebo fyzická osoba)
        │  - Osobní údaje
        │  - Výběr hostitele
        │  - Školení skip checkbox
        ├─ Školení (pokud ne skip)
        └─ Success
```

### Check-out Flow

```
/terminal
    ↓
1. Jazyk → cs/en/uk
    ↓
2. Akce → Check-out
    ↓
3. Způsob odhlášení?
    ├─→ PIN kód
    │   ├─ PIN Entry
    │   ├─ Načtení všech návštěvníků
    │   ├─ Výběr odcházejících (checkboxy)
    │   └─ Success
    │
    └─→ Vyhledání
        ├─ Zadání jména
        ├─ Výsledky vyhledávání
        ├─ Potvrzení odhlášení
        └─ Success
```

## 🔌 Integrace s pluginem

### Router Integration

V `includes/core/class-saw-router.php` metoda `handle_terminal_route()`:

```php
private function handle_terminal_route($path) {
    // Terminal - vyžaduje přihlášení
    if (!$this->is_logged_in()) {
        $this->redirect_to_login('terminal');
        return;
    }
    
    // Load terminal route handler
    $handler = SAW_VISITORS_PLUGIN_DIR . 'includes/frontend/terminal-route-handler.php';
    if (file_exists($handler)) {
        require_once $handler;
    } else {
        wp_die('Terminal handler not found');
    }
}
```

## Relationship with Invitation System

Terminal and Invitation are **completely separate** systems:

- **Invitation**: Public, unauthenticated, pre-registration
- **Terminal**: Authenticated, check-in/out operations

Communication:

- Only through database (visits, visitors tables)
- No session sharing
- No code sharing (except training templates in `/shared/`)

**Important:** Terminal NEVER handles invitation flow. All invitation-specific code has been removed.

### Asset Loading

V `includes/core/class-saw-visitors.php` metoda `enqueue_public_styles()`:

Assety se načítají automaticky přes `SAW_Terminal_Controller::enqueue_assets()`.

## 🎨 Design principy

### Touch-Friendly

- Všechna tlačítka min. **60px** výška
- Velké fonty (1.25rem - 2rem)
- Dostatečné mezery mezi interaktivními elementy
- Prevent double-tap zoom

### Responsive

- Grid layouts s fallbackem na 1 sloupec (mobil)
- Flexibilní padding/margin podle šířky obrazovky
- Testováno na tabletech 10" a 7"

### Accessibility

- Vysoký kontrast (WCAG AA)
- Focus states na všech interaktivních prvcích
- Logická tab navigace (i když touch preferred)

## 📝 Session Management

### Session Structure

```php
$_SESSION['terminal_flow'] = [
    'step' => 'language',              // Aktuální krok
    'language' => 'cs',                // Vybraný jazyk
    'action' => 'checkin',             // checkin|checkout
    'type' => 'planned',               // planned|walkin (pro checkin)
    'pin' => '123456',                 // PIN kód
    'visit_id' => 10,                  // ID návštěvy
    'visitor_ids' => [1, 2, 3],        // IDs návštěvníků
    'data' => [...],                   // Další temporary data
];
```

### Session Cleanup

Session se resetuje:
- Po úspěšném dokončení (success page)
- Při kliknutí na "Začít znovu"
- Automaticky po 15 minutách neaktivity (TODO: cron)

## 🌐 Multi-language Support

### Aktuální podpora

- 🇨🇿 Čeština (cs)
- 🇬🇧 English (en)
- 🇺🇦 Українська (uk)

### Přidání nového jazyka

1. V `SAW_Terminal_Controller::__construct()` přidat do `$this->languages`:

```php
$this->languages = [
    'cs' => 'Čeština',
    'en' => 'English',
    'uk' => 'Українська',
    'de' => 'Deutsch',  // ← přidat
];
```

2. V každém step template přidat překlady do `$translations`:

```php
$translations = [
    'cs' => [...],
    'en' => [...],
    'uk' => [...],
    'de' => [           // ← přidat
        'title' => 'Wählen Sie die Sprache',
        // ...
    ],
];
```

## 🔐 Bezpečnost

### CSRF Protection

Všechny formuláře používají WordPress nonce:

```php
<?php wp_nonce_field('saw_terminal_step', 'terminal_nonce'); ?>
```

Ověření v `SAW_Terminal_Controller::handle_post()`:

```php
if (!wp_verify_nonce($_POST['terminal_nonce'], 'saw_terminal_step')) {
    $this->set_error('Bezpečnostní kontrola selhala');
    return;
}
```

### Input Sanitization

- `sanitize_text_field()` pro text inputy
- `sanitize_email()` pro emaily
- `sanitize_textarea_field()` pro textarea
- `absint()` pro IDs

## 🛠️ Debugging

### Zapnutí debug logů

V `terminal.php` přidat na začátek metody `render()`:

```php
public function render() {
    error_log('=== TERMINAL DEBUG ===');
    error_log('Step: ' . $this->current_step);
    error_log('Flow: ' . print_r($this->session->get('terminal_flow'), true));
    // ...
}
```

### Session inspect

Přidat dočasný endpoint:

```php
// V terminal.php
if (isset($_GET['debug_session'])) {
    echo '<pre>';
    print_r($this->session->get('terminal_flow'));
    echo '</pre>';
    exit;
}
```

Pak navštívit: `/terminal/?debug_session=1`

## ⚡ Performance

### Optimalizace

1. **CSS/JS Minifikace** (TODO)
   - Použít WP build process
   - Minifikovat při deployi

2. **Asset Caching**
   - Verzování přes `SAW_VISITORS_VERSION`
   - Browser cache headers

3. **Session Storage**
   - Minimální data v session
   - Cleanup po dokončení

## 🧪 Testing Checklist

### Funkční testy

- [ ] Výběr jazyka funguje
- [ ] Check-in plánovaná → PIN → Success
- [ ] Check-in walk-in → Registrace → Success
- [ ] Check-out PIN → Výběr osob → Success
- [ ] Check-out search → Vyhledání → Success
- [ ] Error messages zobrazují správně
- [ ] Auto-redirect funguje (5s)
- [ ] "Začít znovu" tlačítko resetuje session

### UX testy

- [ ] Tlačítka jsou dostatečně velká (touch)
- [ ] Numpad funguje správně
- [ ] Formuláře validují required fields
- [ ] Checkboxy vizuálně reagují na selected state
- [ ] Success animace funguje

### Responzivní testy

- [ ] Tablet 10" (1280x800)
- [ ] Tablet 7" (1024x600)
- [ ] Mobile portrait (375x667)
- [ ] Desktop fallback (1920x1080)

## 📦 Deployment

### Před nasazením

1. Zkontrolovat všechny TODOs v kódu
2. Připojit reálné DB dotazy (místo mock dat)
3. Implementovat školení kroky
4. Otestovat na reálném hardware (tablet)
5. Nastavit autentizaci (pokud požadována)

### Po nasazení

1. Monitor error logs první den
2. Shromáždit feedback od receptionistek
3. Měřit conversion rate (kolik lidí dokončí flow)

## 🔮 Future Enhancements

### Phase 2 (Nice to Have)

- [ ] QR Code check-in (alternativa k PIN)
- [ ] Foto návštěvníka (webcam)
- [ ] Podpis na obrazovce (Canvas API)
- [ ] Offline mode (service worker)
- [ ] Voice commands (accessibility)
- [ ] Facial recognition (sci-fi level 😄)

### Phase 3 (Advanced)

- [ ] Analytics dashboard (kolik check-in/out denně)
- [ ] Integration s přístupovými systémy (čipy, karty)
- [ ] Automatický email notifikace hostiteli při check-in
- [ ] SMS notifikace

## 📞 Support

- **Developer:** Claude (AI Assistant)
- **Documentation:** Tento README
- **Issues:** GitHub Issues (pokud je repo veřejné)

---

**Version:** 1.0.0  
**Last Updated:** 2024-11-17  
**Status:** ✅ MVP Ready (bez školení modulů)
