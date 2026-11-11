<?php

namespace App\Banques\Questions;

use Filament\Support\Contracts\HasLabel;

/**
 * Résultat de la correction d'une réponse (PRD Banques §3). Une réponse
 * partiellement juste compte comme fausse (PRD Leitner Q9, choix provisoire).
 */
enum Verdict: string implements HasLabel
{
    case Juste = 'juste';
    case Fausse = 'fausse';

    public function getLabel(): string
    {
        return match ($this) {
            self::Juste => 'Bonne réponse',
            self::Fausse => 'Mauvaise réponse',
        };
    }

    public function isCorrect(): bool
    {
        return $this === self::Juste;
    }
}
