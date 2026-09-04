<?php

namespace App\Models;

use App\Core\Database;

/**
 * Distinct-value lookups powering <datalist> autocomplete on the freeform
 * project / phase / task-list text inputs used across work logs, reports,
 * and project forms.
 */
class Suggestions
{
    public static function projectNames(): array
    {
        return self::distinctUnion([
            'SELECT DISTINCT name AS value FROM projects WHERE name IS NOT NULL AND name <> \'\' AND archived_at IS NULL',
            'SELECT DISTINCT project_group AS value FROM work_logs WHERE project_group IS NOT NULL AND project_group <> \'\'',
        ]);
    }

    /** Strictly real project names (no client or free-text history mixed in) - used where a field is dedicated to projects only, e.g. the Reports > Project Report search. */
    public static function projectsOnly(): array
    {
        return self::distinctUnion([
            'SELECT DISTINCT name AS value FROM projects WHERE name IS NOT NULL AND name <> \'\' AND archived_at IS NULL',
        ]);
    }

    /** Strictly real client names - used where a field is dedicated to clients only, e.g. the Reports > Customer Report search. */
    public static function clientNames(): array
    {
        return self::distinctUnion([
            'SELECT DISTINCT name AS value FROM clients WHERE name IS NOT NULL AND name <> \'\'',
        ]);
    }

    public static function phases(): array
    {
        return self::distinctUnion([
            'SELECT DISTINCT name AS value FROM project_phases WHERE name IS NOT NULL AND name <> \'\'',
            'SELECT DISTINCT phase AS value FROM work_logs WHERE phase IS NOT NULL AND phase <> \'\'',
        ]);
    }

    public static function taskLists(): array
    {
        return self::distinctUnion([
            'SELECT DISTINCT name AS value FROM task_lists WHERE name IS NOT NULL AND name <> \'\'',
            'SELECT DISTINCT module_name AS value FROM work_logs WHERE module_name IS NOT NULL AND module_name <> \'\'',
        ]);
    }

    /** Real active tasks for the cascading Daily Log task picker. */
    public static function tasks(): array
    {
        return Database::connection()->query(
            "SELECT t.id, t.title, p.name AS project, COALESCE(pp.name, 'No Phase') AS phase,
                    COALESCE(tl.name, 'General') AS task_list
             FROM tasks t
             JOIN projects p ON p.id = t.project_id
             LEFT JOIN project_phases pp ON pp.id = t.phase_id
             LEFT JOIN task_lists tl ON tl.id = t.task_list_id
             WHERE p.archived_at IS NULL
             ORDER BY p.name, pp.sort_order, tl.sort_order, t.title"
        )->fetchAll();
    }

    /**
     * Project -> Phase -> Module[] tree used to cascade-filter the Daily Log
     * suggestion dropdowns (picking a project narrows the phase list, picking
     * a phase narrows the module list). Built from both what's actually been
     * logged before (work_logs) and the structured project/phase/task-list
     * setup, so options exist even before the first log against them.
     */
    public static function hierarchy(): array
    {
        $tree = [];

        $ensurePhase = function (string $project, string $phase) use (&$tree): void {
            $project = trim($project);
            if ($project === '') {
                return;
            }
            $phase = trim($phase) ?: 'No Phase';
            if (!isset($tree[$project])) {
                $tree[$project] = [];
            }
            if (!isset($tree[$project][$phase])) {
                $tree[$project][$phase] = [];
            }
        };

        $addModule = function (string $project, string $phase, string $module) use (&$tree, $ensurePhase): void {
            $ensurePhase($project, $phase);
            $project = trim($project);
            $phase = trim($phase) ?: 'No Phase';
            $module = trim($module);
            if ($project === '' || $module === '' || in_array($module, $tree[$project][$phase], true)) {
                return;
            }
            $tree[$project][$phase][] = $module;
        };

        $db = Database::connection();

        // Seed top-level keys from real, non-archived project names only, so
        // stray historical free-text values logged before this field meant
        // "project" (e.g. an old client name someone typed) can't introduce
        // their own suggestion entry, and archived projects stop being
        // offered for new logging once closed out.
        foreach ($db->query(
            "SELECT DISTINCT name AS value FROM projects WHERE name IS NOT NULL AND name <> '' AND archived_at IS NULL"
        )->fetchAll(\PDO::FETCH_COLUMN) as $project) {
            $project = trim((string) $project);
            if ($project !== '' && !isset($tree[$project])) {
                $tree[$project] = [];
            }
        }

        foreach ($db->query(
            "SELECT DISTINCT project_group AS project, phase, module_name AS module
             FROM work_logs
             WHERE project_group IS NOT NULL AND project_group <> ''"
        )->fetchAll() as $row) {
            if (!isset($tree[(string) $row['project']])) {
                continue;
            }
            $addModule((string) $row['project'], (string) $row['phase'], (string) $row['module']);
        }

        foreach ($db->query(
            "SELECT p.name AS project, pp.name AS phase
             FROM project_phases pp
             JOIN projects p ON p.id = pp.project_id
             WHERE p.archived_at IS NULL"
        )->fetchAll() as $row) {
            $ensurePhase((string) $row['project'], (string) $row['phase']);
        }

        foreach ($db->query(
            "SELECT p.name AS project, pp.name AS phase, tl.name AS module
             FROM task_lists tl
             JOIN projects p ON p.id = tl.project_id
             LEFT JOIN project_phases pp ON pp.id = tl.phase_id
             WHERE p.archived_at IS NULL"
        )->fetchAll() as $row) {
            $addModule((string) $row['project'], (string) ($row['phase'] ?? ''), (string) $row['module']);
        }

        ksort($tree);
        foreach (array_keys($tree) as $project) {
            ksort($tree[$project]);
            foreach (array_keys($tree[$project]) as $phase) {
                sort($tree[$project][$phase]);
            }
        }

        return $tree;
    }

    private static function distinctUnion(array $selects): array
    {
        $sql = 'SELECT DISTINCT value FROM (' . implode(' UNION ', $selects) . ') combined ORDER BY value';
        $values = Database::connection()->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
        return array_values(array_filter($values, static fn (string $value): bool => trim($value) !== ''));
    }
}
