-- =====================================================================
-- Migration: v6 -> v7 (Expeditions, SAV repair tracking)
-- Run this ONLY if you already have the v6 database and want to keep
-- your existing data.
--
-- What this adds:
--   - stock_transfers.type ('transfer' for same-town, 'expedition' for
--     between towns) — existing transfers are set to 'transfer'
--   - technicians, sav_items, sav_activities tables
--   - 'sav' added to stock_movements.category
--   - New permissions: transfers.* (relabeled), expeditions.*, sav.*
--   - The old stock.loss permission is left in place (for historical
--     role_permissions rows) but no longer used by any page — replace
--     it with sav.* on any custom roles via Roles & permissions.
--
-- Usage:
--   mysql -u root -p computer_nations_bi < migrate_v6_to_v7.sql
-- =====================================================================
USE computer_nations_bi;

ALTER TABLE stock_transfers ADD COLUMN IF NOT EXISTS type ENUM('transfer','expedition') NOT NULL DEFAULT 'transfer' AFTER transfer_number;

-- Existing transfers: classify by whether source/destination share a town
UPDATE stock_transfers st
JOIN branches fb ON fb.id = st.from_branch_id
JOIN branches tb ON tb.id = st.to_branch_id
SET st.type = IF(fb.town_id = tb.town_id, 'transfer', 'expedition');

ALTER TABLE stock_movements MODIFY COLUMN category ENUM('opening','manual','purchase','order','transfer','loss','count','sav') NOT NULL DEFAULT 'manual';

CREATE TABLE IF NOT EXISTS technicians (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sav_items (
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

CREATE TABLE IF NOT EXISTS sav_activities (
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

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'technicians' AND CONSTRAINT_NAME = 'fk_technicians_created_by');
SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE technicians ADD CONSTRAINT fk_technicians_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- New/relabeled permissions
INSERT IGNORE INTO permissions (slug, label, module) VALUES
('expeditions.view',      'View expeditions (between towns)',   'expeditions'),
('expeditions.create',    'Send expeditions (between towns)',   'expeditions'),
('expeditions.receive',   'Receive expeditions (between towns)','expeditions'),
('sav.view',              'View SAV items',                 'sav'),
('sav.create',            'Report a new SAV item',          'sav'),
('sav.manage',            'Send/receive SAV items, manage technicians', 'sav');

UPDATE permissions SET label = 'View transfers (same town)' WHERE slug = 'transfers.view';
UPDATE permissions SET label = 'Send transfers (same town)' WHERE slug = 'transfers.create';
UPDATE permissions SET label = 'Receive transfers (same town)' WHERE slug = 'transfers.receive';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE slug != 'users.manage';

-- Inventory Staff gets SAV in place of the old stock.loss
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE slug IN ('sav.view','sav.create','sav.manage');

SELECT 'Migration to v7 complete. Existing transfers were classified as Transfer/Expedition by whether their branches share a town. Set up Technicians before using SAV.' AS status;
