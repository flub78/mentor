<?php

namespace Tests\Feature\Banques;

use App\Banques\Filament\Resources\Banks\BankResource;
use App\Banques\Filament\Resources\Banks\Pages\EditBank;
use App\Banques\Filament\Resources\Banks\Pages\ListBanks;
use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Usage\QuestionUsage;
use App\Banques\Usage\UsageRegistry;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Lot B1 du plan Banques, étape B1 : module Banques de questions, banque par
 * défaut, interface avec les modules qui utilisent les questions.
 */
class ModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_bank_is_created_by_the_migration(): void
    {
        $default = Bank::query()->where('is_default', true)->sole();

        $this->assertSame('Banque par défaut', $default->name);
        $this->assertTrue(Bank::default()->is($default));
    }

    public function test_default_bank_is_recreated_if_missing(): void
    {
        Bank::query()->where('is_default', true)->update(['is_default' => false]);

        $this->assertTrue(Bank::default()->is_default);
        $this->assertSame(1, Bank::query()->where('is_default', true)->count());
    }

    public function test_default_bank_cannot_be_deleted(): void
    {
        $default = Bank::default();
        Question::factory()->for($default)->create();

        try {
            $default->delete();
            $this->fail('La banque par défaut ne doit pas pouvoir être supprimée.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('ne peut pas être supprimée', $exception->getMessage());
        }

        $this->assertModelExists($default);
        $this->assertSame(1, $default->questions()->count());
    }

    public function test_there_is_only_one_default_bank(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Il ne peut y avoir qu\'une seule banque par défaut.');

        Bank::factory()->create(['is_default' => true]);
    }

    public function test_default_bank_can_be_renamed_but_offers_no_deletion(): void
    {
        Filament::setCurrentPanel('leitner');
        $default = Bank::default();

        Livewire::test(ListBanks::class)
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($default));

        Livewire::test(EditBank::class, ['record' => $default->getRouteKey()])
            ->assertActionHidden(DeleteAction::class)
            ->fillForm(['name' => 'À ranger'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('À ranger', $default->fresh()->name);
    }

    public function test_bank_resource_is_offered_in_the_leitner_panel(): void
    {
        Filament::setCurrentPanel('leitner');

        $this->assertStringStartsWith(url('/leitner/banks'), BankResource::getUrl('index'));
        $this->get('/leitner/banks')->assertOk()->assertSee('Banque par défaut');
    }

    public function test_usage_registry_joins_the_notices_of_the_modules(): void
    {
        $question = Question::factory()->create();
        $usage = new class implements QuestionUsage
        {
            public int $applied = 0;

            public function bankDeletionNotice(Bank $bank): ?string
            {
                return 'Utilisée par le module témoin.';
            }

            public function questionDeletionNotice(Question $question): ?string
            {
                return null;
            }

            public function substantiveChangeNotice(Question $question): ?string
            {
                return 'Effet dans le module témoin.';
            }

            public function applySubstantiveChange(Question $question): ?string
            {
                $this->applied++;

                return 'Appliqué dans le module témoin.';
            }

            public function moveNotice(Question $question, Bank $target): ?string
            {
                return null;
            }

            public function applyMove(Question $question): ?string
            {
                return null;
            }
        };
        $this->app->instance('usage-temoin', $usage);
        $this->app->tag(['usage-temoin'], UsageRegistry::TAG);
        $registry = $this->app->make(UsageRegistry::class);

        $this->assertStringContainsString('Utilisée par le module témoin.', $registry->bankDeletionNotice($question->bank));
        $this->assertTrue($registry->hasSubstantiveChangeEffect($question));
        $this->assertStringContainsString('Appliqué dans le module témoin.', $registry->applySubstantiveChange($question));
        $this->assertSame(1, $usage->applied);
    }

    public function test_banques_module_does_not_depend_on_other_modules(): void
    {
        $offenders = [];
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Banques')));

        foreach ($files as $file) {
            if ($file->isFile() && preg_match('/^use App\\\\(Leitner|Qcm)\\\\/m', (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = $file->getPathname();
            }
        }

        $this->assertSame([], $offenders, 'Le module Banques ne doit dépendre d\'aucun autre module (PRD Mentor §1.4).');
    }
}
