-- ==============================================================================
-- PMS Complete Clean Production Schema (22 Relational Tables)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(30) NOT NULL,
  `summary` text DEFAULT NULL,
  `old_values` longtext DEFAULT NULL,
  `new_values` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(180) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `client_batches`;
CREATE TABLE `client_batches` (
  `id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `batch_name` varchar(180) NOT NULL,
  `region_department` varchar(180) DEFAULT NULL,
  `licence_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `creation_date` date NOT NULL,
  `renewal_date` date NOT NULL,
  `platform_start_date` date DEFAULT NULL,
  `platform_renewal_date` date DEFAULT NULL,
  `given_by` varchar(180) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('active','renewed','cancelled','archived') NOT NULL DEFAULT 'active',
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `archived_at` timestamp NULL DEFAULT NULL,
  `is_extended` tinyint(1) NOT NULL DEFAULT 0,
  `extension_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `client_batch_history`;
CREATE TABLE `client_batch_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `batch_id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `action_type` enum('created','renewed','updated','extended','expired','archived','unarchived') NOT NULL DEFAULT 'renewed',
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `platform_period_start` date DEFAULT NULL,
  `platform_period_end` date DEFAULT NULL,
  `licence_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `given_by` varchar(180) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `custom_field_definitions`;
CREATE TABLE `custom_field_definitions` (
  `id` int(10) UNSIGNED NOT NULL,
  `field_key` varchar(60) NOT NULL,
  `label` varchar(120) NOT NULL,
  `field_type` enum('text','number','date') NOT NULL DEFAULT 'text',
  `visible` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `notification_logs`;
CREATE TABLE `notification_logs` (
  `id` int(11) NOT NULL,
  `trigger_key` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(160) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(180) NOT NULL,
  `code` varchar(40) DEFAULT NULL,
  `client_id` int(10) UNSIGNED DEFAULT NULL,
  `project_group` varchar(180) DEFAULT NULL,
  `color` varchar(7) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `owner_id` int(10) UNSIGNED NOT NULL,
  `status` enum('planned','active','on_hold','completed','cancelled') NOT NULL DEFAULT 'planned',
  `archived_at` timestamp NULL DEFAULT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `billed_learners` int(10) UNSIGNED DEFAULT NULL,
  `learners_on_platform` int(10) UNSIGNED DEFAULT NULL,
  `learners_connected` int(10) UNSIGNED DEFAULT NULL,
  `started_with_courses` int(10) UNSIGNED DEFAULT NULL,
  `total_time` decimal(10,2) DEFAULT NULL,
  `average_time_per_learner` decimal(8,2) DEFAULT NULL,
  `adoption_percent` decimal(6,2) DEFAULT NULL,
  `build_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `run_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_open_po` tinyint(1) NOT NULL DEFAULT 0,
  `is_internal` tinyint(1) NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `progress` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `project_custom_field_values`;
CREATE TABLE `project_custom_field_values` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `field_definition_id` int(10) UNSIGNED NOT NULL,
  `value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `project_members`;
CREATE TABLE `project_members` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `project_role` enum('owner','manager','member','viewer') NOT NULL DEFAULT 'member',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `project_phases`;
CREATE TABLE `project_phases` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(160) NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `phase_id` int(10) UNSIGNED DEFAULT NULL,
  `task_list_id` int(10) UNSIGNED DEFAULT NULL,
  `parent_task_id` int(10) UNSIGNED DEFAULT NULL,
  `task_code` varchar(40) DEFAULT NULL,
  `title` varchar(220) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('open','in_progress','review','completed','blocked') NOT NULL DEFAULT 'open',
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `start_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `estimated_hours` decimal(7,2) DEFAULT NULL,
  `progress` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `task_assignees`;
CREATE TABLE `task_assignees` (
  `task_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

DROP TABLE IF EXISTS `task_attachments`;
CREATE TABLE `task_attachments` (
  `id` int(10) UNSIGNED NOT NULL,
  `task_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(120) DEFAULT NULL,
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `task_comments`;
CREATE TABLE `task_comments` (
  `id` int(10) UNSIGNED NOT NULL,
  `task_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `task_lists`;
CREATE TABLE `task_lists` (
  `id` int(10) UNSIGNED NOT NULL,
  `project_id` int(10) UNSIGNED NOT NULL,
  `phase_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(180) NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `task_list_templates`;
CREATE TABLE `task_list_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(180) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `task_list_template_tasks`;
CREATE TABLE `task_list_template_tasks` (
  `id` int(10) UNSIGNED NOT NULL,
  `template_id` int(10) UNSIGNED NOT NULL,
  `title` varchar(220) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `estimated_hours` decimal(7,2) DEFAULT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `timesheets`;
CREATE TABLE `timesheets` (
  `id` int(10) UNSIGNED NOT NULL,
  `task_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `work_date` date NOT NULL,
  `hours` decimal(6,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `billable` tinyint(1) NOT NULL DEFAULT 0,
  `approval_status` enum('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','manager','member','viewer') NOT NULL DEFAULT 'member',
  `permissions` text DEFAULT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `department` varchar(120) DEFAULT NULL,
  `avatar_path` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `work_logs`;
CREATE TABLE `work_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `task_id` int(10) UNSIGNED DEFAULT NULL,
  `project_group` varchar(180) NOT NULL,
  `phase` varchar(120) NOT NULL,
  `module_name` varchar(180) NOT NULL,
  `task_category` varchar(180) NOT NULL,
  `notes` text NOT NULL,
  `daily_log` varchar(160) DEFAULT NULL,
  `time_period` varchar(80) DEFAULT NULL,
  `hours` decimal(6,2) NOT NULL,
  `log_date` date NOT NULL,
  `billing_type` enum('Billable','Non-Billable') NOT NULL DEFAULT 'Billable',
  `billing_status` enum('unbilled','billed') NOT NULL DEFAULT 'unbilled',
  `invoice_reference` varchar(120) DEFAULT NULL,
  `billed_at` timestamp NULL DEFAULT NULL,
  `billed_by` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Indexes, AUTO_INCREMENT & Constraints
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_created_at` (`created_at`),
  ADD KEY `idx_audit_action` (`action`);

ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

ALTER TABLE `client_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cb_client` (`client_id`),
  ADD KEY `fk_cb_project` (`project_id`),
  ADD KEY `fk_cb_creator` (`created_by`);

ALTER TABLE `client_batch_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cbh_batch` (`batch_id`),
  ADD KEY `fk_cbh_client` (`client_id`),
  ADD KEY `fk_cbh_project` (`project_id`),
  ADD KEY `fk_cbh_creator` (`created_by`);

ALTER TABLE `custom_field_definitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `field_key` (`field_key`);

ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`);

ALTER TABLE `notification_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `trigger_key` (`trigger_key`);

ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token_hash`),
  ADD KEY `idx_email` (`email`);

ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `fk_projects_owner` (`owner_id`),
  ADD KEY `fk_projects_client` (`client_id`);

ALTER TABLE `project_custom_field_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_project_field` (`project_id`,`field_definition_id`),
  ADD KEY `fk_pcfv_field` (`field_definition_id`);

ALTER TABLE `project_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_project_user` (`project_id`,`user_id`),
  ADD KEY `fk_pm_user` (`user_id`);

ALTER TABLE `project_phases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_project_phases_project` (`project_id`);

ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tasks_project` (`project_id`),
  ADD KEY `fk_tasks_phase` (`phase_id`),
  ADD KEY `fk_tasks_task_list` (`task_list_id`),
  ADD KEY `fk_tasks_parent` (`parent_task_id`),
  ADD KEY `fk_tasks_assigned` (`assigned_to`),
  ADD KEY `fk_tasks_creator` (`created_by`);

ALTER TABLE `task_assignees`
  ADD PRIMARY KEY (`task_id`,`user_id`),
  ADD KEY `fk_task_assignees_user` (`user_id`);

ALTER TABLE `task_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_attachments_task` (`task_id`),
  ADD KEY `fk_attachments_user` (`user_id`);

ALTER TABLE `task_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_comments_task` (`task_id`),
  ADD KEY `fk_comments_user` (`user_id`);

ALTER TABLE `task_lists`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_task_lists_project` (`project_id`),
  ADD KEY `fk_task_lists_phase` (`phase_id`);

ALTER TABLE `task_list_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tlt_created_by` (`created_by`);

ALTER TABLE `task_list_template_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tltt_template` (`template_id`);

ALTER TABLE `timesheets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_timesheets_task` (`task_id`),
  ADD KEY `fk_timesheets_user` (`user_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

ALTER TABLE `work_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_work_logs_user` (`user_id`);

ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=425;

ALTER TABLE `clients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

ALTER TABLE `client_batches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

ALTER TABLE `client_batch_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

ALTER TABLE `custom_field_definitions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

ALTER TABLE `notification_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `projects`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

ALTER TABLE `project_custom_field_values`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

ALTER TABLE `project_members`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

ALTER TABLE `project_phases`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

ALTER TABLE `tasks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2901;

ALTER TABLE `task_attachments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `task_comments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `task_lists`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=269;

ALTER TABLE `task_list_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `task_list_template_tasks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

ALTER TABLE `timesheets`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

ALTER TABLE `work_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3903;

ALTER TABLE `client_batches`
  ADD CONSTRAINT `fk_cb_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cb_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_cb_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

ALTER TABLE `client_batch_history`
  ADD CONSTRAINT `fk_cbh_batch` FOREIGN KEY (`batch_id`) REFERENCES `client_batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cbh_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cbh_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_cbh_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

ALTER TABLE `projects`
  ADD CONSTRAINT `fk_projects_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_projects_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`);

ALTER TABLE `project_custom_field_values`
  ADD CONSTRAINT `fk_pcfv_field` FOREIGN KEY (`field_definition_id`) REFERENCES `custom_field_definitions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pcfv_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

ALTER TABLE `project_members`
  ADD CONSTRAINT `fk_pm_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `project_phases`
  ADD CONSTRAINT `fk_project_phases_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_tasks_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tasks_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_tasks_parent` FOREIGN KEY (`parent_task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tasks_phase` FOREIGN KEY (`phase_id`) REFERENCES `project_phases` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tasks_task_list` FOREIGN KEY (`task_list_id`) REFERENCES `task_lists` (`id`) ON DELETE SET NULL;

ALTER TABLE `task_assignees`
  ADD CONSTRAINT `fk_task_assignees_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_task_assignees_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `task_attachments`
  ADD CONSTRAINT `fk_attachments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attachments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `task_comments`
  ADD CONSTRAINT `fk_comments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `task_lists`
  ADD CONSTRAINT `fk_task_lists_phase` FOREIGN KEY (`phase_id`) REFERENCES `project_phases` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_task_lists_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

ALTER TABLE `task_list_templates`
  ADD CONSTRAINT `fk_tlt_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `task_list_template_tasks`
  ADD CONSTRAINT `fk_tltt_template` FOREIGN KEY (`template_id`) REFERENCES `task_list_templates` (`id`) ON DELETE CASCADE;

ALTER TABLE `timesheets`
  ADD CONSTRAINT `fk_timesheets_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_timesheets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

ALTER TABLE `work_logs`
  ADD CONSTRAINT `fk_work_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

SET FOREIGN_KEY_CHECKS = 1;
