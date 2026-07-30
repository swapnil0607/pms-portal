-- Adds the clients table and projects.client_id for installs upgrading from
-- the pre-clients-table schema. Safe to run multiple times.

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL UNIQUE,
  notes TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Backfill one client row per distinct existing project_group value.
INSERT INTO clients (name)
SELECT DISTINCT project_group FROM projects
WHERE project_group IS NOT NULL AND project_group <> ''
ON DUPLICATE KEY UPDATE name = VALUES(name);

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'client_id'
);

SET @sql := IF(@col_exists = 0,
  'ALTER TABLE projects ADD COLUMN client_id INT UNSIGNED NULL AFTER code',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE projects p
JOIN clients c ON c.name = p.project_group
SET p.client_id = c.id
WHERE p.client_id IS NULL AND p.project_group IS NOT NULL AND p.project_group <> '';

SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND CONSTRAINT_NAME = 'fk_projects_client'
);

SET @sql := IF(@fk_exists = 0,
  'ALTER TABLE projects ADD CONSTRAINT fk_projects_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
