<?php

namespace App\Qcm\Questionnaires;

use App\Banques\Models\Question as BankQuestion;
use App\Qcm\Models\Question;

/**
 * Questions des banques que le module QCM ne pose pas (PRD Banques §7,
 * PRD QCM §6) : seuls les QCM et les vrai / faux sont utilisés, les autres
 * types sont écartés avec un avertissement.
 */
final class UsableQuestions
{
    /**
     * Nombre de questions des banques données qui ne sont pas utilisables.
     *
     * @param  list<int>  $bankIds
     */
    public static function ignoredCount(array $bankIds): int
    {
        if ($bankIds === []) {
            return 0;
        }

        return BankQuestion::query()->whereIn('bank_id', $bankIds)->count()
            - Question::query()->whereIn('bank_id', $bankIds)->count();
    }

    /**
     * Avertissement pour les banques données, ou null si rien n'est écarté.
     *
     * @param  list<int>  $bankIds
     */
    public static function notice(array $bankIds): ?string
    {
        return self::message(self::ignoredCount($bankIds));
    }

    public static function message(int $ignored): ?string
    {
        return match (true) {
            $ignored <= 0 => null,
            $ignored === 1 => '1 question n\'était pas de type QCM ou vrai / faux et n\'a pas été utilisée.',
            default => "{$ignored} questions n'étaient pas de type QCM ou vrai / faux et n'ont pas été utilisées.",
        };
    }
}
