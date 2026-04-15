<?php

declare(strict_types=1);

namespace Quiz\Models;

use PDO;
use Quiz\Database\Connection;

class Session
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function create(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+8 hours'));

        $stmt = $this->db->prepare(
            'INSERT INTO sessions (user_id, token, expires_at)
            VALUES (:user_id, :token, :expires_at)'
        );
        $stmt->execute([
            ':user_id'    => $userId,
            ':token'      => $token,
            ':expires_at' => $expires,
        ]);

        return $token;
    }

    public function findByToken(string $token): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT s.token, s.expires_at, u.id, u.name, u.email
            FROM sessions s
            JOIN users u ON u.id = s.user_id
            WHERE s.token = :token
            AND s.expires_at > NOW()'
        );
        $stmt->execute([':token' => $token]);

        return $stmt->fetch();
    }

    public function delete(string $token): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM sessions WHERE token = :token'
        );
        $stmt->execute([':token' => $token]);
    }

    public function deleteExpired(): void
    {
        $this->db->exec('DELETE FROM sessions WHERE expires_at < NOW()');
    }
}
