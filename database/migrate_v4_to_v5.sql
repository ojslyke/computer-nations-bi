-- =====================================================================
-- Migration: v4 -> v5 (user attribution everywhere)
-- Run this ONLY if you already have the v4 database (with Towns) and
-- want to keep your existing data. This adds "who did it" columns so
-- every operation — not just stock movements — is traceable to a person:
--   - stock_transfers.received_by  (who confirmed receipt)
--   - orders.cancelled_by
--   - purchase_orders.received_by / cancelled_by
--   - branches / categories / suppliers / customers . created_by
--
-- Usage:
--   mysql -u root -p computer_nations_bi < migrate_v4_to_v5.sql
-- =====================================================================
USE computer_nations_bi;

ALTER TABLE stock_transfers ADD COLUMN IF NOT EXISTS received_by INT DEFAULT NULL AFTER user_id;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS cancelled_by INT DEFAULT NULL AFTER total_amount;
ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS received_by INT DEFAULT NULL AFTER notes;
ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS cancelled_by INT DEFAULT NULL AFTER received_by;
ALTER TABLE branches ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL AFTER is_active;
ALTER TABLE categories ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL AFTER name;
ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL AFTER address;
ALTER TABLE customers ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL AFTER address;
ALTER TABLE products ADD COLUMN IF NOT EXISTS created_by INT DEFAULT NULL AFTER status;

-- Foreign keys (wrapped so re-running this script doesn't error on a
-- constraint that already exists — MySQL/MariaDB has no ADD CONSTRAINT
-- IF NOT EXISTS, so we check information_schema first)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'stock_transfers' AND CONSTRAINT_NAME = 'fk_st_received_by');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE stock_transfers ADD CONSTRAINT fk_st_received_by FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND CONSTRAINT_NAME = 'fk_orders_cancelled_by');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE orders ADD CONSTRAINT fk_orders_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND CONSTRAINT_NAME = 'fk_po_received_by');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE purchase_orders ADD CONSTRAINT fk_po_received_by FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_orders' AND CONSTRAINT_NAME = 'fk_po_cancelled_by');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE purchase_orders ADD CONSTRAINT fk_po_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'branches' AND CONSTRAINT_NAME = 'fk_branches_created_by');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE branches ADD CONSTRAINT fk_branches_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'categories' AND CONSTRAINT_NAME = 'fk_categories_created_by');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE categories ADD CONSTRAINT fk_categories_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'suppliers' AND CONSTRAINT_NAME = 'fk_suppliers_created_by');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE suppliers ADD CONSTRAINT fk_suppliers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'customers' AND CONSTRAINT_NAME = 'fk_customers_created_by');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE customers ADD CONSTRAINT fk_customers_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND CONSTRAINT_NAME = 'fk_products_created_by');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE products ADD CONSTRAINT fk_products_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Existing records predate this feature, so their creator is unknown —
-- leave created_by as NULL for them (the UI shows "—" in that case)
-- rather than guessing. Only new records going forward will be attributed.

SELECT 'Migration to v5 (user attribution) complete.' AS status;
