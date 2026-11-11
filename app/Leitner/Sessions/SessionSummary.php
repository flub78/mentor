<?php

namespace App\Leitner\Sessions;

use App\Banques\Questions\Verdict;
use App\Leitner\Models\Session;
use App\Leitner\Models\SessionEntry;
use Illuminate\Support\Collection;

/**
 * Bilan d'une séance (PRD Leitner UC-08, étape 6).
 */
final readonly class SessionSummary
{
    /**
     * @param  array<string, int>  $moves  « 2 → 3 », « 5 → maîtrisée »… → nombre de questions
     * @param  Collection<int, SessionEntry>  $failed  premières réponses fausses
     */
    public function __construct(
        public int $asked,
        public int $correct,
        public int $wrong,
        public int $skipped,
        public int $introduced,
        public int $mastered,
        public array $moves,
        public Collection $failed,
        public NextSession $next,
    ) {}

    public static function for(Session $session): self
    {
        $entries = $session->entries()->with('question')->get();
        $first = $entries->filter(fn (SessionEntry $entry): bool => ! $entry->is_retry && $entry->isAnswered());

        $moves = [];
        foreach ($first as $entry) {
            if ($entry->box_before === null) {
                continue;
            }

            $label = $entry->box_before.' → '.($entry->mastered ? 'maîtrisée' : $entry->box_after);
            $moves[$label] = ($moves[$label] ?? 0) + 1;
        }
        ksort($moves);

        return new self(
            asked: $first->count(),
            correct: $first->where('verdict', Verdict::Juste)->count(),
            wrong: $first->where('verdict', Verdict::Fausse)->count(),
            skipped: $entries->whereNotNull('skipped_at')->count(),
            introduced: $entries->where('is_new', true)->count(),
            mastered: $first->where('mastered', true)->count(),
            moves: $moves,
            failed: $first->where('verdict', Verdict::Fausse)->values(),
            next: NextSession::for($session->progression->fresh()),
        );
    }

    public function successRate(): ?int
    {
        return $this->asked === 0 ? null : (int) round(100 * $this->correct / $this->asked);
    }
}
