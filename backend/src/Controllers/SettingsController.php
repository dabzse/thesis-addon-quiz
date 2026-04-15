<?php

declare(strict_types=1);

namespace Quiz\Controllers;

use Quiz\Models\Settings;

class SettingsController
{
    private Settings $settings;

    public function __construct()
    {
        $this->settings = new Settings();
    }

    public function getSettings(): void
    {
        $this->respond($this->settings->getAll());
    }

    public function updateSettings(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data)) {
            $this->respond(['error' => 'Hiányzó adatok.'], 400);
            return;
        }

        foreach ($data as $key => $value) {
            $this->settings->set($key, (string) $value);
        }

        $this->respond(['success' => true]);
    }

    private function respond(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        echo json_encode($data);
    }
}
