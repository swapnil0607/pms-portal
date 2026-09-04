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

        $defaultMap = [];
        foreach (self::defaults() as $def) {
            $defaultMap[$def['key']] = $def;
        }

        $result = [];
        $seenKeys = [];

        // Return fields in the exact stored order
        foreach ($stored as $field) {
            $key = $field['key'] ?? '';
            if ($key && isset($defaultMap[$key]) && !isset($seenKeys[$key])) {
                $result[] = [
                    'key' => $key,
                    'label' => trim((string) ($field['label'] ?? '')) ?: $defaultMap[$key]['label'],
                    'visible' => array_key_exists('visible', $field) ? (bool) $field['visible'] : $defaultMap[$key]['visible'],
                ];
                $seenKeys[$key] = true;
            }
        }

        // Append any default fields that weren't in stored json
        foreach (self::defaults() as $def) {
            if (!isset($seenKeys[$def['key']])) {
                $result[] = $def;
            }
        }

        return $result;
    }

    public static function visible(): array
    {
        return array_values(array_filter(self::all(), static fn (array $field): bool => (bool) $field['visible']));
    }

    /** Returns [field_key => ['key' => ..., 'label' => ..., 'visible' => bool]] */
    public static function getMap(): array
    {
        $map = [];
        foreach (self::all() as $f) {
            $map[$f['key']] = $f;
        }
        return $map;
    }

    public static function isVisible(string $key): bool
    {
        $map = self::getMap();
        return (bool) ($map[$key]['visible'] ?? true);
    }

    public static function label(string $key, ?string $fallback = null): string
    {
        $map = self::getMap();
        if (isset($map[$key]['label']) && trim($map[$key]['label']) !== '') {
            return $map[$key]['label'];
        }
        return $fallback ?? $key;
    }

    public static function save(array $labels, array $visibleKeys, array $fieldOrder = []): void
    {
        $defaultMap = [];
        foreach (self::defaults() as $def) {
            $defaultMap[$def['key']] = $def;
        }

        $visibleMap = array_fill_keys($visibleKeys, true);
        $order = !empty($fieldOrder) ? $fieldOrder : array_keys($defaultMap);
        $fields = [];
        $seenKeys = [];

        foreach ($order as $key) {
            if (isset($defaultMap[$key]) && !isset($seenKeys[$key])) {
                $fields[] = [
                    'key' => $key,
                    'label' => trim((string) ($labels[$key] ?? '')) ?: $defaultMap[$key]['label'],
                    'visible' => isset($visibleMap[$key]),
                ];
                $seenKeys[$key] = true;
            }
        }

        foreach (self::defaults() as $def) {
            if (!isset($seenKeys[$def['key']])) {
                $fields[] = [
                    'key' => $def['key'],
                    'label' => trim((string) ($labels[$def['key']] ?? '')) ?: $def['label'],
                    'visible' => isset($visibleMap[$def['key']]),
                ];
            }
        }

        $dir = dirname(self::path());
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents(self::path(), json_encode($fields, JSON_PRETTY_PRINT));
    }

    private static function path(): string
    {
        return __DIR__ . '/../../storage/project_fields.json';
    }
}
