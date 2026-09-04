<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

class AuditService
{
    /**
     * Ensures the audit_logs table exists in the database.
     */
    public static function ensureTableExists(): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        try {
            $db = Database::connection();
            $db->exec("
                CREATE TABLE IF NOT EXISTS audit_logs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NULL,
                    entity_type VARCHAR(50) NOT NULL,
                    entity_id INT UNSIGNED NOT NULL,
                    action VARCHAR(30) NOT NULL,
                    summary TEXT NULL,
                    old_values LONGTEXT NULL,
                    new_values LONGTEXT NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent VARCHAR(255) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_audit_entity (entity_type, entity_id),
                    INDEX idx_audit_user (user_id),
                    INDEX idx_audit_created_at (created_at),
                    INDEX idx_audit_action (action)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            $ensured = true;
        } catch (Throwable $e) {
            error_log('Failed to ensure audit_logs table exists: ' . $e->getMessage());
        }
    }

    /**
     * Record an audit log entry.
     *
     * @param string $entityType e.g., 'work_log', 'task', 'project', 'client_batch'
     * @param int $entityId ID of the entity
     * @param string $action e.g., 'created', 'updated', 'deleted', 'billed', 'unbilled', 'moved'
     * @param array|null $oldValues Snapshot of fields before change
     * @param array|null $newValues Snapshot of fields after change
     * @param string|null $summary Human-readable summary of the action
     * @param int|null $userId User ID performing the action (defaults to session user)
     */
    public static function log(
        string $entityType,
        int $entityId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $summary = null,
        ?int $userId = null
    ): void {
        self::ensureTableExists();

        if ($entityId <= 0) {
            return;
        }

        $userId ??= (int) ($_SESSION['user_id'] ?? 0);
        $userId = $userId > 0 ? $userId : null;

        $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ipAddress && str_contains($ipAddress, ',')) {
            $ipAddress = trim(explode(',', $ipAddress)[0]);
        }
        $ipAddress = $ipAddress ? substr($ipAddress, 0, 45) : null;

        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $userAgent = $userAgent ? substr($userAgent, 0, 255) : null;

        // Auto-generate summary if none provided
        if (empty($summary)) {
            $summary = self::generateSummary($entityType, $entityId, $action, $oldValues, $newValues);
        }

        try {
            $db = Database::connection();
            $stmt = $db->prepare('
                INSERT INTO audit_logs 
                (user_id, entity_type, entity_id, action, summary, old_values, new_values, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([
                $userId,
                $entityType,
                $entityId,
                $action,
                $summary,
                $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null,
                $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null,
                $ipAddress,
                $userAgent,
            ]);
        } catch (Throwable $e) {
            error_log('Failed to write audit log: ' . $e->getMessage());
        }
    }

    /**
     * Generates a clear human-readable summary of changes between old and new values.
     */
    public static function generateSummary(
        string $entityType,
        int $entityId,
        string $action,
        ?array $oldValues,
        ?array $newValues
    ): string {
        $entityLabel = ucfirst(str_replace('_', ' ', $entityType));

        if ($action === 'created') {
            $name = $newValues['notes'] ?? $newValues['title'] ?? $newValues['name'] ?? "#{$entityId}";
            $hours = isset($newValues['hours']) ? " ({$newValues['hours']}h)" : '';
            return "Created {$entityLabel} #{$entityId}: {$name}{$hours}";
        }

        if ($action === 'deleted') {
            $name = $oldValues['notes'] ?? $oldValues['title'] ?? $oldValues['name'] ?? "#{$entityId}";
            $hours = isset($oldValues['hours']) ? " ({$oldValues['hours']}h)" : '';
            return "Deleted {$entityLabel} #{$entityId}: {$name}{$hours}";
        }

        if ($action === 'updated' && $oldValues && $newValues) {
            $diffs = [];
            foreach ($newValues as $key => $newVal) {
                if (!array_key_exists($key, $oldValues)) {
                    continue;
                }
                $oldVal = $oldValues[$key];
                if ((string) $oldVal !== (string) $newVal) {
                    $fieldLabel = ucwords(str_replace('_', ' ', $key));
                    $oldDisplay = is_null($oldVal) ? 'None' : (string) $oldVal;
                    $newDisplay = is_null($newVal) ? 'None' : (string) $newVal;
                    if ($key === 'hours') {
                        $diffs[] = "Hours changed from {$oldDisplay} to {$newDisplay}";
                    } else {
                        $diffs[] = "{$fieldLabel}: '{$oldDisplay}' ➔ '{$newDisplay}'";
                    }
                }
            }
            if ($diffs) {
                return "Updated {$entityLabel} #{$entityId} (" . implode(', ', $diffs) . ')';
            }
            return "Updated {$entityLabel} #{$entityId}";
        }

        return ucfirst($action) . " {$entityLabel} #{$entityId}";
    }

    /**
     * Query audit logs with rich filters and pagination.
     */
    public static function query(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        self::ensureTableExists();

        $where = [];
        $params = [];

        if (!empty($filters['from_date'])) {
            $where[] = 'al.created_at >= ?';
            $params[] = $filters['from_date'] . ' 00:00:00';
        }

        if (!empty($filters['to_date'])) {
            $where[] = 'al.created_at <= ?';
            $params[] = $filters['to_date'] . ' 23:59:59';
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['entity_type'])) {
            $where[] = 'al.entity_type = ?';
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'al.action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['q'])) {
            $term = '%' . trim((string) $filters['q']) . '%';
            $where[] = '(al.summary LIKE ? OR u.name LIKE ? OR al.entity_id LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql = '
            SELECT al.*, u.name AS user_name, u.email AS user_email
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
        ';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY al.created_at DESC, al.id DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            error_log('Audit query error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Total count of audit logs matching filters.
     */
    public static function count(array $filters = []): int
    {
        self::ensureTableExists();

        $where = [];
        $params = [];

        if (!empty($filters['from_date'])) {
            $where[] = 'al.created_at >= ?';
            $params[] = $filters['from_date'] . ' 00:00:00';
        }

        if (!empty($filters['to_date'])) {
            $where[] = 'al.created_at <= ?';
            $params[] = $filters['to_date'] . ' 23:59:59';
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'al.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }

        if (!empty($filters['entity_type'])) {
            $where[] = 'al.entity_type = ?';
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['action'])) {
            $where[] = 'al.action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['q'])) {
            $term = '%' . trim((string) $filters['q']) . '%';
            $where[] = '(al.summary LIKE ? OR u.name LIKE ? OR al.entity_id LIKE ?)';
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $sql = '
            SELECT COUNT(*)
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id
        ';

        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Get change history for a specific entity (e.g. for modal popup).
     */
    public static function forEntity(string $entityType, int $entityId, int $limit = 25): array
    {
        self::ensureTableExists();

        try {
            $stmt = Database::connection()->prepare('
                SELECT al.*, u.name AS user_name, u.email AS user_email
                FROM audit_logs al
                LEFT JOIN users u ON u.id = al.user_id
                WHERE al.entity_type = ? AND al.entity_id = ?
                ORDER BY al.created_at DESC, al.id DESC
                LIMIT ' . (int) $limit
            );
            $stmt->execute([$entityType, $entityId]);
            return $stmt->fetchAll();
        } catch (Throwable $e) {
            return [];
        }
    }
}
