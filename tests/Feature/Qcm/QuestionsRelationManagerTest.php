<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Filament\Resources\Banks\Pages\EditBank;
use App\Qcm\Filament\Resources\Banks\RelationManagers\QuestionsRelationManager;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Étape 1 du plan QCM : gestion des questions d'une banque (PRD QCM UC-04).
 */
class QuestionsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
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

    /**
     * @return array<string, mixed>
     */
    private function questionData(array $overrides = []): array
    {
        return [
            'statement' => 'Quel nuage annonce un orage ?',
            'correctAnswer' => ['text' => 'Cumulonimbus'],
            'wrongAnswers' => [
                ['text' => 'Cirrus', 'explanation' => 'Nuage de haute altitude, sans convection.'],
                ['text' => 'Stratus', 'explanation' => null],
                ['text' => 'Altostratus', 'explanation' => null],
            ],
            'explanation' => 'Le cumulonimbus est le nuage d\'orage.',
            'module' => 'Météorologie',
            ...$overrides,
        ];
    }

    public function test_create_question_with_three_wrong_answers(): void
    {
        $this->manager()
            ->callAction(TestAction::make(CreateAction::class)->table(), $this->questionData())
            ->assertHasNoActionErrors();

        $question = $this->bank->questions()->sole();
        $this->assertSame('Quel nuage annonce un orage ?', $question->statement);
        $this->assertSame('Météorologie', $question->module);
        $this->assertSame('Cumulonimbus', $question->correctAnswer->text);
        $this->assertSame(0, $question->correctAnswer->position);
        $this->assertSame(['Cirrus', 'Stratus', 'Altostratus'], $question->wrongAnswers->pluck('text')->all());
        $this->assertSame([1, 2, 3], $question->wrongAnswers->pluck('position')->all());
        $this->assertSame('Nuage de haute altitude, sans convection.', $question->wrongAnswers->first()->explanation);
        $this->assertSame(1, $question->answers()->where('is_correct', true)->count());
    }

    public function test_create_form_proposes_three_wrong_answers_by_default(): void
    {
        $this->manager()
            ->mountAction(TestAction::make(CreateAction::class)->table())
            ->assertSchemaStateSet(fn (array $state): bool => count($state['wrongAnswers']) === 3, 'mountedActionSchema0');
    }

    public function test_question_requires_statement_correct_answer_and_one_wrong_answer(): void
    {
        $this->manager()
            ->callAction(TestAction::make(CreateAction::class)->table(), $this->questionData([
                'statement' => '',
                'correctAnswer' => ['text' => ''],
                'wrongAnswers' => [],
            ]))
            ->assertHasActionErrors(['statement' => 'required', 'correctAnswer.text' => 'required', 'wrongAnswers' => 'min']);

        $this->assertSame(0, $this->bank->questions()->count());
    }

    public function test_edit_question_and_its_answers(): void
    {
        $question = Question::factory()->for($this->bank)->withAnswers(3)->create();

        $this->manager()
            ->callAction(TestAction::make(EditAction::class)->table($question), $this->questionData([
                'wrongAnswers' => [['text' => 'Cirrus', 'explanation' => null]],
            ]))
            ->assertHasNoActionErrors();

        $question->refresh();
        $this->assertSame('Cumulonimbus', $question->correctAnswer->text);
        $this->assertSame(['Cirrus'], $question->wrongAnswers->pluck('text')->all());
        $this->assertSame(2, $question->answers()->count());
    }

    public function test_delete_question(): void
    {
        $question = Question::factory()->for($this->bank)->withAnswers()->create();

        $this->manager()->callAction(TestAction::make(DeleteAction::class)->table($question));

        $this->assertModelMissing($question);
        $this->assertDatabaseMissing('qcm_answers', ['question_id' => $question->id]);
    }

    public function test_filter_questions_by_module(): void
    {
        $meteo = Question::factory()->for($this->bank)->create(['module' => 'Météorologie']);
        $regl = Question::factory()->for($this->bank)->create(['module' => 'Réglementation']);

        $this->manager()
            ->filterTable('module', 'Météorologie')
            ->assertCanSeeTableRecords([$meteo])
            ->assertCanNotSeeTableRecords([$regl]);
    }

    public function test_upload_image(): void
    {
        Storage::fake(Question::IMAGE_DISK);

        $this->manager()
            ->callAction(TestAction::make(CreateAction::class)->table(), $this->questionData([
                'image_path' => UploadedFile::fake()->image('carte.png'),
            ]))
            ->assertHasNoActionErrors();

        $path = $this->bank->questions()->sole()->image_path;
        $this->assertNotNull($path);
        Storage::disk(Question::IMAGE_DISK)->assertExists($path);
    }

    public function test_reject_a_file_that_is_not_an_image(): void
    {
        Storage::fake(Question::IMAGE_DISK);

        $this->manager()
            ->callAction(TestAction::make(CreateAction::class)->table(), $this->questionData([
                'image_path' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'),
            ]))
            ->assertHasActionErrors(['image_path']);

        $this->assertSame(0, $this->bank->questions()->count());
    }
}
