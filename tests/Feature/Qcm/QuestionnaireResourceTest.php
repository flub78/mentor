<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Filament\Resources\Questionnaires\Pages\CreateQuestionnaire;
use App\Qcm\Filament\Resources\Questionnaires\Pages\EditQuestionnaire;
use App\Qcm\Filament\Resources\Questionnaires\Pages\ListQuestionnaires;
use App\Qcm\Filament\Resources\Questionnaires\QuestionnaireResource;
use App\Qcm\Filament\Resources\Questionnaires\RelationManagers\QuestionsRelationManager;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Exam;
use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Étape 5 du plan QCM : écrans des questionnaires (PRD QCM UC-08).
 */
class QuestionnaireResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Repeater::fake();
    }

    private function questions(Questionnaire $questionnaire): Testable
    {
        return Livewire::test(QuestionsRelationManager::class, [
            'ownerRecord' => $questionnaire,
            'pageClass' => EditQuestionnaire::class,
        ]);
    }

    public function test_create_questionnaire_then_open_it(): void
    {
        Livewire::test(CreateQuestionnaire::class)
            ->fillForm(['title' => 'Examen blanc planeur', 'version' => '2026-1'])
            ->call('create')
            ->assertHasNoFormErrors();

        $questionnaire = Questionnaire::where('title', 'Examen blanc planeur')->sole();
        $this->assertSame('2026-1', $questionnaire->version);

        Livewire::test(CreateQuestionnaire::class)
            ->fillForm(['title' => ''])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    }

    public function test_list_shows_questions_and_exams_counts(): void
    {
        $questionnaire = Questionnaire::factory()->withOwnQuestions(2)->create();

        $this->get(QuestionnaireResource::getUrl('index'))->assertOk()->assertSee($questionnaire->title);
        Livewire::test(ListQuestionnaires::class)->assertCanSeeTableRecords([$questionnaire]);
    }

    public function test_create_an_own_question_in_the_questionnaire(): void
    {
        $questionnaire = Questionnaire::factory()->withBankQuestions(1)->create();

        $this->questions($questionnaire)
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'statement' => 'Question propre ?',
                'correctAnswer' => ['text' => 'Oui'],
                'wrongAnswers' => [['text' => 'Non', 'explanation' => null]],
                'module' => 'Météo',
            ])
            ->assertHasNoActionErrors();

        $own = $questionnaire->ownQuestions()->sole();
        $this->assertNull($own->bank_id);
        $this->assertSame('Oui', $own->correctAnswer->text);
        $this->assertSame(['Non'], $own->wrongAnswers->pluck('text')->all());
        $this->assertSame($own->id, $questionnaire->questions()->get()->last()->id);
    }

    public function test_edit_and_delete_own_question_but_only_detach_bank_question(): void
    {
        $questionnaire = Questionnaire::factory()->withOwnQuestions(1)->withBankQuestions(1)->create();
        [$own, $fromBank] = $questionnaire->questions()->get()->all();

        $this->questions($questionnaire)
            ->assertActionVisible(TestAction::make(EditAction::class)->table($own))
            ->assertActionHidden(TestAction::make(EditAction::class)->table($fromBank))
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($fromBank))
            ->assertActionHidden(TestAction::make(DetachAction::class)->table($own))
            ->callAction(TestAction::make(DetachAction::class)->table($fromBank))
            ->callAction(TestAction::make(DeleteAction::class)->table($own));

        $this->assertSame(0, $questionnaire->questions()->count());
        $this->assertModelExists($fromBank);
        $this->assertModelMissing($own);
    }

    public function test_questions_can_be_reordered(): void
    {
        $questionnaire = Questionnaire::factory()->withOwnQuestions(1)->withBankQuestions(2)->create();
        [$first, $second, $third] = $questionnaire->questions()->pluck('qcm_questions.id')->all();

        $this->questions($questionnaire)->call('reorderTable', [(string) $third, (string) $first, (string) $second]);

        $this->assertSame([$third, $first, $second], $questionnaire->questions()->pluck('qcm_questions.id')->all());
    }

    public function test_add_questions_from_a_bank(): void
    {
        $bank = Bank::factory()->create();
        $questions = Question::factory()->count(3)->for($bank)->create();
        $questionnaire = Questionnaire::factory()->create();

        $this->questions($questionnaire)
            ->callAction(TestAction::make('addFromBank')->table(), [
                'bank_id' => $bank->id,
                'question_ids' => [$questions[0]->id, $questions[2]->id],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('2 question(s) ajoutée(s)');

        $this->assertSame([$questions[0]->id, $questions[2]->id], $questionnaire->questions()->pluck('qcm_questions.id')->all());

        $this->questions($questionnaire)
            ->callAction(TestAction::make('addFromBank')->table(), ['bank_id' => $bank->id, 'question_ids' => []])
            ->assertHasActionErrors(['question_ids' => 'required']);
    }

    public function test_draw_questions_into_the_questionnaire(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->count(3)->for($bank)->create(['module' => 'Météo']);
        Question::factory()->count(2)->for($bank)->create(['module' => 'Réglementation']);
        $questionnaire = Questionnaire::factory()->create();

        $this->questions($questionnaire)
            ->callAction(TestAction::make('draw')->table(), [
                'bank_ids' => [$bank->id],
                'counts' => [0 => 5],
                'all' => [1 => true],
            ])
            ->assertNotified('5 question(s) tirée(s) et ajoutée(s)');

        $this->assertSame(5, $questionnaire->questions()->count());
    }

    public function test_draw_without_any_count_is_refused_with_a_message(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->create();
        $questionnaire = Questionnaire::factory()->create();

        $this->questions($questionnaire)
            ->callAction(TestAction::make('draw')->table(), ['bank_ids' => [$bank->id], 'counts' => [0 => 0]])
            ->assertNotified('Aucune question demandée');

        $this->assertSame(0, $questionnaire->questions()->count());
    }

    public function test_generate_a_questionnaire_by_draw(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->count(4)->for($bank)->create(['module' => 'Météo']);

        Livewire::test(ListQuestionnaires::class)
            ->callAction('generate', ['title' => 'Blanc tiré', 'bank_ids' => [$bank->id], 'counts' => [0 => 3]])
            ->assertHasNoActionErrors()
            ->assertNotified('Questionnaire « Blanc tiré » généré : 3 question(s)');

        $this->assertSame(3, Questionnaire::where('title', 'Blanc tiré')->sole()->questions()->count());
    }

    public function test_questionnaire_used_by_an_exam_is_not_deleted_and_the_user_is_told_why(): void
    {
        $exam = Exam::factory()->create(['title' => 'Blanc n° 1']);

        Livewire::test(EditQuestionnaire::class, ['record' => $exam->questionnaire_id])
            ->callAction(DeleteAction::class)
            ->assertNotified('Suppression impossible');

        $this->assertModelExists($exam->questionnaire);
    }

    public function test_delete_and_duplicate_from_the_list(): void
    {
        $questionnaire = Questionnaire::factory()->withOwnQuestions(1)->create(['title' => 'Blanc']);

        Livewire::test(ListQuestionnaires::class)
            ->callAction(TestAction::make('duplicate')->table($questionnaire))
            ->assertNotified('Questionnaire « Blanc (copie) » créé');

        Livewire::test(ListQuestionnaires::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($questionnaire));

        $this->assertModelMissing($questionnaire);
        $this->assertSame(1, Questionnaire::where('title', 'Blanc (copie)')->sole()->questions()->count());
    }
}
