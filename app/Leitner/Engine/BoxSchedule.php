<?php

namespace App\Leitner\Engine;

/**
 * Boîtes posées à une séance (PRD Leitner §7.1) : la boîte k est posée à la
 * séance n si n est un multiple de 2^(k−1). La boîte 1 l'est à chaque séance.
 */
final class BoxSchedule
{
    /**
     * @return list<int>
     */
    public static function boxesDue(int $sessionNumber, int $boxCount): array
    {
        $boxes = [];

        for ($box = 1; $box <= $boxCount; $box++) {
            if ($sessionNumber % (2 ** ($box - 1)) === 0) {
                $boxes[] = $box;
            }
        }

        return $boxes;
    }
}
