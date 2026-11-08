<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Filament\Resources\Banks\BankResource;
use App\Qcm\Filament\Resources\Banks\Pages\CreateBank;
use App\Qcm\Filament\Resources\Banks\Pages\EditBank;
use App\Qcm\Filament\Resources\Banks\Pages\ListBanks;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Étape 1 du plan QCM : gestion des banques (PRD QCM UC-03), sans connexion.
 */
class BankResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_bank_list_is_reachable_without_login(): void
    {
        $bank = Bank::factory()->create(['name' => 'Planeur — théorie']);

        $this->get(BankResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Planeur — théorie');

        Livewire::test(ListBanks::class)->assertCanSeeTableRecords([$bank]);
    }

    public function test_create_bank_then_open_it(): void
    {
        Livewire::test(CreateBank::class)
            ->fillForm(['name' => 'Météo', 'description' => 'Questions de météorologie'])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertRedirect(BankResource::getUrl('edit', ['record' => Bank::where('name', 'Météo')->firstOrFail()]));

        $this->assertDatabaseHas('qcm_banks', ['name' => 'Météo', 'description' => 'Questions de météorologie']);
    }

    public function test_bank_name_is_required_and_unique(): void
    {
        Bank::factory()->create(['name' => 'Météo']);

        Livewire::test(CreateBank::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);

        Livewire::test(CreateBank::class)
            ->fillForm(['name' => 'Météo'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique'])
            ->assertSee('Une banque porte déjà ce nom');
    }

    public function test_rename_bank(): void
    {
        $bank = Bank::factory()->create(['name' => 'Ancien nom']);

        Livewire::test(EditBank::class, ['record' => $bank->getRouteKey()])
            ->fillForm(['name' => 'Nouveau nom'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nouveau nom', $bank->fresh()->name);
    }

    public function test_delete_bank_announces_and_removes_its_questions(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->count(2)->for($bank)->withAnswers()->create();

        Livewire::test(ListBanks::class)
            ->mountAction(TestAction::make(DeleteAction::class)->table($bank))
            ->assertMountedActionModalSee('Ses 2 questions et leurs réponses seront définitivement supprimées.')
            ->callMountedAction();

        $this->assertModelMissing($bank);
        $this->assertDatabaseCount('qcm_questions', 0);
    }
}
