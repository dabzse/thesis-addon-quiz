<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Quiz\Controllers\AuthController;
use Quiz\Controllers\QuizController;
use Quiz\Controllers\SettingsController;

// .env betöltése
$env = parse_ini_file(__DIR__ . '/../.env');

foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
}

// Headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5173');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// OPTIONS preflight kezelése
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// URL feldolgozása
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = trim($uri, '/');
$segments = explode('/', $uri);
$method = $_SERVER['REQUEST_METHOD'];

$authController = new AuthController();
$controller = new QuizController();
$settingsController = new SettingsController();

// Admin végpontok — token ellenőrzés
$isAdmin =
    count($segments) >= 2 &&
    $segments[0] === 'api' &&
    $segments[1] === 'admin';

if ($isAdmin && !$authController->validateToken()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Router
match (true) {
    // GET /api/categories
    $method === 'GET' && $segments === ['api', 'categories']
        => $controller->getCategories(),

    // GET /api/categories/1
    $method === 'GET' && count($segments) === 3
        && $segments[0] === 'api'
        && $segments[1] === 'categories'
        && ctype_digit($segments[2])
        => $controller->getCategory((int) $segments[2]),

    // GET /api/categories/php/questions (slug alapú)
    $method === 'GET' && count($segments) === 4
        && $segments[0] === 'api'
        && $segments[1] === 'categories'
        && !ctype_digit($segments[2])
        && $segments[3] === 'questions'
        => $controller->getQuestionsBySlug(
            $segments[2],
            (int) ($_GET['limit'] ?? 10)
        ),

    // GET /api/questions/random
    $method === 'GET' && $segments === ['api', 'questions', 'random']
        => $controller->getRandomQuestions(
            (int) ($_GET['limit'] ?? 10)
        ),

    // GET /api/questions/1
    $method === 'GET' && count($segments) === 3
        && $segments[0] === 'api'
        && $segments[1] === 'questions'
        && ctype_digit($segments[2])
        => $controller->getQuestion((int) $segments[2]),

    // POST /api/entries
    $method === 'POST' && $segments === ['api', 'entries']
        => $controller->submitEntry(),

    // POST /api/auth/login
    $method === 'POST' && $segments === ['api', 'auth', 'login']
        => $authController->login(),

    // POST /api/auth/logout
    $method === 'POST' && $segments === ['api', 'auth', 'logout']
        => $authController->logout(),

    // GET /api/auth/me
    $method === 'GET' && $segments === ['api', 'auth', 'me']
        => $authController->me(),

    // GET /api/admin/questions
    $method === 'GET' && $segments === ['api', 'admin', 'questions']
        => $controller->getQuestionsAdmin(),

    // DELETE /api/admin/questions/1
    $method === 'DELETE' && count($segments) === 4
        && $segments[0] === 'api'
        && $segments[1] === 'admin'
        && $segments[2] === 'questions'
        && ctype_digit($segments[3])
        => $controller->deleteQuestion((int) $segments[3]),

    // POST /api/admin/questions
    $method === 'POST' && $segments === ['api', 'admin', 'questions']
        => $controller->createQuestion(),

    // PUT /api/admin/questions/1
    $method === 'PUT' && count($segments) === 4
        && $segments[0] === 'api'
        && $segments[1] === 'admin'
        && $segments[2] === 'questions'
        && ctype_digit($segments[3])
        => $controller->updateQuestion((int) $segments[3]),

    // POST /api/admin/categories
    $method === 'POST' && $segments === ['api', 'admin', 'categories']
        => $controller->createCategory(),

    // PUT /api/admin/categories/1
    $method === 'PUT' && count($segments) === 4
        && $segments[0] === 'api'
        && $segments[1] === 'admin'
        && $segments[2] === 'categories'
        && ctype_digit($segments[3])
        => $controller->updateCategory((int) $segments[3]),

    // GET /api/admin/categories
    $method === 'GET' && $segments === ['api', 'admin', 'categories']
        => $controller->getCategories(),

    // GET /api/admin/categories/1
    $method === 'GET' && count($segments) === 4
        && $segments[0] === 'api'
        && $segments[1] === 'admin'
        && $segments[2] === 'categories'
        && ctype_digit($segments[3])
        => $controller->getCategory((int) $segments[3]),

    // GET /api/question-types
    $method === 'GET' && $segments === ['api', 'question-types']
        => $controller->getQuestionTypes(),

    // GET /api/settings
    $method === 'GET' && $segments === ['api', 'settings']
        => $settingsController->getSettings(),

    // PUT /api/admin/settings
    $method === 'PUT' && $segments === ['api', 'admin', 'settings']
        => $settingsController->updateSettings(),

    // GET /api/admin/entries
    $method === 'GET' && $segments === ['api', 'admin', 'entries']
        => $controller->getEntries(),

    default => (function () {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    })()
};
