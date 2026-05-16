<?php

declare(strict_types=1);

namespace Quiz\Controllers {
    function getallheaders(): array
    {
        return $GLOBALS['__test_headers'] ?? [];
    }

    function file_get_contents(string $filename): string|false
    {
        if ($filename === 'php://input') {
            return $GLOBALS['__test_input'] ?? '';
        }

        return \file_get_contents($filename);
    }
}

namespace {
    require_once __DIR__ . '/../vendor/autoload.php';

    const TEST_ADMIN_ID = 7;
    const TEST_ADMIN_NAME = 'Admin';
    const TEST_ADMIN_EMAIL = 'admin@example.com';
    const TEST_ADMIN_PASS = 'secret';
    const TEST_TOKEN = 'session-token';

    use Quiz\Controllers\AuthController;
    use Quiz\Models\Session;
    use Quiz\Models\User;

    final class FakeUser extends User
    {
        public array|false $emailResult = false;

        public function __construct()
        {
            // No need to call the parent constructor since we're not using a real database connection
        }

        public function findByEmail(string $email): array|false
        {
            return $this->emailResult;
        }
    }

    final class FakeSession extends Session
    {
        public ?string $tokenToCreate = null;
        public array|false $findResult = false;
        public ?string $deletedToken = null;
        public ?int $createdForUserId = null;

        public function __construct()
        {
            // No need to call the parent constructor since we're not using a real database connection
        }

        public function create(int $userId): string
        {
            $this->createdForUserId = $userId;

            return $this->tokenToCreate ?? 'generated-token';
        }

        public function findByToken(string $token): array|false
        {
            return $this->findResult;
        }

        public function delete(string $token): void
        {
            $this->deletedToken = $token;
        }
    }

    function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \AssertionError($message);
        }
    }

    function assertSameValue(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new \AssertionError($message . ' Expected: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true));
        }
    }

    function inject(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setValue($object, $value);
    }

    function makeController(): AuthController
    {
        $ref = new \ReflectionClass(AuthController::class);

        return $ref->newInstanceWithoutConstructor();
    }

    function setRequestContext(array $headers = [], ?string $input = null): void
    {
        $GLOBALS['__test_headers'] = $headers;

        if ($input === null) {
            unset($GLOBALS['__test_input']);
            return;
        }

        $GLOBALS['__test_input'] = $input;
    }

    function runController(callable $callback): array
    {
        $previousStatus = http_response_code() ?: 200;
        ob_start();

        try {
            $callback();
            $body = (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            http_response_code($previousStatus);
            throw $exception;
        }

        $status = http_response_code() ?: 200;
        http_response_code($previousStatus);

        return [$status, $body];
    }

    $tests = [
        'login returns token and user payload' => function (): void {
            $controller = makeController();
            $user = new FakeUser();
            $user->emailResult = [
                'id' => TEST_ADMIN_ID,
                'name' => TEST_ADMIN_NAME,
                'email' => TEST_ADMIN_EMAIL,
                'password' => password_hash(TEST_ADMIN_PASS, PASSWORD_DEFAULT),
            ];
            $session = new FakeSession();
            $session->tokenToCreate = TEST_TOKEN;

            inject($controller, 'user', $user);
            inject($controller, 'session', $session);
            setRequestContext([], json_encode(['email' => TEST_ADMIN_EMAIL, 'password' => TEST_ADMIN_PASS], JSON_THROW_ON_ERROR));

            [$status, $body] = runController(static fn () => $controller->login());
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            assertSameValue(200, $status, 'Login should succeed');
            assertSameValue(TEST_TOKEN, $payload['token'], 'Login should return the session token');
            assertSameValue(TEST_ADMIN_NAME, $payload['user']['name'], 'Login should return the user name');
            assertSameValue(TEST_ADMIN_ID, $session->createdForUserId, 'Login should create a session for the matched user');
        },
        'login rejects missing credentials' => function (): void {
            $controller = makeController();
            inject($controller, 'user', new FakeUser());
            inject($controller, 'session', new FakeSession());
            setRequestContext([], json_encode(['email' => TEST_ADMIN_EMAIL], JSON_THROW_ON_ERROR));

            [$status, $body] = runController(static fn () => $controller->login());
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            assertSameValue(400, $status, 'Login should reject missing password');
            assertSameValue('Hiányzó adatok.', $payload['error'], 'Login should return the validation error');
        },
        'login rejects invalid password' => function (): void {
            $controller = makeController();
            $user = new FakeUser();
            $user->emailResult = [
                'id' => TEST_ADMIN_ID,
                'name' => TEST_ADMIN_NAME,
                'email' => TEST_ADMIN_EMAIL,
                'password' => password_hash(TEST_ADMIN_PASS, PASSWORD_DEFAULT),
            ];

            inject($controller, 'user', $user);
            inject($controller, 'session', new FakeSession());
            setRequestContext([], json_encode(['email' => TEST_ADMIN_EMAIL, 'password' => 'wrong'], JSON_THROW_ON_ERROR));

            [$status, $body] = runController(static fn () => $controller->login());
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            assertSameValue(401, $status, 'Login should reject invalid credentials');
            assertSameValue('Hibás e-mail cím vagy jelszó.', $payload['error'], 'Login should return the auth error');
        },
        'me rejects missing bearer token' => function (): void {
            $controller = makeController();
            inject($controller, 'session', new FakeSession());
            setRequestContext([]);

            [$status, $body] = runController(static fn () => $controller->me());
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            assertSameValue(401, $status, 'me should reject requests without a token');
            assertSameValue('Nincs token.', $payload['error'], 'me should explain the missing token');
        },
        'logout deletes the current token' => function (): void {
            $controller = makeController();
            $session = new FakeSession();
            inject($controller, 'session', $session);
            setRequestContext(['Authorization' => 'Bearer logout-token']);

            [$status, $body] = runController(static fn () => $controller->logout());
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

            assertSameValue(200, $status, 'Logout should succeed');
            assertTrue($payload['success'] === true, 'Logout should return success');
            assertSameValue('logout-token', $session->deletedToken, 'Logout should delete the current token');
        },
        'validateToken reflects session lookup' => function (): void {
            $controller = makeController();
            $session = new FakeSession();
            inject($controller, 'session', $session);

            setRequestContext([]);
            assertTrue($controller->validateToken() === false, 'validateToken should fail without a token');

            setRequestContext(['Authorization' => 'Bearer valid-token']);
            $session->findResult = [
                'id' => TEST_ADMIN_ID,
                'name' => TEST_ADMIN_NAME,
                'email' => TEST_ADMIN_EMAIL,
            ];
            assertTrue($controller->validateToken() === true, 'validateToken should succeed when the session exists');
        },
    ];

    $failures = 0;
    $output = [];

    foreach ($tests as $name => $test) {
        try {
            $test();
            $output[] = "PASS: {$name}\n";
        } catch (\Throwable $exception) {
            $failures++;
            $output[] = "FAIL: {$name}\n";
            $output[] = $exception->getMessage() . "\n";
        }
    }

    echo implode('', $output);

    exit($failures > 0 ? 1 : 0);
}
