<?php

namespace App\Qcm\Exchange;

use App\Qcm\Models\Question;

/**
 * Question telle qu'elle figure dans un fichier d'échange (PRD QCM §6.1).
 */
final readonly class QuestionData
{
    /**
     * @param  int  $line  numéro de la ligne du fichier (0 pour une question exportée)
     * @param  list<array{text: string, explanation: string|null}>  $wrongAnswers
     * @param  string|null  $image  nom du fichier d'illustration dans l'archive
     */
    public function __construct(
        public int $line,
        public string $statement,
        public string $correctAnswer,
        public array $wrongAnswers,
        public ?string $explanation = null,
        public ?string $module = null,
        public ?string $image = null,
    ) {}

    /**
     * Données d'une question enregistrée, pour l'export.
     */
    public static function fromQuestion(Question $question, ?string $imageName = null): self
    {
        $correct = $question->answers->firstWhere('is_correct', true);

        return new self(
            line: 0,
            statement: $question->statement,
            correctAnswer: $correct === null ? '' : $correct->text,
            wrongAnswers: $question->answers
                ->where('is_correct', false)
                ->sortBy('position')
                ->map(fn ($answer): array => ['text' => $answer->text, 'explanation' => $answer->explanation])
                ->values()
                ->all(),
            explanation: $question->explanation,
            module: $question->module,
            image: $imageName,
        );
    }

    /**
     * Énoncé normalisé pour repérer les doublons probables (PRD QCM UC-04) :
     * minuscules, espaces réduits, ponctuation finale retirée.
     */
    public static function normalizeStatement(string $statement): string
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $statement)));

        return rtrim($normalized, " \t?.!:;…");
    }
}
