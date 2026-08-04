<?php

namespace App\Models;

use App\Core\Database;

class Project
{
    /** $archived: false = active only (default), true = archived only, null = both. */
    public static function all(?bool $archived = false): array
    {
        $sql = "SELECT p.*, u.name AS owner_name, c.name AS client_name,
                       COUNT(DISTINCT t.id) AS task_count,
                       COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) AS completed_tasks,
                       (
                           SELECT COALESCE(SUM(wl.hours), 0)
                           FROM work_logs wl
                           WHERE wl.project_group = p.name OR wl.project_group = p.project_group
                       ) AS logged_hours
                FROM projects p
                JOIN users u ON u.id = p.owner_id
                LEFT JOIN clients c ON c.id = p.client_id
                LEFT JOIN tasks t ON t.project_id = p.id";

        if ($archived !== null) {
            $sql .= ' WHERE p.archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL');
        }

        $sql .= ' GROUP BY p.id ORDER BY p.updated_at DESC';

        return Database::connection()->query($sql)->fetchAll();
    }

    public static function archivedCount(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM projects WHERE archived_at IS NOT NULL')->fetchColumn();
    }

    public static function archive(int $id): void
    {
        Database::connection()->prepare('UPDATE projects SET archived_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public static function unarchive(int $id): void
    {
        Database::connection()->prepare('UPDATE projects SET archived_at = NULL WHERE id = ?')->execute([$id]);
    }

    /** Hard delete - phases/task lists/tasks/comments/attachments/members cascade via FK. */
    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT p.*, u.name AS owner_name, c.name AS client_name
             FROM projects p
             JOIN users u ON u.id = p.owner_id
             LEFT JOIN clients c ON c.id = p.client_id
             WHERE p.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** Numeric/date columns editable via the Projects list's click-to-edit cells. */
    private const QUICK_UPDATE_INT_FIELDS = ['billed_learners', 'learners_on_platform', 'learners_connected', 'started_with_courses'];
    private const QUICK_UPDATE_DECIMAL_FIELDS = ['total_time', 'average_time_per_learner', 'adoption_percent'];
    private const QUICK_UPDATE_DATE_FIELDS = ['start_date', 'due_date'];

    public static function quickUpdate(int $projectId, string $field, ?string $value): void
    {
        $value = trim((string) $value);
        $allowed = array_merge(self::QUICK_UPDATE_INT_FIELDS, self::QUICK_UPDATE_DECIMAL_FIELDS, self::QUICK_UPDATE_DATE_FIELDS);
        if (!in_array($field, $allowed, true)) {
            return;
        }

        if ($value === '') {
            $bound = null;
        } elseif (in_array($field, self::QUICK_UPDATE_INT_FIELDS, true)) {
            $bound = (string) max(0, (int) $value);
        } elseif (in_array($field, self::QUICK_UPDATE_DECIMAL_FIELDS, true)) {
            $bound = (string) max(0, (float) $value);
        } else {
            $bound = $value;
        }

        $stmt = Database::connection()->prepare("UPDATE projects SET {$field} = ? WHERE id = ?");
        $stmt->execute([$bound, $projectId]);
    }

    public static function create(array $data): int
    {
        $data = self::resolveClientGroup($data);
        $stmt = Database::connection()->prepare(
            'INSERT INTO projects
                (name, code, client_id, project_group, color, description, owner_id, status, priority, billed_learners, learners_on_platform, learners_connected, started_with_courses, total_time, average_time_per_learner, adoption_percent, start_date, due_date)
             VALUES
                (:name, :code, :client_id, :project_group, :color, :description, :owner_id, :status, :priority, :billed_learners, :learners_on_platform, :learners_connected, :started_with_courses, :total_time, :average_time_per_learner, :adoption_percent, :start_date, :due_date)'
        );
        $stmt->execute($data);
        $projectId = (int) Database::connection()->lastInsertId();
        self::addMember($projectId, (int) $data['owner_id'], 'owner');
        return $projectId;
    }

    public static function update(int $id, array $data): void
    {
        $data = self::resolveClientGroup($data);
        $stmt = Database::connection()->prepare(
            'UPDATE projects
             SET name = :name,
                 code = :code,
                 client_id = :client_id,
                 project_group = :project_group,
                 color = :color,
                 description = :description,
                 owner_id = :owner_id,
                 status = :status,
                 priority = :priority,
                 billed_learners = :billed_learners,
                 learners_on_platform = :learners_on_platform,
                 learners_connected = :learners_connected,
                 started_with_courses = :started_with_courses,
                 total_time = :total_time,
                 average_time_per_learner = :average_time_per_learner,
                 adoption_percent = :adoption_percent,
                 start_date = :start_date,
                 due_date = :due_date
             WHERE id = :id'
        );
        $data['id'] = $id;
        $stmt->execute($data);
    }

    /**
     * When a client is picked, project_group (the denormalized "Client Name"
     * used across reports and work logs) is always resolved from the client
     * record rather than trusted from the form, so reports can't drift from
     * the client's actual name.
     */
    private static function resolveClientGroup(array $data): array
    {
        $clientId = $data['client_id'] ?? null;
        if ($clientId) {
            $client = Client::find((int) $clientId);
            if ($client) {
                $data['project_group'] = $client['name'];
            }
        }

        return $data;
    }

    /** Moves a project to a different client (drag-and-drop on the Clients page), keeping project_group in sync like a normal edit would. */
    public static function reassignClient(int $projectId, int $clientId): bool
    {
        $client = Client::find($clientId);
        if (!$client) {
            return false;
        }

        $stmt = Database::connection()->prepare('UPDATE projects SET client_id = ?, project_group = ? WHERE id = ?');
        $stmt->execute([$clientId, $client['name'], $projectId]);
        return true;
    }

    public static function members(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            "SELECT pm.*, u.name, u.email, u.designation
             FROM project_members pm
             JOIN users u ON u.id = pm.user_id
             WHERE pm.project_id = ?
             ORDER BY FIELD(pm.project_role, 'owner','manager','member','viewer'), u.name"
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function phases(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM project_phases
             WHERE project_id = ?
             ORDER BY sort_order, id'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function taskLists(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT tl.*, pp.name AS phase_name
             FROM task_lists tl
             LEFT JOIN project_phases pp ON pp.id = tl.phase_id
             WHERE tl.project_id = ?
             ORDER BY pp.sort_order IS NULL, pp.sort_order, pp.id, tl.sort_order, tl.id'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public static function createPhase(int $projectId, string $name): int
    {
        $orderStmt = Database::connection()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM project_phases WHERE project_id = ?');
        $orderStmt->execute([$projectId]);

        $stmt = Database::connection()->prepare(
            'INSERT INTO project_phases (project_id, name, sort_order) VALUES (?, ?, ?)'
        );
        $stmt->execute([$projectId, $name, (int) $orderStmt->fetchColumn()]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function createTaskList(int $projectId, ?int $phaseId, string $name): int
    {
        $orderStmt = Database::connection()->prepare('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM task_lists WHERE project_id = ? AND (phase_id <=> ?)');
        $orderStmt->execute([$projectId, $phaseId]);

        $stmt = Database::connection()->prepare(
            'INSERT INTO task_lists (project_id, phase_id, name, sort_order) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$projectId, $phaseId, $name, (int) $orderStmt->fetchColumn()]);
        return (int) Database::connection()->lastInsertId();
    }

    public static function movePhaseBefore(int $projectId, int $phaseId, ?int $beforePhaseId): void
    {
        if ($phaseId === $beforePhaseId) {
            return;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id FROM project_phases WHERE project_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$projectId]);
        $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));
        self::moveIdBefore($ids, $phaseId, $beforePhaseId);
        self::saveSortOrder('project_phases', $projectId, $ids);
    }

    public static function moveTaskList(int $projectId, int $taskListId, ?int $phaseId, ?int $beforeTaskListId = null): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE task_lists SET phase_id = ? WHERE id = ? AND project_id = ?'
        );
        $stmt->execute([$phaseId, $taskListId, $projectId]);

        $listStmt = Database::connection()->prepare(
            'SELECT id FROM task_lists WHERE project_id = ? AND (phase_id <=> ?) ORDER BY sort_order, id'
        );
        $listStmt->execute([$projectId, $phaseId]);
        $ids = array_map('intval', array_column($listStmt->fetchAll(), 'id'));
        self::moveIdBefore($ids, $taskListId, $beforeTaskListId);
        self::saveSortOrder('task_lists', $projectId, $ids);
    }

    public static function deletePhase(int $projectId, int $phaseId): void
    {
        $db = Database::connection();
        $db->prepare('UPDATE tasks SET phase_id = NULL, task_list_id = NULL WHERE project_id = ? AND phase_id = ?')->execute([$projectId, $phaseId]);
        $db->prepare('DELETE FROM project_phases WHERE id = ? AND project_id = ?')->execute([$phaseId, $projectId]);
    }

    public static function deleteTaskList(int $projectId, int $taskListId): void
    {
        $db = Database::connection();
        $db->prepare('UPDATE tasks SET task_list_id = NULL WHERE project_id = ? AND task_list_id = ?')->execute([$projectId, $taskListId]);
        $db->prepare('DELETE FROM task_lists WHERE id = ? AND project_id = ?')->execute([$taskListId, $projectId]);
    }

    public static function renamePhase(int $projectId, int $phaseId, string $name): void
    {
        $stmt = Database::connection()->prepare('UPDATE project_phases SET name = ? WHERE id = ? AND project_id = ?');
        $stmt->execute([$name, $phaseId, $projectId]);
    }

    public static function renameTaskList(int $projectId, int $taskListId, string $name): void
    {
        $stmt = Database::connection()->prepare('UPDATE task_lists SET name = ? WHERE id = ? AND project_id = ?');
        $stmt->execute([$name, $taskListId, $projectId]);
    }

    private static function moveIdBefore(array &$ids, int $movedId, ?int $beforeId): void
    {
        $ids = array_values(array_filter($ids, fn (int $id): bool => $id !== $movedId));
        $insertAt = $beforeId ? array_search($beforeId, $ids, true) : false;

        if ($insertAt === false) {
            $ids[] = $movedId;
            return;
        }

        array_splice($ids, (int) $insertAt, 0, [$movedId]);
    }

    private static function saveSortOrder(string $table, int $projectId, array $ids): void
    {
        $db = Database::connection();
        $stmt = $db->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ? AND project_id = ?");

        foreach (array_values($ids) as $index => $id) {
            $stmt->execute([$index + 1, $id, $projectId]);
        }
    }

    public static function addMember(int $projectId, int $userId, string $role): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO project_members (project_id, user_id, project_role)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE project_role = VALUES(project_role)'
        );
        $stmt->execute([$projectId, $userId, $role]);
    }

    public static function removeMember(int $projectId, int $userId): void
    {
        $stmt = Database::connection()->prepare('DELETE FROM project_members WHERE project_id = ? AND user_id = ?');
        $stmt->execute([$projectId, $userId]);
    }
}
