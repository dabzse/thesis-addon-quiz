<?php

declare(strict_types=1);

namespace Quiz\Controllers;

use Quiz\Models\User;
use Quiz\Models\Session;

class AuthController
{
    private User $user;
    private Session $session;

    public function __construct()
    {
        $this->user = new User();
        $this->session = new Session();
    }

    public function login(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['email']) || empty($data['password'])) {
            $this->respond(['error' => 'Hiányzó adatok.'], 400);
            return;
        }

        $user = $this->user->findByEmail($data['email']);

        if ($user === false || !password_verify($data['password'], $user['password'])) {
            $this->respond(['error' => 'Hibás e-mail cím vagy jelszó.'], 401);
            return;
        }

        $token = $this->session->create((int) $user['id']);

        $this->respond([
            'token' => $token,
            'user'  => [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
            ],
        ]);
    }

    public function logout(): void
    {
        $token = $this->getBearerToken();

        if ($token) {
            $this->session->delete($token);
        }

        $this->respond(['success' => true]);
    }

    public function me(): void
    {
        $token = $this->getBearerToken();

        if (!$token) {
            $this->respond(['error' => 'Nincs token.'], 401);
            return;
        }

        $session = $this->session->findByToken($token);

        if ($session === false) {
            $this->respond(['error' => 'Érvénytelen vagy lejárt token.'], 401);
            return;
        }

        $this->respond([
            'user' => [
                'id'    => $session['id'],
                'name'  => $session['name'],
                'email' => $session['email'],
            ],
        ]);
    }

    public function getBearerToken(): ?string
    {
        $headers = getallheaders();
        $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return null;
    }

    private function respond(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }

    public function validateToken(): bool
    {
        $token = $this->getBearerToken();

        if (!$token) {
            return false;
        }

        $session = $this->session->findByToken($token);

        return $session !== false;
    }
}
