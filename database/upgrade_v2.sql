-- ==========================================================
-- Upgrade Script: Renewal Extensions, Service Hours & Billing Status
-- Execute in phpMyAdmin / MySQL to add new columns safely
-- ==========================================================

-- 1. Client Batches: Courtesy Extension flags
ALTER TABLE client_batches
ADD COLUMN IF NOT EXISTS is_extended TINYINT(1) NOT NULL DEFAULT 0 AFTER archived_at;

ALTER TABLE client_batches
ADD COLUMN IF NOT EXISTS extension_reason TEXT NULL AFTER is_extended;

-- 2. Client Batch History: Extension action type
ALTER TABLE client_batch_history
MODIFY COLUMN action_type ENUM('created', 'renewed', 'updated', 'extended', 'expired') NOT NULL DEFAULT 'renewed';

-- 3. Projects: Build Hours, Run Hours & Open PO for Service Capacity
ALTER TABLE projects
ADD COLUMN IF NOT EXISTS build_hours DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER adoption_percent;

ALTER TABLE projects
ADD COLUMN IF NOT EXISTS run_hours DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER build_hours;

ALTER TABLE projects
ADD COLUMN IF NOT EXISTS is_open_po TINYINT(1) NOT NULL DEFAULT 0 AFTER run_hours;

-- 4. Work Logs: Billed / Unbilled Status & Invoice Tracking
ALTER TABLE work_logs
ADD COLUMN IF NOT EXISTS billing_status ENUM('unbilled', 'billed') NOT NULL DEFAULT 'unbilled' AFTER billing_type;

ALTER TABLE work_logs
ADD COLUMN IF NOT EXISTS invoice_reference VARCHAR(120) NULL DEFAULT NULL AFTER billing_status;

ALTER TABLE work_logs
ADD COLUMN IF NOT EXISTS billed_at TIMESTAMP NULL DEFAULT NULL AFTER invoice_reference;

ALTER TABLE work_logs
ADD COLUMN IF NOT EXISTS billed_by INT UNSIGNED NULL DEFAULT NULL AFTER billed_at;
