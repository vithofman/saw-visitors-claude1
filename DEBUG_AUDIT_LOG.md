# Debug Audit Log - SQL dotazy pro ověření

## 1. Zobrazit poslední audit logy pro návštěvu

```sql
SELECT 
    id,
    action,
    entity_type,
    entity_id,
    details,
    created_at
FROM wp_saw_audit_log
WHERE entity_type = 'visits'
  AND entity_id = YOUR_VISIT_ID
ORDER BY created_at DESC
LIMIT 10;
```

## 2. Zobrazit related_items v details JSON

```sql
SELECT 
    id,
    action,
    JSON_EXTRACT(details, '$.related_items') as related_items,
    JSON_EXTRACT(details, '$.source') as source,
    created_at
FROM wp_saw_audit_log
WHERE entity_type = 'visits'
  AND entity_id = YOUR_VISIT_ID
  AND JSON_EXTRACT(details, '$.related_items') IS NOT NULL
ORDER BY created_at DESC;
```

## 3. Zobrazit všechny OOPP related_items

```sql
SELECT 
    id,
    action,
    JSON_EXTRACT(details, '$.related_items') as related_items,
    created_at
FROM wp_saw_audit_log
WHERE JSON_EXTRACT(details, '$.related_items[*].type') LIKE '%oopp%'
ORDER BY created_at DESC
LIMIT 20;
```

## 4. Zobrazit všechny visitor related_items

```sql
SELECT 
    id,
    action,
    JSON_EXTRACT(details, '$.related_items') as related_items,
    created_at
FROM wp_saw_audit_log
WHERE JSON_EXTRACT(details, '$.related_items[*].type') LIKE '%visitor%'
ORDER BY created_at DESC
LIMIT 20;
```

## 5. Zobrazit celý details JSON pro konkrétní záznam

```sql
SELECT 
    id,
    action,
    details,
    created_at
FROM wp_saw_audit_log
WHERE id = YOUR_LOG_ID;
```

## 6. Zkontrolovat, jestli se ukládají názvy OOPP

```sql
SELECT 
    id,
    action,
    JSON_EXTRACT(details, '$.related_items[*].name') as names,
    JSON_EXTRACT(details, '$.related_items[*].id') as ids,
    created_at
FROM wp_saw_audit_log
WHERE JSON_EXTRACT(details, '$.related_items[*].type') LIKE '%action_oopp%'
ORDER BY created_at DESC
LIMIT 10;
```

## 7. Zobrazit všechny záznamy s prázdnými názvy

```sql
SELECT 
    id,
    action,
    entity_type,
    entity_id,
    JSON_EXTRACT(details, '$.related_items') as related_items,
    created_at
FROM wp_saw_audit_log
WHERE JSON_EXTRACT(details, '$.related_items[*].name') = ''
   OR JSON_EXTRACT(details, '$.related_items[*].name') IS NULL
ORDER BY created_at DESC
LIMIT 20;
```


