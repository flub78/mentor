<?php

namespace App\Leitner\Questions;

use Filament\Support\Contracts\HasLabel;

/**
 * Niveau de difficulté d'une question (PRD Leitner §3, Q13 : trois niveaux,
 * choix provisoire). Une question sans niveau compte comme moyenne.
 */
enum Difficulty: int implements HasLabel
{
    case Facile = 1;
    case Moyen = 2;
    case Difficile = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Facile => 'Facile',
            self::Moyen => 'Moyen',
            self::Difficile => 'Difficile',
        };
    }

    /**
     * Niveau retenu pour l'introduction des questions (PRD Leitner §7.3) :
     * une question sans niveau est traitée comme moyenne.
     */
    public static function effective(self|int|null $difficulty): self
    {
        if ($difficulty instanceof self) {
            return $difficulty;
        }

        return self::tryFrom((int) $difficulty) ?? self::Moyen;
    }
}
