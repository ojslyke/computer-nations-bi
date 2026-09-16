-- =====================================================================
-- Migration: v3 -> v4 (towns)
-- Run this ONLY if you already have the v3 database (with Branches,
-- Transfers) and want to keep your existing data. This introduces a
-- proper Towns table — so a town can hold several branches — and moves
-- each branch's free-text city into it.
--
-- Usage:
--   mysql -u root -p computer_nations_bi < migrate_v3_to_v4.sql
-- =====================================================================
USE computer_nations_bi;

-- ---------------------------------------------------------------------
-- 1. Towns table, seeded from whatever city names your branches already use
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS towns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT IGNORE INTO towns (name)
SELECT DISTINCT city FROM branches WHERE city IS NOT NULL;

-- ---------------------------------------------------------------------
-- 2. Link branches to towns, then drop the old free-text column
-- ---------------------------------------------------------------------
ALTER TABLE branches ADD COLUMN IF NOT EXISTS town_id INT DEFAULT NULL AFTER name;

UPDATE branches b
JOIN towns t ON t.name = b.city
SET b.town_id = t.id
WHERE b.town_id IS NULL;

ALTER TABLE branches MODIFY COLUMN town_id INT NOT NULL;
ALTER TABLE branches ADD CONSTRAINT fk_branches_town FOREIGN KEY (town_id) REFERENCES towns(id);
ALTER TABLE branches DROP COLUMN city;

SELECT 'Migration to v4 (towns) complete. Add more towns and branches from the Branches page.' AS status;
