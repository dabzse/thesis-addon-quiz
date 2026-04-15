<?php

declare(strict_types=1);

namespace Quiz\Models;

use PDO;
use Quiz\Database\Connection;

class Entry
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function create(
        string $ticketNumber,
        int $score,
        int $maxScore,
        int $eventYear,
        ?int $categoryId = null,
        ?string $email = null,
        ?string $name = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO entries (ticket_number, name, email, score, max_score, category_id, event_year)
            VALUES (:ticket_number, :name, :email, :score, :max_score, :category_id, :event_year)'
        );

        $stmt->execute([
            ':ticket_number' => $ticketNumber,
            ':name'          => $name,
            ':email'         => $email,
            ':score'         => $score,
            ':max_score'     => $maxScore,
            ':category_id'   => $categoryId,
            ':event_year'    => $eventYear,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getAll(int $year = 0): array
    {
        $year = $year ?: (int) date('Y');

        $stmt = $this->db->prepare(
            'SELECT e.id, e.ticket_number, e.name, e.email, e.score, e.max_score, e.event_year, e.created_at,
            c.name as category
            FROM entries e
            LEFT JOIN categories c ON c.id = e.category_id
            WHERE e.event_year = :year
            ORDER BY e.score DESC, e.created_at ASC'
        );
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
