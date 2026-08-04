-- Adds users.avatar_path (stored filename of an uploaded profile photo,
-- resolved under public/assets/uploads/avatars/) so users can set a profile
-- picture from the self-service profile page. Safe to run multiple times.

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_path'
);

SET @sql := IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER department',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
