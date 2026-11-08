<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use App\Qcm\Study\ModuleCatalog;
use App\Qcm\Study\StudyDraw;
use App\Qcm\Study\StudySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

/**
 * Étape 2 du plan QCM : regroupement des modules, tirage et session d'étude.
 */
class StudyLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_modules_are_grouped_ignoring_case_with_majority_label(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->count(2)->for($bank)->create(['module' => 'Météo']);
        Question::factory()->for($bank)->create(['module' => 'météo']);
        Question::factory()->for($bank)->create(['module' => 'Réglementation']);
        Question::factory()->for($bank)->create(['module' => null]);

        $this->assertSame([
            ['key' => 'météo', 'label' => 'Météo', 'count' => 3],
            ['key' => 'réglementation', 'label' => 'Réglementation', 'count' => 1],
            ['key' => '', 'label' => 'Sans module', 'count' => 1],
        ], (new ModuleCatalog)->forBanks([$bank->id]));
    }

    public function test_modules_of_several_banks_are_merged_and_other_banks_ignored(): void
    {
        [$a, $b, $other] = Bank::factory()->count(3)->create();
        Question::factory()->for($a)->create(['module' => 'Météo']);
        Question::factory()->for($b)->create(['module' => 'MÉTÉO']);
        Question::factory()->for($other)->create(['module' => 'Météo']);

        $modules = (new ModuleCatalog)->forBanks([$a->id, $b->id]);

        $this->assertCount(1, $modules);
        $this->assertSame(2, $modules[0]['count']);
        $this->assertSame([], (new ModuleCatalog)->forBanks([]));
    }

    public function test_draw_takes_the_requested_number_per_module_without_duplicates(): void
    {
        $bank = Bank::factory()->create();
        $meteo = Question::factory()->count(5)->for($bank)->create(['module' => 'Météo'])->modelKeys();
        $regl = Question::factory()->count(4)->for($bank)->create(['module' => 'Réglementation'])->modelKeys();

        $result = (new StudyDraw)->draw([$bank->id], ['météo' => 3, 'réglementation' => null]);

        $ids = $result['questionIds'];
        $this->assertCount(7, $ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
        $this->assertCount(3, array_intersect($ids, $meteo));
        $this->assertEqualsCanonicalizing($regl, array_values(array_intersect($ids, $regl)));
        $this->assertSame([], $result['reduced']);
    }

    public function test_draw_reduces_a_request_above_the_available_count(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->count(2)->for($bank)->create(['module' => 'Météo']);

        $result = (new StudyDraw)->draw([$bank->id], ['météo' => 10]);

        $this->assertCount(2, $result['questionIds']);
        $this->assertSame(['météo' => 2], $result['reduced']);
    }

    public function test_draw_ignores_modules_with_zero_requested(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->count(2)->for($bank)->create(['module' => 'Météo']);

        $this->assertSame([], (new StudyDraw)->draw([$bank->id], ['météo' => 0])['questionIds']);
    }

    public function test_answers_order_is_shuffled(): void
    {
        $draw = new StudyDraw(new Randomizer(new Mt19937(42)));
        $orders = [];

        for ($i = 0; $i < 20; $i++) {
            $orders[] = implode(',', $draw->shuffleAnswers([1, 2, 3, 4]));
        }

        $this->assertGreaterThan(1, count(array_unique($orders)));
        foreach ($orders as $order) {
            $this->assertEqualsCanonicalizing(['1', '2', '3', '4'], explode(',', $order));
        }
    }

    public function test_session_scores_globally_and_per_module(): void
    {
        $bank = Bank::factory()->create();
        $q1 = Question::factory()->for($bank)->withAnswers()->create(['module' => 'Météo']);
        $q2 = Question::factory()->for($bank)->withAnswers()->create(['module' => 'Météo']);
        $q3 = Question::factory()->for($bank)->withAnswers()->create(['module' => 'Réglementation']);

        $session = StudySession::start([$q1->id, $q2->id, $q3->id], ['météo' => 'Météo', 'réglementation' => 'Réglementation'], new StudyDraw);

        $this->assertTrue($session->answer($q1->correctAnswer->id));
        $session->next();
        $this->assertFalse($session->answer($q2->wrongAnswers->first()->id));
        $session->next();
        $this->assertSame(50, $session->percentage());
        $this->assertSame(1, $session->remaining());
        $this->assertTrue($session->answer($q3->correctAnswer->id));
        $session->next();

        $this->assertTrue($session->isFinished());
        $this->assertSame(67, $session->percentage());
        $this->assertSame([
            ['label' => 'Météo', 'answered' => 2, 'correct' => 1, 'percentage' => 50],
            ['label' => 'Réglementation', 'answered' => 1, 'correct' => 1, 'percentage' => 100],
        ], $session->byModule());
        $this->assertSame([$q2->id], $session->failedQuestionIds());
    }

    public function test_session_refuses_an_answer_of_another_question_and_double_answers(): void
    {
        $q1 = Question::factory()->withAnswers()->create();
        $q2 = Question::factory()->withAnswers()->create();
        $session = StudySession::start([$q1->id], ['' => 'Sans module'], new StudyDraw);

        try {
            $session->answer($q2->correctAnswer->id);
            $this->fail('Une réponse d\'une autre question doit être refusée.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(0, $session->answeredCount());
        }

        $session->answer($q1->correctAnswer->id);
        $this->expectException(\InvalidArgumentException::class);
        $session->answer($q1->correctAnswer->id);
    }

    public function test_session_survives_livewire_serialization(): void
    {
        $q = Question::factory()->withAnswers()->create(['module' => 'Météo']);
        $session = StudySession::start([$q->id], ['météo' => 'Météo'], new StudyDraw);
        $session->answer($q->correctAnswer->id);

        $copy = StudySession::fromLivewire(json_decode(json_encode($session->toLivewire()), true));

        $this->assertEquals($session, $copy);
        $this->assertSame(100, $copy->percentage());
    }
}
