<?php

namespace App\Models;

use App\Core\Database;

class Task
{
    public const STATUSES = ['open', 'in_progress', 'review', 'blocked', 'completed'];

    public const STATUS_LABELS = [
        'open' => 'To Do',
        'in_progress' => 'Doing',
        'review' => 'Submitted for Review',
        'blocked' => 'Need Help',
        'completed' => 'Completed',
    ];

    public static function statusLabels(): array
    {
        return self::STATUS_LABELS;
    }

    public static function forProject(int $projectId, string $statusFilter = 'all_open'): array
    {
        $where = ['t.project_id = ?'];
        $params = [$projectId];

        if ($statusFilter === 'all_open') {
            $where[] = "t.status <> 'completed'";
        } elseif (in_array($statusFilter, self::STATUSES, true)) {
            $where[] = 't.status = ?';
            $params[] = $statusFilter;
        }

        $stmt = Database::connection()->prepare(
            "SELECT t.*, u.name AS assignee, pp.name AS phase_name, tl.name AS task_list_name
             FROM tasks t
             LEFT JOIN users u ON u.id = t.assigned_to
             LEFT JOIN project_phases pp ON pp.id = t.phase_id
             LEFT JOIN task_lists tl ON tl.id = t.task_list_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY pp.sort_order IS NULL, pp.sort_order, pp.id, tl.sort_order IS NULL, tl.sort_order, tl.id, t.sort_order, t.due_date IS NULL, t.due_date, t.id"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.*, p.name AS project_name, p.project_group, u.name AS assignee, creator.name AS creator_name,
                    pp.name AS phase_name, tl.name AS task_list_name
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN users u ON u.id = t.assigned_to
             JOIN users creator ON creator.id = t.created_by
             LEFT JOIN project_phases pp ON pp.id = t.phase_id
             LEFT JOIN task_lists tl ON tl.id = t.task_list_id
             WHERE t.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function kanban(?int $projectId = null, ?int $assigneeId = null): array
    {
        $where = [];
        $params = [];

        if ($projectId) {
            $where[] = 't.project_id = ?';
            $params[] = $projectId;
        }

        if ($assigneeId) {
            $where[] = 't.assigned_to = ?';
            $params[] = $assigneeId;
        }

        $sql = "SELECT t.*, p.name AS project_name, u.name AS assignee
                FROM tasks t
                JOIN projects p ON p.id = t.project_id
                LEFT JOIN users u ON u.id = t.assigned_to";

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= " ORDER BY FIELD(t.priority, 'critical','high','medium','low'), t.due_date IS NULL, t.due_date, t.updated_at DESC";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);

        $grouped = array_fill_keys(self::STATUSES, []);
        foreach ($stmt->fetchAll() as $task) {
            $grouped[$task['status']][] = $task;
        }

        return $grouped;
    }

    public static function myWork(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.*, p.name AS project_name
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             WHERE t.assigned_to = ?
               AND t.status <> 'completed'
             ORDER BY
               CASE
                 WHEN t.due_date < CURDATE() THEN 0
                 WHEN t.due_date = CURDATE() THEN 1
                 WHEN t.due_date IS NULL THEN 3
                 ELSE 2
               END,
               t.due_date IS NULL,
               t.due_date,
               FIELD(t.priority, 'critical','high','medium','low')"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function myWorkStats(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT
                SUM(CASE WHEN status <> 'completed' THEN 1 ELSE 0 END) AS open_total,
                SUM(CASE WHEN due_date = CURDATE() AND status <> 'completed' THEN 1 ELSE 0 END) AS due_today,
                SUM(CASE WHEN due_date < CURDATE() AND status <> 'completed' THEN 1 ELSE 0 END) AS overdue,
                SUM(CASE WHEN status = 'review' THEN 1 ELSE 0 END) AS submitted
             FROM tasks
             WHERE assigned_to = ?"
        );
        $stmt->execute([$userId]);
        $stats = $stmt->fetch() ?: [];

        return [
            'open_total' => (int) ($stats['open_total'] ?? 0),
            'due_today' => (int) ($stats['due_today'] ?? 0),
            'overdue' => (int) ($stats['overdue'] ?? 0),
            'submitted' => (int) ($stats['submitted'] ?? 0),
        ];
    }

    public static function create(array $data): int
    {
        $data['progress'] = ($data['status'] ?? '') === 'completed' ? 100 : 0;

        $stmt = Database::connection()->prepare(
            'INSERT INTO tasks (project_id, phase_id, task_list_id, task_code, title, description, assigned_to, status, priority, start_date, due_date, estimated_hours, progress, created_by)
             VALUES (:project_id, :phase_id, :task_list_id, :task_code, :title, :description, :assigned_to, :status, :priority, :start_date, :due_date, :estimated_hours, :progress, :created_by)'
        );
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public static function updateStatus(int $id, string $status, int $progress): void
    {
        if ($status === 'completed') {
            $progress = 100;
        }

        $stmt = Database::connection()->prepare('UPDATE tasks SET status = ?, progress = ? WHERE id = ?');
        $stmt->execute([$status, $progress, $id]);
    }

    public static function moveMany(int $projectId, array $taskIds, ?int $phaseId, ?int $taskListId): void
    {
        $taskIds = array_values(array_filter(array_map('intval', $taskIds)));
        if (!$taskIds) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE tasks SET phase_id = ?, task_list_id = ? WHERE project_id = ? AND id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$phaseId, $taskListId, $projectId], $taskIds));
    }

    public static function quickUpdate(int $projectId, int $taskId, string $field, ?string $value): void
    {
        $allowed = [
            'title' => 'title',
            'start_date' => 'start_date',
            'due_date' => 'due_date',
            'estimated_hours' => 'estimated_hours',
            'priority' => 'priority',
            'status' => 'status',
        ];

        if (!isset($allowed[$field])) {
            return;
        }

        if (in_array($field, ['start_date', 'due_date'], true) && $value === '') {
            $value = null;
        }

        if ($field === 'estimated_hours') {
            $value = $value === '' ? null : (string) max(0, (float) $value);
        }

        if ($field === 'priority' && !in_array($value, ['low', 'medium', 'high', 'critical'], true)) {
            return;
        }

        if ($field === 'status' && !in_array($value, self::STATUSES, true)) {
            return;
        }

        $sql = 'UPDATE tasks SET ' . $allowed[$field] . ' = ?';
        $params = [$value];

        if ($field === 'status' && $value === 'completed') {
            $sql .= ', progress = 100';
        }

        $sql .= ' WHERE id = ? AND project_id = ?';
        $params[] = $taskId;
        $params[] = $projectId;

        Database::connection()->prepare($sql)->execute($params);
    }

    public static function moveBefore(int $projectId, int $taskId, ?int $phaseId, ?int $taskListId, ?int $beforeTaskId): void
    {
        self::moveMany($projectId, [$taskId], $phaseId, $taskListId);

        $stmt = Database::connection()->prepare(
            'SELECT id FROM tasks WHERE project_id = ? AND (task_list_id <=> ?) ORDER BY sort_order, due_date IS NULL, due_date, id'
        );
        $stmt->execute([$projectId, $taskListId]);
        $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        $ids = array_values(array_filter($ids, fn (int $id): bool => $id !== $taskId));
        $insertAt = $beforeTaskId ? array_search($beforeTaskId, $ids, true) : false;

        if ($insertAt === false) {
            $ids[] = $taskId;
        } else {
            array_splice($ids, (int) $insertAt, 0, [$taskId]);
        }

        $update = Database::connection()->prepare('UPDATE tasks SET sort_order = ? WHERE id = ? AND project_id = ?');
        foreach ($ids as $index => $id) {
            $update->execute([$index + 1, $id, $projectId]);
        }
    }

    public static function deleteMany(int $projectId, array $taskIds): void
    {
        $taskIds = array_values(array_filter(array_map('intval', $taskIds)));
        if (!$taskIds) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($taskIds), '?'));
        $stmt = Database::connection()->prepare("DELETE FROM tasks WHERE project_id = ? AND id IN ($placeholders)");
        $stmt->execute(array_merge([$projectId], $taskIds));
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE tasks
             SET title = :title,
                 description = :description,
                 assigned_to = :assigned_to,
                 status = :status,
                 priority = :priority,
                 start_date = :start_date,
                 due_date = :due_date,
                 estimated_hours = :estimated_hours,
                 progress = :progress
             WHERE id = :id'
        );
        $data['id'] = $id;
        $stmt->execute($data);
    }

    public static function comments(int $taskId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT c.*, u.name AS user_name
             FROM task_comments c
             JOIN users u ON u.id = c.user_id
             WHERE c.task_id = ?
             ORDER BY c.created_at ASC"
        );
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    public static function attachments(int $taskId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT a.*, u.name AS user_name
             FROM task_attachments a
             JOIN users u ON u.id = a.user_id
             WHERE a.task_id = ?
             ORDER BY a.created_at ASC"
        );
        $stmt->execute([$taskId]);
        return $stmt->fetchAll();
    }

    public static function addComment(int $taskId, int $userId, string $comment): void
    {
        $stmt = Database::connection()->prepare('INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)');
        $stmt->execute([$taskId, $userId, $comment]);
    }

    public static function addAttachment(array $data): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO task_attachments (task_id, user_id, original_name, stored_name, mime_type, file_size)
             VALUES (:task_id, :user_id, :original_name, :stored_name, :mime_type, :file_size)'
        );
        $stmt->execute($data);
    }

    public static function attachment(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM task_attachments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

}
