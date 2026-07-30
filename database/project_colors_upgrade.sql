-- Adds projects.color (hex string, e.g. #1f6f8b) used to tint project
-- title text on the Projects page for brand-based grouping. Safe to run
-- multiple times.

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'color'
);

SET @sql := IF(@col_exists = 0,
  'ALTER TABLE projects ADD COLUMN color VARCHAR(7) NULL AFTER name',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
