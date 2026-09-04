-- EduRiser PMS: Client Renewals and Licences Upgrade
-- Run this query once in phpMyAdmin (or via MySQL CLI).

CREATE TABLE IF NOT EXISTS client_batches (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  project_id INT UNSIGNED NOT NULL,
  batch_name VARCHAR(180) NOT NULL,
  region_department VARCHAR(180) NULL,
  licence_count INT UNSIGNED NOT NULL DEFAULT 0,
  creation_date DATE NOT NULL,
  renewal_date DATE NOT NULL,
  given_by VARCHAR(180) NULL,
  notes TEXT NULL,
  status ENUM('active', 'renewed', 'cancelled') NOT NULL DEFAULT 'active',
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cb_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_cb_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_cb_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
