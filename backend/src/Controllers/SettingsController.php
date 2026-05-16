<?php

declare(strict_types=1);

namespace Quiz\Controllers;

use Quiz\Models\Settings;

class SettingsController extends BaseController
{
    private const ALLOWED_KEYS = [
        'question_timer',
        'total_timer',
        'active_year',
        'show_correct_during',
        'show_correct_final',
        'show_unanswered_final',
    ];

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
        $data = $this->getJsonInput();

        if (empty($data)) {
            $this->respond(['error' => 'Hiányzó adatok.'], 400);
            return;
        }

        foreach ($data as $key => $value) {
            if (!in_array($key, self::ALLOWED_KEYS, true)) {
                continue;
            }
            $this->settings->set($key, (string) $value);
        }

        $this->respond(['success' => true]);
    }

}
