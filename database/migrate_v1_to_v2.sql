-- =====================================================================
-- Migration: v1 -> v2
-- Run this ONLY if you already imported the original schema.sql and have
-- existing data you want to keep. It adds Purchase Orders, new
-- permissions, and grants them to existing roles. Safe to re-run (uses
-- IF NOT EXISTS / INSERT IGNORE style guards where possible).
--
-- Usage:
--   mysql -u root -p computer_nations_bi < migrate_v1_to_v2.sql
-- =====================================================================
USE computer_nations_bi;

-- ---------------------------------------------------------------------
-- New tables
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(30) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('draft','ordered','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_at DATETIME DEFAULT NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_ordered INT NOT NULL,
    quantity_received INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- New permissions (INSERT IGNORE so re-running this is harmless)
-- ---------------------------------------------------------------------
INSERT IGNORE INTO permissions (slug, label, module) VALUES
('reports.export',        'Export reports to CSV',          'reports'),
('categories.manage',     'Manage product categories',      'categories'),
('purchasing.view',       'View purchase orders',           'purchasing'),
('purchasing.create',     'Create purchase orders',         'purchasing'),
('purchasing.receive',    'Receive purchase order stock',   'purchasing'),
('activity.view',         'View activity log',              'activity');

-- ---------------------------------------------------------------------
-- Grant the new permissions to existing roles
-- ---------------------------------------------------------------------

-- Admin (role 1): everything, always
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Manager (role 2): everything except user management
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE slug != 'users.manage';

-- Inventory Staff (role 4): the new purchasing + category permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions
WHERE slug IN ('categories.manage','purchasing.view','purchasing.create','purchasing.receive');

-- Sales Staff (role 3) intentionally gets nothing new here — adjust via
-- the Roles & permissions screen in the app if you want them to see more.

SELECT 'Migration complete.' AS status;
