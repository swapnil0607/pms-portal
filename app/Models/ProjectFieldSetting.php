<?php

namespace App\Models;

class ProjectFieldSetting
{
    public static function defaults(): array
    {
        return [
            ['key' => 'code', 'label' => 'ID', 'visible' => true],
            ['key' => 'name', 'label' => 'Project Name', 'visible' => true],
            ['key' => 'owner_name', 'label' => 'Owner', 'visible' => true],
            ['key' => 'status', 'label' => 'Status', 'visible' => true],
            ['key' => 'billed_learners', 'label' => 'Billed Learners', 'visible' => true],
            ['key' => 'learners_on_platform', 'label' => 'Learners On Platform', 'visible' => true],
            ['key' => 'learners_connected', 'label' => 'Learners Connected', 'visible' => true],
            ['key' => 'started_with_courses', 'label' => 'Started With Courses', 'visible' => true],
            ['key' => 'total_time', 'label' => 'Total Time', 'visible' => true],
            ['key' => 'average_time_per_learner', 'label' => 'Avg Time', 'visible' => true],
            ['key' => 'adoption_percent', 'label' => 'Adoption %', 'visible' => true],
            ['key' => 'start_date', 'label' => 'Start Date', 'visible' => true],
            ['key' => 'due_date', 'label' => 'End Date', 'visible' => true],
            ['key' => 'tasks', 'label' => 'Tasks', 'visible' => true],
        ];
    }

    public static function all(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return self::defaults();
        }

        $stored = json_decode((string) file_get_contents($path), true);
        if (!is_array($stored)) {
            return self::defaults();
        }

        $byKey = [];
        foreach ($stored as $field) {
            if (!empty($field['key'])) {
                $byKey[$field['key']] = $field;
            }
        }

        return array_map(static function (array $default) use ($byKey): array {
            $stored = $byKey[$default['key']] ?? [];
            return [
                'key' => $default['key'],
                'label' => trim((string) ($stored['label'] ?? '')) ?: $default['label'],
                'visible' => array_key_exists('visible', $stored) ? (bool) $stored['visible'] : $default['visible'],
            ];
        }, self::defaults());
    }

    public static function visible(): array
    {
        return array_values(array_filter(self::all(), static fn (array $field): bool => (bool) $field['visible']));
    }

    public static function save(array $labels, array $visibleKeys): void
    {
        $visibleMap = array_fill_keys($visibleKeys, true);
        $fields = [];

        foreach (self::defaults() as $field) {
            $fields[] = [
                'key' => $field['key'],
                'label' => trim((string) ($labels[$field['key']] ?? '')) ?: $field['label'],
                'visible' => isset($visibleMap[$field['key']]),
            ];
        }

        file_put_contents(self::path(), json_encode($fields, JSON_PRETTY_PRINT));
    }

    private static function path(): string
    {
        return __DIR__ . '/../../storage/project_fields.json';
    }
}
