<?php

declare(strict_types=1);

namespace Quiz\Controllers;

use PDO;
use Quiz\Models\Category;
use Quiz\Models\Question;
use Quiz\Models\Settings;
use Quiz\Services\QuizResultMailer;

class QuizController
{
    private const INPUT_STREAM = 'php://input';
    private const ERROR_MISSING_DATA = 'Hiányzó adatok.';

    private Category $category;
    private Question $question;
    private QuizResultMailer $resultMailer;
    private PDO $db;

    public function __construct()
    {
        $this->category = new Category();
        $this->question = new Question();
        $this->resultMailer = new QuizResultMailer();
        $this->db = \Quiz\Database\Connection::getInstance();
    }

    private function getActiveYear(): int
    {
        $settings = new Settings();
        $year = (int) $settings->get('active_year');
        return $year ?: (int) date('Y');
    }

    public function getCategories(): void
    {
        $categories = $this->category->getAll();
        $this->respond($categories);
    }

    public function getCategory(int $id): void
    {
        $category = $this->category->getById($id);

        if ($category === false) {
            $this->respondNotFound('Category not found');
            return;
        }

        $this->respond($category);
    }

    public function getQuestionsBySlug(string $slug, int $limit = 10): void
    {
        $category = $this->category->getBySlug($slug);

        if ($category === false) {
            $this->respondNotFound('Category not found');
            return;
        }

        $year = $this->getActiveYear();
        $questions = $this->question->getByCategory((int) $category['id'], $limit, $year);
        $this->respond([
            'category'  => $category,
            'questions' => $questions,
        ]);
    }

    public function getRandomQuestions(int $limit = 10): void
    {
        $year = $this->getActiveYear();
        $questions = $this->question->getRandom($limit, $year);
        $this->respond(['questions' => $questions]);
    }

    public function getQuestion(int $id): void
    {
        $question = $this->question->getById($id);

        if ($question === false) {
            $this->respondNotFound('Question not found');
            return;
        }

        $this->respond($question);
    }

    private function respond(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }

    private function respondNotFound(string $message): void
    {
        $this->respond(['error' => $message], 404);
    }

    public function submitEntry(): void
    {
        $data = json_decode(file_get_contents(self::INPUT_STREAM), true);

        if (empty($data['ticket_number']) || !isset($data['score'], $data['max_score'], $data['event_year'])) {
            $this->respond(['error' => self::ERROR_MISSING_DATA], 400);
            return;
        }

        $categoryId = null;
        if (!empty($data['category_slug'])) {
            $category = $this->category->getBySlug($data['category_slug']);
            if ($category !== false) {
                $categoryId = (int) $category['id'];
            }
        }

        $entry = new \Quiz\Models\Entry();

        $id = $entry->create(
            ticketNumber: $data['ticket_number'],
            score:        (int) $data['score'],
            maxScore:     (int) $data['max_score'],
            eventYear:    (int) $data['event_year'],
            categoryId:   $categoryId,
            email:        $data['email'] ?? null,
            name:         $data['name'] ?? null,
        );

        if (!empty($data['email']) && !empty($data['questions'])) {
            $this->resultMailer->sendResultEmail(
                email: $data['email'],
                name: $data['name'] ?? null,
                score: (int) $data['score'],
                maxScore: (int) $data['max_score'],
                questions: $data['questions'],
            );
        }

        $this->respond(['success' => true, 'id' => $id]);
    }

    public function getQuestionsAdmin(): void
    {
        $year = (int) ($_GET['year'] ?? 0);
        $questions = $this->question->getAllWithCategory($year);
        $this->respond($questions);
    }

    public function deleteQuestion(int $id): void
    {
        $this->question->delete($id);
        $this->respond(['success' => true]);
    }

    public function getQuestionTypes(): void
    {
        $stmt = $this->db->query(
            'SELECT id, name, label FROM question_types ORDER BY id ASC'
        );
        $this->respond($stmt->fetchAll());
    }

    public function createQuestion(): void
    {
        $data = json_decode(file_get_contents(self::INPUT_STREAM), true);

        if (
            empty($data['category_id'])
            || empty($data['question'])
            || empty($data['type_id'])
            || empty($data['answers'])
        ) {
            $this->respond(['error' => self::ERROR_MISSING_DATA], 400);
            return;
        }

        $id = $this->question->create(
            categoryId: (int) $data['category_id'],
            question:   $data['question'],
            typeId:     (int) $data['type_id'],
            answers:    $data['answers'],
            year:       (int) ($data['event_year'] ?? 0),
        );

        $this->respond(['success' => true, 'id' => $id], 201);
    }

    public function updateQuestion(int $id): void
    {
        $data = json_decode(file_get_contents(self::INPUT_STREAM), true);

        if (
            empty($data['category_id'])
            || empty($data['question'])
            || empty($data['type_id'])
            || empty($data['answers'])
        ) {
            $this->respond(['error' => self::ERROR_MISSING_DATA], 400);
            return;
        }

        $this->question->update(
            id:         $id,
            categoryId: (int) $data['category_id'],
            question:   $data['question'],
            typeId:     (int) $data['type_id'],
            answers:    $data['answers'],
        );

        $this->respond(['success' => true]);
    }

    public function createCategory(): void
    {
        $data = json_decode(file_get_contents(self::INPUT_STREAM), true);

        if (empty($data['name']) || empty($data['slug'])) {
            $this->respond(['error' => self::ERROR_MISSING_DATA], 400);
            return;
        }

        $id = $this->category->create(
            name: $data['name'],
            slug: $data['slug'],
        );

        $this->respond(['success' => true, 'id' => $id], 201);
    }

    public function updateCategory(int $id): void
    {
        $data = json_decode(file_get_contents(self::INPUT_STREAM), true);

        if (empty($data['name']) || empty($data['slug'])) {
            $this->respond(['error' => self::ERROR_MISSING_DATA], 400);
            return;
        }

        $this->category->update(
            id:   $id,
            name: $data['name'],
            slug: $data['slug'],
        );

        $this->respond(['success' => true]);
    }

    public function getEntries(): void
    {
        $entry = new \Quiz\Models\Entry();
        $year = (int) ($_GET['year'] ?? 0);
        $this->respond($entry->getAll($year));
    }
}
