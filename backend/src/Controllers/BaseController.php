<?php

declare(strict_types=1);

namespace Quiz\Controllers;

abstract class BaseController
{
    protected const INPUT_STREAM = 'php://input';

    protected function respond(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    protected function respondNotFound(string $message): void
    {
        $this->respond(['error' => $message], 404);
    }

    protected function getBearerToken(): ?string
    {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return null;
    }

    protected function getJsonInput(): array
    {
        $payload = file_get_contents(self::INPUT_STREAM);
        return json_decode($payload, true) ?? [];
    }
}
