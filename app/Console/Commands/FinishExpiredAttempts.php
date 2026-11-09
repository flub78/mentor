<?php

namespace App\Console\Commands;

use App\Qcm\Exams\AttemptFinisher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Termine les passages d'examen abandonnés après leur échéance et enregistre
 * leur résultat (PRD QCM UC-12, 3a ; plan QCM §6.2). Planifiée toutes les
 * minutes.
 */
#[Signature('qcm:terminer-passages-echus')]
#[Description('Termine les passages d\'examen dont la durée est écoulée et enregistre leur résultat.')]
class FinishExpiredAttempts extends Command
{
    public function handle(AttemptFinisher $finisher): int
    {
        $closed = $finisher->closeOverdue();

        $this->info($closed === 0
            ? 'Aucun passage échu à terminer.'
            : sprintf('%d passage(s) échu(s) terminé(s), résultat enregistré.', $closed));

        return self::SUCCESS;
    }
}
