<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Permissions;

class User
{
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT id, name, email, role, permissions, designation, department, status, created_at FROM users ORDER BY name')
            ->fetchAll();
    }

    /** The page keys this user can access: their own saved set, or their role's default if never explicitly set. */
    public static function permissionsFor(array $user): array
    {
        $decoded = json_decode((string) ($user['permissions'] ?? ''), true);
        if (is_array($decoded)) {
            return $decoded;
        }
        $role = $user['role'] ?? 'member';
        return array_values(array_unique(array_merge(
            Permissions::defaultPagesForRole($role),
            Permissions::defaultRightsForRole($role)
        )));
    }

    public static function allActive(): array
    {
        return Database::connection()
            ->query("SELECT id, name, email, role, designation FROM users WHERE status = 'active' ORDER BY name")
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO users (name, email, password_hash, role, permissions, designation, department, status)
             VALUES (:name, :email, :password_hash, :role, :permissions, :designation, :department, :status)'
        );
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    /** Full field update — Admin-only path (see updateAccess() for the Manager-restricted one). */
    public static function update(int $id, array $data): void
    {
        $sql = 'UPDATE users
                SET name = :name,
                    email = :email,
                    role = :role,
                    permissions = :permissions,
                    designation = :designation,
                    department = :department,
                    status = :status';
        if (!empty($data['password_hash'])) {
            $sql .= ', password_hash = :password_hash';
        } else {
            unset($data['password_hash']);
        }
        $sql .= ' WHERE id = :id';

        $data['id'] = $id;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($data);
    }

    /** Manager-restricted update: page access + active/inactive status only. */
    public static function updateAccess(int $id, array $pages, string $status): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET permissions = ?, status = ? WHERE id = ?');
        $stmt->execute([json_encode(array_values($pages)), $status, $id]);
    }

    public static function updatePermissions(int $id, array $permissions): void
    {
        $stmt = Database::connection()->prepare('UPDATE users SET permissions = ? WHERE id = ?');
        $stmt->execute([json_encode(array_values(array_unique($permissions))), $id]);
    }

    /** Self-service profile update: name, designation, department, avatar, and (optionally) password. Never touches email/role/permissions/status. */
    public static function updateSelfProfile(int $id, array $data): void
    {
        $sql = 'UPDATE users SET name = :name, designation = :designation, department = :department';
        if (array_key_exists('avatar_path', $data)) {
            $sql .= ', avatar_path = :avatar_path';
        } else {
            unset($data['avatar_path']);
        }
        if (!empty($data['password_hash'])) {
            $sql .= ', password_hash = :password_hash';
        } else {
            unset($data['password_hash']);
        }
        $sql .= ' WHERE id = :id';

        $data['id'] = $id;
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($data);
    }

    /**
     * Safely and permanently delete a user.
     * Automatically reassigns any existing projects, tasks, client batches, and logs
     * to the admin performing the deletion (or specified replacement admin)
     * to satisfy all MySQL foreign key constraints without error.
     */
    public static function delete(int $id, ?int $reassignToId = null): void
    {
        $db = Database::connection();

        // If no replacement user specified, find another active admin
        if (!$reassignToId) {
            $stmt = $db->prepare("SELECT id FROM users WHERE id <> ? AND role = 'admin' AND status = 'active' ORDER BY id LIMIT 1");
            $stmt->execute([$id]);
            $reassignToId = (int) $stmt->fetchColumn();
        }

        // If still no admin, find any other user
        if (!$reassignToId) {
            $stmt = $db->prepare("SELECT id FROM users WHERE id <> ? ORDER BY id LIMIT 1");
            $stmt->execute([$id]);
            $reassignToId = (int) $stmt->fetchColumn();
        }

        $db->beginTransaction();
        try {
            if ($reassignToId) {
                // 1. Reassign projects where this user is the owner
                $db->prepare('UPDATE projects SET owner_id = ? WHERE owner_id = ?')->execute([$reassignToId, $id]);

                // 2. Reassign tasks created by this user
                $db->prepare('UPDATE tasks SET created_by = ? WHERE created_by = ?')->execute([$reassignToId, $id]);

                // 3. Unassign or reassign assigned tasks
                $db->prepare('UPDATE tasks SET assigned_to = NULL WHERE assigned_to = ?')->execute([$id]);

                // 4. Reassign client batches created by this user
                $db->prepare('UPDATE client_batches SET created_by = ? WHERE created_by = ?')->execute([$reassignToId, $id]);

                // 5. Reassign client batch history entries
                $db->prepare('UPDATE client_batch_history SET created_by = ? WHERE created_by = ?')->execute([$reassignToId, $id]);

                // 6. Reassign task comments
                $db->prepare('UPDATE task_comments SET user_id = ? WHERE user_id = ?')->execute([$reassignToId, $id]);

                // 7. Reassign task attachments
                $db->prepare('UPDATE task_attachments SET user_id = ? WHERE user_id = ?')->execute([$reassignToId, $id]);

                // 8. Reassign task list templates
                $db->prepare('UPDATE task_list_templates SET created_by = ? WHERE created_by = ?')->execute([$reassignToId, $id]);

                // 9. Reassign work logs
                $db->prepare('UPDATE work_logs SET user_id = ? WHERE user_id = ?')->execute([$reassignToId, $id]);
            }

            // 10. Delete project memberships
            $db->prepare('DELETE FROM project_members WHERE user_id = ?')->execute([$id]);

            // 11. Delete task assignees
            try {
                $db->prepare('DELETE FROM task_assignees WHERE user_id = ?')->execute([$id]);
            } catch (\Throwable $e) {
                // Table may not exist in all environments
            }

            // 12. Delete notifications
            try {
                $db->prepare('DELETE FROM notifications WHERE user_id = ?')->execute([$id]);
            } catch (\Throwable $e) {
                // Table may not exist
            }

            // 13. Finally delete the user row
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
