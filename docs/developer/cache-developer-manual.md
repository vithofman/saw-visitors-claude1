# 💾 Cache System Guide

**SAW Visitors Plugin - Developer Manual**  
**Version:** 1.0.0  
**Last Updated:** 22. listopadu 2024  
**Target Audience:** Plugin Developers

---

## 📋 Obsah

1. [Co je Cache a proč ho používat](#1-co-je-cache-a-proč-ho-používat)
2. [SAW_Cache - 3-vrstvý systém](#2-saw_cache---3-vrstvý-systém)
3. [Kdy cachovat a kdy ne](#3-kdy-cachovat-a-kdy-ne)
4. [Praktické příklady](#4-praktické-příklady)
5. [Common Mistakes & How to Fix](#5-common-mistakes--how-to-fix)
6. [Quick Reference Card](#6-quick-reference-card)
7. [Testing & Debugging](#7-testing--debugging)
8. [Performance Guidelines](#8-performance-guidelines)

---

## 1. Co je Cache a proč ho používat

### 1.1 Definice

**Cache** = **dočasné úložiště** často používaných dat pro rychlý přístup.

Místo opakovaných dotazů do databáze:
- ✅ Uložíš výsledek do paměti/Redis/databáze
- ✅ Další requesty čtou z cache (10-100x rychlejší)
- ✅ Cache má omezenou platnost (TTL - Time To Live)
- ✅ Při změně dat se cache invaliduje (smaže)

### 1.2 Jak cache funguje

```
┌─────────────────────────────────────────────────────────┐
│ REQUEST 1 - CACHE MISS (data nejsou v cache)           │
├─────────────────────────────────────────────────────────┤
│ 1. Controller: Potřebuji data pro company ID=5         │
│ 2. Model: Zkontroluju cache... NOT FOUND               │
│ 3. Model: Dotaz do DB (200ms)                          │
│ 4. Model: Uložím do cache (5ms)                        │
│ 5. Return data → Controller                            │
│                                                         │
│ TOTAL: ~205ms                                           │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ REQUEST 2 - CACHE HIT (data jsou v cache)              │
├─────────────────────────────────────────────────────────┤
│ 1. Controller: Potřebuji data pro company ID=5         │
│ 2. Model: Zkontroluju cache... FOUND!                  │
│ 3. Return data → Controller (bez DB dotazu)            │
│                                                         │
│ TOTAL: ~2ms                                             │
└─────────────────────────────────────────────────────────┘

🚀 ZRYCHLENÍ: 205ms → 2ms (100x rychlejší!)
```

### 1.3 Proč je to důležité

**Bez cache:**
```php
// ❌ POMALÉ - 10 uživatelů = 10 DB dotazů
public function get_all_companies() {
    global $wpdb;
    return $wpdb->get_results("SELECT * FROM saw_companies");  
    // → 200ms pokaždé
}
```

**S cache:**
```php
// ✅ RYCHLÉ - 10 uživatelů = 1 DB dotaz + 9 cache hits
public function get_all_companies() {
    $cached = SAW_Cache::get('companies_list', 'companies');
    if ($cached !== false) {
        return $cached;  // → 2ms
    }
    
    global $wpdb;
    $data = $wpdb->get_results("SELECT * FROM saw_companies");
    SAW_Cache::set('companies_list', $data, 300, 'companies');
    
    return $data;  // → 200ms (pouze první request)
}
```

**Výsledek:** Server utáhne 10x více uživatelů při stejném HW! 🎯

---

## 2. SAW_Cache - 3-vrstvý systém

### 2.1 Architektura

SAW Visitors používá **inteligentní 3-vrstvou cache** s fallbackem:

```
┌──────────────────────────────────────────────────────────┐
│               LAYER 1: MEMORY CACHE                      │
│           (Static PHP array - fastest)                   │
│                                                          │
│  • Rychlost: <1ms                                       │
│  • Platnost: Jeden HTTP request                        │
│  • Kapacita: ~10MB RAM                                 │
│  • Use case: Multiple stejné query v requestu          │
└──────────────────────────────────────────────────────────┘
                          │
                    MISS  ▼
┌──────────────────────────────────────────────────────────┐
│            LAYER 2: OBJECT CACHE                        │
│        (Redis/Memcached - very fast)                    │
│                                                          │
│  • Rychlost: 1-5ms                                      │
│  • Platnost: Konfigurovat (default 5min)               │
│  • Kapacita: ~500MB RAM                                │
│  • Use case: Sdílení mezi requesty                    │
└──────────────────────────────────────────────────────────┘
                          │
                    MISS  ▼
┌──────────────────────────────────────────────────────────┐
│             LAYER 3: TRANSIENTS                         │
│          (WordPress DB - fallback)                      │
│                                                          │
│  • Rychlost: 10-50ms                                    │
│  • Platnost: Konfigurovat (default 5-60min)            │
│  • Kapacita: Neomezená (DB)                            │
│  • Use case: Když Redis/Memcached nedostupný           │
└──────────────────────────────────────────────────────────┘
```

### 2.2 Automatický fallback

```php
// ✅ SAW_Cache automaticky:
SAW_Cache::get('key', 'group');

// 1. Zkusí Memory Cache → HIT? Return (1ms)
// 2. MISS → Zkusí Object Cache → HIT? Return (3ms)
// 3. MISS → Zkusí Transient → HIT? Return (15ms)
// 4. MISS → Return false (musíš načíst z DB a set cache)
```

**Žádná konfigurace není potřeba** - SAW_Cache detekuje dostupnost Redis/Memcached automaticky!

### 2.3 Cache Groups (organizace)

Cache je organizovaná do **groups** (namespaces):

| Group | Použití | TTL | Příklad |
|-------|---------|-----|---------|
| `companies` | Company data | 300s | `companies_list_page1` |
| `visits` | Visit records | 300s | `visits_detail_123` |
| `visitors` | Visitor data | 300s | `visitors_list_active` |
| `users` | SAW user data | 1800s | `users_list_branch5` |
| `lookups` | Reference data | 3600s | `lookup_account_types` |
| `branches` | Branch data | 600s | `branches_customer10` |

**Výhoda groups:**
```php
// Smaž VŠECHNY companies cache najednou
SAW_Cache::flush('companies');  

// Místo:
SAW_Cache::delete('companies_list_page1', 'companies');
SAW_Cache::delete('companies_list_page2', 'companies');
SAW_Cache::delete('companies_detail_5', 'companies');
// ... 50+ klíčů
```

---

## 3. Kdy cachovat a kdy ne

### 3.1 Decision Tree

```
Přidávám novou DB operaci?
│
├─ Jsou data ČASTO ČTENÁ? (>10x za minutu)
│  └─ ANO → Použij cache
│
├─ Jsou data RELATIVNĚ STATICKÁ? (mění se <1x za hodinu)
│  └─ ANO → Použij cache
│
├─ Je dotaz POMALÝ? (>50ms)
│  └─ ANO → Určitě použij cache
│
├─ Jsou data UNIKÁTNÍ pro každý request? (např. random results)
│  └─ ANO → NECACHUJ
│
└─ Jsou data REAL-TIME CRITICAL? (např. live stock prices)
   └─ ANO → NECACHUJ (nebo velmi krátký TTL <10s)
```

### 3.2 ✅ KDY CACHOVAT

#### Příklad 1: List view s paginací
```php
// ✅ CACHUJ - stejná stránka se často opakuje
public function get_all($filters = []) {
    $cache_key = $this->get_cache_key_with_scope('list', $filters);
    $cached = SAW_Cache::get($cache_key, $this->config['entity']);
    
    if ($cached !== false) {
        return $cached;  // Hit!
    }
    
    // DB query
    $data = $wpdb->get_results(...);
    
    SAW_Cache::set($cache_key, $data, 300, $this->config['entity']);
    
    return $data;
}
```

**Proč:** Uživatelé se často vracejí na stejnou stránku seznamu.

---

#### Příklad 2: Detail view
```php
// ✅ CACHUJ - detail se zobrazuje opakovaně
public function get_by_id($id) {
    $cache_key = $this->get_cache_key_with_scope('item', $id);
    $cached = SAW_Cache::get($cache_key, $this->config['entity']);
    
    if ($cached !== false) {
        return $cached;
    }
    
    $item = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$this->table} WHERE id = %d", 
        $id
    ), ARRAY_A);
    
    SAW_Cache::set($cache_key, $item, 300, $this->config['entity']);
    
    return $item;
}
```

**Proč:** Uživatel může otevřít detail vícekrát (ctrl+click, back button, etc.)

---

#### Příklad 3: Lookup tables (referenční data)
```php
// ✅ CACHUJ - lookup data se téměř nemění
protected function load_account_types() {
    return SAW_Cache::remember(
        'account_types',
        function() {
            global $wpdb;
            return $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}saw_account_types ORDER BY name"
            , ARRAY_A);
        },
        3600,  // 1 hodina TTL
        'lookups'
    );
}
```

**Proč:** Account types se mění jednou za měsíc, ale čtou se 1000x denně.

---

### 3.3 ❌ KDY NECACHOVAT

#### Příklad 1: Live data (real-time)
```php
// ❌ NECACHUJ - data se mění každou vteřinu
public function get_active_visitors_count() {
    global $wpdb;
    
    // Vždy čerstvý dotaz (bez cache)
    return $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visit_daily_logs 
         WHERE checked_out_at IS NULL 
         AND log_date = CURDATE()"
    );
}
```

**Proč:** Počet aktivních návštěvníků se mění každou minutu.

---

#### Příklad 2: Random/unique results
```php
// ❌ NECACHUJ - každý request je jiný
public function get_random_companies($limit = 5) {
    global $wpdb;
    
    // Vždy nový náhodný výběr
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_companies 
         ORDER BY RAND() 
         LIMIT %d",
        $limit
    ), ARRAY_A);
}
```

**Proč:** RAND() vrací pokaždé jiné výsledky.

---

#### Příklad 3: User-specific sensitive data
```php
// ❌ NECACHUJ (nebo velmi krátký TTL) - bezpečnost
public function get_user_permissions($user_id) {
    global $wpdb;
    
    // Vždy čerstvý dotaz (permissions se mohou změnit)
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}saw_user_permissions 
         WHERE user_id = %d",
        $user_id
    ), ARRAY_A);
}
```

**Proč:** Když admin změní oprávnění, musí platit OKAMŽITĚ (ne až za 5 minut).

---

## 4. Praktické příklady

### 4.1 Base Model - Automatická cache

Base Model již má **vestavěnou cache** pro get_by_id a get_all:

```php
<?php
/**
 * Base Model už cachuje automaticky!
 * 
 * Stačí normálně použít parent::get_by_id()
 */
class SAW_Module_Companies_Model extends SAW_Base_Model {
    
    public function get_by_id($id) {
        // ✅ Automaticky cachováno Base Modelem
        return parent::get_by_id($id);
    }
    
    public function get_all($filters = []) {
        // ✅ Automaticky cachováno Base Modelem
        return parent::get_all($filters);
    }
}
```

**Není potřeba psát cache logiku ručně!** 🎉

---

### 4.2 Custom Methods - Manuální cache

Pro **custom metody** musíš cache přidat ručně:

```php
<?php
/**
 * Custom metoda - potřebuje manuální cache
 */
public function get_companies_with_active_visits() {
    $cache_key = 'companies_with_visits_' . SAW_Context::get_customer_id();
    
    // 1. Try cache
    $cached = SAW_Cache::get($cache_key, 'companies');
    if ($cached !== false) {
        return $cached;
    }
    
    // 2. DB query
    global $wpdb;
    $data = $wpdb->get_results(
        "SELECT c.*, COUNT(v.id) as visit_count
         FROM {$wpdb->prefix}saw_companies c
         INNER JOIN {$wpdb->prefix}saw_visits v ON c.id = v.company_id
         WHERE v.status = 'active'
         GROUP BY c.id
         ORDER BY visit_count DESC"
    , ARRAY_A);
    
    // 3. Set cache
    SAW_Cache::set($cache_key, $data, 600, 'companies');  // 10min TTL
    
    return $data;
}
```

---

### 4.3 Remember Pattern (lazy loading)

Pro **jednodušší syntax** použij `SAW_Cache::remember()`:

```php
<?php
/**
 * Remember pattern - kombinuje get+set do jednoho
 */
protected function load_branch_statistics($branch_id) {
    return SAW_Cache::remember(
        'branch_stats_' . $branch_id,
        function() use ($branch_id) {
            // Tato funkce se spustí POUZE při cache miss
            global $wpdb;
            
            return [
                'total_visits' => $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visits WHERE branch_id = %d",
                    $branch_id
                )),
                'active_visitors' => $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}saw_visitors v
                     INNER JOIN {$wpdb->prefix}saw_visits vi ON v.visit_id = vi.id
                     WHERE vi.branch_id = %d AND vi.status = 'active'",
                    $branch_id
                )),
            ];
        },
        1800,  // 30min TTL
        'branches'
    );
}
```

**Výhoda:** Méně kódu, čitelnější logic.

---

### 4.4 Cache Invalidation (kritické!)

**ZLATÉ PRAVIDLO:** Po změně dat MUSÍŠ invalidovat cache!

```php
<?php
/**
 * Base Model automaticky invaliduje po create/update/delete
 */
public function update($id, $data) {
    // ... update logic ...
    
    $result = parent::update($id, $data);
    
    // ✅ Base Model volá: $this->invalidate_cache();
    // → Všechna cache pro tento entity group se smaže
    
    return $result;
}
```

---

**Custom invalidation:**

```php
<?php
/**
 * Pro custom metody musíš invalidovat ručně
 */
public function assign_user_to_branches($user_id, $branch_ids) {
    // ... assignment logic ...
    
    // ✅ Invaliduj user cache
    SAW_Cache::flush('users');
    
    // ✅ Invaliduj branch cache (obsahuje user counts)
    SAW_Cache::flush('branches');
}
```

---

### 4.5 Scoped Cache Keys (multi-tenant)

SAW Visitors je **multi-tenant** - každý customer má izolovaná data.

**Base Model automaticky přidává scope** do cache keys:

```php
<?php
/**
 * get_cache_key_with_scope() - automaticky v Base Model
 */
protected function get_cache_key_with_scope($type, $identifier = '') {
    static $context_loaded = false;
    static $customer_id = 0;
    static $branch_id = 0;
    static $role = 'guest';
    
    if (!$context_loaded) {
        if (is_user_logged_in() && class_exists('SAW_Context')) {
            $customer_id = SAW_Context::get_customer_id() ?? 0;
            $branch_id = SAW_Context::get_branch_id() ?? 0;
            $role = SAW_Context::get_role() ?? 'guest';
        }
        $context_loaded = true;
    }
    
    // ✅ Cache key obsahuje customer_id + branch_id + role
    $key = $this->config['entity'] . '_' . $type;
    $key .= '_role_' . $role;
    $key .= '_cc' . $customer_id;
    $key .= '_cb' . $branch_id;
    
    if (!empty($identifier)) {
        if (is_array($identifier)) {
            $key .= '_' . md5(serialize($identifier));
        } else {
            $key .= '_' . $identifier;
        }
    }
    
    return $key;
}
```

**Výsledný key:**
```
companies_list_role_admin_cc10_cb25_page1
│         │    │    │     │   │    │   └─ Page 1
│         │    │    │     │   │    └───── Branch ID 25
│         │    │    │     │   └────────── Customer ID 10
│         │    │    │     └────────────── Role: admin
│         │    │    └──────────────────── Type: list
│         │    └───────────────────────── Entity: companies
│         └────────────────────────────── Group: companies
└──────────────────────────────────────── Prefix
```

**Proč:** Customer A neuvidí cache od Customer B! 🔒

---

## 5. Common Mistakes & How to Fix

### 5.1 Chyba: Zapomenutá invalidace

**Příznaky:**
- Po update vidíš stará data
- Delete nesmaže záznam z listu
- Create nezobrazí nový záznam

**Příčina:**
```php
// ❌ ŠPATNĚ - zapomněl jsi invalidovat
public function update($id, $data) {
    global $wpdb;
    
    $wpdb->update($this->table, $data, ['id' => $id]);
    
    // Chybí: $this->invalidate_cache();
    
    return true;
}
```

**Řešení:**
```php
// ✅ SPRÁVNĚ - vždy invaliduj po změně
public function update($id, $data) {
    global $wpdb;
    
    $wpdb->update($this->table, $data, ['id' => $id]);
    
    // ✅ Smaž všechnu cache pro tento entity
    $this->invalidate_cache();
    
    return true;
}
```

**Nebo použij Base Model (automatická invalidace):**
```php
// ✅ NEJLEPŠÍ - Base Model dělá invalidaci za tebe
public function update($id, $data) {
    return parent::update($id, $data);  // Automaticky invaliduje!
}
```

---

### 5.2 Chyba: Příliš dlouhý TTL

**Příznaky:**
- Data jsou "zastaralá" i po update
- Uživatel vidí starou verzi
- Refresh pomůže, ale trvá dlouho

**Příčina:**
```php
// ❌ ŠPATNĚ - 1 hodina je moc na často měněná data
SAW_Cache::set('companies_list', $data, 3600, 'companies');
```

**Řešení:**
```php
// ✅ SPRÁVNĚ - 5 minut je rozumné
SAW_Cache::set('companies_list', $data, 300, 'companies');

// Pro VELMI statická data (account types, atd):
SAW_Cache::set('account_types', $data, 3600, 'lookups');  // 1h OK
```

**Guidelines TTL:**

| Typ dat | TTL | Příklad |
|---------|-----|---------|
| **Velmi dynamická** | 60-300s | Visit logs, active visitors |
| **Běžná** | 300-600s | Companies, visits, visitors |
| **Polostatic** | 600-1800s | Users, branches, departments |
| **Téměř statická** | 1800-3600s | Lookup tables, account types |

---

### 5.3 Chyba: Cachování user-specific dat globálně

**Příznaky:**
- User A vidí data od User B
- Security leak - unauthorized data access

**Příčina:**
```php
// ❌ NEBEZPEČNÉ - cache key neobsahuje user_id!
$cache_key = 'user_permissions';
$cached = SAW_Cache::get($cache_key, 'users');

// → Všichni uživatelé sdílejí stejný cache!
```

**Řešení:**
```php
// ✅ BEZPEČNÉ - cache key obsahuje user_id
$cache_key = 'user_permissions_' . get_current_user_id();
$cached = SAW_Cache::get($cache_key, 'users');

// Nebo použij Base Model scoped keys (automaticky)
$cache_key = $this->get_cache_key_with_scope('permissions', get_current_user_id());
```

---

### 5.4 Chyba: Cache bez fallbacku

**Příznaky:**
- Na serveru bez Redis nefunguje cache vůbec
- Performance je pořád špatný

**Příčina:**
```php
// ❌ ŠPATNĚ - používáš přímo wp_cache_* (žádný fallback)
$cached = wp_cache_get('companies_list');
if ($cached) {
    return $cached;
}

// ... query ...

wp_cache_set('companies_list', $data);
```

**Řešení:**
```php
// ✅ SPRÁVNĚ - SAW_Cache má automatický fallback
$cached = SAW_Cache::get('companies_list', 'companies');
if ($cached !== false) {
    return $cached;
}

// ... query ...

SAW_Cache::set('companies_list', $data, 300, 'companies');

// → Funguje i bez Redis (použije transients)
```

---

### 5.5 Chyba: N+1 queries i s cache

**Příznaky:**
- Pořád vidíš 50+ DB queries v debug logu
- Cache hit ratio je vysoký, ale performance pořád špatný

**Příčina:**
```php
// ❌ ŠPATNĚ - cachuje jednotlivé položky, ale stále N+1
public function get_companies_with_branches() {
    $companies = $this->get_all();  // 1 query + cache
    
    foreach ($companies as &$company) {
        // ❌ N queries (i když cachované)!
        $company['branches'] = $this->branch_model->get_by_company_id($company['id']);
    }
    
    return $companies;
}
```

**Řešení:**
```php
// ✅ SPRÁVNĚ - batch load + cache
public function get_companies_with_branches() {
    $cache_key = 'companies_with_branches';
    $cached = SAW_Cache::get($cache_key, 'companies');
    
    if ($cached !== false) {
        return $cached;
    }
    
    // Jeden dotaz s JOIN
    global $wpdb;
    $data = $wpdb->get_results(
        "SELECT c.*, b.id as branch_id, b.name as branch_name
         FROM {$wpdb->prefix}saw_companies c
         LEFT JOIN {$wpdb->prefix}saw_branches b ON c.id = b.company_id
         ORDER BY c.id, b.name"
    , ARRAY_A);
    
    // Group v PHP
    $companies = [];
    foreach ($data as $row) {
        $company_id = $row['id'];
        
        if (!isset($companies[$company_id])) {
            $companies[$company_id] = [
                'id' => $row['id'],
                'name' => $row['name'],
                // ... company fields ...
                'branches' => []
            ];
        }
        
        if ($row['branch_id']) {
            $companies[$company_id]['branches'][] = [
                'id' => $row['branch_id'],
                'name' => $row['branch_name']
            ];
        }
    }
    
    $companies = array_values($companies);
    
    SAW_Cache::set($cache_key, $companies, 300, 'companies');
    
    return $companies;
}
```

---

## 6. Quick Reference Card

### 6.1 API Reference

| Metoda | Použití | Parametry |
|--------|---------|-----------|
| `SAW_Cache::get($key, $group)` | Načíst z cache | `$key` = cache key, `$group` = entity |
| `SAW_Cache::set($key, $value, $ttl, $group)` | Uložit do cache | `$ttl` = seconds, `$group` = entity |
| `SAW_Cache::delete($key, $group)` | Smazat jeden klíč | - |
| `SAW_Cache::flush($group)` | Smazat celou group | - |
| `SAW_Cache::remember($key, $callback, $ttl, $group)` | Lazy load + cache | `$callback` = function |
| `SAW_Cache::get_stats()` | Statistiky cache | - |
| `SAW_Cache::reset_stats()` | Reset statistik | - |

---

### 6.2 Code Templates

#### Template 1: Basic Cache (get/set)

```php
<?php
public function get_my_data($param) {
    // 1. Build cache key
    $cache_key = 'my_data_' . $param;
    
    // 2. Try cache
    $cached = SAW_Cache::get($cache_key, 'my_group');
    if ($cached !== false) {
        return $cached;
    }
    
    // 3. DB query
    global $wpdb;
    $data = $wpdb->get_results(/* ... */);
    
    // 4. Set cache
    SAW_Cache::set($cache_key, $data, 300, 'my_group');
    
    return $data;
}
```

---

#### Template 2: Remember Pattern

```php
<?php
protected function load_lookup_data($type) {
    return SAW_Cache::remember(
        'lookup_' . $type,
        function() use ($type) {
            global $wpdb;
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}saw_lookups WHERE type = %s",
                $type
            ), ARRAY_A);
        },
        3600,  // 1 hour
        'lookups'
    );
}
```

---

#### Template 3: Scoped Cache (Base Model)

```php
<?php
public function get_filtered_list($filters) {
    // ✅ Use Base Model helper (auto-scopes)
    $cache_key = $this->get_cache_key_with_scope('list', $filters);
    
    $cached = SAW_Cache::get($cache_key, $this->config['entity']);
    if ($cached !== false) {
        return $cached;
    }
    
    // ... query ...
    
    SAW_Cache::set($cache_key, $data, 300, $this->config['entity']);
    
    return $data;
}
```

---

#### Template 4: Invalidation After Write

```php
<?php
public function update($id, $data) {
    global $wpdb;
    
    // Update DB
    $result = $wpdb->update($this->table, $data, ['id' => $id]);
    
    if ($result === false) {
        return new WP_Error('db_error', 'Update failed');
    }
    
    // ✅ CRITICAL: Invalidate cache
    $this->invalidate_cache();  // or SAW_Cache::flush($group)
    
    return true;
}
```

---

### 6.3 TTL Recommendations

```php
<?php
// Very dynamic data (changes every minute)
SAW_Cache::set($key, $data, 60, $group);  // 1 min

// Standard data (changes every 5-10 minutes)
SAW_Cache::set($key, $data, 300, $group);  // 5 min (DEFAULT)

// Semi-static data (changes hourly)
SAW_Cache::set($key, $data, 1800, $group);  // 30 min

// Almost static data (changes daily)
SAW_Cache::set($key, $data, 3600, $group);  // 1 hour
```

---

## 7. Testing & Debugging

### 7.1 Debugging Checklist

Když cache nefunguje, projdi tento checklist:

```
□ 1. Je SAW_Cache načtený?
     → PHP: var_dump(class_exists('SAW_Cache'));
     
□ 2. Je Bootstrap loading order správný?
     → SAW_Cache PŘED Base Model?
     
□ 3. Funguje cache backend?
     → $stats = SAW_Cache::get_stats();
     → var_dump($stats['backend']);
     
□ 4. Máš správný cache key?
     → echo $cache_key; (musí být unique per data set)
     
□ 5. Je TTL rozumný?
     → 300s (5min) je default, 60s (1min) minimum
     
□ 6. Invaliduješ po write operacích?
     → create/update/delete → invalidate_cache()
     
□ 7. Je cache group správný?
     → 'companies', 'visits', ne 'saw' nebo 'global'
```

---

### 7.2 Cache Statistics

**Zobraz cache statistiky:**

```php
<?php
// V PHP (např. debug endpoint)
$stats = SAW_Cache::get_stats();

print_r($stats);
/*
Array (
    [memory_hits] => 45
    [object_hits] => 12
    [transient_hits] => 3
    [misses] => 10
    [sets] => 10
    [deletes] => 2
    [total_requests] => 70
    [hit_ratio] => 85.71  ← DŮLEŽITÉ!
    [backend] => redis
)
*/
```

**Dobrý hit ratio:**
- ✅ **>80%** = Výborně! Cache funguje perfektně
- ⚠️ **60-80%** = Dobré, ale lze zlepšit (zvýš TTL nebo oprav invalidaci)
- ❌ **<60%** = Špatně! Cache je téměř nepoužitelná (zkontroluj logic)

---

### 7.3 Debug Logging

**Přidej debug logging pro cache operations:**

```php
<?php
public function get_by_id($id) {
    $cache_key = $this->get_cache_key_with_scope('item', $id);
    
    // Debug: Log cache attempt
    if (defined('SAW_DEBUG') && SAW_DEBUG) {
        error_log("[CACHE] Attempting get: {$cache_key}");
    }
    
    $cached = SAW_Cache::get($cache_key, $this->config['entity']);
    
    if ($cached !== false) {
        // Debug: Cache hit
        if (defined('SAW_DEBUG') && SAW_DEBUG) {
            error_log("[CACHE] HIT: {$cache_key}");
        }
        return $cached;
    }
    
    // Debug: Cache miss
    if (defined('SAW_DEBUG') && SAW_DEBUG) {
        error_log("[CACHE] MISS: {$cache_key} - loading from DB");
    }
    
    // ... load from DB ...
    
    SAW_Cache::set($cache_key, $item, 300, $this->config['entity']);
    
    if (defined('SAW_DEBUG') && SAW_DEBUG) {
        error_log("[CACHE] SET: {$cache_key}");
    }
    
    return $item;
}
```

**V wp-config.php:**
```php
define('SAW_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

**Pak sleduj:** `/wp-content/debug.log`

---

### 7.4 Manual Cache Testing

**Test cache functionality:**

```php
<?php
// Test script: test-cache.php

require_once '../../../wp-load.php';

echo "=== SAW_Cache Manual Test ===\n\n";

// 1. Test SET
echo "1. Testing SET...\n";
SAW_Cache::set('test_key', 'test_value', 300, 'test');
echo "   ✓ Set: test_key = test_value\n\n";

// 2. Test GET (cache hit)
echo "2. Testing GET (should be cache hit)...\n";
$value = SAW_Cache::get('test_key', 'test');
echo "   Result: " . ($value === 'test_value' ? '✓ PASS' : '✗ FAIL') . "\n";
echo "   Value: {$value}\n\n";

// 3. Test statistics
echo "3. Cache statistics:\n";
$stats = SAW_Cache::get_stats();
foreach ($stats as $key => $val) {
    echo "   {$key}: {$val}\n";
}
echo "\n";

// 4. Test DELETE
echo "4. Testing DELETE...\n";
SAW_Cache::delete('test_key', 'test');
$value = SAW_Cache::get('test_key', 'test');
echo "   Result: " . ($value === false ? '✓ PASS (deleted)' : '✗ FAIL (still exists)') . "\n\n";

// 5. Test FLUSH
echo "5. Testing FLUSH...\n";
SAW_Cache::set('test_key_1', 'value1', 300, 'test');
SAW_Cache::set('test_key_2', 'value2', 300, 'test');
SAW_Cache::flush('test');
$val1 = SAW_Cache::get('test_key_1', 'test');
$val2 = SAW_Cache::get('test_key_2', 'test');
echo "   Result: " . (($val1 === false && $val2 === false) ? '✓ PASS' : '✗ FAIL') . "\n\n";

echo "=== Test Complete ===\n";
```

**Spuštění:**
```bash
cd wp-content/plugins/saw-visitors
php test-cache.php
```

---

### 7.5 Performance Profiling

**Změř cache performance impact:**

```php
<?php
// Benchmark script: benchmark-cache.php

require_once '../../../wp-load.php';

echo "=== Cache Performance Benchmark ===\n\n";

// Simulate expensive DB query
function expensive_query() {
    global $wpdb;
    return $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}saw_companies 
         LEFT JOIN {$wpdb->prefix}saw_branches ON companies.id = branches.company_id
         ORDER BY companies.name"
    , ARRAY_A);
}

// 1. WITHOUT cache (10 iterations)
echo "1. WITHOUT cache (10 iterations):\n";
$start = microtime(true);
for ($i = 0; $i < 10; $i++) {
    $data = expensive_query();
}
$time_without = (microtime(true) - $start) * 1000;
echo "   Time: " . number_format($time_without, 2) . " ms\n";
echo "   Avg: " . number_format($time_without / 10, 2) . " ms/query\n\n";

// 2. WITH cache (10 iterations)
echo "2. WITH cache (10 iterations):\n";
$start = microtime(true);
for ($i = 0; $i < 10; $i++) {
    $cached = SAW_Cache::get('benchmark_data', 'test');
    if ($cached === false) {
        $cached = expensive_query();
        SAW_Cache::set('benchmark_data', $cached, 300, 'test');
    }
}
$time_with = (microtime(true) - $start) * 1000;
echo "   Time: " . number_format($time_with, 2) . " ms\n";
echo "   Avg: " . number_format($time_with / 10, 2) . " ms/query\n\n";

// 3. Improvement
$improvement = (($time_without - $time_with) / $time_without) * 100;
echo "3. Improvement:\n";
echo "   " . number_format($improvement, 1) . "% faster with cache\n";
echo "   Speedup: " . number_format($time_without / $time_with, 1) . "x\n\n";

// Cleanup
SAW_Cache::delete('benchmark_data', 'test');

echo "=== Benchmark Complete ===\n";
```

**Expected output:**
```
=== Cache Performance Benchmark ===

1. WITHOUT cache (10 iterations):
   Time: 2345.67 ms
   Avg: 234.57 ms/query

2. WITH cache (10 iterations):
   Time: 25.43 ms
   Avg: 2.54 ms/query

3. Improvement:
   98.9% faster with cache
   Speedup: 92.3x

=== Benchmark Complete ===
```

---

## 8. Performance Guidelines

### 8.1 Cache vs Database Tradeoff

**Kdy je cache VÝHODNÁ:**

```
┌────────────────────────────────────────────────┐
│ VÝHODNÁ CACHE                                  │
├────────────────────────────────────────────────┤
│ • Query >50ms                                  │
│ • Read:Write ratio >10:1                       │
│ • Data se čtou opakovaně                       │
│ • Server má RAM (Redis/Memcached)             │
└────────────────────────────────────────────────┘

Examples:
✓ List views (pagination)
✓ Detail views
✓ Lookup tables
✓ User permissions
✓ Statistics/aggregations
```

**Kdy cache NENÍ výhodná:**

```
┌────────────────────────────────────────────────┐
│ NEVÝHODNÁ CACHE                                │
├────────────────────────────────────────────────┤
│ • Query <10ms                                  │
│ • Write-heavy operace                          │
│ • Unikátní data (každý request jiný)          │
│ • Real-time critical data                      │
└────────────────────────────────────────────────┘

Examples:
✗ Simple ID lookups (already fast)
✗ Random results (RAND())
✗ Live counters
✗ Audit logs (write-only)
```

---

### 8.2 Memory Usage Optimization

**Cache může zabrat hodně RAM!**

```php
<?php
// ❌ ŠPATNĚ - cachuje 10MB dat
$huge_data = $wpdb->get_results("SELECT * FROM massive_table");
SAW_Cache::set('massive_data', $huge_data, 3600, 'data');

// → 100 requestů = 1GB RAM!

// ✅ SPRÁVNĚ - cachuj pouze potřebné sloupce
$filtered_data = $wpdb->get_results(
    "SELECT id, name, status FROM massive_table"
);
SAW_Cache::set('filtered_data', $filtered_data, 3600, 'data');

// → 100 requestů = 100MB RAM (OK)
```

**Best practices:**
- ✅ Cachuj pouze **potřebná data** (ne celé tabulky)
- ✅ Používej **pagination** i v cache (per-page cache)
- ✅ Nastav **rozumný TTL** (ne 24h pro všechno)
- ✅ Monitoruj **memory usage** (Redis/Memcached stats)

---

### 8.3 Cache Warming (předčasné načtení)

Pro **kritické pages** (homepage, dashboard) přednačti cache:

```php
<?php
/**
 * Warm cache pro dashboard při každém cron run
 */
add_action('saw_daily_cron', function() {
    // Přednačti statistiky pro všechny customers
    $customers = $wpdb->get_results(
        "SELECT id FROM {$wpdb->prefix}saw_customers WHERE is_active = 1"
    );
    
    foreach ($customers as $customer) {
        // Simulate context
        $cache_key = "dashboard_stats_cc{$customer->id}";
        
        // Load & cache
        $stats = calculate_dashboard_stats($customer->id);
        SAW_Cache::set($cache_key, $stats, 3600, 'statistics');
    }
    
    error_log('[CACHE WARMING] Dashboard stats warmed for ' . count($customers) . ' customers');
});
```

**Výhoda:** První request uživatele je RYCHLÝ (cache už existuje).

---

### 8.4 Cache Stampede Prevention

**Problém:** 1000 uživatelů načte stránku současně → 1000 DB dotazů (cache expirovala).

**Řešení: Soft TTL + Lock**

```php
<?php
/**
 * Advanced: Soft expiration s lockingem
 */
public function get_cached_with_lock($key, $callback, $ttl = 300, $group = 'default') {
    $cache_key = $key;
    $lock_key = $key . '_lock';
    
    // 1. Try cache
    $cached = SAW_Cache::get($cache_key, $group);
    if ($cached !== false) {
        return $cached;
    }
    
    // 2. Try acquire lock
    $lock = SAW_Cache::get($lock_key, $group);
    if ($lock !== false) {
        // Someone else is loading, wait and retry
        usleep(100000);  // 100ms
        $cached = SAW_Cache::get($cache_key, $group);
        return $cached !== false ? $cached : $callback();  // Fallback to callback
    }
    
    // 3. Acquire lock
    SAW_Cache::set($lock_key, time(), 10, $group);  // 10s lock
    
    // 4. Load data
    $data = $callback();
    
    // 5. Set cache
    SAW_Cache::set($cache_key, $data, $ttl, $group);
    
    // 6. Release lock
    SAW_Cache::delete($lock_key, $group);
    
    return $data;
}
```

**Použití:**
```php
$data = $this->get_cached_with_lock(
    'expensive_query',
    function() {
        return expensive_database_query();
    },
    600,
    'statistics'
);
```

---

### 8.5 Monitoring & Alerts

**Production monitoring:**

```php
<?php
/**
 * Log cache statistics každou hodinu
 */
add_action('saw_hourly_cron', function() {
    $stats = SAW_Cache::get_stats();
    
    // Log to file
    error_log(sprintf(
        '[CACHE STATS] Backend: %s | Hit Ratio: %.1f%% | Memory Hits: %d | Object Hits: %d | Misses: %d',
        $stats['backend'],
        $stats['hit_ratio'],
        $stats['memory_hits'],
        $stats['object_hits'],
        $stats['misses']
    ));
    
    // Alert if hit ratio too low
    if ($stats['hit_ratio'] < 60) {
        error_log('[CACHE ALERT] Hit ratio below 60%! Investigate cache configuration.');
        
        // Send email to admin (optional)
        wp_mail(
            get_option('admin_email'),
            'SAW Cache Alert: Low Hit Ratio',
            sprintf('Cache hit ratio is only %.1f%% (threshold: 60%%)', $stats['hit_ratio'])
        );
    }
});
```

---

## 9. Advanced Topics

### 9.1 Cache Tags (future enhancement)

**Problém:** Flush invaliduje VŠECHNO v group, i když se změnil jen 1 záznam.

**Řešení: Cache Tags (v budoucí verzi)**

```php
<?php
// FUTURE: Tag-based invalidation
SAW_Cache::set('company_123_detail', $data, 300, 'companies', [
    'tags' => ['company:123', 'customer:10']
]);

SAW_Cache::set('company_list_page1', $list, 300, 'companies', [
    'tags' => ['company:list', 'customer:10']
]);

// Invalidate pouze company:123
SAW_Cache::invalidate_tag('company:123');
// → Smaže jen company_123_detail, ne list!
```

**Status:** Není implementováno (WordPress core nepodporuje tagging).

---

### 9.2 Multi-Level Caching Strategy

**Kombinuj různé cache layers pro optimální performance:**

```
┌──────────────────────────────────────────────────┐
│ LEVEL 1: Fragment Cache (HTML)                  │
│ • Cache rendered HTML chunks                    │
│ • TTL: 5min                                     │
│ • Use: Sidebar widgets, recent items            │
└──────────────────────────────────────────────────┘
                    ↓ (if miss)
┌──────────────────────────────────────────────────┐
│ LEVEL 2: Object Cache (SAW_Cache)              │
│ • Cache PHP objects/arrays                      │
│ • TTL: 5-30min                                  │
│ • Use: DB query results                         │
└──────────────────────────────────────────────────┘
                    ↓ (if miss)
┌──────────────────────────────────────────────────┐
│ LEVEL 3: Database Query Cache                   │
│ • MySQL query cache                             │
│ • TTL: Variable                                 │
│ • Use: Identical queries                        │
└──────────────────────────────────────────────────┘
```

---

### 9.3 Cache Versioning

**Problem:** Po deploy nové verze může být stará cache nekompatibilní.

**Řešení: Version Prefix**

```php
<?php
/**
 * Include plugin version in cache keys
 */
protected function get_versioned_cache_key($key) {
    return SAW_VISITORS_VERSION . '_' . $key;
}

// Usage
$cache_key = $this->get_versioned_cache_key('companies_list');
SAW_Cache::get($cache_key, 'companies');

// After update: 2.0.0_companies_list (new key, old cache ignored)
```

---

## 10. Závěr

### ✅ Golden Rules

1. **VŽDY** používej `SAW_Cache` (ne `wp_cache_*` nebo `get_transient` přímo)
2. **VŽDY** invaliduj cache po write operacích (create/update/delete)
3. **NIKDY** necachuj real-time nebo user-sensitive data globálně
4. **VŽDY** nastav rozumný TTL (300s default, upravuj dle potřeby)
5. **VŽDY** používej cache groups (ne 'default' nebo 'saw')
6. **MONITORUJ** cache hit ratio (cíl >80%)

### 📊 Performance Targets

| Metrika | Target | Critical |
|---------|--------|----------|
| **Hit Ratio** | >80% | >60% |
| **Avg Response** | <50ms | <100ms |
| **Memory Usage** | <500MB | <1GB |
| **Query Count** | <5/page | <15/page |

### 📚 Další zdroje

- **WordPress Object Cache:** https://developer.wordpress.org/reference/classes/wp_object_cache/
- **Redis Documentation:** https://redis.io/docs/
- **SAW Visitors GitHub:** Internal repository
- **Performance Profiling:** Use Query Monitor plugin

### 🆘 Potřebuješ pomoc?

1. Zkontroluj tento guide
2. Projdi debugging checklist (section 7.1)
3. Zkontroluj cache statistics (section 7.2)
4. Spusť manual testing (section 7.4)
5. Kontaktuj senior developer

---

**Happy Caching! 🚀**

*This document is maintained by the SAW Visitors development team.*  
*Last updated: November 22, 2024*