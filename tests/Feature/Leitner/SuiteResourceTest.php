<?php

namespace Tests\Feature\Leitner;

use App\Banques\Filament\Resources\Banks\Pages\EditBank;
use App\Banques\Filament\Resources\Banks\Pages\ListBanks;
use App\Banques\Filament\Resources\Banks\RelationManagers\QuestionsRelationManager;
use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Leitner\Filament\Resources\Suites\Pages\CreateSuite;
use App\Leitner\Filament\Resources\Suites\Pages\EditSuite;
use App\Leitner\Filament\Resources\Suites\Pages\ListSuites;
use App\Leitner\Filament\Resources\Suites\SuiteResource;
use App\Leitner\Models\Suite;
use App\Leitner\Suites\PresentationOrder;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Étape 2 du plan Leitner : interface des suites (PRD Leitner UC-05, UC-03)
 * et effets sur les banques et les questions (UC-01, UC-02 1a).
 */
class SuiteResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('leitner');
    }

    /**
     * Bouton « Sauvegarder » de la page de modification, dans les actions du formulaire.
     */
    private function saveAction(): TestAction
    {
        return TestAction::make('save')->schemaComponent('form-actions', 'content');
    }

    private function introduce(Suite $suite, Question $question, int $box = 1): void
    {
        $suite->progression->items()->create(['question_id' => $question->id, 'box' => $box, 'introduced_at' => now()]);
    }

    public function test_suite_list_shows_sources_and_distribution(): void
    {
        $bank = Bank::factory()->create(['name' => 'Météorologie']);
        Question::factory()->count(3)->for($bank)->create();
        $suite = Suite::factory()->withBanks($bank)->create(['name' => 'Théorie ULM', 'box_count' => 3]);
        $this->introduce($suite, $bank->questions()->first(), 2);

        $this->get(SuiteResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Théorie ULM')
            ->assertSee('Météorologie')
            ->assertSee('B1 : 0 · B2 : 1 · B3 : 0 — en banque : 2 — maîtrisées : 0');

        Livewire::test(ListSuites::class)->assertCanSeeTableRecords([$suite]);
    }

    public function test_create_suite_with_several_banks_and_defaults(): void
    {
        $meteo = Bank::factory()->create();
        $regl = Bank::factory()->create();

        Livewire::test(CreateSuite::class)
            ->assertSchemaStateSet(['box_count' => 5, 'min_box_one' => 10, 'presentation_order' => PresentationOrder::Melange, 'retry_errors' => true])
            ->assertSee('16 séances environ')
            ->fillForm(['name' => 'Théorie ULM', 'bank_ids' => [$meteo->id, $regl->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $suite = Suite::where('name', 'Théorie ULM')->sole();
        $this->assertEqualsCanonicalizing([$meteo->id, $regl->id], $suite->bankIds());
        $this->assertSame(5, $suite->box_count);
        $this->assertNotNull($suite->progression);
    }

    public function test_suite_name_is_required_and_unique(): void
    {
        Suite::factory()->create(['name' => 'Théorie ULM']);

        Livewire::test(CreateSuite::class)
            ->fillForm(['name' => ''])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);

        Livewire::test(CreateSuite::class)
            ->fillForm(['name' => 'Théorie ULM'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique'])
            ->assertSee('Une suite porte déjà ce nom');
    }

    public function test_harmless_change_is_saved_without_confirmation(): void
    {
        $bank = Bank::factory()->create();
        $other = Bank::factory()->create();
        $suite = Suite::factory()->withBanks($bank)->create();

        Livewire::test(EditSuite::class, ['record' => $suite->getRouteKey()])
            ->assertSchemaStateSet(['bank_ids' => [$bank->id]])
            ->fillForm(['presentation_order' => 'boite_un_d_abord', 'bank_ids' => [$bank->id, $other->id]])
            ->callAction($this->saveAction())
            ->assertHasNoFormErrors()
            ->assertNotified();

        $suite->refresh();
        $this->assertSame(PresentationOrder::BoiteUnDAbord, $suite->presentation_order);
        $this->assertEqualsCanonicalizing([$bank->id, $other->id], $suite->bankIds());
    }

    public function test_destructive_change_asks_for_confirmation_with_its_effects(): void
    {
        $kept = Bank::factory()->create();
        $removed = Bank::factory()->create();
        $suite = Suite::factory()->withBanks($kept, $removed)->create(['box_count' => 5]);
        $this->introduce($suite, Question::factory()->for($removed)->create(), 2);
        $this->introduce($suite, Question::factory()->for($kept)->create(), 5);

        Livewire::test(EditSuite::class, ['record' => $suite->getRouteKey()])
            ->fillForm(['box_count' => 3, 'bank_ids' => [$kept->id]])
            ->mountAction($this->saveAction())
            ->assertActionMounted($this->saveAction())
            ->assertMountedActionModalSee([
                'Retirer une banque fait perdre sa progression à 1 question déjà introduite',
                'Réduire le nombre de boîtes place 1 question dans la boîte 3.',
            ])
            ->callMountedAction()
            ->assertHasNoFormErrors()
            ->assertNotified('Progression ajustée');

        $suite->refresh();
        $this->assertSame(3, $suite->box_count);
        $this->assertSame([$kept->id], $suite->bankIds());
        $this->assertSame([1 => 0, 2 => 0, 3 => 1], $suite->progression->counts()['boxes']);
    }

    public function test_add_a_personal_question_in_box_one(): void
    {
        $suite = Suite::factory()->create(['name' => 'Capitales']);

        Livewire::test(EditSuite::class, ['record' => $suite->getRouteKey()])
            ->callAction('newQuestion', [
                'type' => 'vrai_faux',
                'statement' => 'Berne est la capitale de la Suisse.',
                'payload' => ['value' => 'vrai'],
                'placement' => 'boite_1',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Question ajoutée')
            ->assertSchemaStateSet(fn (array $state): bool => $state['bank_ids'] === [$suite->fresh()->personal_bank_id]);

        $suite->refresh();
        $question = $suite->personalBank->questions()->sole();
        $this->assertSame(['value' => true], $question->payload);
        $this->assertSame(1, $suite->progression->inBox(1)->count());
    }

    public function test_add_a_personal_question_in_bank(): void
    {
        Repeater::fake();
        $suite = Suite::factory()->create();

        Livewire::test(EditSuite::class, ['record' => $suite->getRouteKey()])
            ->callAction('newQuestion', [
                'type' => 'qcm',
                'statement' => 'Capitale de la Norvège ?',
                'payload' => ['correct' => 'Oslo', 'wrong' => [['text' => 'Bergen', 'explanation' => null]], 'shuffle' => true],
                'placement' => 'banque',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame(1, $suite->fresh()->progression->inBank()->count());
    }

    public function test_delete_suite_announces_lost_progression(): void
    {
        $bank = Bank::factory()->create();
        $suite = Suite::factory()->withBanks($bank)->create();
        $this->introduce($suite, Question::factory()->for($bank)->create());

        Livewire::test(ListSuites::class)
            ->mountAction(TestAction::make(DeleteAction::class)->table($suite))
            ->assertMountedActionModalSee('La progression de la question introduite sera perdue. Les banques sources ne sont pas modifiées.')
            ->callMountedAction()
            ->assertNotified('Suite supprimée');

        $this->assertModelMissing($suite);
        $this->assertModelExists($bank);
    }

    public function test_bank_deletion_announces_the_suites_using_it(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->create();
        Suite::factory()->count(2)->withBanks($bank)->create();

        Livewire::test(ListBanks::class)
            ->mountAction(TestAction::make(DeleteAction::class)->table($bank))
            ->assertMountedActionModalSee('Elle sera retirée des 2 suites Leitner qui l\'utilisent');
    }

    public function test_question_deletion_announces_the_suites_where_it_is_revised(): void
    {
        $bank = Bank::factory()->create();
        $question = Question::factory()->for($bank)->create();
        $this->introduce(Suite::factory()->withBanks($bank)->create(), $question);

        Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $bank, 'pageClass' => EditBank::class])
            ->mountAction(TestAction::make(DeleteAction::class)->table($question))
            ->assertMountedActionModalSee('Elle disparaîtra de la suite Leitner où elle est en cours de révision.');
    }

    public function test_change_kind_is_only_asked_for_an_introduced_question(): void
    {
        $bank = Bank::factory()->create();
        $fresh = Question::factory()->for($bank)->vraiFaux()->create();
        $introduced = Question::factory()->for($bank)->vraiFaux()->create();
        $this->introduce(Suite::factory()->withBanks($bank)->create(), $introduced, 3);

        $manager = Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $bank, 'pageClass' => EditBank::class]);

        $manager->mountAction(TestAction::make(EditAction::class)->table($fresh))
            ->assertFormFieldHidden('change_kind');

        Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $bank, 'pageClass' => EditBank::class])
            ->mountAction(TestAction::make(EditAction::class)->table($introduced))
            ->assertFormFieldVisible('change_kind');
    }

    public function test_minor_change_keeps_the_box_and_substantive_change_resets_it(): void
    {
        $bank = Bank::factory()->create();
        $question = Question::factory()->for($bank)->vraiFaux()->create();
        $suite = Suite::factory()->withBanks($bank)->create();
        $this->introduce($suite, $question, 4);
        $manager = fn () => Livewire::test(QuestionsRelationManager::class, ['ownerRecord' => $bank, 'pageClass' => EditBank::class]);

        $manager()
            ->callAction(TestAction::make(EditAction::class)->table($question), ['statement' => 'Le Danube traverse Vienne (Autriche).', 'change_kind' => 'mineure'])
            ->assertHasNoActionErrors();
        $this->assertSame(4, $suite->progression->items()->sole()->box);
        $this->assertSame('Le Danube traverse Vienne (Autriche).', $question->fresh()->statement);

        $manager()
            ->callAction(TestAction::make(EditAction::class)->table($question), ['payload' => ['value' => 'faux'], 'change_kind' => 'fond'])
            ->assertHasNoActionErrors()
            ->assertNotified('Modification de fond appliquée');
        $this->assertSame(1, $suite->progression->items()->sole()->box);
        $this->assertSame(['value' => false], $question->fresh()->payload);
    }
}
