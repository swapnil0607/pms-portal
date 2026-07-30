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

    private static function distinctUnion(array $selects): array
    {
        $sql = 'SELECT DISTINCT value FROM (' . implode(' UNION ', $selects) . ') combined ORDER BY value';
        $values = Database::connection()->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
        return array_values(array_filter($values, static fn (string $value): bool => trim($value) !== ''));
    }
}
