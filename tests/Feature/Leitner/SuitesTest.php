<?php

namespace Tests\Feature\Leitner;

use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Leitner\Engine\BoxSchedule;
use App\Leitner\Models\ProgressItem;
use App\Leitner\Models\Suite;
use App\Leitner\Suites\MasteryEstimate;
use App\Leitner\Suites\PersonalQuestionCreator;
use App\Leitner\Suites\PresentationOrder;
use App\Leitner\Suites\SuiteSettingsUpdater;
use App\Leitner\Suites\SuiteUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Étape 2 du plan Leitner : suites, progression et paramètres
 * (PRD Leitner UC-05, UC-03, UC-02 1a).
 */
class SuitesTest extends TestCase
{
    use RefreshDatabase;

    private function updater(): SuiteSettingsUpdater
    {
        return new SuiteSettingsUpdater;
    }

    /**
     * Place `$count` questions de la banque dans la boîte `$box`.
     *
     * @return list<ProgressItem>
     */
    private function placeInBox(Suite $suite, Bank $bank, int $count, int $box): array
    {
        return Question::factory()->count($count)->for($bank)->create()
            ->map(fn (Question $question): ProgressItem => $suite->progression->items()->create([
                'question_id' => $question->id,
                'box' => $box,
                'introduced_at' => now(),
            ]))
            ->all();
    }

    public function test_new_suite_has_default_settings_and_everything_in_bank(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->count(4)->for($bank)->create();

        $suite = $this->updater()->create(['name' => 'Capitales'], [$bank->id]);

        $this->assertSame(5, $suite->box_count);
        $this->assertSame(10, $suite->min_box_one);
        $this->assertSame(PresentationOrder::Melange, $suite->presentation_order);
        $this->assertTrue($suite->retry_errors);
        $this->assertSame(0, $suite->progression->last_session_number);
        $this->assertSame(
            ['inBank' => 4, 'boxes' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0], 'mastered' => 0, 'suspended' => 0, 'total' => 4],
            $suite->progression->counts(),
        );
    }

    public function test_suite_gathers_several_banks(): void
    {
        $meteo = Bank::factory()->create();
        $regl = Bank::factory()->create();
        Question::factory()->count(2)->for($meteo)->create();
        Question::factory()->count(3)->for($regl)->create();
        Question::factory()->count(5)->create();

        $suite = $this->updater()->create(['name' => 'Théorie ULM'], [$meteo->id, $regl->id]);

        $this->assertSame(5, $suite->progression->suiteQuestions()->count());
        $this->assertEqualsCanonicalizing([$meteo->id, $regl->id], $suite->bankIds());
    }

    public function test_two_suites_on_the_same_bank_progress_independently(): void
    {
        $bank = Bank::factory()->create();
        $question = Question::factory()->for($bank)->create();
        $first = Suite::factory()->withBanks($bank)->create();
        $second = Suite::factory()->withBanks($bank)->create();
        $before = $question->fresh()->toArray();

        $first->progression->introduce($question);

        $this->assertSame(1, $first->progression->inBox(1)->count());
        $this->assertSame(0, $first->progression->inBank()->count());
        $this->assertSame(1, $second->progression->inBank()->count());
        $this->assertSame($before, $question->fresh()->toArray());
    }

    public function test_question_added_to_a_source_bank_is_in_bank(): void
    {
        $bank = Bank::factory()->create();
        $suite = Suite::factory()->withBanks($bank)->create();

        $question = Question::factory()->for($bank)->create();

        $this->assertTrue($suite->progression->inBank()->get()->contains($question));
    }

    public function test_counts_separate_boxes_mastered_and_suspended(): void
    {
        $bank = Bank::factory()->create();
        $suite = Suite::factory()->withBanks($bank)->create(['box_count' => 3]);
        $this->placeInBox($suite, $bank, 2, 1);
        [$suspended] = $this->placeInBox($suite, $bank, 1, 2);
        $suspended->update(['suspended_at' => now()]);
        [$mastered] = $this->placeInBox($suite, $bank, 1, 3);
        $mastered->update(['mastered_at' => now()]);
        Question::factory()->for($bank)->create();

        $counts = $suite->progression->counts();

        $this->assertSame([1 => 2, 2 => 0, 3 => 0], $counts['boxes']);
        $this->assertSame(1, $counts['suspended']);
        $this->assertSame(1, $counts['mastered']);
        $this->assertSame(1, $counts['inBank']);
        $this->assertSame(5, $counts['total']);
        $this->assertNull($mastered->fresh()->box);
        $this->assertSame(2, $suspended->fresh()->box);
    }

    public function test_question_in_circulation_must_be_in_an_existing_box(): void
    {
        $bank = Bank::factory()->create();
        $suite = Suite::factory()->withBanks($bank)->create(['box_count' => 5]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Une question en circulation doit être dans une boîte de 1 à 5 (boîte reçue : 6).');

        $this->placeInBox($suite, $bank, 1, 6);
    }

    public function test_reducing_the_box_count_moves_questions_to_the_new_last_box(): void
    {
        $bank = Bank::factory()->create();
        $suite = Suite::factory()->withBanks($bank)->create(['box_count' => 6]);
        $this->placeInBox($suite, $bank, 1, 2);
        $this->placeInBox($suite, $bank, 2, 5);
        $this->placeInBox($suite, $bank, 1, 6);

        $preview = $this->updater()->preview($suite, 4, [$bank->id]);
        $this->assertSame(3, $preview->movedItems);
        $this->assertSame(['Réduire le nombre de boîtes place 3 questions dans la boîte 4.'], $preview->describe());

        $change = $this->updater()->update($suite, ['box_count' => 4], [$bank->id]);

        $this->assertSame(3, $change->movedItems);
        $this->assertSame([1 => 0, 2 => 1, 3 => 0, 4 => 3], $suite->fresh()->progression->counts()['boxes']);
    }

    public function test_increasing_the_box_count_moves_nothing(): void
    {
        $bank = Bank::factory()->create();
        $suite = Suite::factory()->withBanks($bank)->create(['box_count' => 3]);
        $this->placeInBox($suite, $bank, 2, 3);

        $change = $this->updater()->update($suite, ['box_count' => 7], [$bank->id]);

        $this->assertFalse($change->isDestructive());
        $this->assertSame(2, $suite->fresh()->progression->counts()['boxes'][3]);
    }

    public function test_adding_a_source_puts_its_questions_in_bank(): void
    {
        $first = Bank::factory()->create();
        $second = Bank::factory()->create();
        Question::factory()->count(3)->for($second)->create();
        $suite = Suite::factory()->withBanks($first)->create();

        $change = $this->updater()->update($suite, [], [$first->id, $second->id]);

        $this->assertFalse($change->isDestructive());
        $this->assertSame(3, $suite->progression->inBank()->count());
        $this->assertSame(0, $suite->progression->items()->count());
    }

    public function test_removing_a_source_drops_the_progression_of_its_questions(): void
    {
        $kept = Bank::factory()->create();
        $removed = Bank::factory()->create();
        $suite = Suite::factory()->withBanks($kept, $removed)->create();
        $this->placeInBox($suite, $kept, 1, 2);
        $this->placeInBox($suite, $removed, 2, 3);

        $this->assertSame(
            ['Retirer une banque fait perdre leur progression à 2 questions déjà introduites (elles disparaissent des boîtes).'],
            $this->updater()->preview($suite, 5, [$kept->id])->describe(),
        );

        $change = $this->updater()->update($suite, [], [$kept->id]);

        $this->assertSame(2, $change->lostItems);
        $this->assertSame([$kept->id], $suite->bankIds());
        $this->assertSame(1, $suite->progression->items()->count());
        $this->assertModelExists($removed);
        $this->assertSame(2, $removed->questions()->count());
    }

    public function test_personal_question_goes_to_a_single_personal_bank(): void
    {
        $suite = Suite::factory()->create(['name' => 'Capitales']);
        $creator = new PersonalQuestionCreator;
        $attributes = Question::factory()->vraiFaux()->make()->only(['type', 'statement', 'payload']);

        $inBoxOne = $creator->create($suite, $attributes, intoBoxOne: true);
        $inBank = $creator->create($suite->fresh(), $attributes, intoBoxOne: false);

        $suite->refresh();
        $this->assertSame('Capitales — mes questions', $suite->personalBank->name);
        $this->assertSame([$suite->personal_bank_id], $suite->bankIds());
        $this->assertSame(2, $suite->personalBank->questions()->count());
        $this->assertSame(1, $suite->progression->inBox(1)->where('question_id', $inBoxOne->id)->count());
        $this->assertTrue($suite->progression->inBank()->get()->contains($inBank));
    }

    public function test_personal_bank_name_avoids_existing_names(): void
    {
        Bank::factory()->create(['name' => 'Capitales — mes questions']);
        $suite = Suite::factory()->create(['name' => 'Capitales']);

        (new PersonalQuestionCreator)->create($suite, Question::factory()->autoEvaluation()->make()->only(['type', 'statement', 'payload']), true);

        $this->assertSame('Capitales — mes questions (2)', $suite->fresh()->personalBank->name);
    }

    public function test_substantive_change_puts_the_question_back_in_box_one_everywhere(): void
    {
        $bank = Bank::factory()->create();
        $first = Suite::factory()->withBanks($bank)->create();
        $second = Suite::factory()->withBanks($bank)->create();
        [$item] = $this->placeInBox($first, $bank, 1, 4);
        $item->update(['consecutive_failures' => 1]);
        $mastered = $second->progression->items()->create([
            'question_id' => $item->question_id,
            'box' => 2,
            'introduced_at' => now(),
        ]);
        $mastered->update(['mastered_at' => now()]);

        $this->assertSame(2, SuiteUsage::progressionsWithQuestion($item->question));
        $this->assertSame(2, SuiteUsage::resetProgress($item->question));

        $this->assertSame([1, 0], [$item->fresh()->box, $item->fresh()->consecutive_failures]);
        $this->assertSame(1, $mastered->fresh()->box);
        $this->assertNull($mastered->fresh()->mastered_at);
        $this->assertSame(2, SuiteUsage::suitesUsingBank($bank));
    }

    public function test_deleting_a_suite_keeps_banks_and_personal_bank(): void
    {
        $bank = Bank::factory()->create();
        $suite = Suite::factory()->withBanks($bank)->create();
        (new PersonalQuestionCreator)->create($suite, Question::factory()->autoEvaluation()->make()->only(['type', 'statement', 'payload']), true);
        $personal = $suite->fresh()->personalBank;

        $suite->delete();

        $this->assertModelExists($bank);
        $this->assertModelExists($personal);
        $this->assertSame(0, ProgressItem::count());
    }

    public function test_mastery_estimate(): void
    {
        $this->assertSame(16, MasteryEstimate::sessions(5));
        $this->assertSame(['fastest' => 9, 'slowest' => 24], MasteryEstimate::range(5));
        $this->assertStringContainsString('16 séances en moyenne (de 9 à 24 selon la séance où elle est introduite), soit environ 3 semaines', MasteryEstimate::describe(5));
        $this->assertStringContainsString('4 séances en moyenne (de 3 à 6 selon la séance où elle est introduite), soit moins d\'une semaine', MasteryEstimate::describe(3));
        $this->assertStringContainsString('64 séances en moyenne (de 33 à 96 selon la séance où elle est introduite), soit environ 3 mois', MasteryEstimate::describe(7));
    }

    /**
     * L'estimation affichée suit la règle des séances : on rejoue
     * `BoxSchedule` pour une question introduite à chaque séance possible
     * d'un cycle, sans erreur.
     */
    public function test_mastery_estimate_matches_the_session_schedule(): void
    {
        for ($boxes = Suite::MIN_BOXES; $boxes <= Suite::MAX_BOXES; $boxes++) {
            $cycle = 2 ** ($boxes - 1);
            $durations = [];

            for ($introduced = 1; $introduced <= $cycle; $introduced++) {
                $box = 1;
                $session = $introduced;

                while (true) {
                    if (in_array($box, BoxSchedule::boxesDue($session, $boxes), true)) {
                        if ($box === $boxes) {
                            break;
                        }

                        $box++;
                    }

                    $session++;
                }

                $durations[] = $session - $introduced + 1;
            }

            $this->assertSame(['fastest' => min($durations), 'slowest' => max($durations)], MasteryEstimate::range($boxes), "{$boxes} boîtes");
            $this->assertSame(MasteryEstimate::sessions($boxes), (int) floor(array_sum($durations) / $cycle), "{$boxes} boîtes");
        }
    }
}
