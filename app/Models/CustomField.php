<?php

namespace App\Models;

use App\Core\Database;

class CustomField
{
    public const TYPES = ['text', 'number', 'date'];

    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT * FROM custom_field_definitions ORDER BY sort_order, id')
            ->fetchAll();
    }

    public static function visible(): array
    {
        return array_values(array_filter(self::all(), static fn (array $field): bool => (bool) $field['visible']));
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM custom_field_definitions WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByKey(string $key): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM custom_field_definitions WHERE field_key = ?');
        $stmt->execute([$key]);
        return $stmt->fetch() ?: null;
    }

    public static function create(string $label, string $type): int
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'text';
        $db = Database::connection();
        $key = self::uniqueKeyFor($label);

        $orderStmt = $db->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM custom_field_definitions');
        $sortOrder = (int) $orderStmt->fetchColumn();

        $stmt = $db->prepare(
            'INSERT INTO custom_field_definitions (field_key, label, field_type, visible, sort_order)
             VALUES (?, ?, ?, 1, ?)'
        );
        $stmt->execute([$key, $label, $type, $sortOrder]);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, string $label, string $type, bool $visible): void
    {
        $type = in_array($type, self::TYPES, true) ? $type : 'text';
        $stmt = Database::connection()->prepare(
            'UPDATE custom_field_definitions SET label = ?, field_type = ?, visible = ? WHERE id = ?'
        );
        $stmt->execute([$label, $type, $visible ? 1 : 0, $id]);
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM custom_field_definitions WHERE id = ?')->execute([$id]);
    }

    /** [field_key => value] for one project, across every defined field. */
    public static function valuesForProject(int $projectId): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT d.field_key, v.value
             FROM custom_field_definitions d
             LEFT JOIN project_custom_field_values v ON v.field_definition_id = d.id AND v.project_id = ?
             ORDER BY d.sort_order, d.id'
        );
        $stmt->execute([$projectId]);

        $values = [];
        foreach ($stmt->fetchAll() as $row) {
            $values[$row['field_key']] = $row['value'];
        }
        return $values;
    }

    /** [project_id => [field_key => value]] for many projects in one query. */
    public static function valuesForProjects(array $projectIds): array
    {
        $projectIds = array_values(array_filter(array_map('intval', $projectIds)));
        if (!$projectIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($projectIds), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT v.project_id, d.field_key, v.value
             FROM project_custom_field_values v
             JOIN custom_field_definitions d ON d.id = v.field_definition_id
             WHERE v.project_id IN ($placeholders)"
        );
        $stmt->execute($projectIds);

        $values = [];
        foreach ($stmt->fetchAll() as $row) {
            $values[(int) $row['project_id']][$row['field_key']] = $row['value'];
        }
        return $values;
    }

    /** Upserts every key in $values (field_key => value) that matches a known definition. */
    public static function saveValues(int $projectId, array $values): void
    {
        $definitions = array_column(self::all(), null, 'field_key');
        $stmt = Database::connection()->prepare(
            'INSERT INTO project_custom_field_values (project_id, field_definition_id, value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );

        foreach ($values as $key => $value) {
            if (!isset($definitions[$key])) {
                continue;
            }
            $value = trim((string) $value);
            $stmt->execute([$projectId, $definitions[$key]['id'], $value === '' ? null : $value]);
        }
    }

    /** Sets a single field's value, coercing per the field's declared type. Used by inline edit. */
    public static function quickUpdateValue(int $projectId, string $fieldKey, ?string $value): void
    {
        $definition = self::findByKey($fieldKey);
        if (!$definition || !in_array($definition['field_type'], ['number', 'date'], true)) {
            return;
        }

        $value = trim((string) $value);
        if ($definition['field_type'] === 'number' && $value !== '') {
            $value = (string) (float) $value;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO project_custom_field_values (project_id, field_definition_id, value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        $stmt->execute([$projectId, $definition['id'], $value === '' ? null : $value]);
    }

    private static function uniqueKeyFor(string $label): string
    {
        $base = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $label), '_'));
        $base = $base !== '' ? $base : 'field';

        $key = $base;
        $suffix = 2;
        while (self::findByKey($key) !== null) {
            $key = $base . '_' . $suffix;
            $suffix++;
        }

        return $key;
    }
}
