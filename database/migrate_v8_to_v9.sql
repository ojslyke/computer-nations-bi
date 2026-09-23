-- =====================================================================
-- Migration: v8 -> v9 (Technician/Accountant/Secretary roles, stock
-- audits, technician-as-user SAV assignment, Excel stock import)
-- Safe to run repeatedly — every statement is guarded.
--
-- Usage:
--   mysql -u root -p computer_nations_bi < migrate_v8_to_v9.sql
-- =====================================================================
USE computer_nations_bi;

-- New roles
INSERT IGNORE INTO roles (id, name, description) VALUES
(5, 'Technician', 'Repairs SAV items assigned to them, at their branch'),
(6, 'Accountant', 'Views financial reports, orders and stock audits for reconciliation'),
(7, 'Secretary', 'Handles customers and order entry at their branch');

-- New permissions
INSERT IGNORE INTO permissions (slug, label, module) VALUES
('inventory.import',      'Bulk import stock from Excel',  'inventory'),
('sav.technician',        'Mark SAV items assigned to me as received/repaired', 'sav'),
('audit.view',            'View stock audits',              'audit'),
('audit.create',          'Conduct a stock audit (count)',  'audit'),
('audit.export',          'Export stock audits to CSV',     'audit');

-- New columns
SET @c = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sav_items' AND column_name='assigned_technician_id');
SET @s = IF(@c = 0, 'ALTER TABLE sav_items ADD COLUMN assigned_technician_id INT DEFAULT NULL, ADD FOREIGN KEY (assigned_technician_id) REFERENCES users(id)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sav_items' AND column_name='technician_received_at');
SET @s = IF(@c = 0, 'ALTER TABLE sav_items ADD COLUMN technician_received_at DATETIME DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sav_activities' AND column_name='technician_user_id');
SET @s = IF(@c = 0, 'ALTER TABLE sav_activities ADD COLUMN technician_user_id INT DEFAULT NULL, ADD FOREIGN KEY (technician_user_id) REFERENCES users(id)', 'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @s = (SELECT IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='sav_activities' AND column_name='action' AND COLUMN_TYPE LIKE '%confirmed_receipt%') = 0,
  "ALTER TABLE sav_activities MODIFY COLUMN action ENUM('reported','sent_to_technician','confirmed_receipt','received_from_technician','returned_to_stock','written_off') NOT NULL",
  'SELECT 1'));
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Stock audit tables
CREATE TABLE IF NOT EXISTS stock_audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_number VARCHAR(30) NOT NULL UNIQUE,
    branch_id INT NOT NULL,
    conducted_by INT NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (conducted_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_audit_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_id INT NOT NULL,
    product_id INT NOT NULL,
    system_quantity INT NOT NULL,
    counted_quantity INT NOT NULL,
    variance INT NOT NULL,
    FOREIGN KEY (audit_id) REFERENCES stock_audits(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- Role grants for the new roles
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions WHERE slug IN ('dashboard.view','sav.technician');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions
WHERE slug IN ('dashboard.view','reports.view','reports.export','orders.view',
               'purchasing.view','activity.view','audit.view','audit.export');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions
WHERE slug IN ('dashboard.view','customers.view','customers.manage',
               'orders.view','orders.create','suppliers.view');

-- Give the new permissions to existing roles that should have them
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE slug IN ('inventory.import','sav.technician','audit.view','audit.create','audit.export');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE slug IN ('inventory.import','sav.technician','audit.view','audit.create','audit.export');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE slug IN ('inventory.import','audit.view','audit.create','audit.export');

SELECT 'Migration to v9 complete.' AS status;
