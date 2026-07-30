<?php

namespace App\Models;

use App\Core\Database;

class TaskListTemplate
{
    public static function all(): array
    {
        $sql = "SELECT t.*, COUNT(tt.id) AS task_count
                FROM task_list_templates t
                LEFT JOIN task_list_template_tasks tt ON tt.template_id = t.id
                GROUP BY t.id
                ORDER BY t.name";

        return Database::connection()->query($sql)->fetchAll();
    }

    public static function allWithTasks(): array
    {
        $templates = self::all();
        foreach ($templates as &$template) {
            $template['tasks'] = self::tasks((int) $template['id']);
        }
        unset($template);
        return $templates;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM task_list_templates WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function tasks(int $templateId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT *
             FROM task_list_template_tasks
             WHERE template_id = ?
             ORDER BY sort_order, id'
        );
        $stmt->execute([$templateId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data, array $taskTitles): int
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO task_list_templates (name, description, created_by) VALUES (?, ?, ?)'
        );
        $stmt->execute([$data['name'], $data['description'], $data['created_by']]);
        $templateId = (int) $db->lastInsertId();

        self::replaceTasks($templateId, $taskTitles);
        return $templateId;
    }

    public static function update(int $templateId, array $data, array $taskTitles): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE task_list_templates SET name = ?, description = ? WHERE id = ?'
        );
        $stmt->execute([$data['name'], $data['description'], $templateId]);
        self::replaceTasks($templateId, $taskTitles);
    }

    public static function delete(int $templateId): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM task_list_template_tasks WHERE template_id = ?')->execute([$templateId]);
        $db->prepare('DELETE FROM task_list_templates WHERE id = ?')->execute([$templateId]);
    }

    public static function replaceTasks(int $templateId, array $taskTitles): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM task_list_template_tasks WHERE template_id = ?')->execute([$templateId]);
        $stmt = $db->prepare(
            'INSERT INTO task_list_template_tasks (template_id, title, priority, sort_order)
             VALUES (?, ?, ?, ?)'
        );

        $index = 1;
        foreach ($taskTitles as $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }
            $stmt->execute([$templateId, $title, 'medium', $index]);
            $index++;
        }
    }

    public static function applyToProject(int $projectId, ?int $phaseId, int $templateId, int $createdBy): ?int
    {
        $template = self::find($templateId);
        if (!$template) {
            return null;
        }

        $taskListId = Project::createTaskList($projectId, $phaseId, $template['name']);
        $tasks = self::tasks($templateId);
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO tasks
                (project_id, phase_id, task_list_id, title, description, priority, estimated_hours, sort_order, created_by)
             VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($tasks as $index => $task) {
            $stmt->execute([
                $projectId,
                $phaseId,
                $taskListId,
                $task['title'],
                $task['description'],
                $task['priority'],
                $task['estimated_hours'],
                $index + 1,
                $createdBy,
            ]);
        }

        return $taskListId;
    }
}
