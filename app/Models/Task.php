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

    /**
     * Powers the Home page's "Find a Task" search: matches by title text
     * and/or the client/project/phase/task-list filters, whichever are set.
     * Requires at least one of those so it never dumps the whole task table.
     */
    public static function search(array $filters): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $clientId = (int) ($filters['client_id'] ?? 0);
        $projectId = (int) ($filters['project_id'] ?? 0);
        $phaseId = (int) ($filters['phase_id'] ?? 0);
        $taskListId = (int) ($filters['task_list_id'] ?? 0);

        $where = [];
        $params = [];

        if ($q !== '') {
            $where[] = 't.title LIKE ?';
            $params[] = '%' . $q . '%';
        }
        if ($clientId) {
            $where[] = 'p.client_id = ?';
            $params[] = $clientId;
        }
        if ($projectId) {
            $where[] = 't.project_id = ?';
            $params[] = $projectId;
        }
        if ($phaseId) {
            $where[] = 't.phase_id = ?';
            $params[] = $phaseId;
        }
        if ($taskListId) {
            $where[] = 't.task_list_id = ?';
            $params[] = $taskListId;
        }

        if (!$where) {
            return [];
        }

        $stmt = Database::connection()->prepare(
            "SELECT t.id, t.title, t.status, p.name AS project_name, pp.name AS phase_name, tl.name AS task_list_name
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN project_phases pp ON pp.id = t.phase_id
             LEFT JOIN task_lists tl ON tl.id = t.task_list_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY t.updated_at DESC
             LIMIT 30'
        );
        $stmt->execute($params);

        return array_map(static function (array $row): array {
            $row['status_label'] = self::STATUS_LABELS[$row['status']] ?? $row['status'];
            return $row;
        }, $stmt->fetchAll());
    }

    /** Client -> Project -> Phase/Task-List option lists for the Home page search filters, each carrying its parent id(s) so the UI can cascade them. */
    public static function filterOptions(): array
    {
        $db = Database::connection();

        return [
            'clients' => $db->query("SELECT id, name FROM clients WHERE status = 'active' ORDER BY name")->fetchAll(),
            'projects' => $db->query('SELECT id, name, client_id FROM projects WHERE archived_at IS NULL ORDER BY name')->fetchAll(),
            'phases' => $db->query('SELECT id, name, project_id FROM project_phases ORDER BY sort_order, name')->fetchAll(),
            'taskLists' => $db->query('SELECT id, name, project_id, phase_id FROM task_lists ORDER BY sort_order, name')->fetchAll(),
        ];
    }

    /**
     * Live Daily Log task lookup. The project is the only mandatory hierarchy
     * constraint: older migrated tasks sometimes lack a phase/task-list id,
     * so phase/list are used to rank results rather than hiding valid tasks.
     */
    public static function dailyLogSearch(string $project = '', string $query = '', string $phase = '', string $taskList = ''): array
    {
        $project = trim($project);
        $query = trim($query);
        $phase = trim($phase);
        $taskList = trim($taskList);

        $where = ['p.archived_at IS NULL'];
        $params = [];
        if ($project !== '') {
            $where[] = 'p.name = ?';
            $params[] = $project;
        }
        if ($phase !== '') {
            $where[] = 'pp.name = ?';
            $params[] = $phase;
        }
        if ($taskList !== '') {
            $where[] = 'tl.name = ?';
            $params[] = $taskList;
        }
        if ($query !== '') {
            $where[] = 't.title LIKE ?';
            $params[] = '%' . $query . '%';
        }

        $stmt = Database::connection()->prepare(
            "SELECT t.id, t.title, p.name AS project, COALESCE(pp.name, 'No Phase') AS phase, COALESCE(tl.name, 'General') AS task_list
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN project_phases pp ON pp.id = t.phase_id
             LEFT JOIN task_lists tl ON tl.id = t.task_list_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY t.title ASC
             LIMIT 30"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Resolves a Daily Log entry (free-text project/phase/module/notes) to a
     * real Task so the entry is clickable from Time Logs, creating whatever
     * structure is missing along the way: Project must already exist (never
     * guessed/created - too many required fields to invent safely) - Phase
     * and Task List are found-or-created under it, then a Task is found (by
     * exact title match within that task list) or created, marked completed
     * since a daily log always describes work already done. Returns null
     * (never throws) when the typed project name doesn't match a real,
     * non-archived project - the log still saves fine, just stays unlinked
     * exactly like before this feature existed.
     */
    public static function findOrCreateForLog(string $projectName, string $phase, string $moduleName, string $notes, int $userId): ?int
    {
        $projectName = trim($projectName);
        $phase = trim($phase);
        $moduleName = trim($moduleName);
        $title = mb_substr(trim($notes), 0, 220);

        if ($projectName === '' || $title === '') {
            return null;
        }

        $db = Database::connection();

        $stmt = $db->prepare('SELECT id FROM projects WHERE name = ? AND archived_at IS NULL LIMIT 1');
        $stmt->execute([$projectName]);
        $projectId = $stmt->fetchColumn();

        if (!$projectId) {
            $stmt = $db->prepare('
                SELECT p.id 
                FROM projects p 
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN project_phases ph ON ph.project_id = p.id
                LEFT JOIN task_lists tl ON tl.project_id = p.id
                WHERE (c.name = ? OR p.name LIKE ?)
                  AND (ph.name = ? OR tl.name = ?)
                  AND p.archived_at IS NULL
                LIMIT 1
            ');
            $stmt->execute([$projectName, '%' . $projectName . '%', $phase, $moduleName]);
            $projectId = $stmt->fetchColumn();
        }

        if (!$projectId) {
            $stmt = $db->prepare('
                SELECT p.id 
                FROM projects p 
                JOIN clients c ON c.id = p.client_id
                WHERE c.name = ? AND p.archived_at IS NULL
                LIMIT 1
            ');
            $stmt->execute([$projectName]);
            $projectId = $stmt->fetchColumn();
        }

        if (!$projectId) {
            return null;
        }
        $projectId = (int) $projectId;

        $phaseId = null;
        if ($phase !== '') {
            $stmt = $db->prepare('SELECT id FROM project_phases WHERE project_id = ? AND name = ? LIMIT 1');
            $stmt->execute([$projectId, $phase]);
            $phaseId = $stmt->fetchColumn();
            if ($phaseId) {
                $phaseId = (int) $phaseId;
            } else {
                $order = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM project_phases WHERE project_id = ?');
                $order->execute([$projectId]);
                $db->prepare('INSERT INTO project_phases (project_id, name, sort_order) VALUES (?, ?, ?)')
                    ->execute([$projectId, $phase, (int) $order->fetchColumn()]);
                $phaseId = (int) $db->lastInsertId();
            }
        }

        $taskListId = null;
        if ($moduleName !== '') {
            $stmt = $db->prepare('SELECT id FROM task_lists WHERE project_id = ? AND (phase_id <=> ?) AND name = ? LIMIT 1');
            $stmt->execute([$projectId, $phaseId, $moduleName]);
            $taskListId = $stmt->fetchColumn();
            if ($taskListId) {
                $taskListId = (int) $taskListId;
            } else {
                $order = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM task_lists WHERE project_id = ? AND (phase_id <=> ?)');
                $order->execute([$projectId, $phaseId]);
                $db->prepare('INSERT INTO task_lists (project_id, phase_id, name, sort_order) VALUES (?, ?, ?, ?)')
                    ->execute([$projectId, $phaseId, $moduleName, (int) $order->fetchColumn()]);
                $taskListId = (int) $db->lastInsertId();
            }
        }

        $stmt = $db->prepare('SELECT id FROM tasks WHERE project_id = ? AND (task_list_id <=> ?) AND title = ? LIMIT 1');
        $stmt->execute([$projectId, $taskListId, $title]);
        $taskId = $stmt->fetchColumn();
        if ($taskId) {
            return (int) $taskId;
        }

        $db->prepare(
            'INSERT INTO tasks (project_id, phase_id, task_list_id, title, assigned_to, status, priority, progress, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$projectId, $phaseId, $taskListId, $title, $userId, 'completed', 'medium', 100, $userId]);

        $taskId = (int) $db->lastInsertId();
        self::syncAssignees($taskId, [$userId]);
        return $taskId;
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
            "SELECT t.*, " . self::assigneeNamesSql() . ", pp.name AS phase_name, tl.name AS task_list_name
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
            "SELECT t.*, p.name AS project_name, p.project_group, " . self::assigneeNamesSql() . ", creator.name AS creator_name,
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

    public static function kanban(?int $projectId = null, ?int $assigneeId = null, ?int $completedLimit = 25): array
    {
        $db = Database::connection();
        $where = [];
        $params = [];

        if ($projectId) {
            $where[] = 't.project_id = ?';
            $params[] = $projectId;
        }

        if ($assigneeId) {
            $where[] = 'EXISTS (SELECT 1 FROM task_assignees task_assignees_filter WHERE task_assignees_filter.task_id = t.id AND task_assignees_filter.user_id = ?)';
            $params[] = $assigneeId;
        }

        // 1. Get true counts for all statuses
        $countSql = "SELECT t.status, COUNT(*) AS cnt
                     FROM tasks t";
        if ($where) {
            $countSql .= ' WHERE ' . implode(' AND ', $where);
        }
        $countSql .= " GROUP BY t.status";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($countStmt->fetchAll() as $row) {
            if (isset($counts[$row['status']])) {
                $counts[$row['status']] = (int) $row['cnt'];
            }
        }

        // 2. Fetch Active (non-completed) tasks
        $activeWhere = $where;
        $activeWhere[] = "t.status != 'completed'";
        $activeParams = $params;

        $activeSql = "SELECT t.*, p.name AS project_name, tl.name AS task_list_name, ph.name AS phase_name, " . self::assigneeNamesSql() . "
                      FROM tasks t
                      JOIN projects p ON p.id = t.project_id
                      LEFT JOIN task_lists tl ON tl.id = t.task_list_id
                      LEFT JOIN project_phases ph ON ph.id = t.phase_id
                      LEFT JOIN users u ON u.id = t.assigned_to";
        if ($activeWhere) {
            $activeSql .= ' WHERE ' . implode(' AND ', $activeWhere);
        }
        $activeSql .= " ORDER BY FIELD(t.priority, 'critical','high','medium','low'), t.due_date IS NULL, t.due_date, t.updated_at DESC";

        $activeStmt = $db->prepare($activeSql);
        $activeStmt->execute($activeParams);

        $grouped = array_fill_keys(self::STATUSES, []);
        foreach ($activeStmt->fetchAll() as $task) {
            $grouped[$task['status']][] = $task;
        }

        // 3. Fetch Completed tasks (with limit if applicable)
        $completedWhere = $where;
        $completedWhere[] = "t.status = 'completed'";
        $completedParams = $params;

        $completedSql = "SELECT t.*, p.name AS project_name, tl.name AS task_list_name, ph.name AS phase_name, " . self::assigneeNamesSql() . "
                         FROM tasks t
                         JOIN projects p ON p.id = t.project_id
                         LEFT JOIN task_lists tl ON tl.id = t.task_list_id
                         LEFT JOIN project_phases ph ON ph.id = t.phase_id
                         LEFT JOIN users u ON u.id = t.assigned_to";
        if ($completedWhere) {
            $completedSql .= ' WHERE ' . implode(' AND ', $completedWhere);
        }
        $completedSql .= " ORDER BY t.updated_at DESC, t.due_date DESC, t.id DESC";

        if ($completedLimit !== null && $completedLimit > 0) {
            $completedSql .= " LIMIT " . (int) $completedLimit;
        }

        $completedStmt = $db->prepare($completedSql);
        $completedStmt->execute($completedParams);
        $grouped['completed'] = $completedStmt->fetchAll();

        return [
            'columns' => $grouped,
            'counts' => $counts,
            'completed_total' => $counts['completed'] ?? 0,
            'completed_limit' => $completedLimit,
        ];
    }

    /**
     * A user's committed hours per day (Mon-Fri) within [$fromDate, $toDate],
     * from their other open tasks. Each task's estimated_hours is spread
     * evenly across the working days in its own start_date..due_date range
     * (a task with no start_date puts everything on due_date alone; a range
     * that's entirely a weekend falls back to due_date too, so hours are
     * never silently dropped). Used to preview workload before assigning
     * or rescheduling a task.
     */
    public static function workloadForUser(int $userId, string $fromDate, string $toDate, ?int $excludeTaskId = null): array
    {
        $where = ['EXISTS (SELECT 1 FROM task_assignees WHERE task_assignees.task_id = tasks.id AND task_assignees.user_id = ?)', "status <> 'completed'", 'due_date IS NOT NULL', 'estimated_hours IS NOT NULL'];
        $params = [$userId];
        if ($excludeTaskId) {
            $where[] = 'id <> ?';
            $params[] = $excludeTaskId;
        }

        $stmt = Database::connection()->prepare(
            'SELECT start_date, due_date, estimated_hours FROM tasks WHERE ' . implode(' AND ', $where)
        );
        $stmt->execute($params);

        $rangeStart = new \DateTimeImmutable($fromDate);
        $rangeEnd = new \DateTimeImmutable($toDate);
        $load = [];

        foreach ($stmt->fetchAll() as $row) {
            $hours = (float) $row['estimated_hours'];
            if ($hours <= 0) {
                continue;
            }

            foreach (self::distributeHours($row['start_date'], $row['due_date'], $hours) as $date => $dayHours) {
                $dateObj = new \DateTimeImmutable($date);
                if ($dateObj < $rangeStart || $dateObj > $rangeEnd) {
                    continue;
                }
                $load[$date] = ($load[$date] ?? 0) + $dayHours;
            }
        }

        ksort($load);
        return $load;
    }

    /** Evenly spreads $hours across the Mon-Fri days between $startDate and $dueDate (inclusive); falls back to $dueDate alone if that range has none. */
    private static function distributeHours(?string $startDate, string $dueDate, float $hours): array
    {
        $start = $startDate ?: $dueDate;
        $startDt = new \DateTimeImmutable($start);
        $dueDt = new \DateTimeImmutable($dueDate);
        if ($startDt > $dueDt) {
            $startDt = $dueDt;
        }

        $weekdays = [];
        for ($cursor = $startDt; $cursor <= $dueDt; $cursor = $cursor->modify('+1 day')) {
            if ((int) $cursor->format('N') <= 5) {
                $weekdays[] = $cursor->format('Y-m-d');
            }
        }

        if (!$weekdays) {
            $weekdays = [$dueDt->format('Y-m-d')];
        }

        $perDay = $hours / count($weekdays);
        return array_fill_keys($weekdays, $perDay);
    }

    public static function myWork(int $userId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT t.*, p.name AS project_name
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             WHERE EXISTS (SELECT 1 FROM task_assignees WHERE task_assignees.task_id = t.id AND task_assignees.user_id = ?)
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
             WHERE EXISTS (SELECT 1 FROM task_assignees WHERE task_assignees.task_id = tasks.id AND task_assignees.user_id = ?)"
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
        $assigneeIds = self::normalizeAssigneeIds($data['assignee_ids'] ?? []);
        unset($data['assignee_ids']);
        $data['progress'] = ($data['status'] ?? '') === 'completed' ? 100 : 0;

        $stmt = Database::connection()->prepare(
            'INSERT INTO tasks (project_id, phase_id, task_list_id, task_code, title, description, assigned_to, status, priority, start_date, due_date, estimated_hours, progress, created_by)
             VALUES (:project_id, :phase_id, :task_list_id, :task_code, :title, :description, :assigned_to, :status, :priority, :start_date, :due_date, :estimated_hours, :progress, :created_by)'
        );
        $stmt->execute($data);
        $taskId = (int) Database::connection()->lastInsertId();
        self::syncAssignees($taskId, $assigneeIds);

        \App\Services\AuditService::log(
            'task',
            $taskId,
            'created',
            null,
            $data,
            null,
            (int) ($_SESSION['user_id'] ?? $data['created_by'] ?? 0)
        );

        if (!empty($assigneeIds)) {
            \App\Services\NotificationService::onTaskAssigned(
                $taskId,
                $assigneeIds,
                (int) ($_SESSION['user_id'] ?? $data['created_by'] ?? 0)
            );
        }

        return $taskId;
    }

    public static function updateStatus(int $id, string $status, int $progress): void
    {
        if ($status === 'completed') {
            $progress = 100;
        }

        $stmt = Database::connection()->prepare('UPDATE tasks SET status = ?, progress = ? WHERE id = ?');
        $stmt->execute([$status, $progress, $id]);

        if (in_array($status, ['in_review', 'completed'], true)) {
            \App\Services\NotificationService::onTaskStatusChanged($id, $status, (int) ($_SESSION['user_id'] ?? 0));
        }
    }

    public static function moveMany(int $projectId, array $taskIds, ?int $phaseId, ?int $taskListId): void
    {
        $taskIds = array_values(array_filter(array_map('intval', $taskIds)));
        if (!$taskIds) {
            return;
        }

        if ($taskListId) {
            $stmt = Database::connection()->prepare('SELECT phase_id FROM task_lists WHERE id = ? AND project_id = ?');
            $stmt->execute([$taskListId, $projectId]);
            $listPhase = $stmt->fetchColumn();
            if ($listPhase !== false) {
                $phaseId = $listPhase ? (int) $listPhase : null;
            }
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

        $old = self::find($taskId);

        $sql = 'UPDATE tasks SET ' . $allowed[$field] . ' = ?';
        $params = [$value];

        if ($field === 'status' && $value === 'completed') {
            $sql .= ', progress = 100';
        }

        $sql .= ' WHERE id = ? AND project_id = ?';
        $params[] = $taskId;
        $params[] = $projectId;

        Database::connection()->prepare($sql)->execute($params);

        if ($old) {
            \App\Services\AuditService::log(
                'task',
                $taskId,
                'updated',
                [$field => $old[$field] ?? null],
                [$field => $value],
                "Quick-updated task #{$taskId}: " . ucwords(str_replace('_', ' ', $field)) . " changed to '{$value}'"
            );
        }

        if ($field === 'status' && in_array($value, ['in_review', 'completed'], true)) {
            \App\Services\NotificationService::onTaskStatusChanged($taskId, (string) $value, (int) ($_SESSION['user_id'] ?? 0));
        }
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
        $db = Database::connection();

        // Capture snapshots for audit logging & cascade cleanups
        try {
            $fetchStmt = $db->prepare("SELECT * FROM tasks WHERE project_id = ? AND id IN ($placeholders)");
            $fetchStmt->execute(array_merge([$projectId], $taskIds));
            $oldTasks = $fetchStmt->fetchAll();
            foreach ($oldTasks as $oldTask) {
                \App\Services\AuditService::log(
                    'task',
                    (int) $oldTask['id'],
                    'deleted',
                    $oldTask,
                    null
                );
            }

            // Capture and delete attached work logs so ghost logs are never left behind
            $wlFetch = $db->prepare("SELECT * FROM work_logs WHERE task_id IN ($placeholders)");
            $wlFetch->execute($taskIds);
            $oldWorkLogs = $wlFetch->fetchAll();
            foreach ($oldWorkLogs as $oldWl) {
                \App\Services\AuditService::log(
                    'work_log',
                    (int) $oldWl['id'],
                    'deleted',
                    $oldWl,
                    null
                );
            }

            if (!empty($oldWorkLogs)) {
                $db->prepare("DELETE FROM work_logs WHERE task_id IN ($placeholders)")->execute($taskIds);
            }

            // Clean up task assignees
            $db->prepare("DELETE FROM task_assignees WHERE task_id IN ($placeholders)")->execute($taskIds);
        } catch (\Throwable $e) {
            error_log('Error cleaning up tasks or work logs on deletion: ' . $e->getMessage());
        }

        $stmt = $db->prepare("DELETE FROM tasks WHERE project_id = ? AND id IN ($placeholders)");
        $stmt->execute(array_merge([$projectId], $taskIds));
    }

    public static function update(int $id, array $data): void
    {
        $old = self::find($id);
        $oldAssigneeIds = self::assigneeIds($id);
        $assigneeIds = self::normalizeAssigneeIds($data['assignee_ids'] ?? []);
        unset($data['assignee_ids']);
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
        self::syncAssignees($id, $assigneeIds);

        if ($old) {
            \App\Services\AuditService::log(
                'task',
                $id,
                'updated',
                $old,
                $data
            );
        }

        $newAssigneeIds = array_diff($assigneeIds, $oldAssigneeIds);
        if (!empty($newAssigneeIds)) {
            \App\Services\NotificationService::onTaskAssigned(
                $id,
                $newAssigneeIds,
                (int) ($_SESSION['user_id'] ?? 0)
            );
        }

        if (!empty($data['status']) && in_array($data['status'], ['in_review', 'completed'], true)) {
            \App\Services\NotificationService::onTaskStatusChanged(
                $id,
                $data['status'],
                (int) ($_SESSION['user_id'] ?? 0)
            );
        }
    }

    /** Converts a submitted multi-select value into unique positive user IDs. */
    public static function normalizeAssigneeIds(mixed $values): array
    {
        if (!is_array($values)) {
            $values = $values === null || $values === '' ? [] : [$values];
        }

        $ids = [];
        foreach ($values as $value) {
            $id = (int) $value;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

    /** Returns selected assignee IDs for pre-selecting the task edit form. */
    public static function assigneeIds(int $taskId): array
    {
        $stmt = Database::connection()->prepare('SELECT user_id FROM task_assignees WHERE task_id = ? ORDER BY user_id');
        $stmt->execute([$taskId]);
        return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /** Keeps the assignment list in sync while retaining tasks.assigned_to as the primary assignee for legacy compatibility. */
    private static function syncAssignees(int $taskId, array $assigneeIds): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM task_assignees WHERE task_id = ?')->execute([$taskId]);
        if (!$assigneeIds) {
            return;
        }

        $insert = $db->prepare('INSERT INTO task_assignees (task_id, user_id) VALUES (?, ?)');
        foreach ($assigneeIds as $userId) {
            $insert->execute([$taskId, $userId]);
        }
    }

    /** SQL expression which shows every assigned person's name, with the original primary assignee as a fallback. */
    private static function assigneeNamesSql(): string
    {
        return "COALESCE(NULLIF((SELECT GROUP_CONCAT(assigned_user.name ORDER BY assigned_user.name SEPARATOR ', ')
             FROM task_assignees assigned_link
             JOIN users assigned_user ON assigned_user.id = assigned_link.user_id
             WHERE assigned_link.task_id = t.id), ''), u.name) AS assignee";
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
