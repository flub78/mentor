<?php

namespace Tests\Feature\Leitner;

use App\Leitner\Filament\Resources\Banks\BankResource;
use App\Leitner\Filament\Resources\Banks\Pages\CreateBank;
use App\Leitner\Filament\Resources\Banks\Pages\EditBank;
use App\Leitner\Filament\Resources\Banks\Pages\ListBanks;
use App\Leitner\Models\Bank;
use App\Leitner\Models\Question;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Étape 1 du plan Leitner : gestion des banques (PRD Leitner UC-01), sans
 * connexion.
 */
class BankResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('leitner');
    }

    public function test_bank_list_is_reachable_without_login(): void
    {
        $bank = Bank::factory()->create(['name' => 'Capitales du monde']);
        Question::factory()->count(2)->for($bank)->create();

        $this->get(BankResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Capitales du monde');

        Livewire::test(ListBanks::class)
            ->assertCanSeeTableRecords([$bank])
            ->assertTableColumnStateSet('questions_count', 2, $bank);
    }

    public function test_bank_resource_belongs_to_the_leitner_panel(): void
    {
        $this->assertStringStartsWith(url('/leitner/'), BankResource::getUrl('index'));
    }

    public function test_create_bank_then_open_it(): void
    {
        Livewire::test(CreateBank::class)
            ->fillForm(['name' => 'Capitales', 'description' => 'Capitales européennes'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(BankResource::getUrl('edit', ['record' => Bank::where('name', 'Capitales')->firstOrFail()]));

        $this->assertDatabaseHas('leitner_banks', ['name' => 'Capitales', 'description' => 'Capitales européennes']);
    }

    public function test_bank_name_is_required_and_unique(): void
    {
        Bank::factory()->create(['name' => 'Capitales']);

        Livewire::test(CreateBank::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required'])
            ->assertSee('Le nom de la banque est obligatoire.');

        Livewire::test(CreateBank::class)
            ->fillForm(['name' => 'Capitales'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique'])
            ->assertSee('Une banque porte déjà ce nom');
    }

    public function test_rename_bank(): void
    {
        $bank = Bank::factory()->create(['name' => 'Capitales']);

        Livewire::test(EditBank::class, ['record' => $bank->getRouteKey()])
            ->fillForm(['name' => 'Capitales du monde'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Capitales du monde', $bank->fresh()->name);
    }

    public function test_delete_bank_announces_and_removes_its_questions(): void
    {
        $bank = Bank::factory()->create();
        $questions = Question::factory()->count(3)->for($bank)->create();

        Livewire::test(ListBanks::class)
            ->mountAction(TestAction::make(DeleteAction::class)->table($bank))
            ->assertMountedActionModalSee('Ses 3 questions seront définitivement supprimées.')
            ->callMountedAction()
            ->assertNotified('Banque supprimée');

        $this->assertModelMissing($bank);
        $questions->each(fn (Question $question) => $this->assertModelMissing($question));
    }
}
