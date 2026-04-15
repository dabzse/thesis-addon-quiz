<?php

declare(strict_types=1);

namespace Quiz\Models;

use PDO;
use Quiz\Database\Connection;

class Category
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, slug FROM categories ORDER BY name ASC'
        );

        return $stmt->fetchAll();
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, slug FROM categories WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }

    public function getBySlug(string $slug): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, slug FROM categories WHERE slug = :slug'
        );
        $stmt->execute([':slug' => $slug]);

        return $stmt->fetch();
    }

    public function create(string $name, string $slug): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO categories (name, slug) VALUES (:name, :slug)'
        );
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $name, string $slug): void
    {
        $stmt = $this->db->prepare(
            'UPDATE categories SET name = :name, slug = :slug WHERE id = :id'
        );
        $stmt->execute([
            ':id'   => $id,
            ':name' => $name,
            ':slug' => $slug,
        ]);
    }
}

/**
 * getAll() — összes kategória listázása
 * getById() — egy kategória ID alapján
 * getBySlug() — egy kategória slug alapján (URL-barát, pl. /api/questions/php-alapok)
 */
