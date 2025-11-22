# 🔐 Nonce Security Guide

**SAW Visitors Plugin - Developer Manual**  
**Version:** 1.0.0  
**Last Updated:** 22. listopadu 2024  
**Target Audience:** Plugin Developers

---

## 📋 Obsah

1. [Co je Nonce a proč ho používat](#1-co-je-nonce-a-proč-ho-používat)
2. [Unified Nonce System v SAW Visitors](#2-unified-nonce-system-v-saw-visitors)
3. [Kdy použít jaký nonce](#3-kdy-použít-jaký-nonce)
4. [Praktické příklady](#4-praktické-příklady)
5. [Common Mistakes & How to Fix](#5-common-mistakes--how-to-fix)
6. [Quick Reference Card](#6-quick-reference-card)
7. [Testing & Debugging](#7-testing--debugging)

---

## 1. Co je Nonce a proč ho používat

### 1.1 Definice

**Nonce** = **N**umber used **ONCE** (číslo použité pouze jednou)

Je to **bezpečnostní token**, který:
- ✅ Ověřuje, že požadavek přišel z našeho webu
- ✅ Chrání před CSRF (Cross-Site Request Forgery) útoky
- ✅ Má omezenou platnost (default 24 hodin)
- ✅ Je vázán na konkrétního uživatele a jeho session

### 1.2 Jak nonce funguje

```
┌─────────────────────────────────────────────────────────┐
│ 1. SERVER VYGENERUJE NONCE                              │
│    wp_create_nonce('saw_ajax_nonce')                    │
│    → Výstup: "a1b2c3d4e5"                               │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│ 2. NONCE SE POŠLE DO JAVASCRIPTU                        │
│    wp_localize_script('saw-app', 'sawGlobal', [         │
│        'nonce' => wp_create_nonce('saw_ajax_nonce')     │
│    ]);                                                   │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│ 3. JAVASCRIPT POUŽIJE NONCE V AJAX REQUESTU             │
│    $.ajax({                                              │
│        data: {                                           │
│            nonce: sawGlobal.nonce                        │
│        }                                                 │
│    });                                                   │
└─────────────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────┐
│ 4. SERVER OVĚŘÍ NONCE                                   │
│    saw_verify_ajax_unified();                           │
│    → Pokud platný: pokračuj                             │
│    → Pokud neplatný: wp_send_json_error()               │
└─────────────────────────────────────────────────────────┘
```

### 1.3 Proč je to důležité

**Bez nonce:**
```javascript
// ❌ NEBEZPEČNÉ - útočník může poslat falešný request
fetch('/wp-admin/admin-ajax.php', {
    body: new URLSearchParams({
        action: 'saw_delete_companies',
        id: 123
    })
});
// → Server má smazat firmu bez ověření!
```

**S nonce:**
```javascript
// ✅ BEZPEČNÉ - pouze autorizovaní uživatelé mají platný nonce
fetch('/wp-admin/admin-ajax.php', {
    body: new URLSearchParams({
        action: 'saw_delete_companies',
        id: 123,
        nonce: sawGlobal.nonce  // ← Ověřitelný token
    })
});
```

---

## 2. Unified Nonce System v SAW Visitors

### 2.1 Koncept unified nonce

V SAW Visitors pluginu používáme **centralizovaný systém** s jedním hlavním nonce pro všechny běžné AJAX operace.

**Proč unified?**
- ✅ Jednodušší údržba - 1 místo místo 50+
- ✅ Konzistence napříč pluginem
- ✅ Méně chyb - nelze použít špatný nonce
- ✅ Snadnější onboarding nových vývojářů

### 2.2 Struktura unified nonce systemu

```
┌──────────────────────────────────────────────────────────┐
│                    CENTRÁLNÍ NONCE                       │
│                   saw_ajax_nonce                         │
│                                                          │
│  Použití: 95% všech AJAX requestů v pluginu            │
└──────────────────────────────────────────────────────────┘
                          │
        ┌─────────────────┼─────────────────┐
        ▼                 ▼                 ▼
   ┌─────────┐      ┌──────────┐     ┌──────────┐
   │ Moduly  │      │Components│     │  Core    │
   │         │      │          │     │          │
   │Companies│      │Selectbox │     │Settings  │
   │Visits   │      │Search    │     │Branches  │
   │Visitors │      │Upload    │     │Users     │
   │...      │      │...       │     │...       │
   └─────────┘      └──────────┘     └──────────┘
```

### 2.3 Speciální nonces (výjimky)

Některé operace **MUSÍ** mít vlastní nonce z bezpečnostních důvodů:

| Nonce Action | Použití | Důvod |
|--------------|---------|-------|
| `saw_upload_file` | File upload | Separace upload práv |
| `saw_terminal_search` | Terminal search | Public endpoint |
| `saw_terminal_step` | Terminal navigation | Public endpoint |
| `saw_content_action` | Content module | Sensitive operations |
| `saw_set_password` | Password reset | Security critical |
| `saw_customer_modal_nonce` | Customer switcher | Super admin only |

---

## 3. Kdy použít jaký nonce

### 3.1 Decision Tree

```
Přidávám nový AJAX handler?
│
├─ Je to běžná CRUD operace? (create/read/update/delete)
│  └─ ANO → Použij saw_verify_ajax_unified()
│
├─ Je to file upload?
│  └─ ANO → Použij saw_upload_file nonce
│
├─ Je to terminal endpoint?
│  └─ ANO → Použij saw_terminal_* nonce
│
├─ Je to content management?
│  └─ ANO → Použij saw_content_action nonce
│
└─ Je to password/auth operace?
   └─ ANO → Použij saw_set_password nonce
```

### 3.2 PHP Backend - Ověření nonce

#### ✅ SPRÁVNĚ: Unified nonce pro běžné AJAX

```php
<?php
/**
 * AJAX handler pro smazání firmy
 */
public function ajax_delete_company() {
    // ✅ POUŽIJ unified verifier
    saw_verify_ajax_unified();
    
    // Permission check
    if (!$this->can('delete')) {
        wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
        return;
    }
    
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    // ... rest of logic
    
    wp_send_json_success(['message' => 'Firma smazána']);
}
```

#### ❌ ŠPATNĚ: Ruční nonce verification

```php
<?php
// ❌ NEPOUŽÍVEJ - zastaralý přístup
public function ajax_delete_company() {
    check_ajax_referer('saw_ajax_nonce', 'nonce');  // ← Špatně!
    
    // ... rest of logic
}

// ❌ NEPOUŽÍVEJ - custom nonce bez důvodu
public function ajax_delete_company() {
    check_ajax_referer('saw_delete_company_nonce', 'nonce');  // ← Zbytečné!
    
    // ... rest of logic
}
```

### 3.3 JavaScript Frontend - Odeslání nonce

#### ✅ SPRÁVNĚ: Použití globálního nonce

```javascript
// ✅ AJAX request s unified nonce
$.ajax({
    url: sawGlobal.ajaxurl,
    type: 'POST',
    data: {
        action: 'saw_delete_companies',
        nonce: sawGlobal.nonce,  // ← Vždy sawGlobal.nonce
        id: companyId
    },
    success: function(response) {
        console.log('Success:', response);
    }
});

// ✅ Fetch API varianta
fetch(sawGlobal.ajaxurl, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
        action: 'saw_delete_companies',
        nonce: sawGlobal.nonce,  // ← Vždy sawGlobal.nonce
        id: companyId
    })
})
.then(r => r.json())
.then(data => console.log(data));
```

#### ❌ ŠPATNĚ: Hardcoded nebo inline nonce

```javascript
// ❌ NEPOUŽÍVEJ - hardcoded nonce z PHP
fetch(ajaxurl, {
    body: new URLSearchParams({
        action: 'saw_delete_companies',
        nonce: '<?php echo wp_create_nonce('saw_ajax_nonce'); ?>',  // ← Špatně!
        id: companyId
    })
});

// ❌ NEPOUŽÍVEJ - custom module-specific nonce
fetch(ajaxurl, {
    body: new URLSearchParams({
        action: 'saw_delete_companies',
        nonce: sawCompanies.nonce,  // ← Deprecated!
        id: companyId
    })
});
```

### 3.4 POST Forms - Admin referer

Pro **normální POST formuláře** (ne AJAX) použij WordPress admin referer:

```php
<?php
// ✅ V HTML formuláři
<form method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
    <?php 
    // Vygeneruje hidden field s nonce
    wp_nonce_field('saw_create_company', '_wpnonce'); 
    ?>
    
    <input type="text" name="company_name" required>
    <button type="submit">Vytvořit</button>
</form>

// ✅ V PHP handleru
public function handle_create_post() {
    // Ověř nonce
    check_admin_referer('saw_create_company', '_wpnonce');
    
    // Process form
    $company_name = sanitize_text_field($_POST['company_name']);
    
    // ... create logic
}
```

---

## 4. Praktické příklady

### 4.1 Příklad: Nový modul s AJAX

Vytváříš nový modul `Products`:

#### Krok 1: Controller s AJAX handlerem

```php
<?php
/**
 * Products Module Controller
 */
class SAW_Module_Products_Controller extends SAW_Base_Controller 
{
    use SAW_AJAX_Handlers;  // ← Trait má unified nonce
    
    public function __construct() {
        // Register AJAX actions
        add_action('wp_ajax_saw_delete_products', array($this, 'ajax_delete'));
        add_action('wp_ajax_saw_search_products', array($this, 'ajax_search'));
    }
    
    // ajax_delete() a ajax_search() jsou v traitu
    // a již používají saw_verify_ajax_unified()
    
    /**
     * Custom AJAX handler
     */
    public function ajax_export_products() {
        // ✅ KROK 1: Ověř nonce
        saw_verify_ajax_unified();
        
        // ✅ KROK 2: Ověř permissions
        if (!$this->can('export')) {
            wp_send_json_error(['message' => 'Nedostatečná oprávnění']);
            return;
        }
        
        // ✅ KROK 3: Business logic
        $products = $this->model->get_all();
        $csv = $this->generate_csv($products);
        
        // ✅ KROK 4: Response
        wp_send_json_success([
            'csv' => $csv,
            'count' => count($products)
        ]);
    }
}
```

#### Krok 2: JavaScript

```javascript
// assets/js/modules/products/products.js

(function($) {
    'use strict';
    
    $(document).ready(function() {
        initProductsModule();
    });
    
    function initProductsModule() {
        // Export button
        $('#export-products-btn').on('click', exportProducts);
    }
    
    function exportProducts() {
        // ✅ Použij sawGlobal.nonce
        $.ajax({
            url: sawGlobal.ajaxurl,
            type: 'POST',
            data: {
                action: 'saw_export_products',
                nonce: sawGlobal.nonce  // ← Unified nonce
            },
            success: function(response) {
                if (response.success) {
                    downloadCSV(response.data.csv);
                }
            },
            error: function(xhr) {
                if (xhr.status === 403) {
                    alert('Nonce verification failed. Please refresh the page.');
                }
            }
        });
    }
    
})(jQuery);
```

### 4.2 Příklad: File Upload (speciální nonce)

```php
<?php
/**
 * File Upload Handler
 */
class SAW_File_Upload_Handler {
    
    public function ajax_upload_document() {
        // ✅ File upload má vlastní nonce
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        
        if (!wp_verify_nonce($nonce, 'saw_upload_file')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }
        
        // Check file
        if (!isset($_FILES['file'])) {
            wp_send_json_error(['message' => 'No file uploaded']);
            return;
        }
        
        // Upload logic...
    }
}
```

```javascript
// JavaScript pro file upload
function uploadFile(file) {
    const formData = new FormData();
    formData.append('action', 'saw_upload_document');
    formData.append('file', file);
    
    // ✅ File upload používá speciální nonce
    formData.append('nonce', sawGlobal.uploadNonce);  // ← Ne sawGlobal.nonce!
    
    fetch(sawGlobal.ajaxurl, {
        method: 'POST',
        body: formData  // FormData, ne URLSearchParams
    })
    .then(r => r.json())
    .then(data => console.log(data));
}
```

### 4.3 Příklad: Terminal (public endpoint)

```php
<?php
/**
 * Terminal Search - Public endpoint
 */
public function ajax_terminal_search() {
    // ✅ Terminal má vlastní nonce (může být nopriv)
    $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
    
    if (!wp_verify_nonce($nonce, 'saw_terminal_search')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        return;
    }
    
    // Search logic...
}

// Registrace - pozor na nopriv!
add_action('wp_ajax_saw_terminal_search', [$this, 'ajax_terminal_search']);
add_action('wp_ajax_nopriv_saw_terminal_search', [$this, 'ajax_terminal_search']);
```

---

## 5. Common Mistakes & How to Fix

### 5.1 Chyba: Nonce verification failed

**Příznaky:**
- AJAX request vrací 403 error
- Console: "Nonce verification failed"
- Backend audit log: "ajax_nonce_failed"

**Možné příčiny a řešení:**

#### A) Nesprávný nonce action

```php
// ❌ ŠPATNĚ
check_ajax_referer('saw_admin_nonce', 'nonce');  // ← Jiný action!

// ✅ SPRÁVNĚ
saw_verify_ajax_unified();  // ← Vždy stejný action
```

#### B) Chybějící nonce v JavaScriptu

```javascript
// ❌ ŠPATNĚ - zapomněl jsi nonce
$.ajax({
    data: {
        action: 'saw_delete_companies',
        id: 123
        // ← Chybí nonce!
    }
});

// ✅ SPRÁVNĚ
$.ajax({
    data: {
        action: 'saw_delete_companies',
        nonce: sawGlobal.nonce,  // ← Přidej nonce
        id: 123
    }
});
```

#### C) Nesprávný nonce v JavaScriptu

```javascript
// ❌ ŠPATNĚ - používáš deprecated module nonce
$.ajax({
    data: {
        nonce: sawCompanies.nonce  // ← Deprecated!
    }
});

// ✅ SPRÁVNĚ - vždy sawGlobal
$.ajax({
    data: {
        nonce: sawGlobal.nonce  // ← Unified nonce
    }
});
```

#### D) Vypršela platnost (24h)

**Problém:** Uživatel nechal stránku otevřenou přes noc.

**Řešení:**
```javascript
// ✅ Graceful error handling
$.ajax({
    // ... your ajax
    error: function(xhr, status, error) {
        if (xhr.status === 403) {
            // Nonce expired - inform user
            if (confirm('Session expired. Reload page?')) {
                window.location.reload();
            }
        }
    }
});
```

### 5.2 Chyba: sawGlobal is not defined

**Příznaky:**
- JavaScript error: "sawGlobal is not defined"
- AJAX nefunguje vůbec

**Příčiny a řešení:**

```php
// ❌ ŠPATNĚ - zapomněl jsi enqueue saw-app
wp_enqueue_script('my-module', ..., ['jquery']);  // ← Chybí saw-app!

// ✅ SPRÁVNĚ
wp_enqueue_script('my-module', ..., ['jquery', 'saw-app']);  // ←saw-app je dependency
```

V `class-asset-loader.php`:
```php
// ✅ saw-app vytváří sawGlobal
wp_localize_script('saw-app', 'sawGlobal', [
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('saw_ajax_nonce'),  // ← Unified nonce
    // ...
]);
```

### 5.3 Chyba: Mixing nonce types

```php
// ❌ ŠPATNĚ - mixing AJAX a POST nonce
public function handle_form() {
    // Tohle je POST form, ne AJAX!
    saw_verify_ajax_unified();  // ← Špatný typ nonce!
    
    // ...
}

// ✅ SPRÁVNĚ - použij admin referer
public function handle_form() {
    check_admin_referer('saw_create_company', '_wpnonce');
    
    // ...
}
```

---

## 6. Quick Reference Card

### 6.1 Kdy použít co

| Situace | Backend PHP | Frontend JS |
|---------|-------------|-------------|
| **AJAX CRUD** | `saw_verify_ajax_unified()` | `sawGlobal.nonce` |
| **POST Form** | `check_admin_referer('action', '_wpnonce')` | `<?php wp_nonce_field('action'); ?>` |
| **File Upload** | `wp_verify_nonce($nonce, 'saw_upload_file')` | `sawGlobal.uploadNonce` |
| **Terminal** | `wp_verify_nonce($nonce, 'saw_terminal_*')` | Custom nonce |
| **Content** | `wp_verify_nonce($nonce, 'saw_content_action')` | Custom nonce |

### 6.2 Code Templates

#### Template: Basic AJAX Handler

```php
<?php
public function ajax_my_action() {
    // 1. Verify nonce
    saw_verify_ajax_unified();
    
    // 2. Check permissions
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission denied']);
        return;
    }
    
    // 3. Validate input
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        wp_send_json_error(['message' => 'Invalid ID']);
        return;
    }
    
    // 4. Business logic
    $result = $this->do_something($id);
    
    // 5. Response
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
        return;
    }
    
    wp_send_json_success(['message' => 'Success', 'data' => $result]);
}
```

#### Template: AJAX JavaScript

```javascript
function myAjaxAction(id) {
    $.ajax({
        url: sawGlobal.ajaxurl,
        type: 'POST',
        data: {
            action: 'saw_my_action',
            nonce: sawGlobal.nonce,
            id: id
        },
        success: function(response) {
            if (response.success) {
                console.log('Success:', response.data);
            } else {
                alert('Error: ' + response.data.message);
            }
        },
        error: function(xhr, status, error) {
            if (xhr.status === 403) {
                alert('Security check failed. Please refresh the page.');
            } else {
                alert('AJAX error: ' + error);
            }
        }
    });
}
```

### 6.3 Helper Functions

```php
<?php
/**
 * Unified AJAX nonce verification
 * 
 * @since 5.1.0
 * @return void Dies with JSON error if verification fails
 */
function saw_verify_ajax_unified() {
    saw_verify_ajax_nonce('saw_ajax_nonce');
}

/**
 * Verify AJAX nonce
 * 
 * @since 1.0.0
 * @param string $action Action name for nonce verification
 * @return void
 */
function saw_verify_ajax_nonce($action) {
    $nonce = isset($_POST['nonce']) 
        ? sanitize_text_field(wp_unslash($_POST['nonce'])) 
        : '';

    if (!wp_verify_nonce($nonce, $action)) {
        // Log failure
        if (class_exists('SAW_Audit')) {
            SAW_Audit::log([
                'action'     => 'ajax_nonce_failed',
                'details'    => sprintf('Invalid nonce for action: %s', $action),
                'ip_address' => saw_get_client_ip(),
            ]);
        }

        wp_send_json_error([
            'message' => __('Security check failed. Please refresh the page.', 'saw-visitors'),
            'code'    => 'nonce_failed',
        ]);
    }
}
```

---

## 7. Testing & Debugging

### 7.1 Debugging Checklist

Když nonce nefunguje, projdi tento checklist:

```
□ 1. Je sawGlobal definovaný?
     → Console: console.log(sawGlobal)
     
□ 2. Má sawGlobal.nonce hodnotu?
     → Console: console.log(sawGlobal.nonce)
     
□ 3. Posílá JavaScript nonce v requestu?
     → Network tab: zkontroluj Form Data
     
□ 4. Používá backend správný verifier?
     → PHP: saw_verify_ajax_unified()
     
□ 5. Je action správně?
     → saw_ajax_nonce (ne saw_admin_nonce!)
     
□ 6. Je saw-app enqueued před modulem?
     → wp_enqueue_script dependencies
     
□ 7. Je uživatel přihlášený?
     → wp_doing_ajax() + current_user_can()
```

### 7.2 Debug Logging

Přidej debug logging do AJAX handleru:

```php
<?php
public function ajax_delete_company() {
    // Debug: Log all POST data
    if (defined('SAW_DEBUG') && SAW_DEBUG) {
        error_log('[AJAX] POST data: ' . print_r($_POST, true));
        error_log('[AJAX] Nonce value: ' . ($_POST['nonce'] ?? 'MISSING'));
        error_log('[AJAX] Current user: ' . get_current_user_id());
    }
    
    saw_verify_ajax_unified();
    
    // ... rest of code
}
```

Do `wp-config.php` přidej:

```php
define('SAW_DEBUG', true);
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Pak sleduj `/wp-content/debug.log`

### 7.3 Browser Console Testing

```javascript
// Test 1: Ověř sawGlobal
console.log('sawGlobal:', sawGlobal);
// Expected: {ajaxurl: "...", nonce: "a1b2c3...", ...}

// Test 2: Ověř nonce hodnotu
console.log('Nonce:', sawGlobal.nonce);
// Expected: "a1b2c3d4e5" (10 chars)

// Test 3: Manuální AJAX test
fetch(sawGlobal.ajaxurl, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
        action: 'saw_delete_companies',
        nonce: sawGlobal.nonce,
        id: 1
    })
})
.then(r => r.json())
.then(data => console.log('Response:', data));

// Expected: {success: true, data: {...}}
// Pokud nonce failed: {success: false, data: {code: "nonce_failed"}}
```

### 7.4 Network Tab Analysis

Chrome DevTools → Network → Zkontroluj AJAX request:

```
Request URL: /wp-admin/admin-ajax.php
Request Method: POST
Status Code: 200 OK

Form Data:
  action: saw_delete_companies
  nonce: a1b2c3d4e5           ← ✅ Musí být přítomen
  id: 123

Response:
  {"success":true,"data":{...}}
```

Pokud Status Code = 403:
```
Response:
  {"success":false,"data":{"message":"Security check failed","code":"nonce_failed"}}
```

→ Nonce je neplatný nebo chybí!

---

## 8. Migrace ze starého systému

### 8.1 Před unified nonce (deprecated)

```php
// ❌ DEPRECATED - každý modul měl vlastní nonce
wp_localize_script('saw-module-companies', 'sawCompanies', [
    'nonce' => wp_create_nonce('saw_companies_ajax')
]);

// JavaScript
$.ajax({
    data: {
        nonce: sawCompanies.nonce  // ← Module-specific
    }
});

// PHP
check_ajax_referer('saw_companies_ajax', 'nonce');
```

### 8.2 Po unified nonce (current)

```php
// ✅ CURRENT - jeden globální nonce
wp_localize_script('saw-app', 'sawGlobal', [
    'nonce' => wp_create_nonce('saw_ajax_nonce')
]);

// JavaScript
$.ajax({
    data: {
        nonce: sawGlobal.nonce  // ← Unified
    }
});

// PHP
saw_verify_ajax_unified();
```

### 8.3 Migration Checklist

Pokud refactoruješ starý kód:

```
□ 1. PHP Backend
     ✓ Nahraď check_ajax_referer() → saw_verify_ajax_unified()
     ✓ Odstraň custom nonce actions
     
□ 2. JavaScript Frontend
     ✓ Nahraď sawModuleName.nonce → sawGlobal.nonce
     ✓ Odstraň wp_localize_script pro module nonce
     
□ 3. Asset Loader
     ✓ Odstraň nonce z wp_localize_script pro moduly
     ✓ Zachovej pouze sawGlobal.nonce v saw-app
     
□ 4. Testing
     ✓ Otestuj všechny AJAX operace
     ✓ Zkontroluj console errors
     ✓ Ověř Network tab requests
```

---

## 9. Security Best Practices

### 9.1 Nonce není autentizace

```php
// ❌ ŠPATNĚ - nonce sám o sobě nestačí
public function ajax_delete_all_data() {
    saw_verify_ajax_unified();
    
    // Tady smažeš všechna data bez permission check!
    $this->delete_everything();  // ← NEBEZPEČNÉ!
}

// ✅ SPRÁVNĚ - vždy check permissions
public function ajax_delete_all_data() {
    saw_verify_ajax_unified();
    
    // Check permissions FIRST
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
        return;
    }
    
    $this->delete_everything();
}
```

### 9.2 Vždy sanitize input

```php
// ❌ ŠPATNĚ - používáš raw POST data
public function ajax_search() {
    saw_verify_ajax_unified();
    
    $search = $_POST['search'];  // ← SQL injection risk!
    $results = $wpdb->get_results("SELECT * FROM table WHERE name LIKE '%$search%'");
}

// ✅ SPRÁVNĚ - sanitize + prepare
public function ajax_search() {
    saw_verify_ajax_unified();
    
    $search = isset($_POST['search']) 
        ? sanitize_text_field(wp_unslash($_POST['search']))  // ← Sanitize
        : '';
    
    $results = $wpdb->get_results($wpdb->prepare(  // ← Prepare
        "SELECT * FROM table WHERE name LIKE %s",
        '%' . $wpdb->esc_like($search) . '%'
    ));
}
```

### 9.3 Rate limiting

Pro citlivé operace přidej rate limiting:

```php
public function ajax_send_email() {
    saw_verify_ajax_unified();
    
    // Check rate limit
    saw_ajax_rate_limit('send_email', 5, 60);  // Max 5 requests per 60s
    
    // Send email...
}
```

### 9.4 Audit logging

Loguj kritické operace:

```php
public function ajax_delete_company() {
    saw_verify_ajax_unified();
    
    $id = intval($_POST['id']);
    
    // Delete
    $result = $this->model->delete($id);
    
    // Log action
    if (class_exists('SAW_Audit')) {
        SAW_Audit::log([
            'action' => 'company_deleted',
            'entity_type' => 'company',
            'entity_id' => $id,
            'user_id' => get_current_user_id(),
            'ip_address' => saw_get_client_ip(),
        ]);
    }
    
    wp_send_json_success();
}
```

---

## 10. FAQ

### Q: Proč ne používáme WordPress REST API?

**A:** SAW Visitors je legacy plugin s established AJAX architecture. REST API má své výhody, ale:
- Vyžaduje kompletní refactor (tisíce řádků kódu)
- AJAX s nonce je proven solution
- Performance rozdíl je minimální
- Unified nonce system je dostatečně bezpečný

### Q: Můžu mít více unified nonces?

**A:** Technicky ano, ale **nedoporučujeme**. Účel unified nonce je mít JEDEN centrální nonce. Pokud potřebuješ speciální nonce (upload, terminal), ten má vlastní action, ale není "unified".

### Q: Co když uživatel má otevřeno více tabů?

**A:** Nonce je **session-based**, ne **page-based**. Funguje ve všech tabech současně. Když vyprší v jednom tabu, vyprší ve všech.

### Q: Jak často se nonce mění?

**A:** WordPress nonce má default lifetime **24 hodin**. Ale není to hard limit - WordPress používá "tick" system (12h bloky). Nonce je platný pokud:
- Je z aktuálního ticku (0-12h starý)
- Nebo je z předchozího ticku (12-24h starý)

### Q: Můžu nonce použít vícekrát?

**A:** **ANO!** Navzdory názvu "number used ONCE", WordPress nonce **NENÍ** one-time token. Můžeš ho použít opakovaně během jeho lifetime. To umožňuje:
- Retry failed requests
- Stejný nonce pro více AJAX calls
- Background syncs

### Q: Co když potřebuji one-time token?

**A:** Pro critical operations (password reset, delete account) použij **transient-based token**:

```php
// Generate one-time token
$token = wp_generate_password(32, false);
set_transient('saw_delete_account_' . $user_id, $token, HOUR_IN_SECONDS);

// Verify and delete
$stored_token = get_transient('saw_delete_account_' . $user_id);
if ($token === $stored_token) {
    delete_transient('saw_delete_account_' . $user_id);  // One-time use
    // Proceed...
}
```

---

## 11. Závěr

### ✅ Golden Rules

1. **VŽDY** použij `saw_verify_ajax_unified()` pro běžné AJAX
2. **VŽDY** použij `sawGlobal.nonce` v JavaScriptu
3. **NIKDY** netvořte custom nonce bez důvodu
4. **VŽDY** check permissions PO nonce verification
5. **VŽDY** sanitize input data

### 📚 Další zdroje

- **WordPress Codex:** https://codex.wordpress.org/WordPress_Nonces
- **Plugin Developer Handbook:** https://developer.wordpress.org/plugins/security/nonces/
- **SAW Visitors GitHub:** Internal repository

### 🆘 Potřebuješ pomoc?

1. Zkontroluj tento guide
2. Projdi debugging checklist
3. Zkontroluj audit logy
4. Kontaktuj senior developer

---

**Happy Coding! 🚀**

*This document is maintained by the SAW Visitors development team.*  
*Last updated: November 22, 2024*