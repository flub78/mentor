<?php

namespace Tests\Feature\Banques;

use App\Banques\Filament\Resources\Banks\Pages\EditBank;
use App\Banques\Filament\Resources\Banks\RelationManagers\QuestionsRelationManager;
use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Questions\QuestionMover;
use App\Leitner\Models\Suite;
use App\Qcm\Models\Questionnaire;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Plan Banques, étape B6 : déplacer et copier des questions (PRD Banques UC-03).
 */
class MoveCopyTest extends TestCase
{
    use RefreshDatabase;

    private function mover(): QuestionMover
    {
        return app(QuestionMover::class);
    }

    private function introduce(Suite $suite, Question $question): void
    {
        $suite->progression->items()->create(['question_id' => $question->id, 'box' => 2, 'introduced_at' => now()]);
    }

    public function test_questions_are_moved_out_of_the_default_bank_and_keep_their_questionnaires(): void
    {
        $default = Bank::default();
        $meteo = Bank::factory()->create(['name' => 'Météo']);
        $questions = Question::factory()->count(2)->qcm()->create(['bank_id' => $default->id]);
        $questionnaire = Questionnaire::factory()->create();
        $questionnaire->questions()->attach($questions->modelKeys());

        $result = $this->mover()->move($questions, $meteo);

        $this->assertSame(['moved' => 2, 'report' => '', 'droppedReferences' => 0], $result);
        $this->assertSame(0, $default->questions()->count());
        $this->assertSame(2, $meteo->questions()->count());
        $this->assertSame($questions->modelKeys(), $questionnaire->questions()->pluck('banques_questions.id')->all());
    }

    public function test_a_question_already_in_the_target_bank_is_not_counted(): void
    {
        $bank = Bank::factory()->create();
        $question = Question::factory()->create(['bank_id' => $bank->id]);

        $this->assertSame(0, $this->mover()->move([$question], $bank)['moved']);
    }

    public function test_moving_announces_and_applies_the_effect_on_leitner_suites(): void
    {
        $from = Bank::factory()->create();
        $to = Bank::factory()->create();
        $question = Question::factory()->create(['bank_id' => $from->id, 'statement' => 'Capitale de la Suisse ?']);
        $losing = Suite::factory()->withBanks($from)->create();
        $keeping = Suite::factory()->withBanks($from, $to)->create();
        $this->introduce($losing, $question);
        $this->introduce($keeping, $question);

        $this->assertSame(
            '« Capitale de la Suisse ? » : 1 suite Leitner n\'a pas cette banque pour source : la question en sortira, avec sa progression.',
            $this->mover()->moveNotice([$question], $to),
        );

        $result = $this->mover()->move([$question], $to);

        $this->assertSame('Question retirée d\'1 suite Leitner qui n\'a pas sa nouvelle banque pour source.', $result['report']);
        $this->assertSame(0, $losing->progression->items()->count());
        $this->assertSame(1, $keeping->progression->items()->count());
    }

    public function test_a_copy_is_an_independent_question_with_its_own_image(): void
    {
        Storage::fake(Question::IMAGE_DISK);
        Storage::disk(Question::IMAGE_DISK)->put('banques/images/carte.png', 'image');
        $from = Bank::factory()->create();
        $to = Bank::factory()->create();
        $original = Question::factory()->valeurNumerique()->difficulty(2)->create([
            'bank_id' => $from->id, 'theme' => 'Relief', 'image_path' => 'banques/images/carte.png', 'image_alt' => 'Une carte',
        ]);
        $questionnaire = Questionnaire::factory()->create();
        $questionnaire->questions()->attach($original->id);

        $copy = $this->mover()->copy([$original], $to)->sole();

        $this->assertNotSame($original->id, $copy->id);
        $this->assertSame($to->id, $copy->bank_id);
        $this->assertSame([$original->type, $original->statement, $original->payload, $original->theme, $original->difficulty, 'Une carte'], [$copy->type, $copy->statement, $copy->payload, $copy->theme, $copy->difficulty, $copy->image_alt]);
        $this->assertNotSame($original->image_path, $copy->image_path);
        $this->assertSame('image', Storage::disk(Question::IMAGE_DISK)->get((string) $copy->image_path));
        $this->assertSame(0, $questionnaire->questions()->whereKey($copy->id)->count(), 'Une copie n\'est utilisée nulle part.');
        $this->assertSame($from->id, $original->fresh()->bank_id);
    }

    public function test_bulk_actions_move_and_copy_with_a_clear_result(): void
    {
        Filament::setCurrentPanel('leitner');
        $from = Bank::factory()->create();
        $to = Bank::factory()->create(['name' => 'Géographie']);
        $questions = Question::factory()->count(2)->create(['bank_id' => $from->id]);
        $manager = fn () => Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $from, 'pageClass' => EditBank::class]);

        $manager()
            ->selectTableRecords([$questions[0]->id])
            ->callAction(TestAction::make('copyQuestions')->table()->bulk(), ['target_bank_id' => $to->id])
            ->assertHasNoActionErrors()
            ->assertNotified('1 question copiée dans « Géographie »');

        $manager()
            ->selectTableRecords($questions->modelKeys())
            ->callAction(TestAction::make('moveQuestions')->table()->bulk(), ['target_bank_id' => $to->id])
            ->assertHasNoActionErrors()
            ->assertNotified(Notification::make()->success()->title('2 questions déplacées vers « Géographie »'));

        $this->assertSame(0, $from->questions()->count());
        $this->assertSame(3, $to->questions()->count());
    }

    public function test_target_bank_is_required_and_excludes_the_current_bank(): void
    {
        Filament::setCurrentPanel('leitner');
        $from = Bank::factory()->create();
        $question = Question::factory()->create(['bank_id' => $from->id]);

        Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $from, 'pageClass' => EditBank::class])
            ->selectTableRecords([$question->id])
            ->callAction(TestAction::make('moveQuestions')->table()->bulk(), ['target_bank_id' => $from->id])
            ->assertHasActionErrors(['target_bank_id']);

        $this->assertSame($from->id, $question->fresh()->bank_id);
    }
}
