<?php

namespace App\Leitner\Suites;

/**
 * Durée estimée pour maîtriser une question sans erreur selon le nombre de
 * boîtes (PRD Leitner §7.2) : environ 2^(N−1) séances pour N boîtes.
 */
final class MasteryEstimate
{
    /** Rythme de référence, en séances par semaine (PRD Leitner §7.5). */
    public const SESSIONS_PER_WEEK = 5;

    public static function sessions(int $boxCount): int
    {
        return 2 ** max(0, $boxCount - 1);
    }

    /**
     * Texte affiché à côté du choix du nombre de boîtes.
     */
    public static function describe(int $boxCount): string
    {
        $sessions = self::sessions($boxCount);
        $weeks = $sessions / self::SESSIONS_PER_WEEK;

        $duration = match (true) {
            $weeks < 1 => 'moins d\'une semaine',
            $weeks < 9 => 'environ '.max(1, (int) round($weeks)).' semaine'.((int) round($weeks) > 1 ? 's' : ''),
            default => 'environ '.(int) round($weeks / 4.35).' mois',
        };

        return "Une question réussie à chaque fois est maîtrisée en {$sessions} séances environ, soit {$duration} à raison de "
            .self::SESSIONS_PER_WEEK.' séances par semaine. Chaque erreur allonge cette durée.';
    }
}
