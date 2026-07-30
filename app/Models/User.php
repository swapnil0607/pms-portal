<?php

namespace App\Models;

use App\Core\Database;

class User
{
    public static function all(): array
    {
        return Database::connection()
            ->query('SELECT id, name, email, role, designation, department, status, created_at FROM users ORDER BY name')
            ->fetchAll();
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
            'INSERT INTO users (name, email, password_hash, role, designation, department, status)
             VALUES (:name, :email, :password_hash, :role, :designation, :department, :status)'
        );
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }
}
