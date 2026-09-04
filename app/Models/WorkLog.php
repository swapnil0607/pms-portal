<?php

namespace App\Models;

use App\Core\Database;

class WorkLog
{
    public const CATEGORIES = [
        'SB' => 'SB',
        'VD' => 'VD',
        'DEV' => 'DEV',
        'QA' => 'QA',
        'PUB' => 'PUB',
        'TRN' => 'TRN',
        'MNT' => 'MNT',
    ];

    public static function create(array $data): int
    {
        $data['task_id'] = $data['task_id'] ?? null;
        $data['billing_status'] = $data['billing_status'] ?? (($data['billing_type'] ?? 'Billable') === 'Billable' ? 'unbilled' : 'unbilled');
        $data['invoice_reference'] = $data['invoice_reference'] ?? null;

        $stmt = Database::connection()->prepare(
            'INSERT INTO work_logs
                (task_id, project_group, phase, module_name, task_category, notes, daily_log, time_period, hours, log_date, billing_type, billing_status, invoice_reference, user_id)
             VALUES
                (:task_id, :project_group, :phase, :module_name, :task_category, :notes, :daily_log, :time_period, :hours, :log_date, :billing_type, :billing_status, :invoice_reference, :user_id)'
        );
        $stmt->execute($data);
        $logId = (int) Database::connection()->lastInsertId();

        \App\Services\AuditService::log(
            'work_log',
            $logId,
            'created',
            null,
            $data,
            null,
            (int) ($data['user_id'] ?? 0)
        );

        return $logId;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT wl.*, u.name AS user_name
             FROM work_logs wl
             JOIN users u ON u.id = wl.user_id
             WHERE wl.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function update(int $id, array $data): void
    {
        $old = self::find($id);

        $stmt = Database::connection()->prepare(
            'UPDATE work_logs
             SET project_group = :project_group,
                 phase = :phase,
                 module_name = :module_name,
                 task_category = :task_category,
                 notes = :notes,
                 daily_log = :daily_log,
                 time_period = :time_period,
                 hours = :hours,
                 log_date = :log_date,
                 billing_type = :billing_type
             WHERE id = :id'
        );
        $data['id'] = $id;
        $stmt->execute($data);

        if ($old) {
            \App\Services\AuditService::log(
                'work_log',
                $id,
                'updated',
                $old,
                $data
            );
        }
    }

    public static function ensureBillingColumnsExist(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        try {
            $db = Database::connection();
            $wlCols = $db->query("SHOW COLUMNS FROM work_logs")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('billing_status', $wlCols, true)) {
                $db->exec("ALTER TABLE work_logs ADD COLUMN billing_status ENUM('unbilled', 'billed') NOT NULL DEFAULT 'unbilled' AFTER billing_type");
            }
            if (!in_array('invoice_reference', $wlCols, true)) {
                $db->exec("ALTER TABLE work_logs ADD COLUMN invoice_reference VARCHAR(120) NULL DEFAULT NULL AFTER billing_status");
            }
            if (!in_array('billed_at', $wlCols, true)) {
                $db->exec("ALTER TABLE work_logs ADD COLUMN billed_at TIMESTAMP NULL DEFAULT NULL AFTER invoice_reference");
            }
            if (!in_array('billed_by', $wlCols, true)) {
                $db->exec("ALTER TABLE work_logs ADD COLUMN billed_by INT UNSIGNED NULL DEFAULT NULL AFTER billed_at");
            }

            Project::ensureServiceHoursColumnsExist();
            $ensured = true;
        } catch (\Throwable $e) {
            // Ignored if user lacks ALTER privileges or tables locked
        }
    }

    public static function markBilled(int|array $ids, ?string $invoiceRef = null, ?int $userId = null): void
    {
        self::ensureBillingColumnsExist();
        $idList = is_array($ids) ? array_map('intval', $ids) : [(int) $ids];
        $idList = array_filter($idList, static fn($id) => $id > 0);
        if (empty($idList)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $sql = "UPDATE work_logs
                SET billing_status = 'billed',
                    invoice_reference = ?,
                    billed_at = CURRENT_TIMESTAMP,
                    billed_by = ?
                WHERE id IN ({$placeholders})";

        $params = array_merge([$invoiceRef ? trim($invoiceRef) : null, $userId], $idList);
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        foreach ($idList as $bid) {
            \App\Services\AuditService::log(
                'work_log',
                $bid,
                'billed',
                ['billing_status' => 'unbilled'],
                ['billing_status' => 'billed', 'invoice_reference' => $invoiceRef],
                "Marked work log #{$bid} as Billed" . ($invoiceRef ? " (Invoice: {$invoiceRef})" : ''),
                $userId
            );
        }
    }

    public static function markUnbilled(int|array $ids): void
    {
        self::ensureBillingColumnsExist();
        $idList = is_array($ids) ? array_map('intval', $ids) : [(int) $ids];
        $idList = array_filter($idList, static fn($id) => $id > 0);
        if (empty($idList)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($idList), '?'));
        $sql = "UPDATE work_logs
                SET billing_status = 'unbilled',
                    invoice_reference = NULL,
                    billed_at = NULL,
                    billed_by = NULL
                WHERE id IN ({$placeholders})";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($idList);

        foreach ($idList as $uid) {
            \App\Services\AuditService::log(
                'work_log',
                $uid,
                'unbilled',
                ['billing_status' => 'billed'],
                ['billing_status' => 'unbilled'],
                "Marked work log #{$uid} as Unbilled"
            );
        }
    }

    public static function markProjectBilled(int $projectId, ?string $invoiceRef = null, ?int $userId = null): void
    {
        self::ensureBillingColumnsExist();
        $project = Project::find($projectId);
        if (!$project) {
            return;
        }

        $db = Database::connection();
        $sql = "UPDATE work_logs wl
                LEFT JOIN tasks t ON t.id = wl.task_id
                SET wl.billing_status = 'billed',
                    wl.invoice_reference = :invoice_ref,
                    wl.billed_at = CURRENT_TIMESTAMP,
                    wl.billed_by = :billed_by
                WHERE (t.project_id = :project_id OR wl.project_group = :project_name)
                  AND wl.billing_type = 'Billable'";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'invoice_ref' => $invoiceRef ? trim($invoiceRef) : null,
            'billed_by' => $userId,
            'project_id' => $projectId,
            'project_name' => $project['name'],
        ]);
    }

    public static function markProjectUnbilled(int $projectId): void
    {
        self::ensureBillingColumnsExist();
        $project = Project::find($projectId);
        if (!$project) {
            return;
        }

        $db = Database::connection();
        $sql = "UPDATE work_logs wl
                LEFT JOIN tasks t ON t.id = wl.task_id
                SET wl.billing_status = 'unbilled',
                    wl.invoice_reference = NULL,
                    wl.billed_at = NULL,
                    wl.billed_by = NULL
                WHERE (t.project_id = :project_id OR wl.project_group = :project_name)
                  AND wl.billing_type = 'Billable'";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'project_id' => $projectId,
            'project_name' => $project['name'],
        ]);
    }

    public static function markPhaseBilled(int $phaseId, ?string $invoiceRef = null, ?int $userId = null): void
    {
        self::ensureBillingColumnsExist();
        $db = Database::connection();
        $phaseStmt = $db->prepare("SELECT ph.*, p.name AS project_name FROM project_phases ph JOIN projects p ON p.id = ph.project_id WHERE ph.id = ?");
        $phaseStmt->execute([$phaseId]);
        $phase = $phaseStmt->fetch();
        if (!$phase) {
            return;
        }

        $sql = "UPDATE work_logs wl
                LEFT JOIN tasks t ON t.id = wl.task_id
                SET wl.billing_status = 'billed',
                    wl.invoice_reference = :invoice_ref,
                    wl.billed_at = CURRENT_TIMESTAMP,
                    wl.billed_by = :billed_by
                WHERE ((t.phase_id = :phase_id) OR (wl.phase = :phase_name AND (t.project_id = :project_id OR wl.project_group = :project_name)))
                  AND wl.billing_type = 'Billable'";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'invoice_ref' => $invoiceRef ? trim($invoiceRef) : null,
            'billed_by' => $userId,
            'phase_id' => $phaseId,
            'phase_name' => $phase['name'],
            'project_id' => (int) $phase['project_id'],
            'project_name' => $phase['project_name'],
        ]);
    }

    public static function markPhaseUnbilled(int $phaseId): void
    {
        self::ensureBillingColumnsExist();
        $db = Database::connection();
        $phaseStmt = $db->prepare("SELECT ph.*, p.name AS project_name FROM project_phases ph JOIN projects p ON p.id = ph.project_id WHERE ph.id = ?");
        $phaseStmt->execute([$phaseId]);
        $phase = $phaseStmt->fetch();
        if (!$phase) {
            return;
        }

        $sql = "UPDATE work_logs wl
                LEFT JOIN tasks t ON t.id = wl.task_id
                SET wl.billing_status = 'unbilled',
                    wl.invoice_reference = NULL,
                    wl.billed_at = NULL,
                    wl.billed_by = NULL
                WHERE ((t.phase_id = :phase_id) OR (wl.phase = :phase_name AND (t.project_id = :project_id OR wl.project_group = :project_name)))
                  AND wl.billing_type = 'Billable'";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'phase_id' => $phaseId,
            'phase_name' => $phase['name'],
            'project_id' => (int) $phase['project_id'],
            'project_name' => $phase['project_name'],
        ]);
    }

    public static function userWeeklyBreakdown(int $userId, string $weekStart, string $weekEnd): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT log_date, SUM(hours) AS total_hours,
                    SUM(CASE WHEN billing_type = \'Billable\' THEN hours ELSE 0 END) AS billable_hours,
                    SUM(CASE WHEN billing_type = \'Non-Billable\' THEN hours ELSE 0 END) AS non_billable_hours
             FROM work_logs
             WHERE user_id = ? AND log_date BETWEEN ? AND ?
             GROUP BY log_date
             ORDER BY log_date ASC'
        );
        $stmt->execute([$userId, $weekStart, $weekEnd]);
        return $stmt->fetchAll();
    }

    public static function userRecentLogs(int $userId, int $limit = 10): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT wl.*,
                    COALESCE(p.name, np.name, wl.project_group) AS resolved_project_name,
                    COALESCE(ph.name, wl.phase) AS resolved_phase_name,
                    COALESCE(tl.name, wl.module_name) AS resolved_module_name,
                    t.title AS task_title
             FROM work_logs wl
             LEFT JOIN tasks t ON t.id = wl.task_id
             LEFT JOIN projects p ON p.id = t.project_id
             LEFT JOIN project_phases ph ON ph.id = t.phase_id
             LEFT JOIN task_lists tl ON tl.id = t.task_list_id
             LEFT JOIN projects np ON np.name = wl.project_group
             WHERE wl.user_id = ?
             ORDER BY wl.log_date DESC, wl.id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function delete(int $id): void
    {
        $old = self::find($id);
        Database::connection()->prepare('DELETE FROM work_logs WHERE id = ?')->execute([$id]);
        if ($old) {
            \App\Services\AuditService::log(
                'work_log',
                $id,
                'deleted',
                $old,
                null
            );
        }
    }

    public static function forTask(int $taskId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT wl.*, u.name AS user_name
             FROM work_logs wl
             JOIN users u ON u.id = wl.user_id
             WHERE wl.task_id = ?
             ORDER BY wl.log_date DESC, wl.created_at DESC"
        );
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    public static function recentForUser(int $userId, int $limit = 8): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT wl.*, u.name AS user_name
             FROM work_logs wl
             JOIN users u ON u.id = wl.user_id
             WHERE wl.user_id = ?
             ORDER BY wl.log_date DESC, wl.created_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function billingStats(array $filters = []): array
    {
        self::ensureBillingColumnsExist();
        $where = [];
        $params = [];

        if (!empty($filters['from_date'])) {
            $where[] = 'wl.log_date >= ?';
            $params[] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $where[] = 'wl.log_date <= ?';
            $params[] = $filters['to_date'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'wl.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['billing_status'])) {
            $where[] = 'wl.billing_status = ?';
            $params[] = $filters['billing_status'];
        }
        if (!empty($filters['project_group'])) {
            $term = '%' . trim((string) $filters['project_group']) . '%';
            $where[] = '(COALESCE(p.name, np.name, wl.project_group) LIKE ? OR COALESCE(c.name, nc.name, \'\') LIKE ? OR wl.project_group LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        if (!empty($filters['client_id'])) {
            $where[] = '(p.client_id = ? OR np.client_id = ?)';
            $params[] = (int) $filters['client_id'];
            $params[] = (int) $filters['client_id'];
        }

        if (!empty($filters['phase'])) {
            $where[] = 'COALESCE(ph.name, wl.phase) = ?';
            $params[] = $filters['phase'];
        }

        if (!empty($filters['module_name'])) {
            $where[] = 'COALESCE(tl.name, wl.module_name) = ?';
            $params[] = $filters['module_name'];
        }

        // Differentiate internal projects so internal R&D/meetings don't inflate commercial unbilled receivables
        $sql = "SELECT
                    COALESCE(SUM(wl.hours), 0) AS total_logged,
                    COALESCE(SUM(CASE WHEN wl.billing_type = 'Billable' AND LOWER(COALESCE(c.name, nc.name, '')) NOT LIKE '%internal%' THEN wl.hours ELSE 0 END), 0) AS total_billable,
                    COALESCE(SUM(CASE WHEN wl.billing_type = 'Non-Billable' OR LOWER(COALESCE(c.name, nc.name, '')) LIKE '%internal%' THEN wl.hours ELSE 0 END), 0) AS total_non_billable,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(c.name, nc.name, '')) LIKE '%internal%' THEN wl.hours ELSE 0 END), 0) AS internal_hours,
                    COALESCE(SUM(CASE WHEN wl.billing_status = 'unbilled' AND wl.billing_type = 'Billable' AND LOWER(COALESCE(c.name, nc.name, '')) NOT LIKE '%internal%' THEN wl.hours ELSE 0 END), 0) AS unbilled_hours,
                    COALESCE(SUM(CASE WHEN wl.billing_status = 'billed' THEN wl.hours ELSE 0 END), 0) AS billed_hours,
                    COUNT(CASE WHEN wl.billing_status = 'unbilled' AND wl.billing_type = 'Billable' AND LOWER(COALESCE(c.name, nc.name, '')) NOT LIKE '%internal%' THEN 1 END) AS unbilled_count,
                    COUNT(CASE WHEN wl.billing_status = 'billed' THEN 1 END) AS billed_count
                FROM work_logs wl
                LEFT JOIN tasks t ON t.id = wl.task_id
                LEFT JOIN projects p ON p.id = t.project_id
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN task_lists tl ON tl.id = t.task_list_id
                LEFT JOIN project_phases ph ON ph.id = t.phase_id
                LEFT JOIN projects np ON np.name = wl.project_group
                LEFT JOIN clients nc ON nc.id = np.client_id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch() ?: [
                'total_logged' => 0,
                'total_billable' => 0,
                'total_non_billable' => 0,
                'internal_hours' => 0,
                'unbilled_hours' => 0,
                'billed_hours' => 0,
                'unbilled_count' => 0,
                'billed_count' => 0,
            ];
        } catch (\Throwable $e) {
            return [
                'total_logged' => 0,
                'total_billable' => 0,
                'total_non_billable' => 0,
                'internal_hours' => 0,
                'unbilled_hours' => 0,
                'billed_hours' => 0,
                'unbilled_count' => 0,
                'billed_count' => 0,
            ];
        }
    }

    public static function billingSummaryByProject(array $filters = []): array
    {
        self::ensureBillingColumnsExist();
        $db = Database::connection();
        $where = [];
        $params = [];

        if (!empty($filters['project_group'])) {
            $term = '%' . trim((string) $filters['project_group']) . '%';
            $where[] = '(p.name LIKE ? OR c.name LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['client_id'])) {
            $where[] = 'p.client_id = ?';
            $params[] = (int) $filters['client_id'];
        }

        // Fetch projects with their latest active renewal batch (if any) and historical logging milestones
        $sql = "SELECT
                    p.*,
                    p.id AS project_id,
                    p.name AS project_name,
                    p.code AS project_code,
                    p.status AS project_status,
                    c.name AS client_name,
                    batch.batch_id,
                    batch.batch_name,
                    batch.creation_date AS batch_creation_date,
                    batch.renewal_date AS batch_renewal_date,
                    batch.licence_count AS batch_licence_count,
                    COALESCE(all_logs.total_logged, 0) AS all_time_logged,
                    COALESCE(all_logs.total_billable, 0) AS all_time_billable,
                    COALESCE(all_logs.total_non_billable, 0) AS all_time_non_billable,
                    COALESCE(all_logs.unbilled_hours, 0) AS all_time_unbilled,
                    COALESCE(all_logs.billed_hours, 0) AS all_time_billed,
                    all_logs.first_log_date,
                    all_logs.last_log_date,
                    all_logs.last_billed_date,
                    all_logs.last_billed_at,
                    all_logs.sample_invoice_reference
                FROM projects p
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN (
                    SELECT cb1.id AS batch_id, cb1.project_id AS cb_project_id, cb1.client_id AS cb_client_id,
                           cb1.batch_name, cb1.creation_date, cb1.renewal_date, cb1.licence_count, cb1.status AS batch_status
                    FROM client_batches cb1
                    INNER JOIN (
                        SELECT project_id, MAX(id) AS max_id
                        FROM client_batches
                        WHERE status IN ('active', 'renewed')
                        GROUP BY project_id
                    ) latest_cb ON latest_cb.max_id = cb1.id
                ) batch ON (batch.cb_project_id = p.id OR (batch.cb_project_id IS NULL AND batch.cb_client_id = p.client_id))
                LEFT JOIN (
                    SELECT
                        COALESCE(t.project_id, np.id) AS matched_project_id,
                        SUM(wl.hours) AS total_logged,
                        SUM(CASE WHEN wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS total_billable,
                        SUM(CASE WHEN wl.billing_type = 'Non-Billable' THEN wl.hours ELSE 0 END) AS total_non_billable,
                        SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'unbilled' AND wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS unbilled_hours,
                        SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'billed' THEN wl.hours ELSE 0 END) AS billed_hours,
                        MIN(wl.log_date) AS first_log_date,
                        MAX(wl.log_date) AS last_log_date,
                        MAX(CASE WHEN wl.billing_status = 'billed' THEN wl.log_date END) AS last_billed_date,
                        MAX(CASE WHEN wl.billing_status = 'billed' THEN wl.billed_at END) AS last_billed_at,
                        MAX(wl.invoice_reference) AS sample_invoice_reference
                    FROM work_logs wl
                    LEFT JOIN tasks t ON t.id = wl.task_id
                    LEFT JOIN projects np ON np.name = wl.project_group
                    GROUP BY matched_project_id
                ) all_logs ON all_logs.matched_project_id = p.id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.name ASC';

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            try {
                // Fallback if client_batches table doesn't exist yet
                $sqlFallback = "SELECT
                                    p.*,
                                    p.id AS project_id,
                                    p.name AS project_name,
                                    p.code AS project_code,
                                    p.status AS project_status,
                                    c.name AS client_name,
                                    NULL AS batch_id,
                                    NULL AS batch_name,
                                    NULL AS batch_creation_date,
                                    NULL AS batch_renewal_date,
                                    NULL AS batch_licence_count,
                                    COALESCE(all_logs.total_logged, 0) AS all_time_logged,
                                    COALESCE(all_logs.total_billable, 0) AS all_time_billable,
                                    COALESCE(all_logs.total_non_billable, 0) AS all_time_non_billable,
                                    COALESCE(all_logs.unbilled_hours, 0) AS all_time_unbilled,
                                    COALESCE(all_logs.billed_hours, 0) AS all_time_billed,
                                    all_logs.first_log_date,
                                    all_logs.last_log_date,
                                    all_logs.last_billed_date,
                                    all_logs.last_billed_at,
                                    all_logs.sample_invoice_reference
                                FROM projects p
                                LEFT JOIN clients c ON c.id = p.client_id
                                LEFT JOIN (
                                    SELECT
                                        COALESCE(t.project_id, np.id) AS matched_project_id,
                                        SUM(wl.hours) AS total_logged,
                                        SUM(CASE WHEN wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS total_billable,
                                        SUM(CASE WHEN wl.billing_type = 'Non-Billable' THEN wl.hours ELSE 0 END) AS total_non_billable,
                                        SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'unbilled' AND wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS unbilled_hours,
                                        SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'billed' THEN wl.hours ELSE 0 END) AS billed_hours,
                                        MIN(wl.log_date) AS first_log_date,
                                        MAX(wl.log_date) AS last_log_date,
                                        MAX(CASE WHEN wl.billing_status = 'billed' THEN wl.log_date END) AS last_billed_date,
                                        MAX(CASE WHEN wl.billing_status = 'billed' THEN wl.billed_at END) AS last_billed_at,
                                        MAX(wl.invoice_reference) AS sample_invoice_reference
                                    FROM work_logs wl
                                    LEFT JOIN tasks t ON t.id = wl.task_id
                                    LEFT JOIN projects np ON np.name = wl.project_group
                                    GROUP BY matched_project_id
                                ) all_logs ON all_logs.matched_project_id = p.id" . ($where ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY p.name ASC';
                $stmt = $db->prepare($sqlFallback);
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
            } catch (\Throwable $e2) {
                return [];
            }
        }

        $hasCustomDates = !empty($filters['from_date']) || !empty($filters['to_date']) || !empty($filters['user_id']);
        $contractModelFilter = $filters['contract_model'] ?? '';
        $billingStatusFilter = $filters['billing_status'] ?? '';

        $results = [];

        foreach ($rows as $row) {
            try {
                $isInternal = Project::isInternal($row);
                $hasRenewalBatch = !empty($row['batch_id']) && !empty($row['batch_creation_date']);
                $isOpenPo = !empty($row['is_open_po']);
                $allocated = (float) ($row['build_hours'] ?? 0) + (float) ($row['run_hours'] ?? 0);
                $projId = (int) ($row['project_id'] ?? $row['id'] ?? 0);
                $projName = (string) ($row['project_name'] ?? $row['name'] ?? '');

                // Determine Contract Model
                if ($isInternal) {
                    $contractModelKey = 'internal';
                    $contractModelLabel = '🏢 Internal (Non-Billable)';
                } elseif ($hasRenewalBatch) {
                    $contractModelKey = 'yearly_renewal';
                    $contractModelLabel = '🔄 Yearly Renewal' . (!empty($row['batch_name']) ? ' (' . $row['batch_name'] . ')' : '');
                } elseif ($isOpenPo) {
                    $contractModelKey = 'open_po';
                    $contractModelLabel = '🔵 Open PO (Hourly)';
                } else {
                    $contractModelKey = 'contract_cap';
                    $contractModelLabel = $allocated > 0 ? ('🟢 Cap: ' . number_format($allocated, 1) . ' hrs') : '⚪ Fixed / Unset';
                }

                // Apply contract_model filter if set
                if ($contractModelFilter !== '') {
                    if ($contractModelFilter === 'yearly_renewal' && $contractModelKey !== 'yearly_renewal') {
                        continue;
                    }
                    if ($contractModelFilter === 'open_po' && $contractModelKey !== 'open_po') {
                        continue;
                    }
                    if ($contractModelFilter === 'contract_cap' && $contractModelKey !== 'contract_cap') {
                        continue;
                    }
                    if ($contractModelFilter === 'internal' && $contractModelKey !== 'internal') {
                        continue;
                    }
                }

                // Compute active cycle date range and scoped metrics
                if ($isInternal) {
                    $cycleLabel = 'Ongoing Internal Activity';
                    $totalLogged = (float) ($row['all_time_logged'] ?? 0);
                    $totalBillable = 0.00;
                    $billedHours = 0.00;
                    $unbilledHours = 0.00;
                    $contractBalanceLabel = '-';
                    $statusKey = 'internal';
                    $statusLabel = 'Internal (Non-Billable)';
                    $statusClass = 'internal';
                    $pct = 0.0;
                    $canBill = false;
                } elseif ($hasCustomDates) {
                    // User specified custom date or user filter
                    $fromFmt = !empty($filters['from_date']) ? date('d M Y', strtotime($filters['from_date'])) : 'Inception';
                    $toFmt = !empty($filters['to_date']) ? date('d M Y', strtotime($filters['to_date'])) : 'Till Date';
                    $cycleLabel = "Filtered: {$fromFmt} – {$toFmt}";
                    $cycleStartDate = $filters['from_date'] ?: null;
                    $cycleEndDate = $filters['to_date'] ?: null;

                    // Query cycle logs
                    $cycleLogs = self::getProjectLogsInDateRange($projId, $projName, $cycleStartDate, $cycleEndDate, !empty($filters['user_id']) ? (int) $filters['user_id'] : null);
                    $totalLogged = $cycleLogs['total_logged'];
                    $totalBillable = $cycleLogs['total_billable'];
                    $billedHours = $cycleLogs['billed_hours'];
                    $unbilledHours = $cycleLogs['unbilled_hours'];

                    $contractBalanceLabel = $isOpenPo ? '∞ (Hourly)' : ($allocated > 0 ? (number_format(max(0, $allocated - (float)($row['all_time_logged'] ?? 0)), 2) . ' hrs') : '-');
                    $pct = $totalBillable > 0 ? round(($billedHours / $totalBillable) * 100, 1) : 0.0;
                    $canBill = $unbilledHours > 0;
                } elseif ($hasRenewalBatch) {
                    // Yearly Renewal project: All-time totals matching top KPI cards
                    $renewalStart = $row['batch_creation_date'];
                    $renewalEnd = $row['batch_renewal_date'] ?? null;
                    $startFmt = date('d M Y', strtotime($renewalStart));
                    $endFmt = !empty($renewalEnd) ? (' (Exp: ' . date('d M Y', strtotime($renewalEnd)) . ')') : '';
                    $cycleLabel = "Renewal: {$startFmt} – Till Date{$endFmt}";

                    // Calculate active cycle hours as additional breakdown context
                    $cycleLogs = self::getProjectLogsInDateRange($projId, $projName, $renewalStart, null);
                    $cycleLogged = $cycleLogs['total_logged'];
                    $cycleBillable = $cycleLogs['total_billable'];

                    // Use complete project lifetime hours to align with dashboard KPI cards
                    $totalLogged = (float) ($row['all_time_logged'] ?? 0);
                    $totalBillable = (float) ($row['all_time_billable'] ?? 0);
                    $billedHours = (float) ($row['all_time_billed'] ?? 0);
                    $unbilledHours = (float) ($row['all_time_unbilled'] ?? 0);

                    $contractBalanceLabel = $allocated > 0 ? (number_format(max(0, $allocated - $totalLogged), 2) . ' hrs') : '-';
                    $pct = $totalBillable > 0 ? round(($billedHours / $totalBillable) * 100, 1) : 0.0;
                    $canBill = $unbilledHours > 0;
                } elseif ($isOpenPo) {
                    // Open PO client: default from Last Billed Date to Till Date
                    if (!empty($row['last_billed_date'])) {
                        $billedDateFmt = date('d M Y', strtotime($row['last_billed_date']));
                        $cycleLabel = "Since Last Billed ({$billedDateFmt}) – Till Date";
                    } else {
                        $firstLogFmt = !empty($row['first_log_date']) ? date('d M Y', strtotime($row['first_log_date'])) : 'Project Start';
                        $cycleLabel = "All Time ({$firstLogFmt}) – Till Date";
                    }

                    $totalLogged = (float) ($row['all_time_logged'] ?? 0);
                    $totalBillable = (float) ($row['all_time_billable'] ?? 0);
                    $billedHours = (float) ($row['all_time_billed'] ?? 0);
                    $unbilledHours = (float) ($row['all_time_unbilled'] ?? 0);

                    $contractBalanceLabel = '∞ (Hourly)';
                    $pct = $totalBillable > 0 ? round(($billedHours / $totalBillable) * 100, 1) : 0.0;
                    $canBill = $unbilledHours > 0;
                } else {
                    // Fixed Cap / Standard project
                    if (!empty($row['last_billed_date'])) {
                        $billedDateFmt = date('d M Y', strtotime($row['last_billed_date']));
                        $cycleLabel = "Since Last Billed ({$billedDateFmt}) – Till Date";
                    } else {
                        $cycleLabel = "Project Lifetime";
                    }

                    $totalLogged = (float) ($row['all_time_logged'] ?? 0);
                    $totalBillable = (float) ($row['all_time_billable'] ?? 0);
                    $billedHours = (float) ($row['all_time_billed'] ?? 0);
                    $unbilledHours = (float) ($row['all_time_unbilled'] ?? 0);

                    $contractBalanceLabel = $allocated > 0 ? (number_format(max(0, $allocated - $totalLogged), 2) . ' hrs') : '-';
                    $pct = $totalBillable > 0 ? round(($billedHours / $totalBillable) * 100, 1) : 0.0;
                    $canBill = $unbilledHours > 0;
                }

                if (!$isInternal) {
                    if ($totalBillable <= 0) {
                        $statusKey = 'no_billable';
                        $statusLabel = 'No Billable Time';
                        $statusClass = 'muted';
                    } elseif ($unbilledHours <= 0 && $billedHours > 0) {
                        $statusKey = 'fully_billed';
                        $statusLabel = 'Fully Billed (100%)';
                        $statusClass = 'success';
                    } elseif ($billedHours > 0 && $unbilledHours > 0) {
                        $statusKey = 'partially_billed';
                        $statusLabel = "Partially Billed ({$pct}%)";
                        $statusClass = 'warning';
                    } else {
                        $statusKey = 'unbilled';
                        $statusLabel = 'Unbilled (Pending)';
                        $statusClass = 'unbilled';
                    }
                }

                // Apply billing_status filter if set
                if ($billingStatusFilter !== '') {
                    if ($billingStatusFilter === 'unbilled' && $statusKey !== 'unbilled') {
                        continue;
                    }
                    if ($billingStatusFilter === 'partially_billed' && $statusKey !== 'partially_billed') {
                        continue;
                    }
                    if ($billingStatusFilter === 'fully_billed' && $statusKey !== 'fully_billed') {
                        continue;
                    }
                    if ($billingStatusFilter === 'internal' && $statusKey !== 'internal') {
                        continue;
                    }
                }

                $row['project_id'] = $projId;
                $row['project_name'] = $projName;
                $row['contract_model_key'] = $contractModelKey;
                $row['contract_model_label'] = $contractModelLabel;
                $row['billing_cycle_label'] = $cycleLabel;
                $row['total_logged'] = $totalLogged;
                $row['total_logged_hours'] = $totalLogged;
                $row['total_billable'] = $totalBillable;
                $row['billable_hours'] = $totalBillable;
                $row['billed_hours'] = $billedHours;
                $row['unbilled_hours'] = $unbilledHours;
                $row['billed_percent'] = $pct;
                $row['contract_balance_label'] = $contractBalanceLabel;
                $row['contract_balance'] = $allocated > 0 ? max(0, $allocated - $totalLogged) : null;
                $row['cycle_logged'] = $cycleLogged ?? null;
                $row['cycle_billable'] = $cycleBillable ?? null;
                $row['billing_status_key'] = $statusKey;
                $row['billing_status_label'] = $statusLabel;
                $row['billing_status_class'] = $statusClass;
                $row['total_allocated_hours'] = $allocated;
                $row['is_open_po'] = $isOpenPo;
                $row['is_internal'] = $isInternal;
                $row['can_bill'] = $canBill;

                $results[] = $row;
            } catch (\Throwable $eRow) {
                // If single row has issue, continue safely
                continue;
            }
        }

        return $results;
    }

    private static function getProjectLogsInDateRange(mixed $projectId, mixed $projectName, ?string $fromDate = null, ?string $toDate = null, ?int $userId = null): array
    {
        try {
            $db = Database::connection();
            $where = ['(t.project_id = ? OR wl.project_group = ?)'];
            $params = [(int) $projectId, (string) $projectName];

            if (!empty($fromDate)) {
                $where[] = 'wl.log_date >= ?';
                $params[] = $fromDate;
            }
            if (!empty($toDate)) {
                $where[] = 'wl.log_date <= ?';
                $params[] = $toDate;
            }
            if (!empty($userId) && $userId > 0) {
                $where[] = 'wl.user_id = ?';
                $params[] = $userId;
            }

            $sql = "SELECT
                        COALESCE(SUM(wl.hours), 0) AS total_logged,
                        COALESCE(SUM(CASE WHEN wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END), 0) AS total_billable,
                        COALESCE(SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'billed' THEN wl.hours ELSE 0 END), 0) AS billed_hours,
                        COALESCE(SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'unbilled' AND wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END), 0) AS unbilled_hours
                    FROM work_logs wl
                    LEFT JOIN tasks t ON t.id = wl.task_id
                    WHERE " . implode(' AND ', $where);

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetch();
            return [
                'total_logged' => (float) ($res['total_logged'] ?? 0),
                'total_billable' => (float) ($res['total_billable'] ?? 0),
                'billed_hours' => (float) ($res['billed_hours'] ?? 0),
                'unbilled_hours' => (float) ($res['unbilled_hours'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return [
                'total_logged' => 0.0,
                'total_billable' => 0.0,
                'billed_hours' => 0.0,
                'unbilled_hours' => 0.0,
            ];
        }
    }

    public static function billingSummaryByPhase(array $filters = []): array
    {
        $db = Database::connection();
        $where = [];
        $params = [];

        if (!empty($filters['project_group'])) {
            $term = '%' . trim((string) $filters['project_group']) . '%';
            $where[] = '(p.name LIKE ? OR c.name LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        $logDateWhere = '';
        $logParams = [];
        if (!empty($filters['from_date'])) {
            $logDateWhere .= ' AND wl.log_date >= ?';
            $logParams[] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $logDateWhere .= ' AND wl.log_date <= ?';
            $logParams[] = $filters['to_date'];
        }
        if (!empty($filters['user_id'])) {
            $logDateWhere .= ' AND wl.user_id = ?';
            $logParams[] = (int) $filters['user_id'];
        }

        $sql = "SELECT
                    p.id AS project_id,
                    p.name AS project_name,
                    p.code AS project_code,
                    c.name AS client_name,
                    ph.id AS phase_id,
                    ph.name AS phase_name,
                    ph.sort_order AS phase_sort_order,
                    COALESCE(logs.total_logged, 0) AS total_logged,
                    COALESCE(logs.total_billable, 0) AS total_billable,
                    COALESCE(logs.total_non_billable, 0) AS total_non_billable,
                    COALESCE(logs.unbilled_hours, 0) AS unbilled_hours,
                    COALESCE(logs.billed_hours, 0) AS billed_hours,
                    COALESCE(logs.unbilled_count, 0) AS unbilled_count,
                    COALESCE(logs.billed_count, 0) AS billed_count,
                    logs.sample_invoice_reference
                FROM project_phases ph
                JOIN projects p ON p.id = ph.project_id
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN (
                    SELECT
                        COALESCE(t.project_id, np.id) AS matched_project_id,
                        COALESCE(t.phase_id, nph.id) AS matched_phase_id,
                        wl.phase AS raw_phase_name,
                        SUM(wl.hours) AS total_logged,
                        SUM(CASE WHEN wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS total_billable,
                        SUM(CASE WHEN wl.billing_type = 'Non-Billable' THEN wl.hours ELSE 0 END) AS total_non_billable,
                        SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'unbilled' AND wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS unbilled_hours,
                        SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'billed' THEN wl.hours ELSE 0 END) AS billed_hours,
                        COUNT(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'unbilled' AND wl.billing_type = 'Billable' THEN 1 END) AS unbilled_count,
                        COUNT(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'billed' THEN 1 END) AS billed_count,
                        MAX(wl.invoice_reference) AS sample_invoice_reference
                    FROM work_logs wl
                    LEFT JOIN tasks t ON t.id = wl.task_id
                    LEFT JOIN projects np ON np.name = wl.project_group
                    LEFT JOIN project_phases nph ON nph.name = wl.phase AND nph.project_id = np.id
                    WHERE 1=1 {$logDateWhere}
                    GROUP BY matched_project_id, matched_phase_id, raw_phase_name
                ) logs ON logs.matched_project_id = p.id AND (logs.matched_phase_id = ph.id OR logs.raw_phase_name = ph.name)";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.name ASC, ph.sort_order ASC, ph.name ASC';

        try {
            $allParams = array_merge($logParams, $params);
            $stmt = $db->prepare($sql);
            $stmt->execute($allParams);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            $sqlFallback = str_replace(
                ["COALESCE(wl.billing_status, 'unbilled')", "wl.invoice_reference"],
                ["'unbilled'", "NULL"],
                $sql
            );
            $stmt = $db->prepare($sqlFallback);
            $stmt->execute($allParams);
            $rows = $stmt->fetchAll();
        }

        return array_map(static function ($row) {
            $billable = (float) $row['total_billable'];
            $billed = (float) $row['billed_hours'];
            $unbilled = (float) $row['unbilled_hours'];
            $pct = $billable > 0 ? round(($billed / $billable) * 100, 1) : 0.0;

            if ($billable <= 0) {
                $statusKey = 'no_billable';
                $statusLabel = 'No Billable Time';
                $statusClass = 'muted';
            } elseif ($unbilled <= 0 && $billed > 0) {
                $statusKey = 'fully_billed';
                $statusLabel = 'Fully Billed (100%)';
                $statusClass = 'success';
            } elseif ($billed > 0 && $unbilled > 0) {
                $statusKey = 'partially_billed';
                $statusLabel = "Partially Billed ({$pct}%)";
                $statusClass = 'warning';
            } else {
                $statusKey = 'unbilled';
                $statusLabel = 'Unbilled (Pending)';
                $statusClass = 'unbilled';
            }

            $row['billed_percent'] = $pct;
            $row['billing_status_key'] = $statusKey;
            $row['billing_status_label'] = $statusLabel;
            $row['billing_status_class'] = $statusClass;
            return $row;
        }, $rows);
    }

    public static function serviceHoursReport(array $filters = []): array
    {
        self::ensureBillingColumnsExist();
        $db = Database::connection();
        $where = [];
        $params = [];

        if (!empty($filters['project_group'])) {
            $term = '%' . trim((string) $filters['project_group']) . '%';
            $where[] = '(p.name LIKE ? OR c.name LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['client_id'])) {
            $where[] = 'p.client_id = ?';
            $params[] = (int) $filters['client_id'];
        }

        $sql = "SELECT
                    p.*,
                    p.id AS project_id,
                    p.name AS project_name,
                    p.code AS project_code,
                    p.status AS project_status,
                    c.name AS client_name,
                    batch.batch_id,
                    batch.batch_name,
                    batch.creation_date AS batch_creation_date,
                    batch.renewal_date AS batch_renewal_date,
                    batch.licence_count AS batch_licence_count,
                    COALESCE(all_logs.total_logged, 0) AS all_time_logged,
                    COALESCE(all_logs.total_billable, 0) AS all_time_billable,
                    COALESCE(all_logs.total_non_billable, 0) AS all_time_non_billable,
                    COALESCE(all_logs.unbilled_hours, 0) AS all_time_unbilled,
                    COALESCE(all_logs.billed_hours, 0) AS all_time_billed,
                    all_logs.first_log_date,
                    all_logs.last_log_date,
                    all_logs.last_billed_date
                FROM projects p
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN (
                    SELECT cb1.id AS batch_id, cb1.project_id AS cb_project_id, cb1.client_id AS cb_client_id,
                           cb1.batch_name, cb1.creation_date, cb1.renewal_date, cb1.licence_count, cb1.status AS batch_status
                    FROM client_batches cb1
                    INNER JOIN (
                        SELECT project_id, MAX(id) AS max_id
                        FROM client_batches
                        WHERE status IN ('active', 'renewed')
                        GROUP BY project_id
                    ) latest_cb ON latest_cb.max_id = cb1.id
                ) batch ON (batch.cb_project_id = p.id OR (batch.cb_project_id IS NULL AND batch.cb_client_id = p.client_id))
                LEFT JOIN (
                    SELECT
                        COALESCE(t.project_id, np.id) AS matched_project_id,
                        SUM(wl.hours) AS total_logged,
                        SUM(CASE WHEN wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS total_billable,
                        SUM(CASE WHEN wl.billing_type = 'Non-Billable' THEN wl.hours ELSE 0 END) AS total_non_billable,
                        SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'unbilled' AND wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS unbilled_hours,
                        SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'billed' THEN wl.hours ELSE 0 END) AS billed_hours,
                        MIN(wl.log_date) AS first_log_date,
                        MAX(wl.log_date) AS last_log_date,
                        MAX(CASE WHEN wl.billing_status = 'billed' THEN wl.log_date END) AS last_billed_date
                    FROM work_logs wl
                    LEFT JOIN tasks t ON t.id = wl.task_id
                    LEFT JOIN projects np ON np.name = wl.project_group
                    GROUP BY matched_project_id
                ) all_logs ON all_logs.matched_project_id = p.id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY p.name ASC';

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            try {
                $sqlFallback = "SELECT
                                    p.*,
                                    p.id AS project_id,
                                    p.name AS project_name,
                                    p.code AS project_code,
                                    p.status AS project_status,
                                    c.name AS client_name,
                                    NULL AS batch_id,
                                    NULL AS batch_name,
                                    NULL AS batch_creation_date,
                                    NULL AS batch_renewal_date,
                                    NULL AS batch_licence_count,
                                    COALESCE(all_logs.total_logged, 0) AS all_time_logged,
                                    COALESCE(all_logs.total_billable, 0) AS all_time_billable,
                                    COALESCE(all_logs.total_non_billable, 0) AS all_time_non_billable,
                                    COALESCE(all_logs.unbilled_hours, 0) AS all_time_unbilled,
                                    COALESCE(all_logs.billed_hours, 0) AS all_time_billed,
                                    all_logs.first_log_date,
                                    all_logs.last_log_date,
                                    all_logs.last_billed_date
                                FROM projects p
                                LEFT JOIN clients c ON c.id = p.client_id
                                LEFT JOIN (
                                    SELECT
                                        COALESCE(t.project_id, np.id) AS matched_project_id,
                                        SUM(wl.hours) AS total_logged,
                                        SUM(CASE WHEN wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS total_billable,
                                        SUM(CASE WHEN wl.billing_type = 'Non-Billable' THEN wl.hours ELSE 0 END) AS total_non_billable,
                                        SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'unbilled' AND wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS unbilled_hours,
                                        SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'billed' THEN wl.hours ELSE 0 END) AS billed_hours,
                                        MIN(wl.log_date) AS first_log_date,
                                        MAX(wl.log_date) AS last_log_date,
                                        MAX(CASE WHEN wl.billing_status = 'billed' THEN wl.log_date END) AS last_billed_date
                                    FROM work_logs wl
                                    LEFT JOIN tasks t ON t.id = wl.task_id
                                    LEFT JOIN projects np ON np.name = wl.project_group
                                    GROUP BY matched_project_id
                                ) all_logs ON all_logs.matched_project_id = p.id" . ($where ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY p.name ASC';
                $stmt = $db->prepare($sqlFallback);
                $stmt->execute($params);
                $rows = $stmt->fetchAll();
            } catch (\Throwable $e2) {
                return [];
            }
        }

        $hasCustomDates = !empty($filters['from_date']) || !empty($filters['to_date']) || !empty($filters['user_id']);
        $contractModelFilter = $filters['contract_model'] ?? '';
        $results = [];

        foreach ($rows as $row) {
            try {
                $isInternal = Project::isInternal($row);
                $hasRenewalBatch = !empty($row['batch_id']) && !empty($row['batch_creation_date']);
                $isOpenPo = !empty($row['is_open_po']);
                $buildHours = (float) ($row['build_hours'] ?? 0);
                $runHours = (float) ($row['run_hours'] ?? 0);
                $allocated = $buildHours + $runHours;
                $projId = (int) ($row['project_id'] ?? $row['id'] ?? 0);
                $projName = (string) ($row['project_name'] ?? $row['name'] ?? '');

                // Contract model
                if ($isInternal) {
                    $contractModelKey = 'internal';
                    $contractModelLabel = '🏢 Internal (Non-Billable)';
                } elseif ($hasRenewalBatch) {
                    $contractModelKey = 'yearly_renewal';
                    $contractModelLabel = '🔄 Yearly Renewal' . (!empty($row['batch_name']) ? ' (' . $row['batch_name'] . ')' : '');
                } elseif ($isOpenPo) {
                    $contractModelKey = 'open_po';
                    $contractModelLabel = '🔵 Open PO (Hourly)';
                } else {
                    $contractModelKey = 'contract_cap';
                    $contractModelLabel = $allocated > 0 ? ('🟢 Cap: ' . number_format($allocated, 1) . ' hrs') : '⚪ Fixed / Unset';
                }

                if ($contractModelFilter !== '') {
                    if ($contractModelFilter === 'yearly_renewal' && $contractModelKey !== 'yearly_renewal') {
                        continue;
                    }
                    if ($contractModelFilter === 'open_po' && $contractModelKey !== 'open_po') {
                        continue;
                    }
                    if ($contractModelFilter === 'contract_cap' && $contractModelKey !== 'contract_cap') {
                        continue;
                    }
                    if ($contractModelFilter === 'internal' && $contractModelKey !== 'internal') {
                        continue;
                    }
                }

                if ($isInternal) {
                    $cycleLabel = 'Ongoing Internal Activity';
                    $logged = (float) ($row['all_time_logged'] ?? 0);
                    $billable = 0.00;
                    $nonBillable = $logged;
                    $remaining = 0.00;
                    $consumption = 0.0;
                    $isAlert90 = false;
                    $isAlert75 = false;
                } elseif ($hasCustomDates) {
                    $fromFmt = !empty($filters['from_date']) ? date('d M Y', strtotime($filters['from_date'])) : 'Inception';
                    $toFmt = !empty($filters['to_date']) ? date('d M Y', strtotime($filters['to_date'])) : 'Till Date';
                    $cycleLabel = "Filtered: {$fromFmt} – {$toFmt}";
                    $cycleStartDate = $filters['from_date'] ?: null;
                    $cycleEndDate = $filters['to_date'] ?: null;

                    $cycleLogs = self::getProjectLogsInDateRange($projId, $projName, $cycleStartDate, $cycleEndDate, !empty($filters['user_id']) ? (int) $filters['user_id'] : null);
                    $logged = $cycleLogs['total_logged'];
                    $billable = $cycleLogs['total_billable'];
                    $nonBillable = max(0, $logged - $billable);

                    if ($isOpenPo) {
                        $remaining = 0.0;
                        $consumption = 0.0;
                        $isAlert90 = false;
                        $isAlert75 = false;
                    } else {
                        $remaining = round($allocated - (float)($row['all_time_logged'] ?? 0), 2);
                        $consumption = $allocated > 0 ? round(((float)($row['all_time_logged'] ?? 0) / $allocated) * 100, 1) : 0.0;
                        $isAlert90 = ($allocated > 0 && $consumption >= 90.0);
                        $isAlert75 = ($allocated > 0 && $consumption >= 75.0 && $consumption < 90.0);
                    }
                } elseif ($hasRenewalBatch) {
                    $renewalStart = $row['batch_creation_date'];
                    $renewalEnd = $row['batch_renewal_date'] ?? null;
                    $startFmt = date('d M Y', strtotime($renewalStart));
                    $endFmt = !empty($renewalEnd) ? (' (Exp: ' . date('d M Y', strtotime($renewalEnd)) . ')') : '';
                    $cycleLabel = "Renewal: {$startFmt} – Till Date{$endFmt}";

                    // Calculate active cycle hours as additional breakdown context
                    $cycleLogs = self::getProjectLogsInDateRange($projId, $projName, $renewalStart, null);
                    $cycleLogged = $cycleLogs['total_logged'];
                    $cycleBillable = $cycleLogs['total_billable'];

                    // Use complete project lifetime hours to align with dashboard KPI cards
                    $logged = (float) ($row['all_time_logged'] ?? 0);
                    $billable = (float) ($row['all_time_billable'] ?? 0);
                    $nonBillable = (float) ($row['all_time_non_billable'] ?? 0);

                    $remaining = round($allocated - $logged, 2);
                    $consumption = $allocated > 0 ? round(($logged / $allocated) * 100, 1) : 0.0;
                    $isAlert90 = ($allocated > 0 && $consumption >= 90.0);
                    $isAlert75 = ($allocated > 0 && $consumption >= 75.0 && $consumption < 90.0);
                } elseif ($isOpenPo) {
                    if (!empty($row['last_billed_date'])) {
                        $billedDateFmt = date('d M Y', strtotime($row['last_billed_date']));
                        $cycleLabel = "Since Last Billed ({$billedDateFmt}) – Till Date";
                    } else {
                        $firstLogFmt = !empty($row['first_log_date']) ? date('d M Y', strtotime($row['first_log_date'])) : 'Project Start';
                        $cycleLabel = "All Time ({$firstLogFmt}) – Till Date";
                    }

                    $logged = (float) ($row['all_time_logged'] ?? 0);
                    $billable = (float) ($row['all_time_billable'] ?? 0);
                    $nonBillable = (float) ($row['all_time_non_billable'] ?? 0);
                    $remaining = 0.0;
                    $consumption = 0.0;
                    $isAlert90 = false;
                    $isAlert75 = false;
                } else {
                    if (!empty($row['last_billed_date'])) {
                        $billedDateFmt = date('d M Y', strtotime($row['last_billed_date']));
                        $cycleLabel = "Since Last Billed ({$billedDateFmt}) – Till Date";
                    } else {
                        $cycleLabel = "Project Lifetime";
                    }

                    $logged = (float) ($row['all_time_logged'] ?? 0);
                    $billable = (float) ($row['all_time_billable'] ?? 0);
                    $nonBillable = (float) ($row['all_time_non_billable'] ?? 0);
                    $remaining = round($allocated - $logged, 2);
                    $consumption = $allocated > 0 ? round(($logged / $allocated) * 100, 1) : 0.0;
                    $isAlert90 = ($allocated > 0 && $consumption >= 90.0);
                    $isAlert75 = ($allocated > 0 && $consumption >= 75.0 && $consumption < 90.0);
                }

                $row['project_id'] = $projId;
                $row['project_name'] = $projName;
                $row['is_internal'] = $isInternal;
                $row['is_open_po'] = $isOpenPo;
                $row['contract_model_key'] = $contractModelKey;
                $row['contract_model_label'] = $contractModelLabel;
                $row['billing_cycle_label'] = $cycleLabel;
                $row['build_hours'] = $buildHours;
                $row['run_hours'] = $runHours;
                $row['total_allocated_hours'] = $allocated;
                $row['logged_hours'] = $logged;
                $row['total_logged_hours'] = $logged;
                $row['billable_hours'] = $billable;
                $row['non_billable_hours'] = $nonBillable;
                $row['billed_hours'] = (float) ($row['all_time_billed'] ?? 0);
                $row['unbilled_hours'] = (float) ($row['all_time_unbilled'] ?? 0);
                $row['remaining_hours'] = $remaining;
                $row['consumption_percent'] = $consumption;
                $row['cycle_logged'] = $cycleLogged ?? null;
                $row['cycle_billable'] = $cycleBillable ?? null;
                $row['is_alert_90'] = $isAlert90;
                $row['is_alert_75'] = $isAlert75;

                $results[] = $row;
            } catch (\Throwable $eRow) {
                continue;
            }
        }

        return $results;
    }

    public static function reportCount(array $filters = []): int
    {
        $where = [];
        $params = [];

        if (!empty($filters['from_date'])) {
            $where[] = 'wl.log_date >= ?';
            $params[] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where[] = 'wl.log_date <= ?';
            $params[] = $filters['to_date'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'wl.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['billing_status'])) {
            $where[] = 'wl.billing_status = ?';
            $params[] = $filters['billing_status'];
        }

        if (!empty($filters['client_id'])) {
            $where[] = '(p.client_id = ? OR np.client_id = ?)';
            $params[] = (int) $filters['client_id'];
            $params[] = (int) $filters['client_id'];
        }

        if (!empty($filters['project_group'])) {
            $term = '%' . trim((string) $filters['project_group']) . '%';
            $where[] = '(COALESCE(p.name, np.name, wl.project_group) LIKE ? OR COALESCE(c.name, nc.name, \'\') LIKE ? OR wl.project_group LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['phase'])) {
            $where[] = 'COALESCE(ph.name, wl.phase) = ?';
            $params[] = $filters['phase'];
        }

        if (!empty($filters['module_name'])) {
            $where[] = 'COALESCE(tl.name, wl.module_name) = ?';
            $params[] = $filters['module_name'];
        }

        $sql = "SELECT COUNT(*)
                FROM work_logs wl
                JOIN users u ON u.id = wl.user_id
                LEFT JOIN tasks t ON t.id = wl.task_id
                LEFT JOIN projects p ON p.id = t.project_id
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN task_lists tl ON tl.id = t.task_list_id
                LEFT JOIN project_phases ph ON ph.id = t.phase_id
                LEFT JOIN projects np ON np.name = wl.project_group
                LEFT JOIN clients nc ON nc.id = np.client_id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public static function report(array $filters = [], ?int $limit = null, ?int $offset = null): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['from_date'])) {
            $where[] = 'wl.log_date >= ?';
            $params[] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where[] = 'wl.log_date <= ?';
            $params[] = $filters['to_date'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'wl.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['billing_status'])) {
            $where[] = 'wl.billing_status = ?';
            $params[] = $filters['billing_status'];
        }

        if (!empty($filters['client_id'])) {
            $where[] = '(p.client_id = ? OR np.client_id = ?)';
            $params[] = (int) $filters['client_id'];
            $params[] = (int) $filters['client_id'];
        }

        if (!empty($filters['project_group'])) {
            $term = '%' . trim((string) $filters['project_group']) . '%';
            $where[] = '(COALESCE(p.name, np.name, wl.project_group) LIKE ? OR COALESCE(c.name, nc.name, \'\') LIKE ? OR wl.project_group LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($filters['phase'])) {
            $where[] = 'COALESCE(ph.name, wl.phase) = ?';
            $params[] = $filters['phase'];
        }

        if (!empty($filters['module_name'])) {
            $where[] = 'COALESCE(tl.name, wl.module_name) = ?';
            $params[] = $filters['module_name'];
        }

        $sql = "SELECT wl.*, u.name AS user_name,
                       COALESCE(t.title, wl.task_category) AS report_task_issue,
                       COALESCE(p.name, np.name, wl.project_group) AS resolved_project_name,
                       COALESCE(c.name, nc.name, wl.project_group) AS resolved_client_name,
                       COALESCE(tl.name, wl.module_name) AS resolved_module_name,
                       COALESCE(ph.name, wl.phase) AS resolved_phase_name
                FROM work_logs wl
                JOIN users u ON u.id = wl.user_id
                LEFT JOIN tasks t ON t.id = wl.task_id
                LEFT JOIN projects p ON p.id = t.project_id
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN task_lists tl ON tl.id = t.task_list_id
                LEFT JOIN project_phases ph ON ph.id = t.phase_id
                LEFT JOIN projects np ON np.name = wl.project_group
                LEFT JOIN clients nc ON nc.id = np.client_id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY wl.log_date DESC, u.name, wl.created_at DESC';

        if ($limit !== null && $limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit;
            if ($offset !== null && $offset > 0) {
                $sql .= ' OFFSET ' . (int) $offset;
            }
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function distinctPhases(?string $projectGroup = null): array
    {
        $where = [];
        $params = [];
        if (!empty($projectGroup)) {
            $term = '%' . trim($projectGroup) . '%';
            $where[] = '(COALESCE(p.name, np.name, wl.project_group) LIKE ? OR COALESCE(c.name, nc.name, \'\') LIKE ? OR wl.project_group LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        $sql = "SELECT DISTINCT COALESCE(ph.name, wl.phase) AS phase_name
                FROM work_logs wl
                LEFT JOIN tasks t ON t.id = wl.task_id
                LEFT JOIN projects p ON p.id = t.project_id
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN project_phases ph ON ph.id = t.phase_id
                LEFT JOIN projects np ON np.name = wl.project_group
                LEFT JOIN clients nc ON nc.id = np.client_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY phase_name ASC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return array_values(array_filter(array_column($stmt->fetchAll(), 'phase_name'), fn($v) => $v !== null && $v !== ''));
    }

    public static function distinctModules(?string $projectGroup = null): array
    {
        $where = [];
        $params = [];
        if (!empty($projectGroup)) {
            $term = '%' . trim($projectGroup) . '%';
            $where[] = '(COALESCE(p.name, np.name, wl.project_group) LIKE ? OR COALESCE(c.name, nc.name, \'\') LIKE ? OR wl.project_group LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }
        $sql = "SELECT DISTINCT COALESCE(tl.name, wl.module_name) AS module_name
                FROM work_logs wl
                LEFT JOIN tasks t ON t.id = wl.task_id
                LEFT JOIN projects p ON p.id = t.project_id
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN task_lists tl ON tl.id = t.task_list_id
                LEFT JOIN projects np ON np.name = wl.project_group
                LEFT JOIN clients nc ON nc.id = np.client_id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= " ORDER BY module_name ASC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return array_values(array_filter(array_column($stmt->fetchAll(), 'module_name'), fn($v) => $v !== null && $v !== ''));
    }

    public static function summaryByProject(array $filters = []): array
    {
        return self::projectReport($filters, true);
    }

    /**
     * Project reports must be based on real projects, never a free-text
     * client/group value saved in a historical work log. A task-linked log is
     * resolved through its task's project; older unlinked logs are included
     * only when their saved project text exactly matches a real project name.
     */
    public static function reportByProject(array $filters = []): array
    {
        return self::projectReport($filters, false);
    }

    public static function summaryByCustomer(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['from_date'])) {
            $where[] = 'wl.log_date >= ?';
            $params[] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where[] = 'wl.log_date <= ?';
            $params[] = $filters['to_date'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'wl.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        $sql = "SELECT 
                    COALESCE(
                        c.name,
                        c_direct.name,
                        (
                            SELECT c_sub.name 
                            FROM projects p_sub
                            JOIN clients c_sub ON c_sub.id = p_sub.client_id
                            WHERE p_sub.name = wl.project_group AND p_sub.archived_at IS NULL
                            LIMIT 1
                        ),
                        (
                            SELECT c_sub.name 
                            FROM project_phases ph_sub
                            JOIN projects p_sub ON p_sub.id = ph_sub.project_id
                            JOIN clients c_sub ON c_sub.id = p_sub.client_id
                            WHERE ph_sub.name = wl.phase AND p_sub.archived_at IS NULL
                            LIMIT 1
                        ),
                        (
                            SELECT c_sub.name
                            FROM clients c_sub
                            WHERE wl.project_group LIKE CONCAT('%', c_sub.name, '%')
                               OR c_sub.name LIKE CONCAT('%', wl.project_group, '%')
                            LIMIT 1
                        ),
                        wl.project_group
                    ) AS group_name,
                    COALESCE(SUM(CASE WHEN wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END), 0) AS billable_hours,
                    COALESCE(SUM(CASE WHEN wl.billing_type = 'Non-Billable' THEN wl.hours ELSE 0 END), 0) AS non_billable_hours,
                    COALESCE(SUM(wl.hours), 0) AS logged_hours
                FROM work_logs wl
                LEFT JOIN tasks t ON t.id = wl.task_id
                LEFT JOIN task_lists tl ON tl.id = t.task_list_id
                LEFT JOIN projects p ON p.id = tl.project_id
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN clients c_direct ON c_direct.name = wl.project_group";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " GROUP BY group_name";

        if (!empty($filters['project_group'])) {
            $sql .= " HAVING group_name LIKE ?";
            $params[] = '%' . trim((string) $filters['project_group']) . '%';
        }

        $sql .= " ORDER BY group_name ASC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function timeLogByUser(string $fromDate, string $toDate): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT u.name AS group_name, wl.log_date, COALESCE(SUM(wl.hours), 0) AS hours
             FROM users u
             LEFT JOIN work_logs wl ON wl.user_id = u.id AND wl.log_date BETWEEN ? AND ?
             WHERE u.status = 'active'
             GROUP BY u.id, wl.log_date
             ORDER BY u.name, wl.log_date"
        );
        $stmt->execute([$fromDate, $toDate]);
        return self::matrix($stmt->fetchAll(), $fromDate, $toDate);
    }

    public static function timeLogByClient(string $fromDate, string $toDate, ?string $projectGroup = null): array
    {
        $where = ['wl.log_date BETWEEN ? AND ?'];
        $params = [$fromDate, $toDate];

        if ($projectGroup !== null && $projectGroup !== '') {
            $where[] = '(c.name LIKE ? OR wl.project_group LIKE ?)';
            $params[] = '%' . $projectGroup . '%';
            $params[] = '%' . $projectGroup . '%';
        }

        $stmt = Database::connection()->prepare(
            "SELECT 
                COALESCE(
                    c.name,
                    (
                        SELECT c_sub.name 
                        FROM projects p_sub
                        JOIN clients c_sub ON c_sub.id = p_sub.client_id
                        WHERE p_sub.name = wl.project_group AND p_sub.archived_at IS NULL
                        LIMIT 1
                    ),
                    wl.project_group
                ) AS group_name,
                wl.log_date,
                COALESCE(SUM(wl.hours), 0) AS hours
             FROM work_logs wl
             LEFT JOIN tasks t ON t.id = wl.task_id
             LEFT JOIN task_lists tl ON tl.id = t.task_list_id
             LEFT JOIN projects p ON p.id = tl.project_id
             LEFT JOIN clients c ON c.id = p.client_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY group_name, wl.log_date
             ORDER BY group_name, wl.log_date"
        );
        $stmt->execute($params);
        return self::matrix($stmt->fetchAll(), $fromDate, $toDate);
    }

    /**
     * Per-row drill-down for the Time Logs dashboard: for 'user' rows, nests
     * Project -> Phase -> Module -> Task; for 'client' rows, nests
     * Client -> Project -> Phase -> Module -> Task.
     * Leaves are [log_date => hours] maps (not plain totals) so the
     * breakdown can show hours per day, same as the top-level matrix.
     *
     * Returns ['tree' => ..., 'taskIds' => ...]
     */
    public static function breakdown(string $view, string $fromDate, string $toDate): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT 
                COALESCE(
                    p.name,
                    (
                        SELECT p_sub.name 
                        FROM projects p_sub
                        LEFT JOIN clients c_sub ON c_sub.id = p_sub.client_id
                        LEFT JOIN project_phases ph_sub ON ph_sub.project_id = p_sub.id
                        LEFT JOIN task_lists tl_sub ON tl_sub.project_id = p_sub.id
                        WHERE (c_sub.name = wl.project_group OR p_sub.name LIKE CONCAT('%', wl.project_group, '%'))
                          AND (ph_sub.name = wl.phase OR tl_sub.name = wl.module_name)
                          AND p_sub.archived_at IS NULL
                        LIMIT 1
                    ),
                    (
                        SELECT p_sub.name 
                        FROM projects p_sub
                        WHERE p_sub.name = wl.project_group AND p_sub.archived_at IS NULL
                        LIMIT 1
                    ),
                    (
                        SELECT p_sub.name 
                        FROM projects p_sub
                        JOIN clients c_sub ON c_sub.id = p_sub.client_id
                        WHERE c_sub.name = wl.project_group AND p_sub.archived_at IS NULL
                        LIMIT 1
                    ),
                    wl.project_group
                ) AS project_name,
                COALESCE(
                    c.name,
                    (
                        SELECT c_sub.name 
                        FROM projects p_sub
                        JOIN clients c_sub ON c_sub.id = p_sub.client_id
                        WHERE p_sub.name = wl.project_group AND p_sub.archived_at IS NULL
                        LIMIT 1
                    ),
                    wl.project_group
                ) AS client_name,
                COALESCE(ph.name, wl.phase, 'General Phase') AS phase_name,
                COALESCE(tl.name, wl.module_name, 'General Module') AS module_name,
                COALESCE(t.title, wl.task_category, 'General Task') AS task_label,
                wl.hours,
                wl.log_date,
                u.name AS user_name,
                t.id AS task_id
             FROM work_logs wl
             JOIN users u ON u.id = wl.user_id
             LEFT JOIN tasks t ON t.id = wl.task_id
             LEFT JOIN task_lists tl ON tl.id = t.task_list_id
             LEFT JOIN project_phases ph ON ph.id = tl.phase_id
             LEFT JOIN projects p ON p.id = tl.project_id
             LEFT JOIN clients c ON c.id = p.client_id
             WHERE wl.log_date BETWEEN ? AND ?"
        );
        $stmt->execute([$fromDate, $toDate]);

        $grouped = [];
        $taskIds = [];
        foreach ($stmt->fetchAll() as $row) {
            $userKey = $row['user_name'] ?: 'Unassigned';
            $clientKey = $row['client_name'] ?: 'General Client';
            $projectKey = $row['project_name'] ?: 'General Project';
            $phaseKey = ($row['phase_name'] !== '' && $row['phase_name'] !== 'None') ? $row['phase_name'] : 'General Phase';
            $moduleKey = $row['module_name'] ?: 'General Module';
            $taskKey = $row['task_label'] ?: 'General Task';
            $hours = (float) $row['hours'];
            $date = $row['log_date'];

            if ($view === 'user') {
                // User -> Project -> Phase -> Module -> Task
                $leaf = &$grouped[$userKey][$projectKey][$phaseKey][$moduleKey][$taskKey];
                $taskIdRef = &$taskIds[$userKey][$projectKey][$phaseKey][$moduleKey][$taskKey];
            } else {
                // Client -> Project -> Phase -> Module -> Task
                $leaf = &$grouped[$clientKey][$projectKey][$phaseKey][$moduleKey][$taskKey];
                $taskIdRef = &$taskIds[$clientKey][$projectKey][$phaseKey][$moduleKey][$taskKey];
            }

            $leaf[$date] = ($leaf[$date] ?? 0) + $hours;
            unset($leaf);

            if ($row['task_id'] !== null) {
                $taskIdRef = (int) $row['task_id'];
            }
            unset($taskIdRef);
        }

        self::sortBreakdown($grouped);
        return ['tree' => $grouped, 'taskIds' => $taskIds];
    }

    /**
     * Flattens one row's breakdown() tree into an ordered list of nodes
     * (Client/Task List/Task, whichever levels exist) for rendering as
     * regular table rows: each node carries hours per date (aligned to
     * $dates) plus its own total, and enough parent/depth bookkeeping for
     * the UI to nest and collapse/expand them.
     */
    public static function rowsForBreakdown(array $tree, array $dates, string $rootId = '', array $taskIdTree = []): array
    {
        return self::flattenBreakdown($tree, $dates, 0, $rootId, $taskIdTree)[0];
    }

    private static function flattenBreakdown(array $tree, array $dates, int $depth, string $parentId, array $taskIdTree = []): array
    {
        $rows = [];
        $totalDays = array_fill_keys($dates, 0.0);

        foreach ($tree as $label => $value) {
            $id = ($parentId !== '' ? $parentId . '-' : '') . substr(md5($label), 0, 8);
            $isLeaf = self::isDayMap($value);
            $taskIdNode = $taskIdTree[$label] ?? null;

            if ($isLeaf) {
                $nodeDays = array_fill_keys($dates, 0.0);
                foreach ($value as $date => $hours) {
                    if (array_key_exists($date, $nodeDays)) {
                        $nodeDays[$date] += (float) $hours;
                    }
                }
                $childRows = [];
                $taskId = is_int($taskIdNode) ? $taskIdNode : null;
            } else {
                [$childRows, $nodeDays] = self::flattenBreakdown($value, $dates, $depth + 1, $id, is_array($taskIdNode) ? $taskIdNode : []);
                $taskId = null;
            }

            $rows[] = [
                'id' => $id,
                'parent' => $parentId,
                'depth' => $depth,
                'label' => $label,
                'days' => $nodeDays,
                'total' => array_sum($nodeDays),
                'hasChildren' => !$isLeaf,
                'taskId' => $taskId,
            ];
            $rows = array_merge($rows, $childRows);

            foreach ($nodeDays as $date => $hours) {
                $totalDays[$date] += $hours;
            }
        }

        return [$rows, $totalDays];
    }

    /** A leaf node is a [date => hours] map; branch nodes are keyed by name instead. */
    private static function isDayMap(array $value): bool
    {
        foreach (array_keys($value) as $key) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $key)) {
                return false;
            }
        }
        return true;
    }

    private static function sortBreakdown(array &$branch): void
    {
        if (self::isDayMap($branch)) {
            return;
        }
        ksort($branch);
        foreach ($branch as &$child) {
            if (is_array($child)) {
                self::sortBreakdown($child);
            }
        }
    }

    public static function dateRange(?string $fromDate = null, ?string $toDate = null): array
    {
        $from = $fromDate ?: date('Y-m-01');
        $to = $toDate ?: date('Y-m-t');
        $dates = [];
        $current = strtotime($from);
        $end = strtotime($to);

        while ($current <= $end) {
            $dates[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
        }

        return [$from, $to, $dates];
    }

    private static function summary(string $groupColumn, array $filters, string $joins = ''): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['from_date'])) {
            $where[] = 'wl.log_date >= ?';
            $params[] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where[] = 'wl.log_date <= ?';
            $params[] = $filters['to_date'];
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'wl.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['project_group'])) {
            $where[] = 'wl.project_group LIKE ?';
            $params[] = '%' . $filters['project_group'] . '%';
        }

        $sql = "SELECT {$groupColumn} AS group_name,
                       SUM(CASE WHEN wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS billable_hours,
                       SUM(CASE WHEN wl.billing_type = 'Non-Billable' THEN wl.hours ELSE 0 END) AS non_billable_hours,
                       SUM(wl.hours) AS logged_hours
                FROM work_logs wl";

        if ($joins !== '') {
            $sql .= ' ' . $joins;
        }

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " GROUP BY {$groupColumn} ORDER BY {$groupColumn}";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Shared query for Project Report summaries and project-filtered Excel rows. */
    private static function projectReport(array $filters, bool $summary): array
    {
        $where = ['COALESCE(task_project.id, named_project.id) IS NOT NULL'];
        $params = [];

        if (!empty($filters['from_date'])) {
            $where[] = 'wl.log_date >= ?';
            $params[] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $where[] = 'wl.log_date <= ?';
            $params[] = $filters['to_date'];
        }
        if (!empty($filters['user_id'])) {
            $where[] = 'wl.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['billing_status'])) {
            $where[] = 'wl.billing_status = ?';
            $params[] = $filters['billing_status'];
        }

        $projectName = 'COALESCE(task_project.name, named_project.name, wl.project_group)';
        if (!empty($filters['project_group'])) {
            $term = '%' . trim((string) $filters['project_group']) . '%';
            $where[] = "({$projectName} LIKE ? OR COALESCE(task_client.name, named_client.name, '') LIKE ? OR wl.project_group LIKE ?)";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $joins = 'LEFT JOIN tasks t ON t.id = wl.task_id
                  LEFT JOIN projects task_project ON task_project.id = t.project_id
                  LEFT JOIN clients task_client ON task_client.id = task_project.client_id
                  LEFT JOIN projects named_project ON named_project.name = wl.project_group
                  LEFT JOIN clients named_client ON named_client.id = named_project.client_id';

        if ($summary) {
            $sql = "SELECT {$projectName} AS group_name,
                           SUM(CASE WHEN wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS billable_hours,
                           SUM(CASE WHEN wl.billing_type = 'Non-Billable' THEN wl.hours ELSE 0 END) AS non_billable_hours,
                           SUM(wl.hours) AS logged_hours
                    FROM work_logs wl {$joins}
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY {$projectName}
                    ORDER BY {$projectName}";
        } else {
            $sql = "SELECT wl.*, u.name AS user_name,
                           {$projectName} AS resolved_project_name,
                           COALESCE(t.title, wl.task_category) AS report_task_issue
                    FROM work_logs wl
                    JOIN users u ON u.id = wl.user_id
                    {$joins}
                    WHERE " . implode(' AND ', $where) . '
                    ORDER BY wl.log_date DESC, u.name, wl.created_at DESC';
        }

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private static function matrix(array $rows, string $fromDate, string $toDate): array
    {
        [, , $dates] = self::dateRange($fromDate, $toDate);
        $groups = [];

        foreach ($rows as $row) {
            $name = $row['group_name'] ?: 'Unassigned';
            if (!isset($groups[$name])) {
                $groups[$name] = [
                    'name' => $name,
                    'days' => array_fill_keys($dates, 0.0),
                    'total' => 0.0,
                ];
            }

            if ($row['log_date']) {
                $groups[$name]['days'][$row['log_date']] = (float) $row['hours'];
                $groups[$name]['total'] += (float) $row['hours'];
            }
        }

        return array_values($groups);
    }

    /**
     * Cleans up orphaned work logs whose linked tasks no longer exist.
     */
    public static function cleanupOrphanedLogs(): int
    {
        try {
            $db = Database::connection();
            $stmt = $db->query("
                SELECT wl.* FROM work_logs wl 
                WHERE wl.task_id IS NOT NULL 
                  AND wl.task_id > 0 
                  AND NOT EXISTS (SELECT 1 FROM tasks t WHERE t.id = wl.task_id)
            ");
            $orphaned = $stmt->fetchAll();
            if (empty($orphaned)) {
                return 0;
            }

            $ids = array_column($orphaned, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            foreach ($orphaned as $item) {
                \App\Services\AuditService::log(
                    'work_log',
                    (int) $item['id'],
                    'deleted',
                    $item,
                    null
                );
            }

            $delStmt = $db->prepare("DELETE FROM work_logs WHERE id IN ($placeholders)");
            $delStmt->execute($ids);
            return count($ids);
        } catch (\Throwable $e) {
            error_log('Cleanup orphaned logs error: ' . $e->getMessage());
            return 0;
        }
    }
}
