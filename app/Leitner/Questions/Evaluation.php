<?php

namespace App\Leitner\Questions;

/**
 * Correction d'une réponse : le verdict et, le cas échéant, un détail à
 * afficher à l'étudiant (explication de la mauvaise réponse choisie, écart…).
 */
final readonly class Evaluation
{
    public function __construct(
        public Verdict $verdict,
        public ?string $detail = null,
    ) {}

    public static function correct(?string $detail = null): self
    {
        return new self(Verdict::Juste, $detail);
    }

    public static function wrong(?string $detail = null): self
    {
        return new self(Verdict::Fausse, $detail);
    }
}
