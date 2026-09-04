<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use DateTimeImmutable;

class ClientBatch
{
    public static function ensureSchema(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }
        try {
            $db = Database::connection();
            $cols = $db->query("SHOW COLUMNS FROM client_batches")->fetchAll(\PDO::FETCH_COLUMN);
            if (!in_array('archived_at', $cols, true)) {
                $db->exec("ALTER TABLE client_batches ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at");
            }
            if (!in_array('platform_start_date', $cols, true)) {
                $db->exec("ALTER TABLE client_batches ADD COLUMN platform_start_date DATE NULL DEFAULT NULL AFTER renewal_date");
            }
            if (!in_array('platform_renewal_date', $cols, true)) {
                $db->exec("ALTER TABLE client_batches ADD COLUMN platform_renewal_date DATE NULL DEFAULT NULL AFTER platform_start_date");
            }
            $db->exec("ALTER TABLE client_batches MODIFY COLUMN status ENUM('active', 'renewed', 'cancelled', 'archived') NOT NULL DEFAULT 'active'");

            $historyCols = $db->query("SHOW TABLES LIKE 'client_batch_history'")->fetch();
            if ($historyCols) {
                $hCols = $db->query("SHOW COLUMNS FROM client_batch_history")->fetchAll(\PDO::FETCH_COLUMN);
                if (!in_array('platform_period_start', $hCols, true)) {
                    $db->exec("ALTER TABLE client_batch_history ADD COLUMN platform_period_start DATE NULL DEFAULT NULL AFTER period_end");
                }
                if (!in_array('platform_period_end', $hCols, true)) {
                    $db->exec("ALTER TABLE client_batch_history ADD COLUMN platform_period_end DATE NULL DEFAULT NULL AFTER platform_period_start");
                }
                $db->exec("ALTER TABLE client_batch_history MODIFY COLUMN action_type ENUM('created', 'renewed', 'updated', 'extended', 'expired', 'archived', 'unarchived') NOT NULL DEFAULT 'renewed'");
            }
            $ensured = true;
        } catch (\Throwable $e) {
            // Ignored if lacking ALTER privileges or already updated
        }
    }

    public static function all(array $filters = [], string $scheduleMode = 'contract'): array
    {
        self::ensureSchema();
        $db = Database::connection();
        $where = [];
        $params = [];

        $isPlatform = ($scheduleMode === 'platform');
        $effStartExpr = $isPlatform ? 'COALESCE(cb.platform_start_date, cb.creation_date)' : 'cb.creation_date';
        $effRenewalExpr = $isPlatform ? 'COALESCE(cb.platform_renewal_date, cb.renewal_date)' : 'cb.renewal_date';

        if (!empty($filters['client_id'])) {
            $where[] = 'cb.client_id = ?';
            $params[] = (int) $filters['client_id'];
        }

        if (!empty($filters['project_id'])) {
            $where[] = 'cb.project_id = ?';
            $params[] = (int) $filters['project_id'];
        }

        if (!empty($filters['urgency'])) {
            if ($filters['urgency'] === 'overdue') {
                $where[] = "{$effRenewalExpr} < CURDATE()";
            } elseif ($filters['urgency'] === '30_days') {
                $where[] = "{$effRenewalExpr} >= CURDATE() AND {$effRenewalExpr} <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
            } elseif ($filters['urgency'] === '60_days') {
                $where[] = "{$effRenewalExpr} >= CURDATE() AND {$effRenewalExpr} <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)";
            } elseif ($filters['urgency'] === 'active') {
                $where[] = "{$effRenewalExpr} >= CURDATE()";
            }
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'archived') {
                $where[] = '(cb.status = "archived" OR cb.status = "cancelled" OR cb.archived_at IS NOT NULL)';
            } elseif ($filters['status'] === 'active') {
                $where[] = '(cb.status NOT IN ("archived", "cancelled") AND cb.archived_at IS NULL)';
            }
            // If 'all', no status restriction
        } else {
            $where[] = '(cb.status NOT IN ("archived", "cancelled") AND cb.archived_at IS NULL)';
        }

        if (!empty($filters['q'])) {
            $term = '%' . trim((string) $filters['q']) . '%';
            $where[] = '(cb.batch_name LIKE ? OR c.name LIKE ? OR p.name LIKE ? OR cb.region_department LIKE ? OR cb.given_by LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql = "SELECT cb.*,
                       c.name AS client_name,
                       p.name AS project_name,
                       p.code AS project_code,
                       p.color AS project_color,
                       p.status AS project_status,
                       COALESCE(p.is_open_po, 0) AS is_open_po,
                       COALESCE(p.build_hours, 0) AS build_hours,
                       COALESCE(p.run_hours, 0) AS run_hours,
                       u.name AS creator_name,
                       {$effStartExpr} AS effective_start_date,
                       {$effRenewalExpr} AS effective_renewal_date,
                       DATEDIFF({$effRenewalExpr}, CURDATE()) AS days_remaining
                FROM client_batches cb
                JOIN clients c ON c.id = cb.client_id
                JOIN projects p ON p.id = cb.project_id
                JOIN users u ON u.id = cb.created_by";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " ORDER BY {$effRenewalExpr} ASC, c.name ASC, p.name ASC, cb.batch_name ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map([self::class, 'decorateBatch'], $rows);
    }

    public static function hierarchy(array $filters = [], string $scheduleMode = 'contract'): array
    {
        $batches = self::all($filters, $scheduleMode);
        $tree = [];
        $projectIds = [];

        foreach ($batches as $batch) {
            $clientId = (int) $batch['client_id'];
            $projectId = (int) $batch['project_id'];
            $projectIds[$projectId] = true;
            $batchRenewalDate = $batch['effective_renewal_date'] ?? $batch['renewal_date'];

            if (!isset($tree[$clientId])) {
                $tree[$clientId] = [
                    'client_id' => $clientId,
                    'client_name' => $batch['client_name'],
                    'total_licences' => 0,
                    'batch_count' => 0,
                    'nearest_renewal_date' => $batchRenewalDate,
                    'has_overdue' => false,
                    'has_due_soon' => false,
                    'projects' => [],
                ];
            }

            if (!isset($tree[$clientId]['projects'][$projectId])) {
                $isOpenPo = (bool) ($batch['is_open_po'] ?? false);
                $buildHrs = (float) ($batch['build_hours'] ?? 0);
                $runHrs = (float) ($batch['run_hours'] ?? 0);
                $allocatedHrs = $buildHrs + $runHrs;

                $tree[$clientId]['projects'][$projectId] = [
                    'project_id' => $projectId,
                    'project_name' => $batch['project_name'],
                    'project_code' => $batch['project_code'],
                    'project_color' => $batch['project_color'],
                    'project_status' => $batch['project_status'],
                    'is_open_po' => $isOpenPo,
                    'build_hours' => $buildHrs,
                    'run_hours' => $runHrs,
                    'total_allocated_hours' => $allocatedHrs,
                    'total_logged_hours' => 0.0,
                    'billed_hours' => 0.0,
                    'unbilled_hours' => 0.0,
                    'remaining_hours' => $isOpenPo ? null : $allocatedHrs,
                    'consumption_percent' => 0.0,
                    'total_licences' => 0,
                    'batch_count' => 0,
                    'nearest_renewal_date' => $batchRenewalDate,
                    'has_overdue' => false,
                    'has_due_soon' => false,
                    'batches' => [],
                ];
            }

            $tree[$clientId]['total_licences'] += (int) $batch['licence_count'];
            $tree[$clientId]['batch_count']++;

            $tree[$clientId]['projects'][$projectId]['total_licences'] += (int) $batch['licence_count'];
            $tree[$clientId]['projects'][$projectId]['batch_count']++;

            if ($batch['days_remaining'] < 0) {
                $tree[$clientId]['has_overdue'] = true;
                $tree[$clientId]['projects'][$projectId]['has_overdue'] = true;
            } elseif ($batch['days_remaining'] <= 30) {
                $tree[$clientId]['has_due_soon'] = true;
                $tree[$clientId]['projects'][$projectId]['has_due_soon'] = true;
            }

            if ($batchRenewalDate < $tree[$clientId]['nearest_renewal_date']) {
                $tree[$clientId]['nearest_renewal_date'] = $batchRenewalDate;
            }
            if ($batchRenewalDate < $tree[$clientId]['projects'][$projectId]['nearest_renewal_date']) {
                $tree[$clientId]['projects'][$projectId]['nearest_renewal_date'] = $batchRenewalDate;
            }

            $tree[$clientId]['projects'][$projectId]['batches'][] = $batch;
        }

        // Calculate real-time service hours consumption for all projects in hierarchy
        if (!empty($projectIds)) {
            $db = Database::connection();
            $pIds = array_keys($projectIds);
            $inPlaceholders = implode(',', array_fill(0, count($pIds), '?'));

            try {
                $hoursStmt = $db->prepare(
                    "SELECT 
                        COALESCE(tl.project_id, p_direct.id) AS matched_project_id,
                        SUM(wl.hours) AS total_logged,
                        SUM(CASE WHEN wl.billing_status = 'billed' THEN wl.hours ELSE 0 END) AS billed_hours,
                        SUM(CASE WHEN COALESCE(wl.billing_status, 'unbilled') = 'unbilled' AND wl.billing_type = 'Billable' THEN wl.hours ELSE 0 END) AS unbilled_hours
                     FROM work_logs wl
                     LEFT JOIN tasks t ON t.id = wl.task_id
                     LEFT JOIN task_lists tl ON tl.id = t.task_list_id
                     LEFT JOIN projects p_direct ON p_direct.name = wl.project_group
                     WHERE tl.project_id IN ($inPlaceholders) OR p_direct.id IN ($inPlaceholders)
                     GROUP BY matched_project_id"
                );
                $hoursStmt->execute(array_merge($pIds, $pIds));
                $hoursRows = $hoursStmt->fetchAll();

                $hoursMap = [];
                foreach ($hoursRows as $hr) {
                    $pid = (int) $hr['matched_project_id'];
                    $hoursMap[$pid] = [
                        'logged' => (float) $hr['total_logged'],
                        'billed' => (float) $hr['billed_hours'],
                        'unbilled' => (float) $hr['unbilled_hours'],
                    ];
                }

                foreach ($tree as $cId => &$clientNode) {
                    foreach ($clientNode['projects'] as $pId => &$pNode) {
                        if (isset($hoursMap[$pId])) {
                            $logged = $hoursMap[$pId]['logged'];
                            $billed = $hoursMap[$pId]['billed'];
                            $unbilled = $hoursMap[$pId]['unbilled'];
                            $alloc = $pNode['total_allocated_hours'];
                            $isOpen = $pNode['is_open_po'];

                            $pNode['total_logged_hours'] = $logged;
                            $pNode['billed_hours'] = $billed;
                            $pNode['unbilled_hours'] = $unbilled;
                            $pNode['remaining_hours'] = $isOpen ? null : max(0.0, $alloc - $logged);
                            $pNode['consumption_percent'] = ($alloc > 0 && !$isOpen) ? round(($logged / $alloc) * 100, 1) : 0.0;
                        }
                    }
                    unset($pNode);
                }
                unset($clientNode);
            } catch (\Throwable $e) {
                // Keep defaults if table query fails
            }
        }

        // Sort clients alphabetically by client_name
        uasort($tree, static fn ($a, $b) => strcasecmp((string) ($a['client_name'] ?? ''), (string) ($b['client_name'] ?? '')));

        // Sort projects within each client alphabetically by project_name
        foreach ($tree as &$clientNode) {
            uasort($clientNode['projects'], static fn ($a, $b) => strcasecmp((string) ($a['project_name'] ?? ''), (string) ($b['project_name'] ?? '')));
            $clientNode['projects'] = array_values($clientNode['projects']);
        }
        unset($clientNode);

        return array_values($tree);
    }

    public static function stats(array $filters = [], string $scheduleMode = 'contract'): array
    {
        $batches = self::all($filters, $scheduleMode);

        $totalLicences = 0;
        $clientIds = [];
        $projectIds = [];
        $overdueCount = 0;
        $expiring30Days = 0;
        $activeCount = 0;

        foreach ($batches as $b) {
            $totalLicences += (int) ($b['licence_count'] ?? 0);
            $clientIds[(int) $b['client_id']] = true;
            $projectIds[(int) $b['project_id']] = true;

            $days = (int) ($b['days_remaining'] ?? 0);
            if ($days < 0) {
                $overdueCount++;
            } else {
                $activeCount++;
                if ($days <= 30) {
                    $expiring30Days++;
                }
            }
        }

        return [
            'total_licences' => $totalLicences,
            'total_batches' => count($batches),
            'total_clients' => count($clientIds),
            'total_projects' => count($projectIds),
            'overdue_count' => $overdueCount,
            'expiring_30_days' => $expiring30Days,
            'active_count' => $activeCount,
        ];
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT cb.*,
                    c.name AS client_name,
                    p.name AS project_name,
                    p.code AS project_code,
                    u.name AS creator_name,
                    COALESCE(cb.platform_start_date, cb.creation_date) AS effective_start_date,
                    COALESCE(cb.platform_renewal_date, cb.renewal_date) AS effective_renewal_date,
                    DATEDIFF(cb.renewal_date, CURDATE()) AS days_remaining,
                    DATEDIFF(COALESCE(cb.platform_renewal_date, cb.renewal_date), CURDATE()) AS platform_days_remaining
             FROM client_batches cb
             JOIN clients c ON c.id = cb.client_id
             JOIN projects p ON p.id = cb.project_id
             JOIN users u ON u.id = cb.created_by
             WHERE cb.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? self::decorateBatch($row) : null;
    }

    public static function create(array $data): int
    {
        self::ensureSchema();
        $db = Database::connection();
        $platformStart = !empty($data['platform_start_date']) ? $data['platform_start_date'] : null;
        $platformRenewal = !empty($data['platform_renewal_date']) ? $data['platform_renewal_date'] : null;

        $stmt = $db->prepare(
            'INSERT INTO client_batches
                (client_id, project_id, batch_name, region_department, licence_count, creation_date, renewal_date, platform_start_date, platform_renewal_date, given_by, notes, status, created_by)
             VALUES
                (:client_id, :project_id, :batch_name, :region_department, :licence_count, :creation_date, :renewal_date, :platform_start_date, :platform_renewal_date, :given_by, :notes, :status, :created_by)'
        );
        $stmt->execute([
            'client_id' => (int) $data['client_id'],
            'project_id' => (int) $data['project_id'],
            'batch_name' => trim((string) $data['batch_name']),
            'region_department' => trim((string) ($data['region_department'] ?? '')) ?: null,
            'licence_count' => max(0, (int) ($data['licence_count'] ?? 0)),
            'creation_date' => $data['creation_date'],
            'renewal_date' => $data['renewal_date'],
            'platform_start_date' => $platformStart,
            'platform_renewal_date' => $platformRenewal,
            'given_by' => trim((string) ($data['given_by'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'status' => in_array($data['status'] ?? 'active', ['active', 'renewed', 'cancelled'], true) ? $data['status'] : 'active',
            'created_by' => (int) $data['created_by'],
        ]);

        $batchId = (int) $db->lastInsertId();

        // Write initial period snapshot to client_batch_history
        try {
            $historyStmt = $db->prepare(
                'INSERT INTO client_batch_history
                    (batch_id, client_id, project_id, action_type, period_start, period_end, platform_period_start, platform_period_end, licence_count, given_by, notes, created_by)
                 VALUES
                    (:batch_id, :client_id, :project_id, "created", :period_start, :period_end, :platform_period_start, :platform_period_end, :licence_count, :given_by, :notes, :created_by)'
            );
            $historyStmt->execute([
                'batch_id' => $batchId,
                'client_id' => (int) $data['client_id'],
                'project_id' => (int) $data['project_id'],
                'period_start' => $data['creation_date'],
                'period_end' => $data['renewal_date'],
                'platform_period_start' => $platformStart,
                'platform_period_end' => $platformRenewal,
                'licence_count' => max(0, (int) ($data['licence_count'] ?? 0)),
                'given_by' => trim((string) ($data['given_by'] ?? '')) ?: null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'created_by' => (int) $data['created_by'],
            ]);
        } catch (\Throwable $e) {
            // Table might not exist yet if upgrade SQL pending
        }

        return $batchId;
    }

    public static function update(int $id, array $data): void
    {
        self::ensureSchema();
        $platformStart = !empty($data['platform_start_date']) ? $data['platform_start_date'] : null;
        $platformRenewal = !empty($data['platform_renewal_date']) ? $data['platform_renewal_date'] : null;

        $stmt = Database::connection()->prepare(
            'UPDATE client_batches
             SET client_id = :client_id,
                 project_id = :project_id,
                 batch_name = :batch_name,
                 region_department = :region_department,
                 licence_count = :licence_count,
                 creation_date = :creation_date,
                 renewal_date = :renewal_date,
                 platform_start_date = :platform_start_date,
                 platform_renewal_date = :platform_renewal_date,
                 given_by = :given_by,
                 notes = :notes,
                 status = :status
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'client_id' => (int) $data['client_id'],
            'project_id' => (int) $data['project_id'],
            'batch_name' => trim((string) $data['batch_name']),
            'region_department' => trim((string) ($data['region_department'] ?? '')) ?: null,
            'licence_count' => max(0, (int) ($data['licence_count'] ?? 0)),
            'creation_date' => $data['creation_date'],
            'renewal_date' => $data['renewal_date'],
            'platform_start_date' => $platformStart,
            'platform_renewal_date' => $platformRenewal,
            'given_by' => trim((string) ($data['given_by'] ?? '')) ?: null,
            'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
            'status' => in_array($data['status'] ?? 'active', ['active', 'renewed', 'cancelled'], true) ? $data['status'] : 'active',
        ]);

        // Record edit in history
        try {
            $historyStmt = Database::connection()->prepare(
                'INSERT INTO client_batch_history
                    (batch_id, client_id, project_id, action_type, period_start, period_end, platform_period_start, platform_period_end, licence_count, given_by, notes, created_by)
                 VALUES
                    (:batch_id, :client_id, :project_id, "updated", :period_start, :period_end, :platform_period_start, :platform_period_end, :licence_count, :given_by, :notes, :created_by)'
            );
            $historyStmt->execute([
                'batch_id' => $id,
                'client_id' => (int) $data['client_id'],
                'project_id' => (int) $data['project_id'],
                'period_start' => $data['creation_date'],
                'period_end' => $data['renewal_date'],
                'platform_period_start' => $platformStart,
                'platform_period_end' => $platformRenewal,
                'licence_count' => max(0, (int) ($data['licence_count'] ?? 0)),
                'given_by' => trim((string) ($data['given_by'] ?? '')) ?: null,
                'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                'created_by' => (int) ($_SESSION['user_id'] ?? 1),
            ]);
        } catch (\Throwable $e) {
            // Ignore if error recording history
        }
    }

    public static function renew(
        int $id,
        string $newRenewalDate,
        ?int $newLicenceCount = null,
        ?string $periodStart = null,
        ?string $renewalNotes = null,
        ?string $givenBy = null,
        int $userId = 1,
        ?float $newContractHours = null,
        bool $markPreviousBilled = false,
        ?string $invoiceReference = null,
        ?string $platformPeriodStart = null,
        ?string $platformRenewalDate = null
    ): void {
        $db = Database::connection();
        $batch = self::find($id);
        if (!$batch) {
            return;
        }

        $licenceCount = $newLicenceCount !== null && $newLicenceCount > 0
            ? $newLicenceCount
            : (int) $batch['licence_count'];

        $periodStart = $periodStart ?: ($batch['renewal_date'] ?: date('Y-m-d'));
        $givenBy = $givenBy !== null ? trim($givenBy) : ($batch['given_by'] ?? null);

        // Update the active batch record - resets courtesy extension flag on official paid renewal
        $sql = 'UPDATE client_batches
                SET renewal_date = :renewal_date,
                    licence_count = :licence_count,
                    is_extended = 0,
                    extension_reason = NULL,
                    status = "active"';
        $params = [
            'id' => $id,
            'renewal_date' => $newRenewalDate,
            'licence_count' => $licenceCount,
        ];

        if ($platformRenewalDate !== null && $platformRenewalDate !== '') {
            $sql .= ', platform_renewal_date = :platform_renewal_date';
            $params['platform_renewal_date'] = $platformRenewalDate;
        }
        if ($platformPeriodStart !== null && $platformPeriodStart !== '') {
            $sql .= ', platform_start_date = :platform_start_date';
            $params['platform_start_date'] = $platformPeriodStart;
        }

        if ($givenBy) {
            $sql .= ', given_by = :given_by';
            $params['given_by'] = $givenBy;
        }
        if ($renewalNotes !== null && $renewalNotes !== '') {
            $sql .= ', notes = :notes';
            $params['notes'] = $renewalNotes;
        }

        $sql .= ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // Optionally update project's new contract hours for this cycle
        $projectId = (int) $batch['project_id'];
        if ($newContractHours !== null && $newContractHours >= 0 && $projectId > 0) {
            try {
                $db->prepare('UPDATE projects SET run_hours = ? WHERE id = ?')->execute([$newContractHours, $projectId]);
            } catch (\Throwable $e) {
                // Ignore if error updating project
            }
        }

        // Optionally mark previous cycle hours as Billed
        if ($markPreviousBilled && $projectId > 0) {
            try {
                WorkLog::markProjectBilled($projectId, $invoiceReference, $userId);
            } catch (\Throwable $e) {
                // Ignore if error marking billed
            }
        }

        // Record the new period cycle in client_batch_history
        try {
            $historyStmt = $db->prepare(
                'INSERT INTO client_batch_history
                    (batch_id, client_id, project_id, action_type, period_start, period_end, platform_period_start, platform_period_end, licence_count, given_by, notes, created_by)
                 VALUES
                    (:batch_id, :client_id, :project_id, "renewed", :period_start, :period_end, :platform_period_start, :platform_period_end, :licence_count, :given_by, :notes, :created_by)'
            );
            $historyStmt->execute([
                'batch_id' => $id,
                'client_id' => (int) $batch['client_id'],
                'project_id' => (int) $batch['project_id'],
                'period_start' => $periodStart,
                'period_end' => $newRenewalDate,
                'platform_period_start' => $platformPeriodStart,
                'platform_period_end' => $platformRenewalDate,
                'licence_count' => $licenceCount,
                'given_by' => $givenBy,
                'notes' => ($renewalNotes !== null && $renewalNotes !== '') ? $renewalNotes : ($batch['notes'] ?? null),
                'created_by' => $userId,
            ]);
        } catch (\Throwable $e) {
            // Table might not exist yet if upgrade SQL pending
        }
    }

    public static function extend(
        int $id,
        string $newExtendedDate,
        ?int $newLicenceCount = null,
        ?string $periodStart = null,
        string $extensionReason = 'Courtesy / Grace Period Extension',
        ?string $givenBy = null,
        int $userId = 1,
        ?string $extensionNotes = null,
        ?string $platformPeriodStart = null,
        ?string $platformExtendedDate = null
    ): void {
        $db = Database::connection();
        $batch = self::find($id);
        if (!$batch) {
            return;
        }

        $licenceCount = $newLicenceCount !== null && $newLicenceCount > 0
            ? $newLicenceCount
            : (int) $batch['licence_count'];

        $periodStart = $periodStart ?: ($batch['renewal_date'] ?: date('Y-m-d'));
        $effectiveNote = ($extensionNotes !== null && $extensionNotes !== '')
            ? $extensionNotes
            : ($extensionReason ? 'Extension: ' . $extensionReason : ($batch['notes'] ?? null));

        // Update active batch record marking as extended
        $sql = 'UPDATE client_batches
                SET renewal_date = :renewal_date,
                    licence_count = :licence_count,
                    is_extended = 1,
                    extension_reason = :extension_reason,
                    notes = :notes,
                    status = "active"';
        $params = [
            'id' => $id,
            'renewal_date' => $newExtendedDate,
            'licence_count' => $licenceCount,
            'extension_reason' => $extensionReason,
            'notes' => $effectiveNote,
        ];

        if ($platformExtendedDate !== null && $platformExtendedDate !== '') {
            $sql .= ', platform_renewal_date = :platform_renewal_date';
            $params['platform_renewal_date'] = $platformExtendedDate;
        }

        if ($givenBy) {
            $sql .= ', given_by = :given_by';
            $params['given_by'] = $givenBy;
        }

        $sql .= ' WHERE id = :id';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        // Record the extension in client_batch_history with action_type = 'extended'
        try {
            $historyStmt = $db->prepare(
                'INSERT INTO client_batch_history
                    (batch_id, client_id, project_id, action_type, period_start, period_end, platform_period_start, platform_period_end, licence_count, given_by, notes, created_by)
                 VALUES
                    (:batch_id, :client_id, :project_id, "extended", :period_start, :period_end, :platform_period_start, :platform_period_end, :licence_count, :given_by, :notes, :created_by)'
            );
            $historyStmt->execute([
                'batch_id' => $id,
                'client_id' => (int) $batch['client_id'],
                'project_id' => (int) $batch['project_id'],
                'period_start' => $periodStart,
                'period_end' => $newExtendedDate,
                'platform_period_start' => $platformPeriodStart,
                'platform_period_end' => $platformExtendedDate,
                'licence_count' => $licenceCount,
                'given_by' => $givenBy,
                'notes' => $effectiveNote,
                'created_by' => $userId,
            ]);
        } catch (\Throwable $e) {
            // Table might not exist yet if upgrade SQL pending
        }
    }

    public static function historyForBatch(int $batchId): array
    {
        $db = Database::connection();
        try {
            $stmt = $db->prepare(
                "SELECT cbh.*,
                        c.name AS client_name,
                        p.name AS project_name,
                        cb.batch_name,
                        u.name AS creator_name
                 FROM client_batch_history cbh
                 JOIN client_batches cb ON cb.id = cbh.batch_id
                 LEFT JOIN clients c ON c.id = cbh.client_id
                 LEFT JOIN projects p ON p.id = cbh.project_id
                 LEFT JOIN users u ON u.id = cbh.created_by
                 WHERE cbh.batch_id = ?
                 ORDER BY cbh.created_at DESC, cbh.id DESC"
            );
            $stmt->execute([$batchId]);
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        } catch (\Throwable $e) {
            // Table may not exist yet or query failed
        }

        // Fallback: If no history records yet, return active batch baseline record
        $batch = self::find($batchId);
        if (!$batch) {
            return [];
        }

        return [
            [
                'id' => $batch['id'],
                'batch_id' => $batch['id'],
                'client_id' => $batch['client_id'],
                'project_id' => $batch['project_id'],
                'client_name' => $batch['client_name'],
                'project_name' => $batch['project_name'],
                'batch_name' => $batch['batch_name'],
                'action_type' => 'created',
                'period_start' => $batch['creation_date'],
                'period_end' => $batch['renewal_date'],
                'platform_period_start' => $batch['platform_start_date'] ?? null,
                'platform_period_end' => $batch['platform_renewal_date'] ?? null,
                'licence_count' => $batch['licence_count'],
                'given_by' => $batch['given_by'],
                'notes' => $batch['notes'],
                'creator_name' => $batch['creator_name'] ?? 'Admin',
                'created_at' => $batch['created_at'] ?? date('Y-m-d H:i:s'),
            ]
        ];
    }

    public static function allHistory(array $filters = [], string $scheduleMode = 'contract'): array
    {
        $db = Database::connection();
        $isPlatform = ($scheduleMode === 'platform');
        $startCol = $isPlatform ? 'COALESCE(cbh.platform_period_start, cbh.period_start)' : 'cbh.period_start';
        $endCol = $isPlatform ? 'COALESCE(cbh.platform_period_end, cbh.period_end)' : 'cbh.period_end';

        try {
            $where = [];
            $params = [];

            if (!empty($filters['client_id'])) {
                $where[] = 'cbh.client_id = ?';
                $params[] = (int) $filters['client_id'];
            }

            if (!empty($filters['project_id'])) {
                $where[] = 'cbh.project_id = ?';
                $params[] = (int) $filters['project_id'];
            }

            if (!empty($filters['from_date'])) {
                $where[] = "{$endCol} >= ?";
                $params[] = $filters['from_date'];
            }

            if (!empty($filters['to_date'])) {
                $where[] = "{$startCol} <= ?";
                $params[] = $filters['to_date'];
            }

            if (!empty($filters['q'])) {
                $term = '%' . trim((string) $filters['q']) . '%';
                $where[] = '(cb.batch_name LIKE ? OR c.name LIKE ? OR p.name LIKE ? OR cbh.given_by LIKE ? OR cbh.notes LIKE ?)';
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            }

            $sql = "SELECT cbh.*,
                           c.name AS client_name,
                           p.name AS project_name,
                           p.code AS project_code,
                           cb.batch_name,
                           cb.region_department,
                           u.name AS creator_name,
                           {$startCol} AS effective_period_start,
                           {$endCol} AS effective_period_end
                    FROM client_batch_history cbh
                    JOIN client_batches cb ON cb.id = cbh.batch_id
                    LEFT JOIN clients c ON c.id = cbh.client_id
                    LEFT JOIN projects p ON p.id = cbh.project_id
                    LEFT JOIN users u ON u.id = cbh.created_by";

            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }

            $sql .= ' ORDER BY cbh.created_at DESC, cbh.id DESC';

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        } catch (\Throwable $e) {
            // Ignore and fallback
        }

        // Fallback: If no history records yet, use active batches
        $activeBatches = self::all($filters, $scheduleMode);
        return array_map(static fn ($b) => [
            'id' => $b['id'],
            'batch_id' => $b['id'],
            'client_id' => $b['client_id'],
            'project_id' => $b['project_id'],
            'client_name' => $b['client_name'],
            'project_name' => $b['project_name'],
            'project_code' => $b['project_code'] ?? '',
            'batch_name' => $b['batch_name'],
            'region_department' => $b['region_department'] ?? '',
            'action_type' => 'created',
            'period_start' => $b['effective_start_date'] ?? $b['creation_date'],
            'period_end' => $b['effective_renewal_date'] ?? $b['renewal_date'],
            'effective_period_start' => $b['effective_start_date'] ?? $b['creation_date'],
            'effective_period_end' => $b['effective_renewal_date'] ?? $b['renewal_date'],
            'licence_count' => $b['licence_count'],
            'given_by' => $b['given_by'],
            'notes' => $b['notes'],
            'creator_name' => $b['creator_name'] ?? 'Admin',
            'created_at' => $b['created_at'] ?? date('Y-m-d H:i:s'),
        ], $activeBatches);
    }

    public static function yearComparison(?int $clientId = null, ?int $projectId = null, string $scheduleMode = 'contract'): array
    {
        $history = self::allHistory([
            'client_id' => $clientId,
            'project_id' => $projectId,
        ], $scheduleMode);

        $yearMap = [];
        $seenInYear = [];

        foreach ($history as $item) {
            $startDate = $item['effective_period_start'] ?? $item['period_start'] ?? 'now';
            $yr = (int) date('Y', strtotime($startDate));
            $batchId = $item['batch_id'] ?? $item['id'] ?? null;
            $count = (int) ($item['licence_count'] ?? 0);

            if (!isset($yearMap[$yr])) {
                $yearMap[$yr] = [
                    'year' => $yr,
                    'licences' => 0,
                    'batches' => 0,
                    'status' => $yr < (int) date('Y') ? 'Completed' : ($yr === (int) date('Y') ? 'Active (Current Year)' : 'Upcoming'),
                ];
                $seenInYear[$yr] = [];
            }

            // Deduplicate batch in the same year so it doesn't count twice
            if ($batchId && isset($seenInYear[$yr][$batchId])) {
                continue;
            }
            if ($batchId) {
                $seenInYear[$yr][$batchId] = true;
            }

            $yearMap[$yr]['licences'] += $count;
            $yearMap[$yr]['batches']++;
        }

        krsort($yearMap); // Sort years descending (e.g. 2026, 2025, 2024)

        return $yearMap;
    }

    public static function archive(int $id, int $userId): void
    {
        self::ensureSchema();
        $db = Database::connection();
        $batch = self::find($id);
        if (!$batch) {
            return;
        }

        try {
            $stmt = $db->prepare("UPDATE client_batches SET status = 'archived', archived_at = NOW() WHERE id = ?");
            $stmt->execute([$id]);
        } catch (\Throwable $e) {
            $stmt = $db->prepare("UPDATE client_batches SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$id]);
        }

        try {
            $historyStmt = $db->prepare(
                'INSERT INTO client_batch_history
                    (batch_id, client_id, project_id, action_type, period_start, period_end, licence_count, given_by, notes, created_by)
                 VALUES
                    (:batch_id, :client_id, :project_id, "archived", :period_start, :period_end, :licence_count, :given_by, "Batch Archived", :created_by)'
            );
            $historyStmt->execute([
                'batch_id' => $id,
                'client_id' => (int) $batch['client_id'],
                'project_id' => (int) $batch['project_id'],
                'period_start' => $batch['creation_date'],
                'period_end' => $batch['renewal_date'],
                'licence_count' => (int) $batch['licence_count'],
                'given_by' => $batch['given_by'],
                'created_by' => $userId,
            ]);
        } catch (\Throwable $e) {
            try {
                $historyStmt = $db->prepare(
                    'INSERT INTO client_batch_history
                        (batch_id, client_id, project_id, action_type, period_start, period_end, licence_count, given_by, notes, created_by)
                     VALUES
                        (:batch_id, :client_id, :project_id, "updated", :period_start, :period_end, :licence_count, :given_by, "Batch Archived", :created_by)'
                );
                $historyStmt->execute([
                    'batch_id' => $id,
                    'client_id' => (int) $batch['client_id'],
                    'project_id' => (int) $batch['project_id'],
                    'period_start' => $batch['creation_date'],
                    'period_end' => $batch['renewal_date'],
                    'licence_count' => (int) $batch['licence_count'],
                    'given_by' => $batch['given_by'],
                    'created_by' => $userId,
                ]);
            } catch (\Throwable $e2) {
                // Ignore if history table pending
            }
        }
    }

    public static function unarchive(int $id, int $userId): void
    {
        self::ensureSchema();
        $db = Database::connection();
        $batch = self::find($id);
        if (!$batch) {
            return;
        }

        try {
            $stmt = $db->prepare("UPDATE client_batches SET status = 'active', archived_at = NULL WHERE id = ?");
            $stmt->execute([$id]);
        } catch (\Throwable $e) {
            $stmt = $db->prepare("UPDATE client_batches SET status = 'active' WHERE id = ?");
            $stmt->execute([$id]);
        }

        try {
            $historyStmt = $db->prepare(
                'INSERT INTO client_batch_history
                    (batch_id, client_id, project_id, action_type, period_start, period_end, licence_count, given_by, notes, created_by)
                 VALUES
                    (:batch_id, :client_id, :project_id, "unarchived", :period_start, :period_end, :licence_count, :given_by, "Batch Unarchived / Restored", :created_by)'
            );
            $historyStmt->execute([
                'batch_id' => $id,
                'client_id' => (int) $batch['client_id'],
                'project_id' => (int) $batch['project_id'],
                'period_start' => $batch['creation_date'],
                'period_end' => $batch['renewal_date'],
                'licence_count' => (int) $batch['licence_count'],
                'given_by' => $batch['given_by'],
                'created_by' => $userId,
            ]);
        } catch (\Throwable $e) {
            try {
                $historyStmt = $db->prepare(
                    'INSERT INTO client_batch_history
                        (batch_id, client_id, project_id, action_type, period_start, period_end, licence_count, given_by, notes, created_by)
                     VALUES
                        (:batch_id, :client_id, :project_id, "updated", :period_start, :period_end, :licence_count, :given_by, "Batch Unarchived / Restored", :created_by)'
                );
                $historyStmt->execute([
                    'batch_id' => $id,
                    'client_id' => (int) $batch['client_id'],
                    'project_id' => (int) $batch['project_id'],
                    'period_start' => $batch['creation_date'],
                    'period_end' => $batch['renewal_date'],
                    'licence_count' => (int) $batch['licence_count'],
                    'given_by' => $batch['given_by'],
                    'created_by' => $userId,
                ]);
            } catch (\Throwable $e2) {
                // Ignore if history table pending
            }
        }
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM client_batches WHERE id = ?');
        $stmt->execute([$id]);
    }

    public static function projectsByClient(int $clientId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, code, color, status FROM projects WHERE client_id = ? AND archived_at IS NULL ORDER BY name'
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    public static function allActiveClientsWithProjects(): array
    {
        $clients = Database::connection()->query("SELECT id, name FROM clients WHERE status = 'active' ORDER BY name")->fetchAll();
        $projects = Database::connection()->query("SELECT id, name, client_id, code FROM projects WHERE archived_at IS NULL ORDER BY name")->fetchAll();

        $byClient = [];
        foreach ($projects as $project) {
            if ($project['client_id']) {
                $byClient[(int) $project['client_id']][] = $project;
            }
        }

        foreach ($clients as &$client) {
            $client['projects'] = $byClient[(int) $client['id']] ?? [];
        }
        unset($client);

        return $clients;
    }

    private static function decorateBatch(array $row): array
    {
        if ($row['status'] === 'archived' || $row['status'] === 'cancelled' || !empty($row['archived_at'])) {
            $row['urgency_status'] = 'archived';
            $row['urgency_class'] = 'renewal-status-tag archived';
            $row['urgency_label'] = 'Archived';
            return $row;
        }

        $days = (int) ($row['days_remaining'] ?? 0);

        if ($days < 0) {
            $urgencyStatus = 'overdue';
            $absDays = abs($days);
            $urgencyLabel = 'Overdue by ' . $absDays . ' day' . ($absDays === 1 ? '' : 's');
        } elseif ($days === 0) {
            $urgencyStatus = 'due_today';
            $urgencyLabel = 'Expires Today';
        } elseif ($days <= 30) {
            $urgencyStatus = 'due_soon';
            $urgencyLabel = 'Expires in ' . $days . ' day' . ($days === 1 ? '' : 's');
        } elseif ($days <= 60) {
            $urgencyStatus = 'due_upcoming';
            $urgencyLabel = 'Due in ' . $days . ' days';
        } else {
            $urgencyStatus = 'active';
            $urgencyLabel = 'Active (' . $days . ' days)';
        }

        $row['urgency_status'] = $urgencyStatus;
        $row['urgency_class'] = 'renewal-status-tag ' . $urgencyStatus;
        $row['urgency_label'] = $urgencyLabel;

        return $row;
    }
}
