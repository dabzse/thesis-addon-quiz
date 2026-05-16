<?php

declare(strict_types=1);

namespace Quiz\Services;

use PHPMailer\PHPMailer\PHPMailer;
use Quiz\Exceptions\MailerConfigurationException;


class QuizResultMailer
{
    public function sendResultEmail(
        string $email,
        ?string $name,
        int $score,
        int $maxScore,
        array $questions
    ): void {
        try {
            $body = $this->buildResultEmailBody($name, $score, $maxScore, $questions);
            $this->sendResultMail($email, $name, $body);
        } catch (\Throwable $exception) {
            error_log('Email küldési hiba: ' . $exception->getMessage());
        }
    }

    private function buildResultEmailBody(
        ?string $name,
        int $score,
        int $maxScore,
        array $questions
    ): string {
        $greeting = $name ? "Kedves " . htmlspecialchars($name) . "!" : 'Kedves Játékos!';
        $percent = $maxScore > 0 ? round(($score / $maxScore) * 100) : 0;

        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; background-color: #f3f4f6; color: #1f2937; padding: 20px; line-height: 1.5; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                .header { text-align: center; border-bottom: 2px solid #e5e7eb; padding-bottom: 20px; margin-bottom: 20px; }
                .score { font-size: 24px; font-weight: bold; color: #4f46e5; margin-top: 10px; }
                .question-box { border: 1px solid #e5e7eb; border-radius: 6px; padding: 15px; margin-bottom: 15px; }
                .q-title { font-weight: bold; margin-bottom: 10px; }
                .green { color: #16a34a; font-weight: bold; }
                .red { color: #dc2626; font-weight: bold; }
                .gray { color: #6b7280; }
                .italic { font-style: italic; }
                .solution-box { margin-top: 10px; padding-top: 10px; border-top: 1px dashed #d1d5db; font-size: 14px; }
                .correct-ans { color: #15803d; }
                .ans-row { padding: 4px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>{$greeting}</h2>
                    <p>Köszönjük, hogy kitöltötted a kvízt!</p>
                    <div class='score'>Eredményed: {$score} / {$maxScore} ({$percent}%)</div>
                </div>

                <h3>Válaszaid áttekintése:</h3>
        ";

        foreach ($questions as $index => $question) {
            $body .= $this->formatQuestionResult((int) $index + 1, $question);
        }

        $body .= "
                <p style='text-align: center; margin-top: 30px; color: #6b7280;'>Várunk vissza legközelebb is!</p>
            </div>
        </body>
        </html>
        ";

        return $body;
    }

    private function formatQuestionResult(int $number, array $question): string
    {
        $qTitle = htmlspecialchars($question['question']);
        $body = "<div class='question-box'>";
        $body .= "<div class='q-title'>{$number}. {$qTitle}</div>";

        $type = $question['type'] ?? 'single';
        $isCorrect = $question['isCorrect'] ?? false;

        if ($type === 'ordering') {
            $body .= $this->formatOrderingQuestion($question, $isCorrect);
        } elseif ($type === 'matching') {
            $body .= $this->formatMatchingQuestion($question, $isCorrect);
        } else {
            $body .= $this->formatStandardQuestion($question);
        }

        $body .= "</div>";
        return $body;
    }

    private function formatOrderingQuestion(array $question, bool $isCorrect): string
    {
        $body = '';
        $userOrder = $question['userOrder'] ?? [];

        if (empty($userOrder)) {
            $body .= "<div class='red italic ans-row'>Nem válaszoltad meg.</div>";
        } else {
            foreach ($userOrder as $position => $item) {
                $correct = $item['correct_position'] === $position + 1;
                $marker = $correct ? "<span class='green'>✓</span>" : "<span class='red'>✗</span>";
                $text = htmlspecialchars($item['answer']);
                $body .= "<div class='ans-row'>{$marker} " . ($position + 1) . ". {$text}</div>";
            }
        }

        if (!$isCorrect) {
            $body .= "<div class='solution-box'>";
            $body .= "<div class='gray'>Helyes sorrend:</div>";

            $answers = $question['answers'];
            usort($answers, fn($a, $b) => ($a['correct_position'] ?? 0) <=> ($b['correct_position'] ?? 0));

            foreach ($answers as $ans) {
                $pos = $ans['correct_position'] ?? '?';
                $text = htmlspecialchars($ans['answer']);
                $body .= "<div class='correct-ans ans-row'>{$pos}. {$text}</div>";
            }
            $body .= "</div>";
        }

        return $body;
    }

    private function formatMatchingQuestion(array $question, bool $isCorrect): string
    {
        $body = '';
        $userMatches = $question['userMatches'] ?? [];

        if (empty($userMatches)) {
            $body .= "<div class='red italic ans-row'>Nem válaszoltad meg.</div>";
        } else {
            foreach ($userMatches as $match) {
                $pair = $this->resolveMatchingPair($match);
                $leftAnswer = $this->findAnswerById($question['answers'], $pair['leftId']);
                $rightAnswer = $this->findAnswerById($question['answers'], $pair['rightId']);

                $correct = $pair['leftId'] === $pair['rightId'];
                $marker = $correct ? "<span class='green'>✓</span>" : "<span class='red'>✗</span>";
                $leftText = htmlspecialchars($leftAnswer['answer'] ?? '?');
                $rightText = htmlspecialchars($rightAnswer['match_answer'] ?? $rightAnswer['answer'] ?? '?');
                $body .= "<div class='ans-row'>{$marker} {$leftText} &rarr; {$rightText}</div>";
            }
        }

        if (!$isCorrect) {
            $body .= "<div class='solution-box'>";
            $body .= "<div class='gray'>Helyes párok:</div>";
            foreach ($question['answers'] as $ans) {
                $leftText = htmlspecialchars($ans['answer']);
                $rightText = htmlspecialchars($ans['match_answer'] ?? $ans['answer']);
                $body .= "<div class='correct-ans ans-row'>{$leftText} &rarr; {$rightText}</div>";
            }
            $body .= "</div>";
        }

        return $body;
    }

    private function formatStandardQuestion(array $question): string
    {
        $body = '';
        $selected = $question['selected'] ?? [];

        if (empty($selected)) {
            $body .= "<div class='red italic ans-row'>Nem válaszoltad meg.</div>";
        }

        foreach ($question['answers'] as $answer) {
            $isSelected = in_array($answer['id'], $selected, true);
            $isAnsCorrect = !empty($answer['is_correct']);

            if ($isAnsCorrect) {
                $icon = "<span class='green'>✓</span>";
                $textClass = "green";
            } elseif ($isSelected) {
                $icon = "<span class='red'>✗</span>";
                $textClass = "red";
            } else {
                $icon = "<span class='gray'>&middot;</span>";
                $textClass = "gray";
            }

            $text = htmlspecialchars($answer['answer']);
            $body .= "<div class='{$textClass} ans-row'>{$icon} {$text}</div>";
        }

        return $body;
    }

    private function resolveMatchingPair(array $match): array
    {
        $firstId = $match['firstId'];
        $secondId = $match['secondId'];

        return [
            'leftId' => str_starts_with($firstId, 'l-')
                ? (int) substr($firstId, 2)
                : (int) substr($secondId, 2),
            'rightId' => str_starts_with($firstId, 'r-')
                ? (int) substr($firstId, 2)
                : (int) substr($secondId, 2),
        ];
    }

    private function findAnswerById(array $answers, int $id): ?array
    {
        foreach ($answers as $answer) {
            if ($answer['id'] === $id) {
                return $answer;
            }
        }
        return null;
    }

    private function sendResultMail(string $email, ?string $name, string $body): void
    {
        $host = $_ENV['MAIL_HOST'] ?? null;
        if (!$host) {
            throw new MailerConfigurationException('MAIL_HOST hiányzik a beállításokból!');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'] ?? '';
        $mail->Password   = $_ENV['MAIL_PASSWORD'] ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) ($_ENV['MAIL_PORT'] ?? 587);
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($_ENV['MAIL_FROM'] ?? 'quiz@edujobs.uni', $_ENV['MAIL_FROM_NAME'] ?? 'Edu_Jobs Quiz');
        $mail->addAddress($email, $name ?? '');

        $mail->Subject = 'Kvíz eredményed';

        $mail->isHTML(true);
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['</div>', '</p>', '<br>', '&rarr;'], ["\n", "\n\n", "\n", "->"], $body));

        $mail->send();
    }
}
