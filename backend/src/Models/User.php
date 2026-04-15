<?php

declare(strict_types=1);

namespace Quiz\Models;

use PDO;
use Quiz\Database\Connection;

class User
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email, password FROM users WHERE email = :email'
        );
        $stmt->execute([':email' => $email]);

        return $stmt->fetch();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email FROM users WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        return $stmt->fetch();
    }
}
