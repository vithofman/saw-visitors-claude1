-- ================================================================
-- EMERGENCY SQL FIX - Diagnostika a oprava WP rolí
-- PREFIX: cwp_
-- ================================================================

-- ================================================================
-- ČÁST 1: DIAGNOSTIKA
-- ================================================================

SELECT 
    su.id AS saw_id,
    su.email,
    su.role AS saw_role,
    su.customer_id,
    su.wp_user_id,
    wpu.user_login,
    wpm.meta_value AS wp_capabilities,
    CASE 
        WHEN su.role = 'super_admin' AND wpm.meta_value LIKE '%administrator%' THEN '✅ OK'
        WHEN su.role = 'admin' AND wpm.meta_value LIKE '%saw_admin%' THEN '✅ OK'
        WHEN su.role = 'super_manager' AND wpm.meta_value LIKE '%saw_super_manager%' THEN '✅ OK'
        WHEN su.role = 'manager' AND wpm.meta_value LIKE '%saw_manager%' THEN '✅ OK'
        WHEN su.role = 'terminal' AND wpm.meta_value LIKE '%saw_terminal%' THEN '✅ OK'
        ELSE '❌ NESOUHLASÍ!'
    END AS status
FROM cwp_saw_users su
LEFT JOIN cwp_users wpu ON su.wp_user_id = wpu.ID
LEFT JOIN cwp_usermeta wpm ON wpu.ID = wpm.user_id AND wpm.meta_key = 'cwp_capabilities'
WHERE su.is_active = 1
ORDER BY 
    CASE WHEN status = '❌ NESOUHLASÍ!' THEN 0 ELSE 1 END,
    su.role,
    su.email;

-- ================================================================
-- ČÁST 2: NAJDI PROBLÉM
-- ================================================================

SELECT 
    su.id,
    su.email,
    su.role AS saw_role,
    wpm.meta_value AS current_wp_role
FROM cwp_saw_users su
JOIN cwp_usermeta wpm ON su.wp_user_id = wpm.user_id AND wpm.meta_key = 'cwp_capabilities'
WHERE su.role = 'super_admin'
AND su.is_active = 1
AND wpm.meta_value NOT LIKE '%administrator%';

-- ================================================================
-- ČÁST 3: OPRAVA - SUPER ADMINI
-- ================================================================

UPDATE cwp_usermeta wpm
INNER JOIN cwp_saw_users su ON wpm.user_id = su.wp_user_id
SET wpm.meta_value = 'a:1:{s:13:"administrator";b:1;}'
WHERE wpm.meta_key = 'cwp_capabilities'
AND su.role = 'super_admin'
AND su.is_active = 1;

-- ================================================================
-- ČÁST 4: OPRAVA - OSTATNÍ ROLE
-- ================================================================

UPDATE cwp_usermeta wpm
INNER JOIN cwp_saw_users su ON wpm.user_id = su.wp_user_id
SET wpm.meta_value = 'a:1:{s:9:"saw_admin";b:1;}'
WHERE wpm.meta_key = 'cwp_capabilities'
AND su.role = 'admin'
AND su.is_active = 1;

UPDATE cwp_usermeta wpm
INNER JOIN cwp_saw_users su ON wpm.user_id = su.wp_user_id
SET wpm.meta_value = 'a:1:{s:17:"saw_super_manager";b:1;}'
WHERE wpm.meta_key = 'cwp_capabilities'
AND su.role = 'super_manager'
AND su.is_active = 1;

UPDATE cwp_usermeta wpm
INNER JOIN cwp_saw_users su ON wpm.user_id = su.wp_user_id
SET wpm.meta_value = 'a:1:{s:11:"saw_manager";b:1;}'
WHERE wpm.meta_key = 'cwp_capabilities'
AND su.role = 'manager'
AND su.is_active = 1;

UPDATE cwp_usermeta wpm
INNER JOIN cwp_saw_users su ON wpm.user_id = su.wp_user_id
SET wpm.meta_value = 'a:1:{s:12:"saw_terminal";b:1;}'
WHERE wpm.meta_key = 'cwp_capabilities'
AND su.role = 'terminal'
AND su.is_active = 1;

-- ================================================================
-- ČÁST 5: VYČISTI CACHE
-- ================================================================

DELETE FROM cwp_options 
WHERE option_name LIKE '_transient_saw_%' 
OR option_name LIKE '_transient_timeout_saw_%';

DELETE FROM cwp_usermeta 
WHERE meta_key LIKE 'saw_cache_%';

-- ================================================================
-- ČÁST 6: FINÁLNÍ KONTROLA
-- ================================================================

SELECT 
    su.id,
    su.email,
    su.role AS saw_role,
    CASE 
        WHEN su.role = 'super_admin' THEN 'administrator'
        WHEN su.role = 'admin' THEN 'saw_admin'
        WHEN su.role = 'super_manager' THEN 'saw_super_manager'
        WHEN su.role = 'manager' THEN 'saw_manager'
        WHEN su.role = 'terminal' THEN 'saw_terminal'
    END AS expected_wp_role,
    wpm.meta_value AS actual_wp_role,
    CASE 
        WHEN (su.role = 'super_admin' AND wpm.meta_value LIKE '%administrator%') THEN '✅ OK'
        WHEN (su.role = 'admin' AND wpm.meta_value LIKE '%saw_admin%') THEN '✅ OK'
        WHEN (su.role = 'super_manager' AND wpm.meta_value LIKE '%saw_super_manager%') THEN '✅ OK'
        WHEN (su.role = 'manager' AND wpm.meta_value LIKE '%saw_manager%') THEN '✅ OK'
        WHEN (su.role = 'terminal' AND wpm.meta_value LIKE '%saw_terminal%') THEN '✅ OK'
        ELSE '❌ PROBLÉM PŘETRVÁVÁ'
    END AS final_status
FROM cwp_saw_users su
LEFT JOIN cwp_usermeta wpm ON su.wp_user_id = wpm.user_id AND wpm.meta_key = 'cwp_capabilities'
WHERE su.is_active = 1
ORDER BY final_status, su.email;

-- ================================================================
-- POZNÁMKY:
-- ================================================================
-- 
-- ✅ Po dokončení:
-- 1. Odhlaste se z WP
-- 2. Vyčistěte cache prohlížeče (Ctrl+Shift+R)
-- 3. Přihlaste se znovu
-- 4. Zkontrolujte přístup k tabulce Users
-- 5. Zkuste přepnout pobočku
--
-- ⚠️ Pokud problémy přetrvávají:
-- - Zkontrolujte error_log WordPressu
-- - Spusťte emergency-fix.php
-- - Zkontrolujte že máte NOVÉ verze souborů:
--   - model.php (users)
--   - controller.php (users)
--   - controller.php (branches)
--
-- ================================================================