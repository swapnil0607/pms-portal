-- Adds users.permissions (JSON array of page keys, e.g. ["projects","kanban"])
-- so page access can be granted per-user instead of purely by role. Existing
-- rows are backfilled with their current role's default page set so no
-- existing user's access changes until an admin/manager edits them
-- explicitly. Safe to run multiple times.

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'permissions'
);

SET @sql := IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN permissions TEXT NULL AFTER role',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE users SET permissions = '["projects","clients","kanban","work_logs","time_logs","reports","users","settings"]'
WHERE role = 'admin' AND permissions IS NULL;

UPDATE users SET permissions = '["projects","clients","kanban","work_logs","time_logs","reports"]'
WHERE role = 'manager' AND permissions IS NULL;

UPDATE users SET permissions = '["kanban","work_logs","time_logs"]'
WHERE role IN ('member', 'viewer') AND permissions IS NULL;
