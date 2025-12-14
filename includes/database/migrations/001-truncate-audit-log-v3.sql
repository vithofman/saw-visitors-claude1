-- Migration: Truncate audit log for Universal Audit System v3.0
-- Purpose: Remove old audit records that don't have the new 'source' field in details JSON
-- Execution: Run this BEFORE deploying v3.0 to ensure clean migration
-- 
-- WARNING: This will DELETE all existing audit logs!
-- Only run this if you understand the implications.

TRUNCATE TABLE wp_saw_audit_log;

