-- ==============================================================================
-- PMS Demo Seeds (All passwords: Demo@2026!)
-- ==============================================================================

SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- 1. Users
INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `permissions`, `designation`, `department`, `status`) VALUES
(1, 'Swapnil Gaonkar', 'admin@demo.com', '$2y$10$T3rGt8ReaE4v5tneu9mOduWR/HCYOIW75pa8JxMeQTIGo4B6LYGP.', 'admin', '*', 'Principal Design Lead & Admin', 'Design & Technology', 'active'),
(2, 'Sarah Jenkins', 'pm@demo.com', '$2y$10$T3rGt8ReaE4v5tneu9mOduWR/HCYOIW75pa8JxMeQTIGo4B6LYGP.', 'manager', '{"projects.manage":true,"tasks.manage":true}', 'Senior Project Manager', 'Delivery', 'active'),
(3, 'Elena Vance', 'designer@demo.com', '$2y$10$T3rGt8ReaE4v5tneu9mOduWR/HCYOIW75pa8JxMeQTIGo4B6LYGP.', 'member', '{"tasks.update":true}', 'Lead UI/UX Designer', 'Product Design', 'active'),
(4, 'Marcus Chen', 'dev@demo.com', '$2y$10$T3rGt8ReaE4v5tneu9mOduWR/HCYOIW75pa8JxMeQTIGo4B6LYGP.', 'member', '{"tasks.update":true}', 'Staff Frontend Engineer', 'Engineering', 'active'),
(5, 'Priya Sharma', 'priya@demo.com', '$2y$10$T3rGt8ReaE4v5tneu9mOduWR/HCYOIW75pa8JxMeQTIGo4B6LYGP.', 'member', '{"tasks.update":true}', 'Product Strategist', 'Product', 'active'),
(6, 'Super Admin', 'admin@pms.demo', '$2y$10$T3rGt8ReaE4v5tneu9mOduWR/HCYOIW75pa8JxMeQTIGo4B6LYGP.', 'admin', '*', 'Principal Design Lead & Admin', 'Design & Technology', 'active'),
(7, 'Project Manager', 'pm@pms.demo', '$2y$10$T3rGt8ReaE4v5tneu9mOduWR/HCYOIW75pa8JxMeQTIGo4B6LYGP.', 'manager', '{"projects.manage":true,"tasks.manage":true}', 'Senior Project Manager', 'Delivery', 'active'),
(8, 'Lead Developer', 'dev@pms.demo', '$2y$10$T3rGt8ReaE4v5tneu9mOduWR/HCYOIW75pa8JxMeQTIGo4B6LYGP.', 'member', '{"tasks.update":true}', 'Staff Frontend Engineer', 'Engineering', 'active');

-- 2. Clients
INSERT INTO `clients` (`id`, `name`, `notes`, `status`) VALUES
(1, 'Aetheria Global Systems', 'Enterprise client with multi-year digital learning portals contract.', 'active'),
(2, 'Apex Mobility & Motors', 'Automotive OEM client developing interactive sales training.', 'active'),
(3, 'NovaTech Cloud Solutions', 'SaaS enterprise with internal engineering onboarding academy.', 'active'),
(4, 'Lumina Healthcare Group', 'Healthcare provider with clinical compliance simulation programs.', 'active');

-- 3. Projects
INSERT INTO `projects` (`id`, `name`, `code`, `client_id`, `project_group`, `color`, `description`, `owner_id`, `status`, `priority`, `build_hours`, `run_hours`, `progress`, `start_date`, `due_date`) VALUES
(1, 'Enterprise Learning Platform 3.0', 'PRJ-AETH-01', 1, 'Web Platforms', '#0284c7', 'Complete UX redesign, course discovery dashboard, and responsive learner portal.', 1, 'active', 'high', 240.00, 60.00, 78, '2026-01-15', '2026-09-30'),
(2, 'Global Rebrand & Unified Design System', 'PRJ-NOVA-02', 3, 'Design Systems', '#8b5cf6', 'Comprehensive tokenized design system in Figma and production HTML/CSS/JS components.', 1, 'active', 'critical', 180.00, 40.00, 85, '2026-02-01', '2026-08-31'),
(3, 'Interactive Technical Training Academy', 'PRJ-APEX-03', 2, 'Interactive Portals', '#10b981', 'Interactive animated modules, 3D simulations, and gamified technician certifications.', 2, 'active', 'medium', 160.00, 25.00, 52, '2026-03-10', '2026-10-15'),
(4, 'Clinical Compliance Simulation Hub', 'PRJ-LUMI-04', 4, 'Healthcare Portals', '#f59e0b', 'Healthcare regulatory and emergency protocol scenario simulations with telemetry.', 2, 'planned', 'medium', 90.00, 10.00, 15, '2026-08-01', '2026-12-20');

-- 4. Project Phases
INSERT INTO `project_phases` (`id`, `project_id`, `name`, `sort_order`) VALUES
(1, 1, 'Research & Information Architecture', 1),
(2, 1, 'Interactive Wireframes & Component Design', 2),
(3, 1, 'High-Fidelity UI & Frontend Implementation', 3),
(4, 1, 'UAT, Analytics & Production Launch', 4),
(5, 2, 'Design Audit & Color Tokenization', 1),
(6, 2, 'Component Library Construction', 2),
(7, 2, 'Documentation & Code Integration', 3);

-- 5. Task Lists
INSERT INTO `task_lists` (`id`, `project_id`, `phase_id`, `name`, `sort_order`) VALUES
(1, 1, 3, 'Frontend Development', 1),
(2, 1, 3, 'Visual Assets & Mockups', 2),
(3, 2, 6, 'Core Atoms & Molecules', 1),
(4, 2, 6, 'Navigation & Island Header Systems', 2);

-- 6. Project Members
INSERT INTO `project_members` (`project_id`, `user_id`, `project_role`) VALUES
(1, 1, 'owner'),
(1, 2, 'manager'),
(1, 3, 'member'),
(1, 4, 'member'),
(2, 1, 'owner'),
(2, 3, 'member'),
(2, 4, 'member'),
(3, 2, 'owner'),
(3, 1, 'manager');

-- 7. Realistic Tasks
INSERT INTO `tasks` (`id`, `project_id`, `phase_id`, `task_list_id`, `title`, `description`, `status`, `priority`, `assigned_to`, `created_by`, `due_date`, `estimated_hours`) VALUES
(1, 1, 3, 1, 'Build responsive floating pill navigation header', 'Implement mobile single-row flex layout with opaque dark menu drawer and Apple glass styling.', 'completed', 'high', 1, 1, '2026-08-15', 14.00),
(2, 1, 3, 1, 'Optimize responsive image loading & WebP compression', 'Scale and compress 50MB of raw screenshots to sub-300KB retina assets with async decoding.', 'completed', 'critical', 4, 1, '2026-08-20', 16.00),
(3, 1, 3, 1, 'Develop interactive learning progress circular widgets', 'Vanilla JS canvas-free telemetry widgets with smooth CSS easing.', 'completed', 'medium', 4, 2, '2026-08-25', 12.00),
(4, 1, 3, 2, 'Design high-fidelity dashboard dark mode themes', 'Craft specular highlights, glassmorphic bento cards, and titanium hardware mockups.', 'under_review', 'high', 3, 1, '2026-09-10', 20.00),
(5, 1, 3, 1, 'Accessibility audit and keyboard tab navigation', 'Ensure WCAG 2.1 AA compliance across all modal dialogs and dropdown menus.', 'in_progress', 'medium', 4, 2, '2026-09-18', 10.00),
(6, 2, 6, 3, 'Tokenize Apple typography scale across all viewports', 'Define clamp formulas for hero display titles and body paragraphs to prevent word-splitting.', 'completed', 'high', 1, 1, '2026-08-28', 8.00),
(7, 2, 6, 4, 'Implement Figma variable token export pipeline', 'Sync Figma color and spacing tokens directly into CSS custom properties.', 'in_progress', 'high', 3, 1, '2026-09-15', 18.00);

-- 8. Work Logs (Rich telemetry for timesheets and dashboards)
INSERT INTO `work_logs` (`task_id`, `project_group`, `phase`, `module_name`, `task_category`, `notes`, `hours`, `log_date`, `billing_type`, `user_id`) VALUES
(1, 'Web Platforms', 'High-Fidelity UI', 'Navigation Bar', 'Frontend Design', 'Implemented single-row flex layout and glassmorphism styling.', 6.50, '2026-08-14', 'Billable', 1),
(1, 'Web Platforms', 'High-Fidelity UI', 'Navigation Bar', 'Frontend Design', 'Tested iPhone Safe-Area compatibility and BFCache reset listener.', 4.00, '2026-08-15', 'Billable', 1),
(2, 'Web Platforms', 'High-Fidelity UI', 'Asset Optimization', 'Performance', 'Batch compressed 36MB of oversized screenshots with bicubic filtering.', 7.00, '2026-08-19', 'Billable', 4),
(3, 'Web Platforms', 'High-Fidelity UI', 'Telemetry Widgets', 'Interaction Design', 'Built reusable 5-card carousel and circle progress rings.', 6.00, '2026-08-24', 'Billable', 4),
(6, 'Design Systems', 'Core Tokens', 'Typography Tokens', 'Design Architecture', 'Established modular clamp typography hierarchy and anti-hyphenation rules.', 5.00, '2026-08-28', 'Billable', 1),
(7, 'Design Systems', 'Tokens', 'Figma Pipeline', 'Design Ops', 'Configured automated CSS variable generation from Figma tokens.', 4.50, '2026-09-02', 'Billable', 3);

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;