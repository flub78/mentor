<?php

namespace Tests\Feature\Qcm;

use App\Banques\Models\Bank as CommonBank;
use App\Banques\Models\Question as CommonQuestion;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Exam;
use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use App\Qcm\Questionnaires\QuestionnaireComposer;
use App\Qcm\Questionnaires\QuestionnaireInUseException;
use App\Qcm\Study\ModuleCatalog;
use App\Qcm\Study\StudyDraw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

/**
 * Questionnaires et composition (PRD QCM UC-08), sur les questions du module
 * Banques : toute question vit dans une banque (plan Banques, étape B4).
 */
class QuestionnaireModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_questions_created_from_a_questionnaire_live_in_the_default_bank(): void
    {
        $questionnaire = Questionnaire::factory()->withOwnQuestions(2)->withBankQuestions(2)->create();

        $questions = $questionnaire->questions()->get();
        $this->assertSame([0, 1, 2, 3], $questions->pluck('pivot.position')->all());
        $this->assertSame(2, $questions->where('bank_id', CommonBank::default()->id)->count());
        $this->assertTrue($questions->every(fn (Question $question): bool => $question->bank_id !== null));
    }

    public function test_deleting_a_questionnaire_keeps_its_questions_in_their_banks(): void
    {
        $bank = Bank::factory()->create();
        $questionnaire = Questionnaire::factory()->withOwnQuestions(2)->withBankQuestions(2, $bank)->create();

        $questionnaire->delete();

        $this->assertSame(2, $bank->questions()->count());
        $this->assertSame(2, CommonBank::default()->questions()->count());
        $this->assertDatabaseCount('qcm_questionnaire_questions', 0);
    }

    public function test_a_questionnaire_used_by_an_exam_cannot_be_deleted(): void
    {
        $exam = Exam::factory()->create(['title' => 'Blanc n° 1']);

        $this->expectException(QuestionnaireInUseException::class);
        $this->expectExceptionMessage('« Blanc n° 1 »');

        $exam->questionnaire->delete();
    }

    public function test_deleting_a_bank_question_removes_it_from_questionnaires(): void
    {
        $questionnaire = Questionnaire::factory()->withBankQuestions(2)->create();

        $questionnaire->questions()->first()->delete();

        $this->assertSame(1, $questionnaire->questions()->count());
    }

    public function test_bank_questions_are_added_once_in_the_requested_order(): void
    {
        $bank = Bank::factory()->create();
        $questions = Question::factory()->count(3)->for($bank)->create();
        $questionnaire = Questionnaire::factory()->withOwnQuestions(1)->create();
        $composer = new QuestionnaireComposer;

        $this->assertSame(2, $composer->addBankQuestions($questionnaire, [$questions[2]->id, $questions[0]->id]));
        $this->assertSame(1, $composer->addBankQuestions($questionnaire, [$questions[0]->id, $questions[1]->id]));

        $this->assertSame(
            [$questionnaire->questions()->first()->id, $questions[2]->id, $questions[0]->id, $questions[1]->id],
            $questionnaire->questions()->pluck('banques_questions.id')->all(),
        );
    }

    public function test_questions_of_other_types_are_never_added(): void
    {
        $bank = Bank::factory()->create();
        $other = CommonQuestion::factory()->autoEvaluation()->create(['bank_id' => $bank->id]);
        $questionnaire = Questionnaire::factory()->create();

        $this->assertSame(0, (new QuestionnaireComposer)->addBankQuestions($questionnaire, [$other->id]));
        $this->assertSame([], (new StudyDraw)->draw([$bank->id], ['' => null])['questionIds']);
    }

    public function test_draw_adds_questions_per_module_without_repeating_present_ones(): void
    {
        [$a, $b] = Bank::factory()->count(2)->create();
        Question::factory()->count(3)->for($a)->create(['module' => 'Météo']);
        Question::factory()->count(2)->for($b)->create(['module' => 'météo']);
        Question::factory()->count(2)->for($a)->create(['module' => 'Réglementation']);
        $questionnaire = Questionnaire::factory()->create();
        $composer = new QuestionnaireComposer(new StudyDraw(new Randomizer(new Mt19937(42))));

        $first = $composer->addDrawnQuestions($questionnaire, [$a->id, $b->id], ['météo' => 4, 'réglementation' => null]);
        $this->assertSame(['added' => 6, 'reduced' => []], $first);

        $second = $composer->addDrawnQuestions($questionnaire, [$a->id, $b->id], ['météo' => 3]);
        $this->assertSame(['added' => 1, 'reduced' => ['météo' => 1]], $second);
        $this->assertSame(7, $questionnaire->questions()->distinct()->count('banques_questions.id'));
    }

    public function test_duplicate_shares_the_questions(): void
    {
        $questionnaire = Questionnaire::factory()->withOwnQuestions(1)->withBankQuestions(1)->create(['title' => 'Blanc']);

        $copy = (new QuestionnaireComposer)->duplicate($questionnaire);

        $this->assertSame('Blanc (copie)', $copy->title);
        $this->assertSame(
            $questionnaire->questions()->pluck('banques_questions.id')->all(),
            $copy->questions()->pluck('banques_questions.id')->all(),
        );
    }

    public function test_module_catalog_of_a_questionnaire(): void
    {
        $questionnaire = Questionnaire::factory()->create();
        $first = Question::factory()->create(['bank_id' => CommonBank::default()->id, 'module' => 'Météo']);
        $fromBank = Question::factory()->create(['module' => 'météo']);
        $questionnaire->questions()->attach([$first->id, $fromBank->id]);
        Question::factory()->create(['module' => 'Navigation']);

        $this->assertSame([['key' => 'météo', 'label' => 'Météo', 'count' => 2]], (new ModuleCatalog)->forQuestionnaire($questionnaire));
    }
}
