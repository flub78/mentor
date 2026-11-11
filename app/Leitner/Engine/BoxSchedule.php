<?php

namespace App\Leitner\Engine;

/**
 * Boîtes posées à une séance (PRD Leitner §7.1) : la boîte 1 à chaque séance,
 * plus une seule boîte haute, en rotation. À la séance n, c'est la boîte
 * 2 + v, où v est le nombre de fois que n est divisible par 2 : la boîte 2
 * revient une séance sur deux, la boîte 3 une sur quatre, la boîte k une sur
 * 2^(k−1). Quand la boîte calculée dépasse le nombre de boîtes, seule la
 * boîte 1 est posée, pour garder ces fréquences exactes.
 */
final class BoxSchedule
{
    /**
     * @return list<int>
     */
    public static function boxesDue(int $sessionNumber, int $boxCount): array
    {
        $high = 2 + self::twoAdicValuation(max(1, $sessionNumber));

        return $high <= $boxCount ? [1, $high] : [1];
    }

    /**
     * Nombre de fois que n est divisible par 2 (n ≥ 1).
     */
    private static function twoAdicValuation(int $n): int
    {
        $count = 0;

        while ($n % 2 === 0) {
            $n = intdiv($n, 2);
            $count++;
        }

        return $count;
    }
}
