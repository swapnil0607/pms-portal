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

    public static function isViewer(): bool
    {
        return self::role() === 'viewer';
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
        if (self::isViewer()) {
            self::deny();
        }
    }

    /** A work log can be edited/deleted by whoever created it, or by a manager/admin. */
    public static function canEditWorkLog(array $log): bool
    {
        if (self::isViewer()) {
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
