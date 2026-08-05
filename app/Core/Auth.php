<?php

namespace App\Core;

use App\Models\User;

class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if (!$user || $user['status'] !== 'active') {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        self::loginAs($user);
        return true;
    }

    /**
     * POST-based SSO from the Central Management System: the master app
     * never links here, it auto-submits a hidden form with just `email`
     * (no password). Trusts that shape alone - see the '/login' handler in
     * public/index.php for the email-present/password-blank check that
     * routes here instead of the normal password path.
     */
    public static function ssoLogin(string $email): bool
    {
        $user = $email !== '' ? User::findByEmail($email) : null;
        if (!$user || $user['status'] !== 'active') {
            return false;
        }

        self::loginAs($user);
        return true;
    }

    /**
     * Establishes a session for an already-verified user record - shared by
     * both attempt() (password-checked) and ssoLogin() above.
     */
    private static function loginAs(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar_path'] ?? null;
        $_SESSION['user_pages'] = User::permissionsFor($user);
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return User::find((int) $_SESSION['user_id']);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            redirect('/login');
        }
    }
}
