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
        $stmt = Database::connection()->prepare(
            'INSERT INTO work_logs
                (task_id, project_group, phase, module_name, task_category, notes, daily_log, time_period, hours, log_date, billing_type, user_id)
             VALUES
                (:task_id, :project_group, :phase, :module_name, :task_category, :notes, :daily_log, :time_period, :hours, :log_date, :billing_type, :user_id)'
        );
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
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
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM work_logs WHERE id = ?')->execute([$id]);
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

    public static function report(array $filters = []): array
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

        $sql = "SELECT wl.*, u.name AS user_name,
                       COALESCE(t.title, wl.task_category) AS report_task_issue
                FROM work_logs wl
                JOIN users u ON u.id = wl.user_id
                LEFT JOIN tasks t ON t.id = wl.task_id";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY wl.log_date DESC, u.name, wl.created_at DESC';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function summaryByProject(array $filters = []): array
    {
        return self::summary(
            'COALESCE(p.name, wl.project_group)',
            $filters,
            'LEFT JOIN tasks t ON t.id = wl.task_id LEFT JOIN projects p ON p.id = t.project_id'
        );
    }

    public static function summaryByCustomer(array $filters = []): array
    {
        return self::summary('wl.project_group', $filters);
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
            $where[] = 'wl.project_group LIKE ?';
            $params[] = '%' . $projectGroup . '%';
        }

        $stmt = Database::connection()->prepare(
            "SELECT wl.project_group AS group_name, wl.log_date, COALESCE(SUM(wl.hours), 0) AS hours
             FROM work_logs wl
             WHERE " . implode(' AND ', $where) . "
             GROUP BY wl.project_group, wl.log_date
             ORDER BY wl.project_group, wl.log_date"
        );
        $stmt->execute($params);
        return self::matrix($stmt->fetchAll(), $fromDate, $toDate);
    }

    /**
     * Per-row drill-down for the Time Logs dashboard: for 'user' rows, nests
     * Client -> Task List -> Task; for 'client' rows the client level is
     * redundant (it IS the row) so nesting starts at Task List -> Task.
     */
    public static function breakdown(string $view, string $fromDate, string $toDate): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT wl.project_group, wl.module_name, COALESCE(t.title, wl.task_category) AS task_label, wl.hours, u.name AS user_name
             FROM work_logs wl
             JOIN users u ON u.id = wl.user_id
             LEFT JOIN tasks t ON t.id = wl.task_id
             WHERE wl.log_date BETWEEN ? AND ?"
        );
        $stmt->execute([$fromDate, $toDate]);

        $grouped = [];
        foreach ($stmt->fetchAll() as $row) {
            $moduleKey = $row['module_name'] ?: 'General';
            $taskKey = $row['task_label'] ?: 'General';
            $hours = (float) $row['hours'];

            if ($view === 'user') {
                $rowKey = $row['user_name'] ?: 'Unassigned';
                $clientKey = $row['project_group'] ?: 'Unassigned';
                $grouped[$rowKey][$clientKey][$moduleKey][$taskKey] = ($grouped[$rowKey][$clientKey][$moduleKey][$taskKey] ?? 0) + $hours;
            } else {
                $rowKey = $row['project_group'] ?: 'Unassigned';
                $grouped[$rowKey][$moduleKey][$taskKey] = ($grouped[$rowKey][$moduleKey][$taskKey] ?? 0) + $hours;
            }
        }

        self::sortBreakdown($grouped);
        return $grouped;
    }

    private static function sortBreakdown(array &$branch): void
    {
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
}
