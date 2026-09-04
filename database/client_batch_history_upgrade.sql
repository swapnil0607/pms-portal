-- EduRiser PMS: Client Batch History & Audit Trail Upgrade
-- Run this query in phpMyAdmin (or via MySQL CLI).

CREATE TABLE IF NOT EXISTS client_batch_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id INT UNSIGNED NOT NULL,
  client_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  action_type ENUM('created', 'renewed', 'updated', 'expired') NOT NULL DEFAULT 'renewed',
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  licence_count INT UNSIGNED NOT NULL DEFAULT 0,
  given_by VARCHAR(180) NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cbh_batch FOREIGN KEY (batch_id) REFERENCES client_batches(id) ON DELETE CASCADE,
  CONSTRAINT fk_cbh_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_cbh_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_cbh_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill initial history records for existing batches if not already present
INSERT INTO client_batch_history (batch_id, client_id, project_id, action_type, period_start, period_end, licence_count, given_by, notes, created_by, created_at)
SELECT
  cb.id,
  cb.client_id,
  cb.project_id,
  'created',
  cb.creation_date,
  cb.renewal_date,
  cb.licence_count,
  cb.given_by,
  cb.notes,
  cb.created_by,
  cb.created_at
FROM client_batches cb
WHERE NOT EXISTS (
  SELECT 1 FROM client_batch_history cbh WHERE cbh.batch_id = cb.id
);
