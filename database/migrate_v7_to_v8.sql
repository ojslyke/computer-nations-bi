-- =====================================================================
-- Migration: v7 -> v8 (performance + security hardening pass)
-- Run this ONLY if you already have the v7 database and want to keep
-- your existing data.
--
-- Usage:
--   mysql -u root -p computer_nations_bi < migrate_v7_to_v8.sql
-- =====================================================================
USE computer_nations_bi;

-- Performance indexes (skipped individually if already present)
SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='orders' AND index_name='idx_orders_status') = 0,
  'CREATE INDEX idx_orders_status ON orders(status)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='orders' AND index_name='idx_orders_branch_status') = 0,
  'CREATE INDEX idx_orders_branch_status ON orders(branch_id, status)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='orders' AND index_name='idx_orders_created_at') = 0,
  'CREATE INDEX idx_orders_created_at ON orders(created_at)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='stock_movements' AND index_name='idx_stock_movements_branch_product') = 0,
  'CREATE INDEX idx_stock_movements_branch_product ON stock_movements(branch_id, product_id)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='stock_movements' AND index_name='idx_stock_movements_category') = 0,
  'CREATE INDEX idx_stock_movements_category ON stock_movements(category)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='stock_movements' AND index_name='idx_stock_movements_created_at') = 0,
  'CREATE INDEX idx_stock_movements_created_at ON stock_movements(created_at)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='purchase_orders' AND index_name='idx_purchase_orders_status') = 0,
  'CREATE INDEX idx_purchase_orders_status ON purchase_orders(status)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='purchase_orders' AND index_name='idx_purchase_orders_created_at') = 0,
  'CREATE INDEX idx_purchase_orders_created_at ON purchase_orders(created_at)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='products' AND index_name='idx_products_status') = 0,
  'CREATE INDEX idx_products_status ON products(status)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='sav_items' AND index_name='idx_sav_items_status') = 0,
  'CREATE INDEX idx_sav_items_status ON sav_items(status)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='sav_items' AND index_name='idx_sav_items_created_at') = 0,
  'CREATE INDEX idx_sav_items_created_at ON sav_items(created_at)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='stock_transfers' AND index_name='idx_stock_transfers_status') = 0,
  'CREATE INDEX idx_stock_transfers_status ON stock_transfers(status)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='stock_transfers' AND index_name='idx_stock_transfers_type') = 0,
  'CREATE INDEX idx_stock_transfers_type ON stock_transfers(type)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='activity_logs' AND index_name='idx_activity_logs_created_at') = 0,
  'CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='users' AND index_name='idx_users_status') = 0,
  'CREATE INDEX idx_users_status ON users(status)', 'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT 'Indexes added.' AS status;

CREATE TABLE IF NOT EXISTS rate_limit_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bucket VARCHAR(50) NOT NULL,
    identifier VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate_limit_lookup (bucket, identifier, created_at)
) ENGINE=InnoDB;

SELECT 'Rate limiting table ready.' AS status;
