<?php

namespace Tests\Feature\Leitner;

use App\Leitner\Engine\BoxMover;
use App\Leitner\Engine\BoxSchedule;
use App\Leitner\Engine\Introducer;
use App\Leitner\Engine\Move;
use App\Leitner\Engine\Regularity;
use App\Leitner\Engine\SessionComposer;
use App\Leitner\Models\ProgressItem;
use App\Leitner\Models\Question;
use App\Leitner\Questions\Difficulty;
use App\Leitner\Questions\Verdict;
use App\Leitner\Suites\PresentationOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Étape 3 du plan Leitner : règles du PRD §7, sans base de données.
 */
class EngineTest extends TestCase
{
    public function test_box_schedule_matches_the_prd_table(): void
    {
        $expected = [
            1 => [1], 2 => [1, 2], 3 => [1], 4 => [1, 2, 3], 5 => [1], 6 => [1, 2], 7 => [1],
            8 => [1, 2, 3, 4], 9 => [1], 10 => [1, 2], 11 => [1], 12 => [1, 2, 3], 13 => [1],
            14 => [1, 2], 15 => [1], 16 => [1, 2, 3, 4, 5],
        ];

        foreach ($expected as $session => $boxes) {
            $this->assertSame($boxes, BoxSchedule::boxesDue($session, 5), "Séance {$session}, 5 boîtes");
        }

        $this->assertSame([1, 2, 3, 4, 5], BoxSchedule::boxesDue(32, 5));
        $this->assertSame([1, 2, 3, 4, 5, 6, 7], BoxSchedule::boxesDue(64, 7));
        $this->assertSame([1, 2, 3, 4, 5, 6], BoxSchedule::boxesDue(32, 7));
        $this->assertSame([1, 2, 3, 4, 5], BoxSchedule::boxesDue(16, 7));
    }

    /**
     * @return array<string, array{int, int, Verdict, Move}>
     */
    public static function moves(): array
    {
        return [
            'juste, boîte 1' => [1, 0, Verdict::Juste, new Move(2, false, 0)],
            'juste, boîte 3 après un échec' => [3, 1, Verdict::Juste, new Move(4, false, 0)],
            'juste, dernière boîte' => [5, 0, Verdict::Juste, new Move(null, true, 0)],
            'fausse, boîte 1' => [1, 0, Verdict::Fausse, new Move(1, false, 1)],
            'fausse, boîte 4, premier échec' => [4, 0, Verdict::Fausse, new Move(3, false, 1)],
            'fausse, dernière boîte, premier échec' => [5, 0, Verdict::Fausse, new Move(4, false, 1)],
            'fausse, boîte 3, second échec' => [3, 1, Verdict::Fausse, new Move(1, false, 2)],
            'fausse, boîte 1, second échec' => [1, 1, Verdict::Fausse, new Move(1, false, 2)],
            'fausse, boîte 2, troisième échec' => [2, 2, Verdict::Fausse, new Move(1, false, 3)],
        ];
    }

    #[DataProvider('moves')]
    public function test_box_mover(int $box, int $failures, Verdict $verdict, Move $expected): void
    {
        $this->assertEquals($expected, BoxMover::move($box, $failures, $verdict, 5));
    }

    /**
     * @param  array<string, int|null>  $difficulties  énoncé → difficulté
     * @return Collection<int, Question>
     */
    private function questions(array $difficulties): Collection
    {
        $id = 0;

        return (new Collection($difficulties))->map(function (?int $difficulty, string $statement) use (&$id): Question {
            $question = new Question(['statement' => $statement]);
            $question->id = ++$id;
            $question->difficulty = $difficulty === null ? null : Difficulty::from($difficulty);

            return $question;
        })->values();
    }

    public function test_introducer_picks_easy_questions_first_and_counts_none_as_medium(): void
    {
        $candidates = $this->questions(['d1' => 3, 'm1' => 2, 'f1' => 1, 'sans' => null, 'f2' => 1, 'd2' => 3]);

        $picked = Introducer::pick($candidates, 4, seed: 7)->pluck('statement')->all();

        $this->assertEqualsCanonicalizing(['f1', 'f2'], array_slice($picked, 0, 2));
        $this->assertEqualsCanonicalizing(['m1', 'sans'], array_slice($picked, 2, 2));
    }

    public function test_introducer_is_reproducible_with_a_seed_and_random_within_a_level(): void
    {
        $candidates = $this->questions(array_fill_keys(range('a', 'l'), 1));

        $first = Introducer::pick($candidates, 12, seed: 42)->pluck('statement')->all();
        $this->assertSame($first, Introducer::pick($candidates, 12, seed: 42)->pluck('statement')->all());
        $this->assertNotSame(range('a', 'l'), Introducer::pick($candidates, 12, seed: 43)->pluck('statement')->all());
    }

    public function test_introducer_with_fewer_candidates_than_requested(): void
    {
        $this->assertCount(2, Introducer::pick($this->questions(['a' => 1, 'b' => 2]), 10));
        $this->assertCount(0, Introducer::pick($this->questions(['a' => 1]), 0));
    }

    /**
     * @param  list<array{int|null, string}>  $boxes  [boîte, état] par question
     * @return Collection<int, ProgressItem>
     */
    private function items(array $boxes): Collection
    {
        return (new Collection($boxes))->map(function (array $spec, int $index): ProgressItem {
            [$box, $state] = $spec;
            $item = new ProgressItem(['question_id' => $index + 1, 'box' => $box]);
            $item->mastered_at = $state === 'maîtrisée' ? Carbon::now() : null;
            $item->suspended_at = $state === 'suspendue' ? Carbon::now() : null;

            return $item;
        });
    }

    public function test_composer_takes_due_boxes_without_suspended_or_mastered_questions(): void
    {
        $items = $this->items([[1, ''], [2, ''], [3, ''], [2, 'suspendue'], [null, 'maîtrisée'], [1, 'suspendue']]);

        $this->assertEqualsCanonicalizing([1], SessionComposer::compose($items, 1, 5, PresentationOrder::Melange)->pluck('question_id')->all());
        $this->assertEqualsCanonicalizing([1, 2], SessionComposer::compose($items, 2, 5, PresentationOrder::Melange)->pluck('question_id')->all());
        $this->assertEqualsCanonicalizing([1, 2, 3], SessionComposer::compose($items, 4, 5, PresentationOrder::Melange)->pluck('question_id')->all());
    }

    public function test_composer_presentation_orders(): void
    {
        $items = $this->items([[3, ''], [1, ''], [2, ''], [1, ''], [3, '']]);

        $boxOneFirst = SessionComposer::compose($items, 4, 5, PresentationOrder::BoiteUnDAbord, 3)->pluck('box')->all();
        $highFirst = SessionComposer::compose($items, 4, 5, PresentationOrder::BoitesHautesDAbord, 3)->pluck('box')->all();
        $mixed = SessionComposer::compose($items, 4, 5, PresentationOrder::Melange, 3)->pluck('box')->all();

        $this->assertSame([1, 1, 2, 3, 3], $boxOneFirst);
        $this->assertSame([3, 3, 2, 1, 1], $highFirst);
        $this->assertEqualsCanonicalizing([1, 1, 2, 3, 3], $mixed);
    }

    public function test_regularity_counts_a_sliding_window(): void
    {
        $now = Carbon::parse('2026-11-20 18:00');
        $dates = [Carbon::parse('2026-11-13 17:00'), Carbon::parse('2026-11-13 19:00'), Carbon::parse('2026-11-18 10:00'), Carbon::parse('2026-11-20 09:00')];

        $regularity = Regularity::evaluate($dates, Carbon::parse('2026-11-01'), $now, 7, 3);

        $this->assertSame(3, $regularity->sessions);
        $this->assertFalse($regularity->warning);
    }

    public function test_regularity_warns_below_the_threshold(): void
    {
        $now = Carbon::parse('2026-11-20 18:00');

        $regularity = Regularity::evaluate([Carbon::parse('2026-11-19'), Carbon::parse('2026-11-15')], Carbon::parse('2026-11-01'), $now, 7, 3);

        $this->assertTrue($regularity->warning);
        $this->assertSame('2 séances sur les 7 derniers jours. Pour bien mémoriser, faites au moins 3 séances par semaine, idéalement une par jour.', $regularity->message());
    }

    public function test_regularity_does_not_warn_during_the_first_week(): void
    {
        $now = Carbon::parse('2026-11-20 18:00');

        $this->assertFalse(Regularity::evaluate([Carbon::parse('2026-11-15')], Carbon::parse('2026-11-15'), $now, 7, 3)->warning);
        $this->assertFalse(Regularity::evaluate([], null, $now, 7, 3)->warning);
        $this->assertTrue(Regularity::evaluate([], Carbon::parse('2026-11-13 18:00'), $now, 7, 3)->warning);
    }

    public function test_regularity_reads_its_thresholds_from_the_configuration(): void
    {
        config(['leitner.regularity.window_days' => 14, 'leitner.regularity.min_sessions' => 5]);

        $regularity = Regularity::evaluate([], null, Carbon::now());

        $this->assertSame([14, 5], [$regularity->windowDays, $regularity->minSessions]);
    }
}
