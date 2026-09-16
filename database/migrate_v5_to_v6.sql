-- =====================================================================
-- Migration: v5 -> v6 (phone numbers + admin email password recovery)
-- Run this ONLY if you already have the v5 database and want to keep
-- your existing data.
--
-- Also introduces, in the application code (no schema needed):
--   - Working-hours access restriction (6am-6pm West African Time) for
--     every role except Admin
--   - Admin-only "forgot password" email recovery
--   - Confirms/keeps the existing admin-resets-any-user's-password
--     feature on the Edit User page
--
-- Usage:
--   mysql -u root -p computer_nations_bi < migrate_v5_to_v6.sql
-- =====================================================================
USE computer_nations_bi;

ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(30) DEFAULT NULL AFTER email;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_hash VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_token_expires DATETIME DEFAULT NULL;

SELECT 'Migration to v6 complete. Phone numbers and admin password-recovery are ready — see the README for configuring outgoing email on XAMPP.' AS status;
