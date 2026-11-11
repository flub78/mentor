<?php

namespace App\Leitner\Sessions;

use Filament\Support\Contracts\HasLabel;

/**
 * État d'une séance (PRD Leitner UC-08).
 */
enum SessionStatus: string implements HasLabel
{
    case EnCours = 'en_cours';
    case Terminee = 'terminee';
    case Abandonnee = 'abandonnee';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnCours => 'En cours',
            self::Terminee => 'Terminée',
            self::Abandonnee => 'Abandonnée',
        };
    }
}
