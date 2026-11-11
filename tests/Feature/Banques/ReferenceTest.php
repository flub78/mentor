<?php

namespace Tests\Feature\Banques;

use App\Banques\Filament\Resources\Banks\Pages\EditBank;
use App\Banques\Filament\Resources\Banks\RelationManagers\QuestionsRelationManager;
use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Questions\InvalidQuestion;
use App\Banques\Questions\QuestionMover;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Plan Banques, lot B1.1, étape R1 : référence des questions (PRD Banques §3,
 * UC-02, UC-03).
 */
class ReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function manager(Bank $bank): Testable
    {
        Filament::setCurrentPanel('leitner');

        return Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $bank, 'pageClass' => EditBank::class]);
    }

    public function test_a_reference_is_unique_in_its_bank_only(): void
    {
        [$first, $second] = Bank::factory()->count(2)->create();
        Question::factory()->for($first)->create(['reference' => 'meteo-042', 'statement' => 'Nuage d\'orage ?']);

        $this->assertSame('meteo-042', Question::factory()->for($second)->create(['reference' => ' meteo-042 '])->reference);
        $this->assertNull(Question::factory()->for($first)->create(['reference' => '  '])->reference);

        $this->expectException(InvalidQuestion::class);
        $this->expectExceptionMessage('La référence « meteo-042 » est déjà portée par la question « Nuage d\'orage ? » de cette banque');

        Question::factory()->for($first)->create(['reference' => 'meteo-042']);
    }

    public function test_the_form_refuses_a_reference_taken_in_the_bank_and_names_the_question(): void
    {
        $bank = Bank::factory()->create();
        $holder = Question::factory()->for($bank)->autoEvaluation()->create(['reference' => 'N1-13', 'statement' => 'Quelle main ?']);
        $data = ['type' => 'auto_evaluation', 'statement' => 'Autre question', 'payload' => ['answer' => 'Réponse']];

        $this->manager($bank)
            ->callAction(TestAction::make(CreateAction::class)->table(), [...$data, 'reference' => 'N1-13'])
            ->assertHasActionErrors(['reference']);

        $this->manager($bank)
            ->callAction(TestAction::make(CreateAction::class)->table(), [...$data, 'reference' => 'N1-14'])
            ->assertHasNoActionErrors();

        $this->manager($bank)
            ->callAction(TestAction::make(EditAction::class)->table($holder), ['reference' => 'N1-13', 'statement' => 'Quelle est la main ?'])
            ->assertHasNoActionErrors();

        $this->assertSame(['N1-13', 'N1-14'], $bank->questions()->orderBy('reference')->pluck('reference')->all());
    }

    public function test_moving_or_copying_drops_a_reference_taken_in_the_target(): void
    {
        [$from, $to] = Bank::factory()->count(2)->create();
        $clash = Question::factory()->for($from)->create(['reference' => 'q-1']);
        $free = Question::factory()->for($from)->create(['reference' => 'q-2']);
        Question::factory()->for($to)->create(['reference' => 'q-1']);
        $mover = app(QuestionMover::class);

        $copies = $mover->copy([$clash, $free], $to, $dropped);
        $this->assertSame([null, 'q-2'], $copies->pluck('reference')->all());
        $this->assertSame(1, $dropped);

        $copies->each->delete();
        $result = $mover->move([$clash->fresh(), $free->fresh()], $to);
        $this->assertSame(1, $result['droppedReferences']);
        $this->assertNull($clash->fresh()->reference);
        $this->assertSame('q-2', $free->fresh()->reference);
    }

    public function test_the_move_notification_tells_about_dropped_references(): void
    {
        [$from, $to] = Bank::factory()->count(2)->create(['name' => fn () => fake()->unique()->word()]);
        $question = Question::factory()->for($from)->create(['reference' => 'q-1']);
        Question::factory()->for($to)->create(['reference' => 'q-1']);

        $this->manager($from)
            ->selectTableRecords([$question->id])
            ->callAction(TestAction::make('moveQuestions')->table()->bulk(), ['target_bank_id' => $to->id])
            ->assertNotified(Notification::make()->success()
                ->title("1 question déplacée vers « {$to->name} »")
                ->body('1 question a perdu sa référence, déjà portée par une question de la banque cible.'));
    }
}
