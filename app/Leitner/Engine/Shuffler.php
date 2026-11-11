<?php

namespace App\Leitner\Engine;

use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Mélange au hasard, reproductible quand une graine est donnée (tests).
 */
final class Shuffler
{
    /**
     * @template TValue
     *
     * @param  Collection<int, TValue>  $items
     * @return Collection<int, TValue>
     */
    public static function shuffle(Collection $items, ?int $seed = null): Collection
    {
        $randomizer = $seed === null ? new Randomizer : new Randomizer(new Mt19937($seed));

        return new Collection($randomizer->shuffleArray($items->values()->all()));
    }
}
