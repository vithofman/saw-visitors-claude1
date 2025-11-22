-- ============================================
-- MIGRACE: Přidání customer_id a branch_id
-- Datum: 2025-01-XX
-- ============================================
-- 
-- Tento skript doplní customer_id a branch_id do tabulek:
-- - visitors
-- - visit_daily_logs
-- - visitor_certificates
--
-- POZNÁMKA: Změňte 'wp_' na správný prefix vaší WordPress databáze!
-- ============================================

-- ============================================
-- MIGRACE 1: visitors
-- ============================================
-- Doplní customer_id a branch_id z nadřazené visit
UPDATE wp_saw_visitors vis
INNER JOIN wp_saw_visits v ON vis.visit_id = v.id
SET 
    vis.customer_id = v.customer_id,
    vis.branch_id = v.branch_id
WHERE vis.customer_id IS NULL OR vis.branch_id IS NULL;

-- ============================================
-- MIGRACE 2: visit_daily_logs
-- ============================================
-- Doplní customer_id a branch_id z nadřazeného visitor
UPDATE wp_saw_visit_daily_logs log
INNER JOIN wp_saw_visitors vis ON log.visitor_id = vis.id
SET 
    log.customer_id = vis.customer_id,
    log.branch_id = vis.branch_id
WHERE log.customer_id IS NULL OR log.branch_id IS NULL;

-- ============================================
-- MIGRACE 3: visitor_certificates
-- ============================================
-- Doplní customer_id a branch_id z nadřazeného visitor
UPDATE wp_saw_visitor_certificates cert
INNER JOIN wp_saw_visitors vis ON cert.visitor_id = vis.id
SET 
    cert.customer_id = vis.customer_id,
    cert.branch_id = vis.branch_id
WHERE cert.customer_id IS NULL OR cert.branch_id IS NULL;

-- ============================================
-- KONTROLA: Ověření migrace
-- ============================================
-- Spusťte tyto dotazy pro ověření:

-- SELECT COUNT(*) as visitors_with_null 
-- FROM wp_saw_visitors 
-- WHERE customer_id IS NULL OR branch_id IS NULL;

-- SELECT COUNT(*) as logs_with_null 
-- FROM wp_saw_visit_daily_logs 
-- WHERE customer_id IS NULL OR branch_id IS NULL;

-- SELECT COUNT(*) as certs_with_null 
-- FROM wp_saw_visitor_certificates 
-- WHERE customer_id IS NULL OR branch_id IS NULL;

-- Všechny dotazy by měly vrátit 0.
-- ============================================

