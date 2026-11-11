<?php

namespace App\Leitner\Engine;

use App\Leitner\Models\ProgressItem;
use App\Leitner\Suites\PresentationOrder;
use Illuminate\Support\Collection;

/**
 * Questions d'une séance (PRD Leitner §7.1, §7.4) : la boîte 1 et les
 * boîtes dues, hors questions suspendues ou maîtrisées, dans l'ordre de
 * présentation choisi.
 */
final class SessionComposer
{
    /**
     * @param  Collection<int, ProgressItem>  $items  questions introduites de la progression
     * @return Collection<int, ProgressItem>
     */
    public static function compose(Collection $items, int $sessionNumber, int $boxCount, PresentationOrder $order, ?int $seed = null): Collection
    {
        $due = BoxSchedule::boxesDue($sessionNumber, $boxCount);
        $selected = $items->filter(fn (ProgressItem $item): bool => $item->mastered_at === null
            && $item->suspended_at === null
            && in_array($item->box, $due, true));

        if ($order === PresentationOrder::Melange) {
            return Shuffler::shuffle($selected, $seed);
        }

        $byBox = $selected->groupBy('box')->sortKeys();

        if ($order === PresentationOrder::BoitesHautesDAbord) {
            $byBox = $byBox->reverse();
        }

        return $byBox->flatMap(fn (Collection $box): Collection => Shuffler::shuffle($box, $seed))->values();
    }
}
