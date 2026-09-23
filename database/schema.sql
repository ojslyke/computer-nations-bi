-- =====================================================================
-- Computer Nations — Business Intelligence System
-- Database schema (v4 — towns + branches, simplified transfers)
-- Import this file in phpMyAdmin, or run:
--   mysql -u root -p < schema.sql
-- =====================================================================

CREATE DATABASE IF NOT EXISTS computer_nations_bi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE computer_nations_bi;

-- ---------------------------------------------------------------------
-- 1. TOWNS & BRANCHES — a town can hold several branches. Branches are
--    the actual stock-holding locations; towns just group them.
-- ---------------------------------------------------------------------
CREATE TABLE towns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    town_id INT NOT NULL,
    address VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (town_id) REFERENCES towns(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. ROLES & PERMISSIONS (this is what powers per-user privileges)
-- ---------------------------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(80) NOT NULL UNIQUE,      -- e.g. 'inventory.edit'
    label VARCHAR(150) NOT NULL,           -- e.g. 'Edit inventory items'
    module VARCHAR(50) NOT NULL            -- e.g. 'inventory'
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. USERS — branch_id NULL means "all branches" (head-office roles like
--    Admin/Manager); a set branch_id restricts a user to one location.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    password VARCHAR(255) NOT NULL,        -- password_hash() output
    role_id INT NOT NULL,
    branch_id INT DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    status ENUM('active','suspended') NOT NULL DEFAULT 'active',
    reset_token_hash VARCHAR(255) DEFAULT NULL,   -- for email password recovery (Admin accounts only)
    reset_token_expires DATETIME DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. CATALOG: categories, suppliers, products
--    Products are branch-agnostic (one SKU, one price list company-wide);
--    the quantity on hand at each branch lives in branch_stock below.
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    contact_person VARCHAR(100) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    category_id INT DEFAULT NULL,
    supplier_id INT DEFAULT NULL,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    selling_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    default_reorder_level INT NOT NULL DEFAULT 5,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('active','discontinued') NOT NULL DEFAULT 'active',
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Per-branch stock level for every product. A row only exists once a
-- branch actually stocks that product (created automatically the first
-- time stock is added there).
CREATE TABLE branch_stock (
    branch_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    reorder_level INT NOT NULL DEFAULT 5,
    PRIMARY KEY (branch_id, product_id),
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. STOCK MOVEMENTS (every change to quantity is logged here, per
--    branch — this is what makes "stock" auditable and traceable)
-- ---------------------------------------------------------------------
CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    product_id INT NOT NULL,
    type ENUM('in','out','adjustment') NOT NULL,
    category ENUM('opening','manual','purchase','order','transfer','loss','count','sav') NOT NULL DEFAULT 'manual',
    quantity INT NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    reference VARCHAR(80) DEFAULT NULL,     -- e.g. order number, PO number, transfer number
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. CUSTOMERS & ORDERS — each order is sold from one branch
-- ---------------------------------------------------------------------
CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    branch_id INT NOT NULL,
    customer_id INT DEFAULT NULL,
    user_id INT NOT NULL,                  -- staff member who created it
    status ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    cancelled_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. PURCHASE ORDERS ("Bon de commande") — restocking from suppliers
--    into a specific branch. Receiving a PO is what moves stock.
-- ---------------------------------------------------------------------
CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(30) NOT NULL UNIQUE,
    branch_id INT NOT NULL,                -- receiving branch
    supplier_id INT NOT NULL,
    user_id INT NOT NULL,                  -- staff member who raised it
    status ENUM('draft','ordered','partially_received','received','cancelled') NOT NULL DEFAULT 'draft',
    notes VARCHAR(255) DEFAULT NULL,
    received_by INT DEFAULT NULL,          -- who completed the final receipt
    cancelled_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    received_at DATETIME DEFAULT NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE purchase_order_items (
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
-- 8. STOCK TRANSFERS ("Transfers") — moving stock between two branches.
--    Dispatching deducts from the source branch; receiving adds to the
--    destination branch. Both legs are logged in stock_movements.
-- ---------------------------------------------------------------------
CREATE TABLE stock_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_number VARCHAR(30) NOT NULL UNIQUE,
    type ENUM('transfer','expedition') NOT NULL DEFAULT 'transfer',  -- transfer = same town; expedition = between towns
    from_branch_id INT NOT NULL,
    to_branch_id INT NOT NULL,
    status ENUM('pending','in_transit','received','cancelled') NOT NULL DEFAULT 'pending',
    user_id INT NOT NULL,                  -- who sent it
    received_by INT DEFAULT NULL,          -- who confirmed receipt
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dispatched_at DATETIME DEFAULT NULL,
    received_at DATETIME DEFAULT NULL,
    FOREIGN KEY (from_branch_id) REFERENCES branches(id),
    FOREIGN KEY (to_branch_id) REFERENCES branches(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE stock_transfer_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transfer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. SAV (Service Après-Vente) — damaged/faulty stock sent out for
--    repair and tracked until it either comes back to sellable stock
--    or is written off for good. Replaces the old simple "Products
--    Lost" write-off with a full repair lifecycle.
-- ---------------------------------------------------------------------
CREATE TABLE technicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE sav_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sav_number VARCHAR(30) NOT NULL UNIQUE,
    branch_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    issue_description VARCHAR(255) NOT NULL,
    status ENUM('reported','with_technician','repaired','unrepairable') NOT NULL DEFAULT 'reported',
    reported_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (reported_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- The full timeline for one SAV item — reported, sent out, came back,
-- resolved. This is what lets the SAV detail page show every step.
CREATE TABLE sav_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sav_item_id INT NOT NULL,
    action ENUM('reported','sent_to_technician','received_from_technician','returned_to_stock','written_off') NOT NULL,
    technician_id INT DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sav_item_id) REFERENCES sav_items(id) ON DELETE CASCADE,
    FOREIGN KEY (technician_id) REFERENCES technicians(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. ACTIVITY LOG (audit trail — who did what, when)
-- ---------------------------------------------------------------------
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. USER-ATTRIBUTION LINKS — every "who did this" column added above
--    gets its foreign key here, now that the users table exists. This
--    keeps every operation traceable to a person, not just a timestamp.
-- ---------------------------------------------------------------------
ALTER TABLE branches ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE categories ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE suppliers ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE customers ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE products ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE technicians ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE orders ADD FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE purchase_orders ADD FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE purchase_orders ADD FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE stock_transfers ADD FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL;

-- =====================================================================
-- SEED DATA
-- =====================================================================

-- Towns, then branches within them — Douala has two branches to show the
-- multi-branch-per-town case; the others have one each for now.
INSERT INTO towns (name) VALUES ('Douala'), ('Yaoundé'), ('Bafoussam');

INSERT INTO branches (name, town_id, address, phone) VALUES
('Douala HQ & Warehouse', 1, 'Rue Joss, Akwa, Douala', '233420001'),
('Douala Bonabéri Branch', 1, 'Axe Lourd, Bonabéri, Douala', '233420004'),
('Yaoundé Branch', 2, 'Avenue Kennedy, Yaoundé', '222230002'),
('Bafoussam Branch', 3, 'Marché A, Bafoussam', '233440003');

-- Roles
INSERT INTO roles (name, description) VALUES
('Admin', 'Full system access across every branch'),
('Manager', 'Manages inventory, orders, reports and staff activity across branches'),
('Sales Staff', 'Creates orders and views products/customers at their branch'),
('Inventory Staff', 'Manages stock levels, transfers and suppliers at their branch');

-- Permissions
INSERT INTO permissions (slug, label, module) VALUES
('dashboard.view',        'View dashboard',              'dashboard'),
('branches.manage',       'Manage branches',              'branches'),
('inventory.view',        'View products',                'inventory'),
('inventory.create',      'Add products',                 'inventory'),
('inventory.edit',        'Edit products',                'inventory'),
('inventory.delete',      'Delete products',               'inventory'),
('stock.view',            'View stock movements',          'stock'),
('stock.adjust',          'Record stock in/out',           'stock'),
('transfers.view',        'View transfers (same town)',    'transfers'),
('transfers.create',      'Send transfers (same town)',    'transfers'),
('transfers.receive',     'Receive transfers (same town)', 'transfers'),
('expeditions.view',      'View expeditions (between towns)',   'expeditions'),
('expeditions.create',    'Send expeditions (between towns)',   'expeditions'),
('expeditions.receive',   'Receive expeditions (between towns)','expeditions'),
('sav.view',              'View SAV items',                 'sav'),
('sav.create',            'Report a new SAV item',          'sav'),
('sav.manage',            'Send/receive SAV items, manage technicians', 'sav'),
('orders.view',           'View orders',                    'orders'),
('orders.create',         'Create orders',                  'orders'),
('orders.cancel',         'Cancel orders',                  'orders'),
('customers.view',        'View customers',                 'customers'),
('customers.manage',      'Add/edit customers',             'customers'),
('suppliers.view',        'View suppliers',                 'suppliers'),
('suppliers.manage',      'Add/edit suppliers',             'suppliers'),
('reports.view',          'View reports',                   'reports'),
('reports.export',        'Export reports to CSV',          'reports'),
('categories.manage',     'Manage product categories',      'categories'),
('purchasing.view',       'View purchase orders',           'purchasing'),
('purchasing.create',     'Create purchase orders',         'purchasing'),
('purchasing.receive',    'Receive purchase order stock',   'purchasing'),
('activity.view',         'View activity log',              'activity'),
('users.manage',          'Manage users and roles',         'users');

-- Role <-> Permission mapping
-- Admin: everything
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

-- Manager: everything except users.manage
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE slug != 'users.manage';

-- Sales Staff: dashboard, view products, orders, customers
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions
WHERE slug IN ('dashboard.view','inventory.view','orders.view','orders.create',
               'customers.view','customers.manage');

-- Inventory Staff: dashboard, full inventory + stock + transfers + SAV + suppliers + purchasing
-- (Expeditions between towns are left to Admin/Manager by default — a bigger
-- logistics operation than a routine same-town transfer — but can be granted
-- to any role from Roles & permissions.)
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions
WHERE slug IN ('dashboard.view','inventory.view','inventory.create','inventory.edit',
               'stock.view','stock.adjust','transfers.view','transfers.create','transfers.receive',
               'sav.view','sav.create','sav.manage',
               'suppliers.view','suppliers.manage','categories.manage',
               'purchasing.view','purchasing.create','purchasing.receive');

-- Default admin user (branch_id NULL = access to all branches)
-- username: admin | password: ComputerNationsBIset — CHANGE THIS AFTER FIRST LOGIN
INSERT INTO users (full_name, username, email, password, role_id, branch_id) VALUES
('System Administrator', 'admin', 'shemeinchinda@gmail.com',
 '$2b$10$b3YLDHDxAdaGlDYzF4gAGOy9w2qZBHV24KJOSL8gRoJo4zQOk3oAK', 1, NULL);

-- Now that the admin account exists, attribute the branches created above to them
UPDATE branches SET created_by = 1 WHERE created_by IS NULL;

-- Sample catalog data so the dashboard isn't empty on first run
INSERT INTO categories (name, created_by) VALUES ('Laptops', 1), ('Desktops', 1), ('Accessories', 1), ('Networking', 1), ('Components', 1);

INSERT INTO suppliers (name, contact_person, phone, email, created_by) VALUES
('TechSource Distributors', 'Alan Mbeki', '677000111', 'sales@techsource.example', 1),
('Global Parts Ltd', 'Grace Fon', '677000222', 'orders@globalparts.example', 1);

INSERT INTO products (sku, name, category_id, supplier_id, cost_price, selling_price, default_reorder_level, created_by) VALUES
('LAP-0001', 'Dell Latitude 5440', 1, 1, 320000, 420000, 5, 1),
('LAP-0002', 'HP ProBook 450 G9', 1, 1, 300000, 395000, 5, 1),
('DSK-0001', 'HP EliteDesk 800', 2, 2, 250000, 340000, 4, 1),
('ACC-0001', 'Logitech MX Master 3', 3, 2, 35000, 55000, 10, 1),
('NET-0001', 'TP-Link Archer C6 Router', 4, 2, 18000, 28000, 5, 1);

-- Opening stock spread across the four branches (two of them in Douala)
INSERT INTO branch_stock (branch_id, product_id, quantity, reorder_level) VALUES
(1, 1, 8, 5), (1, 2, 2, 5), (1, 3, 6, 4), (1, 4, 15, 10), (1, 5, 1, 5),
(2, 1, 3, 5), (2, 4, 6, 10),
(3, 1, 4, 5), (3, 2, 1, 5), (3, 3, 2, 4), (3, 4, 7, 10),
(4, 1, 0, 5), (4, 4, 3, 10), (4, 5, 1, 5);

-- Matching opening-stock movements so the audit trail is consistent from day one
INSERT INTO stock_movements (branch_id, product_id, type, category, quantity, reason, user_id) VALUES
(1, 1, 'in', 'opening', 8, 'Opening stock', 1), (1, 2, 'in', 'opening', 2, 'Opening stock', 1),
(1, 3, 'in', 'opening', 6, 'Opening stock', 1), (1, 4, 'in', 'opening', 15, 'Opening stock', 1),
(1, 5, 'in', 'opening', 1, 'Opening stock', 1),
(2, 1, 'in', 'opening', 3, 'Opening stock', 1), (2, 4, 'in', 'opening', 6, 'Opening stock', 1),
(3, 1, 'in', 'opening', 4, 'Opening stock', 1), (3, 2, 'in', 'opening', 1, 'Opening stock', 1),
(3, 3, 'in', 'opening', 2, 'Opening stock', 1), (3, 4, 'in', 'opening', 7, 'Opening stock', 1),
(4, 4, 'in', 'opening', 3, 'Opening stock', 1), (4, 5, 'in', 'opening', 1, 'Opening stock', 1);

-- A sample open purchase order so the Purchasing module isn't empty on first run
INSERT INTO purchase_orders (po_number, branch_id, supplier_id, user_id, status, notes) VALUES
('PO-20260101-SAMPL', 1, 1, 1, 'ordered', 'Restocking laptops ahead of back-to-school demand');
INSERT INTO purchase_order_items (po_id, product_id, quantity_ordered, unit_cost) VALUES
(1, 2, 10, 300000);

-- ---------------------------------------------------------------------
-- Performance indexes — FK columns already get an automatic index in
-- InnoDB, so these cover the columns that don't: status filters, date
-- sorts on list pages, and the composite lookups the app actually runs.
-- ---------------------------------------------------------------------
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_branch_status ON orders(branch_id, status);
CREATE INDEX idx_orders_created_at ON orders(created_at);

CREATE INDEX idx_stock_movements_branch_product ON stock_movements(branch_id, product_id);
CREATE INDEX idx_stock_movements_category ON stock_movements(category);
CREATE INDEX idx_stock_movements_created_at ON stock_movements(created_at);

CREATE INDEX idx_purchase_orders_status ON purchase_orders(status);
CREATE INDEX idx_purchase_orders_created_at ON purchase_orders(created_at);

CREATE INDEX idx_products_status ON products(status);

CREATE INDEX idx_sav_items_status ON sav_items(status);
CREATE INDEX idx_sav_items_created_at ON sav_items(created_at);

CREATE INDEX idx_stock_transfers_status ON stock_transfers(status);
CREATE INDEX idx_stock_transfers_type ON stock_transfers(type);

CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);

CREATE INDEX idx_users_status ON users(status);

-- ---------------------------------------------------------------------
-- Rate limiting — tracks attempts per IP on sensitive endpoints
-- (login, password reset) to slow down brute-force/abuse.
-- ---------------------------------------------------------------------
CREATE TABLE rate_limit_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bucket VARCHAR(50) NOT NULL,
    identifier VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate_limit_lookup (bucket, identifier, created_at)
) ENGINE=InnoDB;
