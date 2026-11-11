<?php

namespace App\Leitner\Suites;

use App\Banques\Models\Question;
use App\Banques\Questions\Difficulty;
use App\Leitner\Engine\Regularity;
use App\Leitner\Models\Progression;
use App\Leitner\Models\Session;
use App\Leitner\Sessions\NextSession;
use Illuminate\Support\Carbon;

/**
 * État d'une suite à afficher (PRD Leitner UC-11) : répartition, questions en
 * banque par difficulté, prochaine séance, dernière séance et régularité.
 */
final readonly class SuiteState
{
    /**
     * @param  array{inBank: int, boxes: array<int, int>, mastered: int, suspended: int, total: int}  $counts
     * @param  array<string, int>  $inBankByDifficulty  libellé de difficulté → nombre
     */
    public function __construct(
        public array $counts,
        public array $inBankByDifficulty,
        public NextSession $next,
        public ?Carbon $lastSessionAt,
        public Regularity $regularity,
        public ?Session $currentSession,
    ) {}

    public static function for(Progression $progression): self
    {
        $inBankByDifficulty = [];
        foreach ($progression->inBank()->get(['id', 'difficulty']) as $question) {
            /** @var Question $question */
            $label = Difficulty::effective($question->difficulty)->getLabel();
            $inBankByDifficulty[$label] = ($inBankByDifficulty[$label] ?? 0) + 1;
        }
        uksort($inBankByDifficulty, fn (string $a, string $b): int => self::rank($a) <=> self::rank($b));

        $sessionDates = $progression->sessions()
            ->whereNotNull('number')
            ->orderBy('started_at')
            ->pluck('started_at')
            ->map(fn (mixed $date): Carbon => Carbon::parse($date));

        return new self(
            counts: $progression->counts(),
            inBankByDifficulty: $inBankByDifficulty,
            next: NextSession::for($progression),
            lastSessionAt: $sessionDates->last(),
            regularity: Regularity::evaluate($sessionDates, $sessionDates->first(), Carbon::now()),
            currentSession: $progression->currentSession(),
        );
    }

    private static function rank(string $label): int
    {
        foreach (Difficulty::cases() as $difficulty) {
            if ($difficulty->getLabel() === $label) {
                return $difficulty->value;
            }
        }

        return 0;
    }
}
