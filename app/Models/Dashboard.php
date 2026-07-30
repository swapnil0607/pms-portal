<?php

namespace App\Models;

use App\Core\Database;

class Dashboard
{
    public static function stats(int $userId): array
    {
        $db = Database::connection();

        return [
            'clients' => (int) $db->query("SELECT COUNT(DISTINCT COALESCE(project_group, name)) FROM projects")->fetchColumn(),
            'projects' => (int) $db->query('SELECT COUNT(*) FROM projects')->fetchColumn(),
            'active_projects' => (int) $db->query("SELECT COUNT(*) FROM projects WHERE status = 'active'")->fetchColumn(),
            'open_tasks' => (int) $db->query("SELECT COUNT(*) FROM tasks WHERE status <> 'completed'")->fetchColumn(),
            'overdue_tasks' => (int) $db->query("SELECT COUNT(*) FROM tasks WHERE due_date < CURDATE() AND status <> 'completed'")->fetchColumn(),
            'my_open_tasks' => self::single("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status <> 'completed'", [$userId]),
            'my_overdue_tasks' => self::single("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND due_date < CURDATE() AND status <> 'completed'", [$userId]),
            'my_today_tasks' => self::single("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND due_date = CURDATE() AND status <> 'completed'", [$userId]),
            'my_log_hours_today' => (float) self::single("SELECT COALESCE(SUM(hours), 0) FROM work_logs WHERE user_id = ? AND log_date = CURDATE()", [$userId]),
            'my_log_count_today' => self::single("SELECT COUNT(*) FROM work_logs WHERE user_id = ? AND log_date = CURDATE()", [$userId]),
        ];
    }

    public static function recentTasks(): array
    {
        $sql = "SELECT t.*, p.name AS project_name, u.name AS assignee
                FROM tasks t
                JOIN projects p ON p.id = t.project_id
                LEFT JOIN users u ON u.id = t.assigned_to
                ORDER BY t.updated_at DESC
                LIMIT 8";

        return Database::connection()->query($sql)->fetchAll();
    }

    public static function myTasks(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.*, p.name AS project_name
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             WHERE t.assigned_to = ? AND t.status <> 'completed'
             ORDER BY t.due_date IS NULL, t.due_date, FIELD(t.priority, 'critical','high','medium','low')
             LIMIT 8"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function myRecentLogs(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT *
             FROM work_logs
             WHERE user_id = ?
             ORDER BY log_date DESC, created_at DESC
             LIMIT 6"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function myLogSummary(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN log_date = CURDATE() THEN hours ELSE 0 END), 0) AS today_hours,
                COALESCE(SUM(CASE WHEN log_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN hours ELSE 0 END), 0) AS month_hours,
                COUNT(CASE WHEN log_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN 1 END) AS month_entries
             FROM work_logs
             WHERE user_id = ?"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch() ?: [];

        return [
            'today_hours' => (float) ($row['today_hours'] ?? 0),
            'month_hours' => (float) ($row['month_hours'] ?? 0),
            'month_entries' => (int) ($row['month_entries'] ?? 0),
        ];
    }

    private static function single(string $sql, array $params): int|string
    {
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() ?: 0;
    }
}
