-- =====================================================================
-- Migration: v2 -> v3 (multi-branch)
-- Run this ONLY if you already have the v2 database (with Purchasing)
-- and want to keep your existing data. This converts your single
-- location into "Main Branch" and moves every product's quantity into
-- per-branch stock, then adds Transfers.
--
-- Usage:
--   mysql -u root -p computer_nations_bi < migrate_v2_to_v3.sql
--
-- After this runs, go to Branches in the sidebar and rename "Main
-- Branch" to your real branch name, then add your other locations.
-- =====================================================================
USE computer_nations_bi;

-- ---------------------------------------------------------------------
-- 1. Branches table + a branch representing your existing location
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    address VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO branches (id, name, city)
SELECT 1, 'Main Branch', 'Douala'
WHERE NOT EXISTS (SELECT 1 FROM branches WHERE id = 1);

-- ---------------------------------------------------------------------
-- 2. branch_stock — move every product's quantity/reorder_level here
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS branch_stock (
    branch_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 5,
    PRIMARY KEY (branch_id, product_id),
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT IGNORE INTO branch_stock (branch_id, product_id, quantity, reorder_level)
SELECT 1, id, quantity, reorder_level FROM products;

-- Rename products.reorder_level -> default_reorder_level (a template used
-- when a product is first stocked at a new branch); drop the old quantity
-- column now that it lives in branch_stock.
ALTER TABLE products CHANGE COLUMN reorder_level default_reorder_level INT NOT NULL DEFAULT 5;
ALTER TABLE products DROP COLUMN quantity;

-- ---------------------------------------------------------------------
-- 3. Add branch_id to users, orders, purchase_orders, stock_movements
-- ---------------------------------------------------------------------
ALTER TABLE users ADD COLUMN IF NOT EXISTS branch_id INT DEFAULT NULL AFTER role_id;
ALTER TABLE users ADD CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL;
-- Existing users keep branch_id = NULL (access to all branches) so nobody loses access.

ALTER TABLE orders ADD COLUMN IF NOT EXISTS branch_id INT DEFAULT NULL AFTER order_number;
UPDATE orders SET branch_id = 1 WHERE branch_id IS NULL;
ALTER TABLE orders MODIFY COLUMN branch_id INT NOT NULL;
ALTER TABLE orders ADD CONSTRAINT fk_orders_branch FOREIGN KEY (branch_id) REFERENCES branches(id);

ALTER TABLE purchase_orders ADD COLUMN IF NOT EXISTS branch_id INT DEFAULT NULL AFTER po_number;
UPDATE purchase_orders SET branch_id = 1 WHERE branch_id IS NULL;
ALTER TABLE purchase_orders MODIFY COLUMN branch_id INT NOT NULL;
ALTER TABLE purchase_orders ADD CONSTRAINT fk_po_branch FOREIGN KEY (branch_id) REFERENCES branches(id);

ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS branch_id INT DEFAULT NULL AFTER id;
UPDATE stock_movements SET branch_id = 1 WHERE branch_id IS NULL;
ALTER TABLE stock_movements MODIFY COLUMN branch_id INT NOT NULL;
ALTER TABLE stock_movements ADD CONSTRAINT fk_sm_branch FOREIGN KEY (branch_id) REFERENCES branches(id);
ALTER TABLE stock_movements ADD COLUMN IF NOT EXISTS category ENUM('opening','manual','purchase','order','transfer','loss','count') NOT NULL DEFAULT 'manual' AFTER type;

-- ---------------------------------------------------------------------
-- 4. Stock transfers (new feature)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stock_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(30) NOT NULL UNIQUE,
    from_branch_id INT NOT NULL,
    to_branch_id INT NOT NULL,
    status ENUM('pending','in_transit','received','cancelled') NOT NULL DEFAULT 'pending',
    user_id INT NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dispatched_at DATETIME DEFAULT NULL,
    received_at DATETIME DEFAULT NULL,
    FOREIGN KEY (from_branch_id) REFERENCES branches(id),
    FOREIGN KEY (to_branch_id) REFERENCES branches(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_transfer_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. New permissions
-- ---------------------------------------------------------------------
INSERT IGNORE INTO permissions (slug, label, module) VALUES
('branches.manage',   'Manage branches',          'branches'),
('stock.loss',        'Record lost/damaged stock', 'stock'),
('transfers.view',    'View stock transfers',      'transfers'),
('transfers.create',  'Create stock transfers',    'transfers'),
('transfers.receive', 'Receive stock transfers',   'transfers');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE slug != 'users.manage';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions
WHERE slug IN ('stock.loss','transfers.view','transfers.create','transfers.receive');

SELECT 'Migration to v3 (multi-branch) complete. Rename "Main Branch" under Branches, then add your other locations.' AS status;
