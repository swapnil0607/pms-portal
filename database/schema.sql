CREATE DATABASE IF NOT EXISTS eduriser_pms
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE eduriser_pms;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','manager','member','viewer') NOT NULL DEFAULT 'member',
  permissions TEXT NULL,
  designation VARCHAR(120) NULL,
  department VARCHAR(120) NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL UNIQUE,
  notes TEXT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS projects (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  code VARCHAR(40) NULL UNIQUE,
  client_id INT UNSIGNED NULL,
  project_group VARCHAR(180) NULL,
  color VARCHAR(7) NULL,
  description TEXT NULL,
  owner_id INT UNSIGNED NOT NULL,
  status ENUM('planned','active','on_hold','completed','cancelled') NOT NULL DEFAULT 'planned',
  archived_at TIMESTAMP NULL DEFAULT NULL,
  priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  billed_learners INT UNSIGNED NULL,
  learners_on_platform INT UNSIGNED NULL,
  learners_connected INT UNSIGNED NULL,
  started_with_courses INT UNSIGNED NULL,
  total_time DECIMAL(10,2) NULL,
  average_time_per_learner DECIMAL(8,2) NULL,
  adoption_percent DECIMAL(6,2) NULL,
  start_date DATE NULL,
  due_date DATE NULL,
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id) REFERENCES users(id),
  CONSTRAINT fk_projects_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS project_phases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_project_phases_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS task_lists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  phase_id INT UNSIGNED NULL,
  name VARCHAR(180) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_task_lists_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_task_lists_phase FOREIGN KEY (phase_id) REFERENCES project_phases(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS task_list_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tlt_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS task_list_template_tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_id INT UNSIGNED NOT NULL,
  title VARCHAR(220) NOT NULL,
  description TEXT NULL,
  priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  estimated_hours DECIMAL(7,2) NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tltt_template FOREIGN KEY (template_id) REFERENCES task_list_templates(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS project_members (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  project_role ENUM('owner','manager','member','viewer') NOT NULL DEFAULT 'member',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_project_user (project_id, user_id),
  CONSTRAINT fk_pm_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_pm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  phase_id INT UNSIGNED NULL,
  task_list_id INT UNSIGNED NULL,
  parent_task_id INT UNSIGNED NULL,
  task_code VARCHAR(40) NULL,
  title VARCHAR(220) NOT NULL,
  description TEXT NULL,
  assigned_to INT UNSIGNED NULL,
  status ENUM('open','in_progress','review','completed','blocked') NOT NULL DEFAULT 'open',
  priority ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  start_date DATE NULL,
  due_date DATE NULL,
  estimated_hours DECIMAL(7,2) NULL,
  progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tasks_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  CONSTRAINT fk_tasks_phase FOREIGN KEY (phase_id) REFERENCES project_phases(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_task_list FOREIGN KEY (task_list_id) REFERENCES task_lists(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_parent FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_assigned FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_creator FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS task_comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  comment TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comments_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS task_attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NULL,
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_attachments_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  CONSTRAINT fk_attachments_user FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS work_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id INT UNSIGNED NULL,
  project_group VARCHAR(180) NOT NULL,
  phase VARCHAR(120) NOT NULL,
  module_name VARCHAR(180) NOT NULL,
  task_category VARCHAR(180) NOT NULL,
  notes TEXT NOT NULL,
  daily_log VARCHAR(160) NULL,
  time_period VARCHAR(80) NULL,
  hours DECIMAL(6,2) NOT NULL,
  log_date DATE NOT NULL,
  billing_type ENUM('Billable','Non-Billable') NOT NULL DEFAULT 'Billable',
  user_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_work_logs_user FOREIGN KEY (user_id) REFERENCES users(id)
);

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

INSERT INTO users (name, email, password_hash, role, designation, department)
VALUES
  ('System Admin', 'admin@eduriser.in', '$2y$10$vTxGF8z.FWAj/MeY2q3jL.aRYWHjF/ZOBLIpdXt4kVO1LuRIU0vky', 'admin', 'Administrator', 'Technology')
ON DUPLICATE KEY UPDATE email = email;
