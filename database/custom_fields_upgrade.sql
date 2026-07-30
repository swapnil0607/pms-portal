-- Adds custom project fields (Settings > Project Fields > Custom Fields).
-- Safe to run multiple times.

CREATE TABLE IF NOT EXISTS custom_field_definitions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  field_key VARCHAR(60) NOT NULL UNIQUE,
  label VARCHAR(120) NOT NULL,
  field_type ENUM('text','number','date') NOT NULL DEFAULT 'text',
  visible TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS project_custom_field_values (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  field_definition_id INT UNSIGNED NOT NULL,
  value TEXT NULL,
  UNIQUE KEY uq_project_field (project_id, field_definition_id),
  CONSTRAINT fk_pcfv_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pcfv_field FOREIGN KEY (field_definition_id) REFERENCES custom_field_definitions(id) ON DELETE CASCADE
);
