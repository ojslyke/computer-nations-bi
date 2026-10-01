-- =====================================================================
-- Migration: v9 -> v10 (finance: expenses, refunds, installments, fund
-- transfers, daily cash ledger with carry-forward, Data Analyst role,
-- order payment method/status)
-- Safe to run repeatedly — every statement is guarded.
--
-- Usage:
--   mysql -u root -p computer_nations_bi < migrate_v9_to_v10.sql
-- =====================================================================
USE computer_nations_bi;

-- New role
INSERT IGNORE INTO roles (id, name, description) VALUES
(8, 'Data Analyst', 'Read-only visibility into reports, dashboards and activity across the system');

-- New permissions
INSERT IGNORE INTO permissions (slug, label, module) VALUES
('expenses.view',         'View company expenses',          'finance'),
('expenses.manage',       'Record company expenses',        'finance'),
('refunds.view',          'View customer refunds',          'finance'),
('refunds.manage',        'Process customer refunds',       'finance'),
('installments.view',     'View installment payments',      'finance'),
('installments.manage',   'Record installment payments',    'finance'),
('funds.view',            'View fund transfers to bank/credit union', 'finance'),
('funds.manage',          'Record fund transfers to bank/credit union', 'finance');

-- New columns on orders
SET @c = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='orders' AND column_name='payment_method');
SET @s = IF(@c = 0, "ALTER TABLE orders ADD COLUMN payment_method ENUM('cash','mobile_money','bank_transfer') NOT NULL DEFAULT 'cash'", 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='orders' AND column_name='payment_status');
SET @s = IF(@c = 0, "ALTER TABLE orders ADD COLUMN payment_status ENUM('paid','partial','unpaid') NOT NULL DEFAULT 'paid'", 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='orders' AND column_name='amount_paid');
SET @s = IF(@c = 0, "ALTER TABLE orders ADD COLUMN amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0", 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Existing orders were always paid in full at creation — backfill so the
-- new columns reflect that instead of defaulting to 0/unpaid.
UPDATE orders SET amount_paid = total_amount WHERE payment_status = 'paid' AND amount_paid = 0 AND total_amount > 0;

-- New tables
CREATE TABLE IF NOT EXISTS installment_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','mobile_money','bank_transfer') NOT NULL DEFAULT 'cash',
    notes VARCHAR(255) DEFAULT NULL,
    received_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (received_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    expense_date DATE NOT NULL,
    category VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','mobile_money','bank_transfer') NOT NULL DEFAULT 'cash',
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    payment_method ENUM('cash','mobile_money','bank_transfer') NOT NULL DEFAULT 'cash',
    processed_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS fund_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(30) NOT NULL UNIQUE,
    branch_id INT NOT NULL,
    destination VARCHAR(150) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method ENUM('cash','mobile_money','bank_transfer') NOT NULL DEFAULT 'bank_transfer',
    notes VARCHAR(255) DEFAULT NULL,
    transferred_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (transferred_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cash_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    ledger_date DATE NOT NULL,
    opening_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
    cash_in DECIMAL(12,2) NOT NULL DEFAULT 0,
    cash_out DECIMAL(12,2) NOT NULL DEFAULT 0,
    closing_cash DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_branch_date (branch_id, ledger_date),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS daily_sales_summary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    summary_date DATE NOT NULL,
    orders_count INT NOT NULL DEFAULT 0,
    revenue DECIMAL(12,2) NOT NULL DEFAULT 0,
    refreshed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_branch_summary_date (branch_id, summary_date),
    FOREIGN KEY (branch_id) REFERENCES branches(id)
) ENGINE=InnoDB;

-- Role grants
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE slug IN ('expenses.view','expenses.manage','refunds.view','refunds.manage','installments.view','installments.manage','funds.view','funds.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE slug IN ('expenses.view','expenses.manage','refunds.view','refunds.manage','installments.view','installments.manage','funds.view','funds.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE slug IN ('installments.view','installments.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions WHERE slug IN ('expenses.view','expenses.manage','refunds.view','refunds.manage','installments.view','installments.manage','funds.view','funds.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions WHERE slug IN ('installments.view','installments.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions
WHERE slug IN ('dashboard.view','reports.view','reports.export','activity.view',
               'audit.view','audit.export','orders.view','purchasing.view',
               'expenses.view','refunds.view','installments.view','funds.view');

SELECT 'Migration to v10 complete.' AS status;
