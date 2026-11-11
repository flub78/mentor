<?php

namespace Tests\Feature\Qcm;

use App\Banques\Filament\Resources\Banks\BankResource;
use App\Banques\Models\Bank as CommonBank;
use App\Banques\Models\Question as CommonQuestion;
use App\Banques\Usage\UsageRegistry;
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
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Filament\Notifications\Notification;
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

    public function test_a_new_question_goes_to_the_default_bank_unless_another_is_chosen(): void
    {
        $questionnaire = Questionnaire::factory()->withBankQuestions(1)->create();
        $bank = Bank::factory()->create(['name' => 'Météo']);

        $this->questions($questionnaire)
            ->mountAction(TestAction::make(CreateAction::class)->table())
            ->assertSchemaStateSet(fn (array $state): bool => $state['bank_id'] === CommonBank::default()->id && $state['type'] === 'qcm', 'mountedActionSchema0');

        $this->questions($questionnaire)
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'bank_id' => $bank->id,
                'type' => 'qcm',
                'statement' => 'Nouvelle question ?',
                'payload' => ['correct' => 'Oui', 'wrong' => [['text' => 'Non', 'explanation' => null]], 'shuffle' => true],
                'theme' => 'Nuages',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Question créée');

        $created = $questionnaire->questions()->get()->last();
        $this->assertSame('Nouvelle question ?', $created->statement);
        $this->assertTrue($created->bank->is($bank));
        $this->assertSame('Oui', $created->correctAnswer->text);
        $this->assertSame(['Non'], $created->wrongAnswers->pluck('text')->all());
    }

    public function test_a_true_false_question_can_be_created_from_a_questionnaire(): void
    {
        $questionnaire = Questionnaire::factory()->create();

        $this->questions($questionnaire)
            ->callAction(TestAction::make(CreateAction::class)->table(), [
                'bank_id' => CommonBank::default()->id,
                'type' => 'vrai_faux',
                'statement' => 'Le cumulonimbus est un nuage d\'orage.',
                'payload' => ['value' => 'vrai'],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(['Vrai', 'Faux'], $questionnaire->questions()->sole()->answers->pluck('text')->all());
    }

    public function test_questions_are_modified_in_their_bank_and_only_detached_here(): void
    {
        $questionnaire = Questionnaire::factory()->withBankQuestions(1)->create();
        $question = $questionnaire->questions()->sole();

        $this->questions($questionnaire)
            ->assertActionHasUrl(TestAction::make('openBank')->table($question), BankResource::getUrl('edit', ['record' => $question->bank_id]))
            ->callAction(TestAction::make(DetachAction::class)->table($question));

        $this->assertSame(0, $questionnaire->questions()->count());
        $this->assertModelExists($question);
    }

    public function test_banks_with_questions_of_other_types_are_announced(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->create();
        CommonQuestion::factory()->autoEvaluation()->create(['bank_id' => $bank->id]);
        $questionnaire = Questionnaire::factory()->create();

        $usable = $bank->questions()->sole();

        $this->questions($questionnaire)
            ->callAction(TestAction::make('addFromBank')->table(), ['bank_id' => $bank->id, 'question_ids' => [$usable->id]])
            ->assertHasNoActionErrors()
            ->assertNotified(Notification::make()->success()->title('1 question(s) ajoutée(s)')->body('1 question n\'était pas de type QCM ou vrai / faux et n\'a pas été utilisée.'));
    }

    public function test_questions_can_be_reordered(): void
    {
        $questionnaire = Questionnaire::factory()->withOwnQuestions(1)->withBankQuestions(2)->create();
        [$first, $second, $third] = $questionnaire->questions()->pluck('banques_questions.id')->all();

        $this->questions($questionnaire)->call('reorderTable', [(string) $third, (string) $first, (string) $second]);

        $this->assertSame([$third, $first, $second], $questionnaire->questions()->pluck('banques_questions.id')->all());
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

        $this->assertSame([$questions[0]->id, $questions[2]->id], $questionnaire->questions()->pluck('banques_questions.id')->all());

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

    public function test_bank_and_question_deletions_announce_the_questionnaires_using_them(): void
    {
        $bank = Bank::factory()->create();
        $questionnaire = Questionnaire::factory()->withBankQuestions(1, $bank)->create(['title' => 'Blanc de mars']);
        $question = $questionnaire->questions()->sole();
        $registry = app(UsageRegistry::class);

        $this->assertStringContainsString('Ses questions seront retirées du questionnaire QCM qui les utilise.', $registry->bankDeletionNotice($bank));
        $this->assertStringContainsString('Elle sera retirée des questionnaires QCM : « Blanc de mars ».', $registry->questionDeletionNotice($question));
        $this->assertFalse($registry->hasSubstantiveChangeEffect($question));
    }
}
