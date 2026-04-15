<?php

declare(strict_types=1);

namespace Quiz\Services;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

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
            $this->sendResultMail($email, $name, $this->buildResultEmailBody($name, $score, $maxScore, $questions));
        } catch (Exception $exception) {
            error_log('Email küldési hiba: ' . $exception->getMessage());
        }
    }

    private function buildResultEmailBody(
        ?string $name,
        int $score,
        int $maxScore,
        array $questions
    ): string {
        $greeting = $name ? "Kedves {$name}!" : 'Kedves Játékos!';
        $percent = $maxScore > 0 ? round(($score / $maxScore) * 100) : 0;

        $body = "{$greeting}\n\n";
        $body .= "Eredményed: {$score} / {$maxScore} ({$percent}%)\n\n";
        $body .= "Válaszaid áttekintése:\n";
        $body .= str_repeat('-', 40) . "\n";

        foreach ($questions as $index => $question) {
            $body .= $this->formatQuestionResult((int) $index + 1, $question);
        }

        $body .= "\n" . str_repeat('-', 40) . "\n";
        $body .= "Köszönjük a részvételt! Várunk vissza legközelebb is!\n";

        return $body;
    }

    private function formatQuestionResult(int $number, array $question): string
    {
        $body = "\n{$number}. {$question['question']}\n";
        $type = $question['type'] ?? 'single';

        if ($type === 'ordering') {
            return $body . $this->formatOrderingQuestion($question);
        }

        if ($type === 'matching') {
            return $body . $this->formatMatchingQuestion($question);
        }

        return $body . $this->formatStandardQuestion($question);
    }

    private function formatOrderingQuestion(array $question): string
    {
        $body = '';

        foreach ($question['userOrder'] as $position => $item) {
            $correct = $item['correct_position'] === $position + 1;
            $marker = $correct ? '✓' : '✗';
            $body .= "  {$marker} {$item['answer']}";

            if (!$correct) {
                $body .= " (helyes pozíció: {$item['correct_position']})";
            }

            $body .= "\n";
        }

        return $body;
    }

    private function formatMatchingQuestion(array $question): string
    {
        $body = '';

        foreach ($question['userMatches'] as $match) {
            $pair = $this->resolveMatchingPair($match);
            $leftAnswer = $this->findAnswerById($question['answers'], $pair['leftId']);
            $rightAnswer = $this->findAnswerById($question['answers'], $pair['rightId']);

            $marker = $pair['leftId'] === $pair['rightId'] ? '✓' : '✗';
            $leftText = $leftAnswer['answer'] ?? '?';
            $rightText = $rightAnswer['match_answer'] ?? $rightAnswer['answer'] ?? '?';
            $body .= "  {$marker} {$leftText} → {$rightText}\n";
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

    private function formatStandardQuestion(array $question): string
    {
        $body = '';

        foreach ($question['answers'] as $answer) {
            $marker = $answer['is_correct'] ? '✓' : '✗';
            $selected = in_array($answer['id'], $question['selected'] ?? [], true) ? '[X]' : '[ ]';
            $body .= "  {$selected} {$marker} {$answer['answer']}\n";
        }

        return $body;
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
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int) $_ENV['MAIL_PORT'];
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($_ENV['MAIL_FROM'], $_ENV['MAIL_FROM_NAME']);
        $mail->addAddress($email, $name ?? '');

        $mail->Subject = 'Kvíz eredményed';
        $mail->Body    = $body;
        $mail->isHTML(false);

        $mail->send();
    }
}
