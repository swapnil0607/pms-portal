<?php

namespace App\Core;

/**
 * Role-based access control. Two global roles ('admin', 'manager') can manage
 * project/client setup, reports, and (admin-only) users/settings. 'member' can
 * do day-to-day work (tasks, time logs) but not project setup. 'viewer' is
 * read-only everywhere.
 */
class Permissions
{
    public const MANAGER_ROLES = ['admin', 'manager'];
    public const PROJECT_ACTIONS_PERMISSION = 'project_actions';

    public const RIGHT_READ = 'read';
    public const RIGHT_WRITE = 'write';
    public const RIGHT_EXPORT = 'export';

    public const RIGHTS = [
        self::RIGHT_READ => 'Read (View Data)',
        self::RIGHT_WRITE => 'Write (Create & Edit)',
        self::RIGHT_EXPORT => 'Export (Download Reports)',
    ];

    /** The top-level sections a user's Page Access checkboxes can grant/revoke. */
    public const PAGES = [
        'projects' => 'Projects',
        'clients' => 'Clients',
        'client_renewals' => 'Client Renewals',
        'kanban' => 'Kanban',
        'work_logs' => 'Daily Log',
        'time_logs' => 'Time Logs',
        'reports' => 'Reports',
        'users' => 'Users',
        'settings' => 'Settings',
    ];

    /** Starting point offered when creating a user, and the fallback for any user without an explicit permissions row. */
    public static function defaultPagesForRole(string $role): array
    {
        return match ($role) {
            'admin' => array_keys(self::PAGES),
            'manager' => ['projects', 'clients', 'client_renewals', 'kanban', 'work_logs', 'time_logs', 'reports'],
            default => ['kanban', 'work_logs', 'time_logs'],
        };
    }

    public static function defaultRightsForRole(string $role): array
    {
        return match ($role) {
            'viewer' => [self::RIGHT_READ, self::RIGHT_EXPORT],
            default => [self::RIGHT_READ, self::RIGHT_WRITE, self::RIGHT_EXPORT],
        };
    }

    public static function pages(): array
    {
        return $_SESSION['user_pages'] ?? [];
    }

    public static function canAccessPage(string $page): bool
    {
        if (self::isAdmin()) {
            return true;
        }

        $pages = self::pages();

        if (in_array($page, $pages, true)) {
            return true;
        }

        // Backward compatibility for newly introduced pages:
        // Users or managers with 'clients' access automatically get 'client_renewals'
        if ($page === 'client_renewals' && (self::isManager() || in_array('clients', $pages, true))) {
            return true;
        }

        return false;
    }

    public static function requirePage(string $page): void
    {
        if (!self::canAccessPage($page)) {
            self::deny();
        }
    }

    public static function role(): string
    {
        return $_SESSION['user_role'] ?? 'member';
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isManager(): bool
    {
        return in_array(self::role(), self::MANAGER_ROLES, true);
    }

    /** Admins and managers retain their existing access; other users need the explicit checkbox grant. */
    public static function canManageProjectActions(): bool
    {
        return self::isManager() || (in_array(self::PROJECT_ACTIONS_PERMISSION, self::pages(), true) && self::canWrite());
    }

    public static function userCanManageProjectActions(array $user): bool
    {
        if (in_array($user['role'] ?? '', self::MANAGER_ROLES, true)) {
            return true;
        }

        return in_array(self::PROJECT_ACTIONS_PERMISSION, \App\Models\User::permissionsFor($user), true);
    }

    public static function requireProjectActions(): void
    {
        if (!self::canManageProjectActions()) {
            self::deny();
        }
    }

    public static function isViewer(): bool
    {
        return self::role() === 'viewer';
    }

    public static function canRead(): bool
    {
        return true;
    }

    public static function canWrite(): bool
    {
        if (self::isAdmin() || self::isManager()) {
            return true;
        }
        if (self::isViewer()) {
            return false;
        }

        $pages = self::pages();
        // If 'write' right is present, or if legacy permissions don't have explicit rights yet
        if (in_array(self::RIGHT_WRITE, $pages, true)) {
            return true;
        }

        // If rights (read/write/export) were never explicitly saved, default to true for non-viewers
        $hasAnyExplicitRight = in_array(self::RIGHT_READ, $pages, true) || in_array(self::RIGHT_EXPORT, $pages, true);
        return !$hasAnyExplicitRight;
    }

    public static function canExport(): bool
    {
        if (self::isAdmin() || self::isManager()) {
            return true;
        }

        $pages = self::pages();
        if (in_array(self::RIGHT_EXPORT, $pages, true)) {
            return true;
        }

        $hasAnyExplicitRight = in_array(self::RIGHT_READ, $pages, true) || in_array(self::RIGHT_WRITE, $pages, true);
        return !$hasAnyExplicitRight;
    }

    public static function userRights(array $user): array
    {
        $perms = \App\Models\User::permissionsFor($user);
        $role = $user['role'] ?? 'member';

        $hasExplicit = in_array(self::RIGHT_READ, $perms, true)
            || in_array(self::RIGHT_WRITE, $perms, true)
            || in_array(self::RIGHT_EXPORT, $perms, true);

        if (!$hasExplicit) {
            return self::defaultRightsForRole($role);
        }

        $rights = [];
        if (in_array(self::RIGHT_READ, $perms, true)) $rights[] = self::RIGHT_READ;
        if (in_array(self::RIGHT_WRITE, $perms, true)) $rights[] = self::RIGHT_WRITE;
        if (in_array(self::RIGHT_EXPORT, $perms, true)) $rights[] = self::RIGHT_EXPORT;
        return $rights;
    }

    public static function userCanWrite(array $user): bool
    {
        if (in_array($user['role'] ?? '', self::MANAGER_ROLES, true)) {
            return true;
        }
        if (($user['role'] ?? '') === 'viewer') {
            return false;
        }
        return in_array(self::RIGHT_WRITE, self::userRights($user), true);
    }

    public static function userCanExport(array $user): bool
    {
        if (in_array($user['role'] ?? '', self::MANAGER_ROLES, true)) {
            return true;
        }
        return in_array(self::RIGHT_EXPORT, self::userRights($user), true);
    }

    public static function userCanRead(array $user): bool
    {
        return in_array(self::RIGHT_READ, self::userRights($user), true);
    }

    public static function allows(array $allowedRoles): bool
    {
        return in_array(self::role(), $allowedRoles, true);
    }

    public static function require(array $allowedRoles): void
    {
        if (!self::allows($allowedRoles)) {
            self::deny();
        }
    }

    public static function requireWriteAccess(): void
    {
        if (!self::canWrite()) {
            self::deny();
        }
    }

    public static function requireExportAccess(): void
    {
        if (!self::canExport()) {
            self::deny();
        }
    }

    /** A work log can be edited/deleted by whoever created it, or by a manager/admin. */
    public static function canEditWorkLog(array $log): bool
    {
        if (!self::canWrite()) {
            return false;
        }

        return self::isManager() || (int) $log['user_id'] === (int) ($_SESSION['user_id'] ?? 0);
    }

    public static function requireEditWorkLog(array $log): void
    {
        if (!self::canEditWorkLog($log)) {
            self::deny();
        }
    }

    private static function deny(): never
    {
        http_response_code(403);
        View::render('403', ['title' => 'Access Denied']);
        exit;
    }
}
