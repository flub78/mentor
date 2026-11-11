<?php

namespace App\Leitner\Engine;

use App\Leitner\Questions\Verdict;

/**
 * Mouvements entre boîtes (PRD Leitner §7.2) :
 *
 * - réponse juste : boîte suivante, ou maîtrisée depuis la dernière boîte ;
 *   échecs consécutifs remis à zéro ;
 * - réponse fausse : recul d'une boîte (la boîte 1 reste la boîte 1), ou
 *   retour en boîte 1 si la réponse précédente était déjà fausse.
 */
final class BoxMover
{
    public static function move(int $box, int $consecutiveFailures, Verdict $verdict, int $boxCount): Move
    {
        if ($verdict === Verdict::Juste) {
            return $box >= $boxCount
                ? new Move(null, true, 0)
                : new Move($box + 1, false, 0);
        }

        $newBox = $consecutiveFailures > 0 ? 1 : max(1, $box - 1);

        return new Move($newBox, false, $consecutiveFailures + 1);
    }
}
