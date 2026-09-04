-- EduRiser PMS: Batch Archive Upgrade
-- Run this query in phpMyAdmin (or via MySQL CLI).

ALTER TABLE client_batches
ADD COLUMN IF NOT EXISTS archived_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;
