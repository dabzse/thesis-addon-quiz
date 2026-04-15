<?php

declare(strict_types=1);

namespace Quiz\Models;

use PDO;
use Quiz\Database\Connection;

class Question
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function getByCategory(int $categoryId, int $limit = 10, int $year = 0): array
    {
        $year = $year ?: (int) date('Y');

        $stmt = $this->db->prepare(
            'SELECT q.id, q.question, qt.name as type
            FROM questions q
            JOIN question_types qt ON qt.id = q.type_id
            WHERE q.category_id = :category_id
            AND q.event_year = :year
            ORDER BY RAND()
            LIMIT :limit'
        );
        $stmt->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $questions = $stmt->fetchAll();

        foreach ($questions as &$question) {
            $question['answers'] = $this->getAnswers((int) $question['id']);
        }

        return $questions;
    }

    public function getById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT q.id, q.category_id, q.question, q.event_year, qt.name as type
            FROM questions q
            JOIN question_types qt ON qt.id = q.type_id
            WHERE q.id = :id'
        );
        $stmt->execute([':id' => $id]);

        $question = $stmt->fetch();

        if ($question !== false) {
            $question['answers'] = $this->getAnswers((int) $question['id']);
        }

        return $question;
    }

    public function getRandom(int $limit = 10, int $year = 0): array
    {
        $year = $year ?: (int) date('Y');

        $stmt = $this->db->prepare(
            'SELECT q.id, q.question, qt.name as type, c.name as category
            FROM questions q
            JOIN question_types qt ON qt.id = q.type_id
            JOIN categories c ON c.id = q.category_id
            WHERE q.event_year = :year
            ORDER BY RAND()
            LIMIT :limit'
        );
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $questions = $stmt->fetchAll();

        foreach ($questions as &$question) {
            $question['answers'] = $this->getAnswers((int) $question['id']);
        }

        return $questions;
    }

    private function getAnswers(int $questionId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, answer, is_correct, correct_position, match_answer
            FROM answers
            WHERE question_id = :question_id
            ORDER BY RAND()'
        );
        $stmt->execute([':question_id' => $questionId]);

        return $stmt->fetchAll();
    }

    /**
     * getByCategory() — kategória alapján, véletlenszerű sorrendben
     * getById() — egy kérdés az összes válaszával
     * getRandom() — vegyes, kategória nélküli kvíz
     *
    * A getAnswers() private — csak belülről hívható, a válaszok is
    * véletlenszerű sorrendben jönnek vissza, hogy ne legyen mindig az első a helyes.
    */


    public function getAllWithCategory(int $year = 0): array
    {
        $year = $year ?: (int) date('Y');

        $stmt = $this->db->prepare(
            'SELECT q.id, q.category_id, q.question, q.event_year, qt.name as type, c.name as category
            FROM questions q
            JOIN question_types qt ON qt.id = q.type_id
            JOIN categories c ON c.id = q.category_id
            WHERE q.event_year = :year
            ORDER BY c.name ASC, q.id ASC'
        );
        $stmt->bindValue(':year', $year, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM questions WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function create(int $categoryId, string $question, int $typeId, array $answers, int $year = 0): int
    {
        $year = $year ?: (int) date('Y');

        $stmt = $this->db->prepare(
            'INSERT INTO questions (category_id, event_year, question, type_id)
            VALUES (:category_id, :event_year, :question, :type_id)'
        );
        $stmt->execute([
            ':category_id' => $categoryId,
            ':event_year'  => $year,
            ':question'    => $question,
            ':type_id'     => $typeId,
        ]);

        $questionId = (int) $this->db->lastInsertId();

        $stmt = $this->db->prepare(
            'INSERT INTO answers (question_id, answer, is_correct, correct_position, match_answer)
            VALUES (:question_id, :answer, :is_correct, :correct_position, :match_answer)'
        );

        foreach ($answers as $answer) {
            $stmt->execute([
                ':question_id'      => $questionId,
                ':answer'           => $answer['text'],
                ':is_correct'       => $answer['is_correct'] ?? false ? 1 : 0,
                ':correct_position' => $answer['correct_position'] ?? null,
                ':match_answer'     => $answer['match_answer'] ?? null,
            ]);
        }

        return $questionId;
    }

    public function update(int $id, int $categoryId, string $question, int $typeId, array $answers): void
    {
        $stmt = $this->db->prepare(
            'UPDATE questions SET category_id = :category_id, question = :question, type_id = :type_id
            WHERE id = :id'
        );
        $stmt->execute([
            ':id'          => $id,
            ':category_id' => $categoryId,
            ':question'    => $question,
            ':type_id'     => $typeId,
        ]);

        $stmt = $this->db->prepare('DELETE FROM answers WHERE question_id = :question_id');
        $stmt->execute([':question_id' => $id]);

        $stmt = $this->db->prepare(
            'INSERT INTO answers (question_id, answer, is_correct, correct_position, match_answer)
            VALUES (:question_id, :answer, :is_correct, :correct_position, :match_answer)'
        );

        foreach ($answers as $answer) {
            $stmt->execute([
                ':question_id'      => $id,
                ':answer'           => $answer['text'],
                ':is_correct'       => $answer['is_correct'] ?? false ? 1 : 0,
                ':correct_position' => $answer['correct_position'] ?? null,
                ':match_answer'     => $answer['match_answer'] ?? null,
            ]);
        }
    }
}
