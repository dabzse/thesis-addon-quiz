<?php

declare(strict_types=1);

namespace Quiz\Models;

use PDO;
use Quiz\Database\Connection;

class Settings
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function getAll(): array
    {
        $stmt = $this->db->query('SELECT `key`, `value` FROM settings');
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }

        return $result;
    }

    public function get(string $key): string|false
    {
        $stmt = $this->db->prepare('SELECT `value` FROM settings WHERE `key` = :key');
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();

        return $row ? $row['value'] : false;
    }

    public function set(string $key, string $value): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO settings (`key`, `value`) VALUES (:key, :value)
            ON DUPLICATE KEY UPDATE `value` = :value2'
        );
        $stmt->execute([
            ':key'    => $key,
            ':value'  => $value,
            ':value2' => $value,
        ]);
    }
}
