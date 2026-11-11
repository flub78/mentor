<?php

namespace App\Leitner\Suites;

/**
 * Durée estimée pour maîtriser une question sans erreur selon le nombre de
 * boîtes (PRD Leitner §7.2). Avec la rotation des boîtes hautes
 * (`BoxSchedule`), elle dépend de la séance où la question est introduite :
 * 2^(N−1) séances en moyenne pour N boîtes, de 2^(N−2) + 1 à 3 × 2^(N−2).
 * Une question introduite à la première séance d'une suite est dans le cas
 * le plus lent.
 */
final class MasteryEstimate
{
    /** Rythme de référence, en séances par semaine (PRD Leitner §7.5). */
    public const SESSIONS_PER_WEEK = 5;

    /**
     * Nombre moyen de séances (arrondi à l'entier inférieur).
     */
    public static function sessions(int $boxCount): int
    {
        return 2 ** max(0, $boxCount - 1);
    }

    /**
     * Durées extrêmes selon la séance d'introduction, en séances.
     *
     * @return array{fastest: int, slowest: int}
     */
    public static function range(int $boxCount): array
    {
        $quarterCycle = 2 ** max(0, $boxCount - 2);

        return ['fastest' => $quarterCycle + 1, 'slowest' => 3 * $quarterCycle];
    }

    /**
     * Texte affiché à côté du choix du nombre de boîtes.
     */
    public static function describe(int $boxCount): string
    {
        $sessions = self::sessions($boxCount);
        ['fastest' => $fastest, 'slowest' => $slowest] = self::range($boxCount);
        $weeks = $sessions / self::SESSIONS_PER_WEEK;

        $duration = match (true) {
            $weeks < 1 => 'moins d\'une semaine',
            $weeks < 9 => 'environ '.max(1, (int) round($weeks)).' semaine'.((int) round($weeks) > 1 ? 's' : ''),
            default => 'environ '.(int) round($weeks / 4.35).' mois',
        };

        return "Une question réussie à chaque fois est maîtrisée en {$sessions} séances en moyenne (de {$fastest} à {$slowest} selon la séance où elle est introduite), soit {$duration} à raison de "
            .self::SESSIONS_PER_WEEK.' séances par semaine. Chaque erreur allonge cette durée.';
    }
}
