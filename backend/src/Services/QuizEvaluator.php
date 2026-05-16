<?php

declare(strict_types=1);

namespace Quiz\Services;

class QuizEvaluator
{
    public function evaluateQuestion(array $trueQuestion, array $userData): bool
    {
        return match ($trueQuestion['type']) {
            'ordering' => $this->isOrderingCorrect($trueQuestion['answers'], $userData['userOrder'] ?? []),
            'matching' => $this->isMatchingCorrect($trueQuestion['answers'], $userData['userMatches'] ?? []),
            'multiple', 'single', 'truefalse' => $this->isStandardCorrect($trueQuestion['answers'], $userData['selected'] ?? []),
            default => false,
        };
    }

    private function isOrderingCorrect(array $trueAnswers, array $userOrder): bool
    {
        if (count($userOrder) !== count($trueAnswers)) {
            return false;
        }

        foreach ($userOrder as $index => $item) {
            $trueAnswer = null;
            foreach ($trueAnswers as $ans) {
                if ((int)$ans['id'] === (int)$item['id']) {
                    $trueAnswer = $ans;
                    break;
                }
            }
            if (!$trueAnswer || (int)$trueAnswer['correct_position'] !== $index + 1) {
                return false;
            }
        }

        return true;
    }

    private function isMatchingCorrect(array $trueAnswers, array $userMatches): bool
    {
        if (empty($userMatches) || count($userMatches) !== count($trueAnswers)) {
            return false;
        }

        foreach ($userMatches as $match) {
            $lId = str_starts_with($match['firstId'], 'l-') ? (int)substr($match['firstId'], 2) : (int)substr($match['secondId'], 2);
            $rId = str_starts_with($match['firstId'], 'r-') ? (int)substr($match['firstId'], 2) : (int)substr($match['secondId'], 2);
            if ($lId !== $rId) {
                return false;
            }
        }

        return true;
    }

    private function isStandardCorrect(array $trueAnswers, array $selected): bool
    {
        $correctIds = [];
        foreach ($trueAnswers as $ans) {
            if ((int)$ans['is_correct'] === 1) {
                $correctIds[] = (int)$ans['id'];
            }
        }
        $selectedIds = array_map('intval', $selected);

        sort($correctIds);
        sort($selectedIds);

        return count($correctIds) === count($selectedIds) && $correctIds === $selectedIds;
    }
}
