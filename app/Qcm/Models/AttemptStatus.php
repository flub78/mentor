<?php

namespace App\Qcm\Models;

/**
 * État d'un passage d'examen (PRD QCM UC-12).
 */
enum AttemptStatus: string
{
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'En cours',
            self::Finished => 'Terminé',
            self::Expired => 'Terminé (temps écoulé)',
        };
    }
}
