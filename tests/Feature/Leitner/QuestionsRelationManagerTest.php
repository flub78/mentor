<?php

namespace Tests\Feature\Leitner;

use App\Leitner\Filament\Resources\Banks\Pages\EditBank;
use App\Leitner\Filament\Resources\Banks\RelationManagers\QuestionsRelationManager;
use App\Leitner\Models\Bank;
use App\Leitner\Models\Question;
use App\Leitner\Questions\Difficulty;
use App\Leitner\Questions\QuestionType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Étape 1 du plan Leitner : gestion des questions d'une banque (PRD Leitner
 * UC-02), un formulaire par type.
 */
class QuestionsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('leitner');
        Repeater::fake();
        $this->bank = Bank::factory()->create();
    }

    private function manager(): Testable
    {
        return Livewire::test(QuestionsRelationManager::class, [
            'ownerRecord' => $this->bank,
            'pageClass' => EditBank::class,
        ]);
    }

    private function create(array $data): Testable
    {
        return $this->manager()->callAction(TestAction::make(CreateAction::class)->table(), $data);
    }

    public function test_create_qcm_question(): void
    {
        $this->create([
            'type' => 'qcm',
            'statement' => 'Quelle est la capitale de l\'Autriche ?',
            'payload' => [
                'correct' => 'Vienne',
                'wrong' => [
                    ['text' => 'Salzbourg', 'explanation' => 'Ville de Mozart, pas la capitale.'],
                    ['text' => 'Graz', 'explanation' => null],
                ],
                'shuffle' => false,
            ],
            'explanation' => 'Vienne est la capitale depuis le Moyen Âge.',
            'difficulty' => Difficulty::Facile->value,
            'theme' => 'Europe',
        ])->assertHasNoActionErrors()->assertNotified('Question créée');

        $question = $this->bank->questions()->sole();
        $this->assertSame(QuestionType::Qcm, $question->type);
        $this->assertSame(Difficulty::Facile, $question->difficulty);
        $this->assertSame('Europe', $question->theme);
        $this->assertSame([
            'correct' => 'Vienne',
            'wrong' => [
                ['text' => 'Salzbourg', 'explanation' => 'Ville de Mozart, pas la capitale.'],
                ['text' => 'Graz', 'explanation' => null],
            ],
            'shuffle' => false,
        ], $question->payload);
    }

    public function test_create_form_proposes_a_qcm_with_three_wrong_answers(): void
    {
        $this->manager()
            ->mountAction(TestAction::make(CreateAction::class)->table())
            ->assertSchemaStateSet(fn (array $state): bool => $state['type'] === 'qcm'
                && count($state['payload']['wrong']) === 3
                && $state['payload']['shuffle'] === true, 'mountedActionSchema0');
    }

    public function test_create_true_false_question(): void
    {
        $this->create([
            'type' => 'vrai_faux',
            'statement' => 'Le Danube traverse Budapest.',
            'payload' => ['value' => 'vrai'],
        ])->assertHasNoActionErrors();

        $question = $this->bank->questions()->sole();
        $this->assertSame(QuestionType::VraiFaux, $question->type);
        $this->assertSame(['value' => true], $question->payload);
    }

    public function test_create_self_evaluation_question(): void
    {
        $this->create([
            'type' => 'auto_evaluation',
            'statement' => 'Vous rappelez-vous comment démontrer le théorème de Pythagore ?',
            'payload' => ['answer' => 'Découpage d\'un carré en quatre triangles rectangles.'],
        ])->assertHasNoActionErrors();

        $this->assertSame(
            ['answer' => 'Découpage d\'un carré en quatre triangles rectangles.'],
            $this->bank->questions()->sole()->payload,
        );
    }

    public function test_create_numeric_question_with_tolerance(): void
    {
        $this->create([
            'type' => 'valeur_numerique',
            'statement' => 'Altitude du mont Blanc ?',
            'payload' => ['value' => '4806', 'unit' => 'm', 'tolerance_kind' => 'pourcentage', 'tolerance' => '1'],
        ])->assertHasNoActionErrors();

        // Le JSON ne distingue pas 4806 de 4806.0 : comparaison des valeurs.
        $this->assertEquals(
            ['value' => 4806, 'tolerance_kind' => 'pourcentage', 'tolerance' => 1, 'unit' => 'm'],
            $this->bank->questions()->sole()->payload,
        );
    }

    public function test_numeric_tolerance_is_required_unless_none(): void
    {
        $this->create([
            'type' => 'valeur_numerique',
            'statement' => 'Altitude du mont Blanc ?',
            'payload' => ['value' => '4806', 'tolerance_kind' => 'absolue', 'tolerance' => null],
        ])->assertHasActionErrors(['payload.tolerance' => 'required']);

        $this->create([
            'type' => 'valeur_numerique',
            'statement' => 'Altitude du mont Blanc ?',
            'payload' => ['value' => '4806', 'tolerance_kind' => 'aucune'],
        ])->assertHasNoActionErrors();

        $this->assertNull($this->bank->questions()->sole()->payload['tolerance']);
    }

    public function test_incomplete_forms_are_refused(): void
    {
        $this->create([
            'type' => 'qcm',
            'statement' => '',
            'payload' => ['correct' => '', 'wrong' => [], 'shuffle' => true],
        ])->assertHasActionErrors(['statement' => 'required', 'payload.correct' => 'required', 'payload.wrong']);

        $this->create(['type' => 'vrai_faux', 'statement' => 'Affirmation', 'payload' => ['value' => null]])
            ->assertHasActionErrors(['payload.value' => 'required']);

        $this->create(['type' => 'auto_evaluation', 'statement' => 'Question', 'payload' => ['answer' => '']])
            ->assertHasActionErrors(['payload.answer' => 'required']);

        $this->create(['type' => 'valeur_numerique', 'statement' => 'Question', 'payload' => ['value' => 'douze', 'tolerance_kind' => 'aucune']])
            ->assertHasActionErrors(['payload.value' => 'numeric']);

        $this->assertSame(0, $this->bank->questions()->count());
    }

    public function test_edit_question_keeps_its_type(): void
    {
        $question = Question::factory()->for($this->bank)->vraiFaux(true)->create();

        $this->manager()
            ->mountAction(TestAction::make(EditAction::class)->table($question))
            ->assertFormFieldDisabled('type')
            ->assertSchemaStateSet(fn (array $state): bool => $state['payload']['value'] === 'vrai', 'mountedActionSchema0');

        $this->manager()
            ->callAction(TestAction::make(EditAction::class)->table($question), [
                'statement' => 'Le Danube traverse Bratislava.',
                'payload' => ['value' => 'faux'],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Question enregistrée');

        $question->refresh();
        $this->assertSame(QuestionType::VraiFaux, $question->type);
        $this->assertSame('Le Danube traverse Bratislava.', $question->statement);
        $this->assertSame(['value' => false], $question->payload);
    }

    public function test_edit_qcm_answers(): void
    {
        $question = Question::factory()->for($this->bank)->qcm()->create();

        $this->manager()
            ->callAction(TestAction::make(EditAction::class)->table($question), [
                'payload' => ['correct' => 'Berne', 'wrong' => [['text' => 'Zurich', 'explanation' => null]], 'shuffle' => true],
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(['correct' => 'Berne', 'wrong' => [['text' => 'Zurich', 'explanation' => null]], 'shuffle' => true], $question->fresh()->payload);
    }

    public function test_delete_question(): void
    {
        $question = Question::factory()->for($this->bank)->create();

        $this->manager()
            ->callAction(TestAction::make(DeleteAction::class)->table($question))
            ->assertNotified('Question supprimée');

        $this->assertModelMissing($question);
    }

    public function test_filter_questions_by_type_difficulty_and_theme(): void
    {
        $qcm = Question::factory()->for($this->bank)->qcm()->difficulty(1)->create(['theme' => 'Europe']);
        $trueFalse = Question::factory()->for($this->bank)->vraiFaux()->difficulty(3)->create(['theme' => 'Asie']);

        $this->manager()
            ->filterTable('type', 'vrai_faux')
            ->assertCanSeeTableRecords([$trueFalse])
            ->assertCanNotSeeTableRecords([$qcm]);

        $this->manager()
            ->filterTable('difficulty', Difficulty::Facile->value)
            ->assertCanSeeTableRecords([$qcm])
            ->assertCanNotSeeTableRecords([$trueFalse]);

        $this->manager()
            ->filterTable('theme', 'Asie')
            ->assertCanSeeTableRecords([$trueFalse])
            ->assertCanNotSeeTableRecords([$qcm]);
    }

    public function test_upload_image_with_its_description(): void
    {
        Storage::fake(Question::IMAGE_DISK);

        $this->create([
            'type' => 'auto_evaluation',
            'statement' => 'Quel est ce monument ?',
            'image_path' => UploadedFile::fake()->image('monument.png'),
            'image_alt' => 'Grande tour métallique',
            'payload' => ['answer' => 'La tour Eiffel'],
        ])->assertHasNoActionErrors();

        $question = $this->bank->questions()->sole();
        $this->assertSame('Grande tour métallique', $question->image_alt);
        Storage::disk(Question::IMAGE_DISK)->assertExists($question->image_path);
    }

    public function test_image_requires_a_description(): void
    {
        Storage::fake(Question::IMAGE_DISK);

        $this->create([
            'type' => 'auto_evaluation',
            'statement' => 'Quel est ce monument ?',
            'image_path' => UploadedFile::fake()->image('monument.png'),
            'image_alt' => '',
            'payload' => ['answer' => 'La tour Eiffel'],
        ])->assertHasActionErrors(['image_alt' => 'required']);

        $this->assertSame(0, $this->bank->questions()->count());
    }

    public function test_reject_a_file_that_is_not_an_image(): void
    {
        Storage::fake(Question::IMAGE_DISK);

        $this->create([
            'type' => 'auto_evaluation',
            'statement' => 'Quel est ce monument ?',
            'image_path' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            'image_alt' => 'Notes',
            'payload' => ['answer' => 'La tour Eiffel'],
        ])->assertHasActionErrors(['image_path']);

        $this->assertSame(0, $this->bank->questions()->count());
    }
}
