<?php

namespace App\Qcm\Questionnaires;

use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Usage\QuestionUsage;
use App\Qcm\Models\Questionnaire;

/**
 * Utilisation des banques et des questions par les questionnaires QCM,
 * déclarée au module Banques (PRD QCM UC-08). Une modification de fond n'a
 * pas d'effet ici : les résultats déjà enregistrés ne changent jamais.
 */
final class QcmQuestionUsage implements QuestionUsage
{
    public function bankDeletionNotice(Bank $bank): ?string
    {
        $count = Questionnaire::query()->whereHas('questions', fn ($query) => $query->where('bank_id', $bank->id))->count();

        return match ($count) {
            0 => null,
            1 => 'Ses questions seront retirées du questionnaire QCM qui les utilise.',
            default => "Ses questions seront retirées des {$count} questionnaires QCM qui les utilisent.",
        };
    }

    public function questionDeletionNotice(Question $question): ?string
    {
        $titles = Questionnaire::query()
            ->whereHas('questions', fn ($query) => $query->whereKey($question->id))
            ->orderBy('title')
            ->pluck('title')
            ->all();

        return $titles === [] ? null : 'Elle sera retirée des questionnaires QCM : « '.implode(' », « ', $titles).' ».';
    }

    public function substantiveChangeNotice(Question $question): ?string
    {
        return null;
    }

    public function applySubstantiveChange(Question $question): ?string
    {
        return null;
    }

    /**
     * Un questionnaire continue de poser une question déplacée.
     */
    public function moveNotice(Question $question, Bank $target): ?string
    {
        return null;
    }

    public function applyMove(Question $question): ?string
    {
        return null;
    }
}
