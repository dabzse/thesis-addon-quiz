<?php

declare(strict_types=1);

namespace Quiz\Controllers;

use Quiz\Models\User;
use Quiz\Models\Session;

class AuthController extends BaseController
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
        $data = $this->getJsonInput();

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
