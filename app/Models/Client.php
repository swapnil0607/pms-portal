<?php

namespace App\Models;

use App\Core\Database;

class Client
{
    public static function all(): array
    {
        $sql = "SELECT c.*, COUNT(p.id) AS project_count
                FROM clients c
                LEFT JOIN projects p ON p.client_id = c.id
                GROUP BY c.id
                ORDER BY c.name";

        return Database::connection()->query($sql)->fetchAll();
    }

    public static function allActive(): array
    {
        return Database::connection()
            ->query("SELECT * FROM clients WHERE status = 'active' ORDER BY name")
            ->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM clients WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO clients (name, notes, status) VALUES (:name, :notes, :status)'
        );
        $stmt->execute($data);
        return (int) Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE clients SET name = :name, notes = :notes, status = :status WHERE id = :id'
        );
        $data['id'] = $id;
        $stmt->execute($data);
    }
}
