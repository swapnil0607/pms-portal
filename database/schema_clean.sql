-- ==============================================================================
-- PMS (Project Management System) Schema (Sanitized for Recruiter Demo)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

DROP TABLE IF EXISTS `work_logs`;
DROP TABLE IF EXISTS `task_attachments`;
DROP TABLE IF EXISTS `task_comments`;
DROP TABLE IF EXISTS `task_assignees`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `project_members`;
DROP TABLE IF EXISTS `task_list_template_tasks`;
DROP TABLE IF EXISTS `task_list_templates`;
DROP TABLE IF EXISTS `task_lists`;
DROP TABLE IF EXISTS `project_phases`;
DROP TABLE IF EXISTS `project_custom_field_values`;
DROP TABLE IF EXISTS `custom_field_definitions`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `client_batch_history`;
DROP TABLE IF EXISTS `client_batches`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `notification_logs`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `timesheets`;
DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(160) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','manager','member','viewer') NOT NULL DEFAULT 'member',
  `permissions` TEXT NULL,
  `designation` VARCHAR(120) NULL,
  `department` VARCHAR(120) NULL,
  `avatar_url` VARCHAR(255) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clients` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(180) NOT NULL UNIQUE,
  `notes` TEXT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `projects` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(180) NOT NULL,
  `code` VARCHAR(40) NULL UNIQUE,
  `client_id` INT UNSIGNED NULL,
  `project_group` VARCHAR(180) NULL,
  `color` VARCHAR(7) NULL,
  `description` TEXT NULL,
  `owner_id` INT UNSIGNED NOT NULL,
  `status` ENUM('planned','active','on_hold','completed','cancelled') NOT NULL DEFAULT 'planned',
  `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `billed_learners` INT UNSIGNED NULL,
  `learners_on_platform` INT UNSIGNED NULL,
  `learners_connected` INT UNSIGNED NULL,
  `started_with_courses` INT UNSIGNED NULL,
  `total_time` DECIMAL(10,2) NULL,
  `average_time_per_learner` DECIMAL(8,2) NULL,
  `adoption_percent` DECIMAL(6,2) NULL,
  `build_hours` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `run_hours` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `is_open_po` TINYINT(1) NOT NULL DEFAULT 0,
  `start_date` DATE NULL,
  `due_date` DATE NULL,
  `progress` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_projects_owner FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`),
  CONSTRAINT fk_projects_client FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_phases` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_phases_project FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_lists` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT UNSIGNED NOT NULL,
  `phase_id` INT UNSIGNED NULL,
  `name` VARCHAR(160) NOT NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_task_lists_project FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  CONSTRAINT fk_task_lists_phase FOREIGN KEY (`phase_id`) REFERENCES `project_phases`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_members` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `project_role` ENUM('owner','manager','member','viewer') NOT NULL DEFAULT 'member',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_project_user` (`project_id`, `user_id`),
  CONSTRAINT fk_pm_project FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  CONSTRAINT fk_pm_user FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tasks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT UNSIGNED NOT NULL,
  `phase_id` INT UNSIGNED NULL,
  `task_list_id` INT UNSIGNED NULL,
  `parent_task_id` INT UNSIGNED NULL,
  `title` VARCHAR(220) NOT NULL,
  `description` TEXT NULL,
  `status` ENUM('not_started','in_progress','under_review','completed','blocked') NOT NULL DEFAULT 'not_started',
  `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `assigned_to` INT UNSIGNED NULL,
  `created_by` INT UNSIGNED NOT NULL,
  `due_date` DATE NULL,
  `estimated_hours` DECIMAL(7,2) NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_tasks_project FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  CONSTRAINT fk_tasks_phase FOREIGN KEY (`phase_id`) REFERENCES `project_phases`(`id`) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_task_list FOREIGN KEY (`task_list_id`) REFERENCES `task_lists`(`id`) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_parent FOREIGN KEY (`parent_task_id`) REFERENCES `tasks`(`id`) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_assigned FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_creator FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_comments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `comment` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comments_task FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  CONSTRAINT fk_comments_user FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `work_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `task_id` INT UNSIGNED NULL,
  `project_group` VARCHAR(180) NOT NULL,
  `phase` VARCHAR(120) NOT NULL,
  `module_name` VARCHAR(180) NOT NULL,
  `task_category` VARCHAR(180) NOT NULL,
  `notes` TEXT NOT NULL,
  `daily_log` VARCHAR(160) NULL,
  `time_period` VARCHAR(80) NULL,
  `hours` DECIMAL(6,2) NOT NULL,
  `log_date` DATE NOT NULL,
  `billing_type` ENUM('Billable','Non-Billable') NOT NULL DEFAULT 'Billable',
  `billing_status` ENUM('unbilled', 'billed') NOT NULL DEFAULT 'unbilled',
  `invoice_reference` VARCHAR(120) NULL,
  `billed_at` TIMESTAMP NULL,
  `billed_by` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_work_logs_user FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(60) NOT NULL,
  `entity_type` VARCHAR(60) NOT NULL,
  `entity_id` INT UNSIGNED NULL,
  `payload` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;