<?php

namespace Tests\Feature\Leitner;

use App\Leitner\Filament\Pages\Seance;
use App\Leitner\Filament\Resources\Suites\Pages\ViewSuite;
use App\Leitner\Filament\Resources\Suites\SuiteResource;
use App\Leitner\Models\Bank;
use App\Leitner\Models\ProgressItem;
use App\Leitner\Models\Question;
use App\Leitner\Models\Session;
use App\Leitner\Models\Suite;
use App\Leitner\Questions\Verdict;
use App\Leitner\Sessions\SessionStatus;
use App\Leitner\Suites\PresentationOrder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Étape 4 du plan Leitner : écran de séance (PRD Leitner UC-08) et état
 * d'une suite (UC-11).
 */
class SeancePageTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('leitner');
        $this->bank = Bank::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function suite(array $settings = []): Suite
    {
        return Suite::factory()->withBanks($this->bank)->create([
            'min_box_one' => 10,
            'retry_errors' => false,
            'presentation_order' => PresentationOrder::BoiteUnDAbord,
            ...$settings,
        ]);
    }

    private function page(Suite $suite): Testable
    {
        return Livewire::test(Seance::class, ['suite' => $suite->id]);
    }

    private function currentQuestion(Testable $page): Question
    {
        return $page->instance()->displayedEntry()->question;
    }

    public function test_full_session_on_the_four_types(): void
    {
        $suite = $this->suite();
        Question::factory()->for($this->bank)->qcm()->difficulty(1)->create(['statement' => 'Capitale de la Suisse ?', 'payload' => [
            'correct' => 'Berne', 'wrong' => [['text' => 'Zurich', 'explanation' => 'Plus grande ville, pas la capitale.']], 'shuffle' => true,
        ]]);
        Question::factory()->for($this->bank)->vraiFaux(true)->difficulty(1)->create(['statement' => 'Vienne est sur le Danube.']);
        Question::factory()->for($this->bank)->autoEvaluation()->difficulty(1)->create(['statement' => 'Pythagore ?']);
        Question::factory()->for($this->bank)->valeurNumerique(4806, 'absolue', 10, 'm')->difficulty(1)->create(['statement' => 'Altitude du mont Blanc ?']);

        $page = $this->page($suite)
            ->assertSee('Séance n° 1 — boîte posée : 1 — 4 questions, dont 4 nouvelles.')
            ->call('start')
            ->assertSet('step', Seance::STEP_QUESTION);

        $answered = [];
        for ($i = 0; $i < 4; $i++) {
            $question = $this->currentQuestion($page);
            $answered[] = $question->statement;

            match ($question->statement) {
                'Capitale de la Suisse ?' => $page->set('response', 'fausse-0')->call('submit')
                    ->assertSee('Mauvaise réponse')->assertSeeText('Réponse attendue : Berne')->assertSee('Plus grande ville, pas la capitale.'),
                'Vienne est sur le Danube.' => $page->call('answer', 'vrai')->assertSee('Bonne réponse')->assertSee('Boîte 1 → boîte 2.'),
                'Pythagore ?' => $page->call('reveal')->assertSee('Par les aires')->call('selfAssess', true)->assertSee('Bonne réponse'),
                'Altitude du mont Blanc ?' => $page->set('response', '4 810 m')->call('submit')->assertSee('Bonne réponse'),
            };

            $page->call('next');
        }

        $this->assertEqualsCanonicalizing(['Capitale de la Suisse ?', 'Vienne est sur le Danube.', 'Pythagore ?', 'Altitude du mont Blanc ?'], $answered);
        $page->assertSet('step', Seance::STEP_SUMMARY)
            ->assertSee('Bilan de la séance')
            ->assertSee('75 % de bonnes réponses (3 / 4)')
            ->assertSee('Boîte 1 → 2 : 3 question(s)')
            ->assertSee('Capitale de la Suisse ?');

        $this->assertSame(SessionStatus::Terminee, Session::query()->sole()->status);
        $this->assertSame([1 => 1, 2 => 3, 3 => 0, 4 => 0, 5 => 0], $suite->progression->fresh()->counts()['boxes']);
    }

    public function test_answer_is_required_and_invalid_number_is_refused(): void
    {
        $suite = $this->suite();
        Question::factory()->for($this->bank)->valeurNumerique(12.5, 'aucune', null, null)->create();
        $page = $this->page($suite)->call('start');

        $page->call('submit')->assertHasErrors('response')->assertSee('Saisissez un nombre avant de valider.');
        $page->set('response', 'douze')->call('submit')
            ->assertSee('Saisissez un nombre, par exemple 12,5 ou 12.5.')
            ->assertDontSee('Saisissez un nombre avant de valider.');

        $this->assertNull(Session::query()->sole()->number);
        $page->set('response', '12,5')->call('submit')->assertHasNoErrors()->assertSee('Bonne réponse');
    }

    public function test_qcm_choice_is_required(): void
    {
        $suite = $this->suite();
        Question::factory()->for($this->bank)->qcm()->create();

        $this->page($suite)->call('start')->call('submit')->assertSee('Choisissez une réponse avant de valider.');
    }

    public function test_interrupted_session_resumes_at_the_first_unanswered_question(): void
    {
        $suite = $this->suite();
        Question::factory()->count(3)->for($this->bank)->vraiFaux(true)->create();
        $first = $this->page($suite)->call('start');
        $first->call('answer', 'vrai')->call('next');
        $expected = $this->currentQuestion($first);

        $page = $this->page($suite)
            ->assertSee('Une séance est en cours')
            ->assertSee('1 question(s) traitée(s) sur 3')
            ->call('resume');

        $this->assertTrue($this->currentQuestion($page)->is($expected));
        $page->assertSee('Question 2 / 3');
    }

    public function test_abandon_an_interrupted_session(): void
    {
        $suite = $this->suite();
        Question::factory()->count(2)->for($this->bank)->vraiFaux(true)->create();
        $this->page($suite)->call('start')->call('answer', 'vrai');

        $this->page($suite)->call('abandon')->assertNotified('Séance abandonnée')->assertSee('Séance n° 2');

        $this->assertSame(SessionStatus::Abandonnee, Session::query()->sole()->status);
    }

    public function test_undo_and_skip(): void
    {
        $suite = $this->suite();
        Question::factory()->count(2)->for($this->bank)->vraiFaux(true)->create();
        $page = $this->page($suite)->call('start')->call('answer', 'faux')->assertSee('Annuler ma réponse');

        $page->call('undo')->assertNotified('Réponse annulée')->assertDontSee('Mauvaise réponse');
        $this->assertSame(0, ProgressItem::query()->where('box', 2)->count());

        $page->call('answer', 'vrai')->assertSee('Question 1 / 2')->call('next')->assertSee('Question 2 / 2')->call('skip')->assertSet('step', Seance::STEP_SUMMARY)->assertSee('1 question(s) passée(s)');
    }

    public function test_retry_entry_is_announced(): void
    {
        $suite = $this->suite(['retry_errors' => true]);
        Question::factory()->for($this->bank)->vraiFaux(true)->create();

        $this->page($suite)->call('start')
            ->call('answer', 'faux')->assertSee('Elle sera reposée en fin de séance.')
            ->call('next')->assertSee('Reprise d\'une erreur', false)
            ->call('answer', 'vrai')->assertSee('Reprise : la boîte de la question ne change pas.');
    }

    public function test_presentation_order_can_be_chosen_for_the_session(): void
    {
        $suite = $this->suite();
        Question::factory()->for($this->bank)->vraiFaux()->create();

        $this->page($suite)->assertSet('order', 'boite_un_d_abord')->set('order', 'melange')->call('start');

        $this->assertSame(PresentationOrder::Melange, Session::query()->sole()->presentation_order);
    }

    public function test_nothing_to_ask(): void
    {
        $suite = $this->suite();

        $this->page($suite)->assertSee('Rien à poser à cette séance')->call('start')->assertNotified('Pas de séance');
    }

    public function test_deleted_question_is_announced_and_skipped(): void
    {
        $suite = $this->suite();
        $questions = Question::factory()->count(2)->for($this->bank)->vraiFaux(true)->create();
        $page = $this->page($suite)->call('start');
        $current = $this->currentQuestion($page);
        $questions->first(fn (Question $question): bool => ! $question->is($current))->delete();

        $page->call('answer', 'vrai')->call('next')->assertNotified('Question supprimée')->assertSet('step', Seance::STEP_SUMMARY);
    }

    public function test_suite_state_page(): void
    {
        $suite = $this->suite(['box_count' => 3, 'min_box_one' => 1]);
        Question::factory()->for($this->bank)->vraiFaux(true)->difficulty(1)->create();
        Question::factory()->for($this->bank)->vraiFaux(true)->difficulty(3)->create();
        Question::factory()->for($this->bank)->vraiFaux(true)->create();

        $this->travelTo(Carbon::parse('2026-11-02 18:00'));
        $this->page($suite)->call('start')->call('answer', 'vrai')->call('next');

        $this->travelTo(Carbon::parse('2026-11-03 18:00'));
        $this->get(SuiteResource::getUrl('view', ['record' => $suite]))
            ->assertOk()
            ->assertSeeText('En banque (jamais posées) : 2 — Moyen : 1, Difficile : 1')
            ->assertSeeText('Total : 3 question(s) = 2 en banque + 1 dans les boîtes + 0 maîtrisée(s) + 0 suspendue(s).')
            ->assertSee('Séance n° 2 — boîtes posées : 1 et 2 — 2 questions, dont 1 nouvelle.')
            ->assertSee('1 séance(s) sur les 7 derniers jours')
            ->assertDontSee('Rythme insuffisant');

        $this->travelTo(Carbon::parse('2026-11-12 18:00'));
        Livewire::test(ViewSuite::class, ['record' => $suite->getRouteKey()])
            ->assertSee('Rythme insuffisant')
            ->assertSee('0 séance sur les 7 derniers jours.')
            ->assertActionHasLabel('session', 'Commencer la séance');
    }

    public function test_suite_state_offers_to_resume_a_session_in_progress(): void
    {
        $suite = $this->suite();
        Question::factory()->count(2)->for($this->bank)->vraiFaux(true)->create();
        $this->page($suite)->call('start')->call('answer', 'vrai');

        Livewire::test(ViewSuite::class, ['record' => $suite->getRouteKey()])
            ->assertSee('Séance en cours')
            ->assertActionHasLabel('session', 'Reprendre la séance')
            ->assertActionHasUrl('session', Seance::getUrl(['suite' => $suite->id]));
    }

    public function test_answers_record_a_duration(): void
    {
        $suite = $this->suite();
        Question::factory()->for($this->bank)->vraiFaux(true)->create();

        $this->page($suite)->call('start')->call('answer', 'vrai');

        $entry = Session::query()->sole()->entries()->sole();
        $this->assertSame(Verdict::Juste, $entry->verdict);
        $this->assertNotNull($entry->duration_ms);
        $this->assertGreaterThanOrEqual(0, $entry->duration_ms);
    }
}
