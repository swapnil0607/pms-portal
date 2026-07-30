<?php

namespace App\Models;

use App\Core\Database;

/**
 * Distinct-value lookups powering <datalist> autocomplete on the freeform
 * client / phase / task-list text inputs used across work logs, reports,
 * and project forms.
 */
class Suggestions
{
    public static function clients(): array
    {
        return self::distinctUnion([
            'SELECT name AS value FROM clients WHERE name IS NOT NULL AND name <> \'\'',
            'SELECT DISTINCT project_group AS value FROM projects WHERE project_group IS NOT NULL AND project_group <> \'\'',
            'SELECT DISTINCT project_group AS value FROM work_logs WHERE project_group IS NOT NULL AND project_group <> \'\'',
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

    /**
     * Client -> Phase -> Module[] tree used to cascade-filter the Daily Log
     * suggestion dropdowns (picking a client narrows the phase list, picking
     * a phase narrows the module list). Built from both what's actually been
     * logged before (work_logs) and the structured project/phase/task-list
     * setup, so options exist even before the first log against them.
     */
    public static function hierarchy(): array
    {
        $tree = [];

        $ensurePhase = function (string $client, string $phase) use (&$tree): void {
            $client = trim($client);
            if ($client === '') {
                return;
            }
            $phase = trim($phase) ?: 'No Phase';
            if (!isset($tree[$client])) {
                $tree[$client] = [];
            }
            if (!isset($tree[$client][$phase])) {
                $tree[$client][$phase] = [];
            }
        };

        $addModule = function (string $client, string $phase, string $module) use (&$tree, $ensurePhase): void {
            $ensurePhase($client, $phase);
            $client = trim($client);
            $phase = trim($phase) ?: 'No Phase';
            $module = trim($module);
            if ($client === '' || $module === '' || in_array($module, $tree[$client][$phase], true)) {
                return;
            }
            $tree[$client][$phase][] = $module;
        };

        foreach (self::clients() as $client) {
            if (!isset($tree[$client])) {
                $tree[$client] = [];
            }
        }

        $db = Database::connection();

        foreach ($db->query(
            "SELECT DISTINCT project_group AS client, phase, module_name AS module
             FROM work_logs
             WHERE project_group IS NOT NULL AND project_group <> ''"
        )->fetchAll() as $row) {
            $addModule((string) $row['client'], (string) $row['phase'], (string) $row['module']);
        }

        foreach ($db->query(
            "SELECT COALESCE(NULLIF(p.project_group, ''), p.name) AS client, pp.name AS phase
             FROM project_phases pp
             JOIN projects p ON p.id = pp.project_id"
        )->fetchAll() as $row) {
            $ensurePhase((string) $row['client'], (string) $row['phase']);
        }

        foreach ($db->query(
            "SELECT COALESCE(NULLIF(p.project_group, ''), p.name) AS client, pp.name AS phase, tl.name AS module
             FROM task_lists tl
             JOIN projects p ON p.id = tl.project_id
             LEFT JOIN project_phases pp ON pp.id = tl.phase_id"
        )->fetchAll() as $row) {
            $addModule((string) $row['client'], (string) ($row['phase'] ?? ''), (string) $row['module']);
        }

        ksort($tree);
        foreach (array_keys($tree) as $client) {
            ksort($tree[$client]);
            foreach (array_keys($tree[$client]) as $phase) {
                sort($tree[$client][$phase]);
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
