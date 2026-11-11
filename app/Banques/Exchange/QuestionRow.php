<?php

namespace App\Banques\Exchange;

use App\Banques\Models\Question;
use App\Banques\Questions\Difficulty;
use App\Banques\Questions\QuestionType;

/**
 * Question telle qu'elle figure dans un fichier d'échange (PRD Banques §8).
 */
final readonly class QuestionRow
{
    /**
     * @param  int  $line  numéro de la ligne du fichier (0 pour une question exportée)
     * @param  array<string, mixed>  $payload  réponse attendue, sous la forme propre au type
     * @param  string|null  $image  nom du fichier d'illustration dans l'archive
     * @param  list<string>  $warnings  remarques sans conséquence sur l'import de la ligne
     * @param  string|null  $reference  référence de la question (PRD Banques §3)
     */
    public function __construct(
        public int $line,
        public QuestionType $type,
        public string $statement,
        public array $payload,
        public ?string $explanation = null,
        public ?string $theme = null,
        public ?Difficulty $difficulty = null,
        public ?string $image = null,
        public ?string $imageAlt = null,
        public array $warnings = [],
        public ?string $reference = null,
    ) {}

    /**
     * La même ligne sans référence.
     */
    public function withoutReference(): self
    {
        return new self($this->line, $this->type, $this->statement, $this->payload, $this->explanation, $this->theme, $this->difficulty, $this->image, $this->imageAlt, $this->warnings);
    }

    /**
     * Données d'une question enregistrée, pour l'export.
     */
    public static function fromQuestion(Question $question, ?string $imageName = null): self
    {
        return new self(
            line: 0,
            type: $question->type,
            statement: $question->statement,
            payload: $question->payload,
            explanation: $question->explanation,
            theme: $question->theme,
            difficulty: $question->difficulty,
            image: $imageName,
            imageAlt: $imageName === null ? null : $question->image_alt,
            reference: $question->reference,
        );
    }

    /**
     * Énoncé normalisé pour repérer les doublons probables (PRD Banques
     * UC-02) : minuscules, espaces réduits, ponctuation finale retirée.
     */
    public static function normalizeStatement(string $statement): string
    {
        $normalized = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $statement)));

        return rtrim($normalized, " \t?.!:;…");
    }
}
