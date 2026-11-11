<?php

namespace App\Leitner\Sessions;

use App\Leitner\Engine\BoxSchedule;
use App\Leitner\Models\Progression;

/**
 * Composition prévue de la prochaine séance (PRD Leitner UC-08, UC-11) :
 * numéro, boîtes posées, questions par boîte et nouvelles questions.
 */
final readonly class NextSession
{
    /**
     * @param  list<int>  $boxes  boîtes posées
     * @param  array<int, int>  $perBox  questions par boîte posée
     */
    public function __construct(
        public int $number,
        public array $boxes,
        public array $perBox,
        public int $introductions,
    ) {}

    public static function for(Progression $progression): self
    {
        $suite = $progression->suite;
        $number = $progression->last_session_number + 1;
        $boxes = BoxSchedule::boxesDue($number, $suite->box_count);

        $perBox = [];
        foreach ($boxes as $box) {
            $perBox[$box] = $progression->inBox($box)->count();
        }

        $missing = max(0, $suite->min_box_one - $perBox[1]);
        $introductions = $missing === 0 ? 0 : min($missing, $progression->inBank()->count());

        return new self($number, $boxes, $perBox, $introductions);
    }

    public function total(): int
    {
        return array_sum($this->perBox) + $this->introductions;
    }

    /**
     * « Séance n° 4 — boîtes posées : 1, 2 et 3 — 12 questions, dont 2 nouvelles. »
     */
    public function describe(): string
    {
        $boxes = count($this->boxes) === 1
            ? 'boîte posée : 1'
            : 'boîtes posées : '.implode(', ', array_slice($this->boxes, 0, -1)).' et '.$this->boxes[array_key_last($this->boxes)];
        $total = $this->total();
        $questions = $total.' question'.($total > 1 ? 's' : '');
        $new = match ($this->introductions) {
            0 => '',
            1 => ', dont 1 nouvelle',
            default => ", dont {$this->introductions} nouvelles",
        };

        return "Séance n° {$this->number} — {$boxes} — {$questions}{$new}.";
    }
}
