<?php

namespace App\Leitner\Engine;

use App\Leitner\Models\Question;
use App\Leitner\Questions\Difficulty;
use Illuminate\Support\Collection;

/**
 * Choix des nouvelles questions (PRD Leitner §7.3) : par difficulté
 * croissante (sans niveau = moyen), au hasard au sein d'un niveau (Q8,
 * choix provisoire).
 */
final class Introducer
{
    /**
     * @param  Collection<int, Question>  $candidates  questions en banque
     * @return Collection<int, Question>
     */
    public static function pick(Collection $candidates, int $count, ?int $seed = null): Collection
    {
        if ($count <= 0) {
            return new Collection;
        }

        return $candidates
            ->groupBy(fn (Question $question): int => Difficulty::effective($question->difficulty)->value)
            ->sortKeys()
            ->flatMap(fn (Collection $level): Collection => Shuffler::shuffle($level, $seed))
            ->take($count)
            ->values();
    }
}
