-- Adds projects.archived_at (nullable timestamp) so projects can be
-- archived/unarchived without deleting them, and shown separately from the
-- active list. Safe to run multiple times.

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'archived_at'
);

SET @sql := IF(@col_exists = 0,
  'ALTER TABLE projects ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER status',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
