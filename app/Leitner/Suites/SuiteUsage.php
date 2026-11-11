<?php

namespace App\Leitner\Suites;

use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Leitner\Models\ProgressItem;
use App\Leitner\Models\Suite;

/**
 * Usage des banques et des questions par les suites, pour les confirmations
 * et la modification de fond d'une question (PRD Leitner UC-01, UC-02 1a).
 */
final class SuiteUsage
{
    /**
     * Nombre de suites qui ont la banque pour source.
     */
    public static function suitesUsingBank(Bank $bank): int
    {
        return Suite::query()->whereHas('sources', fn ($query) => $query->where('bank_id', $bank->id))->count();
    }

    /**
     * Nombre de progressions où la question a été introduite.
     */
    public static function progressionsWithQuestion(Question $question): int
    {
        return ProgressItem::query()->where('question_id', $question->id)->count();
    }

    /**
     * Modification de fond (UC-02, 1a) : la question revient en boîte 1 dans
     * toutes les progressions, maîtrise et échecs consécutifs effacés ; une
     * question suspendue le reste.
     *
     * @return int nombre de progressions touchées
     */
    public static function resetProgress(Question $question): int
    {
        return ProgressItem::query()
            ->where('question_id', $question->id)
            ->update([
                'box' => 1,
                'mastered_at' => null,
                'consecutive_failures' => 0,
                'updated_at' => now(),
            ]);
    }
}
