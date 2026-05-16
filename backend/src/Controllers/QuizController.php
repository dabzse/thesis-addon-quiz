<?php

declare(strict_types=1);

namespace Quiz\Controllers;

use PDO;

use Quiz\Database\Connection;
use Quiz\Models\Category;
use Quiz\Models\Entry;
use Quiz\Models\Question;
use Quiz\Models\Settings;
use Quiz\Services\QuizEvaluator;
use Quiz\Services\QuizResultMailer;

class QuizController extends BaseController
{
    private const ERROR_MISSING_DATA = 'Hiányzó adatok.';

    private Category $category;
    private Question $question;
    private QuizResultMailer $resultMailer;
    private QuizEvaluator $evaluator;
    private PDO $db;

    public function __construct()
    {
        $this->category = new Category();
        $this->question = new Question();
        $this->resultMailer = new QuizResultMailer();
        $this->evaluator = new QuizEvaluator();
        $this->db = Connection::getInstance();
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
        $settings = new Settings();
        $isPublic = $settings->get('show_correct_during') === '1' ? false : true;

        $questions = $this->question->getByCategory((int) $category['id'], $limit, $year, $isPublic);
        $this->respond([
            'category'  => $category,
            'questions' => $questions,
        ]);
    }

    public function getRandomQuestions(int $limit = 10): void
    {
        $year = $this->getActiveYear();
        $settings = new Settings();
        $isPublic = $settings->get('show_correct_during') === '1' ? false : true;

        $questions = $this->question->getRandom($limit, $year, $isPublic);
        $this->respond(['questions' => $questions]);
    }

    public function getQuestion(int $id): void
    {
        $authController = new AuthController();
        $isAdmin = $authController->validateToken();

        $question = $this->question->getById($id, !$isAdmin);

        if ($question === false) {
            $this->respondNotFound('Question not found');
            return;
        }

        $this->respond($question);
    }

    public function checkTicket(): void
    {
        $ticket = $_GET['ticket'] ?? '';
        $year = (int) ($_GET['year'] ?? 0);

        if ((string)$ticket === '' || empty($year)) {
            $this->respond(['error' => 'Hiányzó adatok (ticket vagy year).'], 400);
            return;
        }

        $entry = new Entry();
        $this->respond(['used' => $entry->hasEntry((string)$ticket, $year)]);
    }

    public function submitEntry(): void
    {
        $data = $this->getJsonInput();

        if (empty($data)) {
            $this->respond(['error' => 'Érvénytelen JSON'], 400);
            return;
        }

        if (!isset($data['ticket_number']) || trim((string)$data['ticket_number']) === '' || !isset($data['event_year'], $data['questions'])) {
            $this->respond(['error' => self::ERROR_MISSING_DATA], 400);
            return;
        }

        $entry = new Entry();

        if ($entry->hasEntry($data['ticket_number'], (int) $data['event_year'])) {
            $this->respond(['error' => 'Már kitöltötted a kvízt ezzel a jeggyel!'], 409);
            return;
        }

        $categoryId = null;
        if (!empty($data['category_slug'])) {
            $category = $this->category->getBySlug($data['category_slug']);
            if ($category !== false) {
                $categoryId = (int) $category['id'];
            }
        }

        $evaluated = $this->evaluateSubmission($data['questions']);
        $score = $evaluated['score'];
        $maxScore = $evaluated['maxScore'];
        $evaluatedQuestions = $evaluated['evaluatedQuestions'];

        $id = $entry->create(
            ticketNumber: $data['ticket_number'],
            score:        $score,
            maxScore:     $maxScore,
            eventYear:    (int) $data['event_year'],
            categoryId:   $categoryId,
            email:        $data['email'] ?? null,
            name:         $data['name'] ?? null,
        );

        if (!empty($data['email']) && !empty($evaluatedQuestions)) {
            $this->resultMailer->sendResultEmail(
                email: $data['email'],
                name: $data['name'] ?? null,
                score: $score,
                maxScore: $maxScore,
                questions: $evaluatedQuestions,
            );
        }

        $this->respond([
            'success' => true,
            'id' => $id,
            'score' => $score,
            'max_score' => $maxScore,
            'questions' => $evaluatedQuestions
        ]);
    }

    private function evaluateSubmission(array $questions): array
    {
        $score = 0;
        $maxScore = count($questions);
        $evaluatedQuestions = [];

        foreach ($questions as $qData) {
            $qId = (int) ($qData['id'] ?? 0);
            if (!$qId) {
                continue;
            }

            $trueQuestion = $this->question->getById($qId, false);
            if (!$trueQuestion) {
                continue;
            }

            $isCorrect = $this->evaluator->evaluateQuestion($trueQuestion, $qData);

            if ($isCorrect) {
                $score++;
            }

            $evaluatedQuestions[] = array_merge([
                'id' => $qId,
                'question' => $trueQuestion['question'],
                'type' => $trueQuestion['type'],
                'answers' => $trueQuestion['answers'],
                'isCorrect' => $isCorrect
            ], $this->getUserResponseData($qData));
        }

        return [
            'score' => $score,
            'maxScore' => $maxScore,
            'evaluatedQuestions' => $evaluatedQuestions
        ];
    }

    private function getUserResponseData(array $userData): array
    {
        return [
            'selected' => $userData['selected'] ?? [],
            'userOrder' => $userData['userOrder'] ?? [],
            'userMatches' => $userData['userMatches'] ?? []
        ];
    }

    public function sendEmailOnly(): void
    {
        $data = $this->getJsonInput();

        if (empty($data['email']) || empty($data['questions'])) {
            $this->respond(['error' => 'Hiányzó adatok (email vagy kérdések).'], 400);
            return;
        }

        if (!empty($data['entry_id'])) {
            $entry = new Entry();
            $entry->updateUser(
                id: (int) $data['entry_id'],
                email: $data['email'],
                name: $data['name'] ?? null
            );
        }

        $this->resultMailer->sendResultEmail(
            email: $data['email'],
            name: $data['name'] ?? null,
            score: (int)($data['score'] ?? 0),
            maxScore: (int)($data['maxScore'] ?? 0),
            questions: $data['questions']
        );

        $this->respond(['success' => true]);
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
        $data = $this->getJsonInput();

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
        $data = $this->getJsonInput();

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
        $data = $this->getJsonInput();

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
        $data = $this->getJsonInput();

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
        $entry = new Entry();
        $year = (int) ($_GET['year'] ?? 0);
        $this->respond($entry->getAll($year));
    }
}
