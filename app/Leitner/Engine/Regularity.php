<?php

namespace App\Leitner\Engine;

use Carbon\CarbonInterface;

/**
 * Régularité d'une suite (PRD Leitner §7.5) : séances des derniers jours, et
 * avertissement en dessous du minimum, à partir d'une semaine après la
 * première séance.
 */
final readonly class Regularity
{
    public function __construct(
        public int $sessions,
        public int $windowDays,
        public int $minSessions,
        public bool $warning,
    ) {}

    /**
     * @param  iterable<CarbonInterface>  $sessionDates  dates des séances comptées
     */
    public static function evaluate(iterable $sessionDates, ?CarbonInterface $firstSessionDate, CarbonInterface $now, ?int $windowDays = null, ?int $minSessions = null): self
    {
        $windowDays ??= (int) config('leitner.regularity.window_days');
        $minSessions ??= (int) config('leitner.regularity.min_sessions');
        $windowStart = $now->copy()->subDays($windowDays);

        $count = 0;
        foreach ($sessionDates as $date) {
            if ($date->greaterThan($windowStart) && $date->lessThanOrEqualTo($now)) {
                $count++;
            }
        }

        $warning = $firstSessionDate !== null
            && $firstSessionDate->lessThanOrEqualTo($windowStart)
            && $count < $minSessions;

        return new self($count, $windowDays, $minSessions, $warning);
    }

    public function message(): string
    {
        return "{$this->sessions} séance".($this->sessions > 1 ? 's' : '')." sur les {$this->windowDays} derniers jours. "
            ."Pour bien mémoriser, faites au moins {$this->minSessions} séances par semaine, idéalement une par jour.";
    }
}
