<?php

namespace App\Leitner\Suites;

use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Usage\QuestionUsage;

/**
 * Utilisation des banques et des questions par les suites Leitner, déclarée
 * au module Banques (PRD Leitner UC-02).
 */
final class LeitnerQuestionUsage implements QuestionUsage
{
    public function bankDeletionNotice(Bank $bank): ?string
    {
        return match ($count = SuiteUsage::suitesUsingBank($bank)) {
            0 => null,
            1 => 'Elle sera retirée de la suite Leitner qui l\'utilise, avec la progression de ses questions.',
            default => "Elle sera retirée des {$count} suites Leitner qui l'utilisent, avec la progression de ses questions.",
        };
    }

    public function questionDeletionNotice(Question $question): ?string
    {
        return match ($count = SuiteUsage::progressionsWithQuestion($question)) {
            0 => null,
            1 => 'Elle disparaîtra de la suite Leitner où elle est en cours de révision.',
            default => "Elle disparaîtra des {$count} suites Leitner où elle est en cours de révision.",
        };
    }

    public function substantiveChangeNotice(Question $question): ?string
    {
        return match ($count = SuiteUsage::progressionsWithQuestion($question)) {
            0 => null,
            1 => 'Cette question est en cours de révision dans 1 suite Leitner : une modification de fond la remet en boîte 1.',
            default => "Cette question est en cours de révision dans {$count} suites Leitner : une modification de fond la remet en boîte 1.",
        };
    }

    public function applySubstantiveChange(Question $question): ?string
    {
        return match ($count = SuiteUsage::resetProgress($question)) {
            0 => null,
            1 => 'Question remise en boîte 1 dans 1 suite Leitner.',
            default => "Question remise en boîte 1 dans {$count} suites Leitner.",
        };
    }
}
