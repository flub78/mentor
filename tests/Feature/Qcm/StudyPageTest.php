<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Filament\Pages\Study;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Étape 2 du plan QCM : page d'étude libre (PRD QCM UC-11), sans connexion.
 */
class StudyPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_study_page_is_reachable_without_login(): void
    {
        $this->get(Study::getUrl())->assertOk()->assertSee('Étudier');
    }

    public function test_bank_given_in_url_is_preselected_with_its_modules(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->count(2)->for($bank)->create(['module' => 'Météo']);

        Livewire::withQueryParams(['bank' => (string) $bank->id])
            ->test(Study::class)
            ->assertSet('selectedBanks', [(string) $bank->id])
            ->assertSet('modules.0.label', 'Météo')
            ->assertSet('modules.0.available', 2)
            ->assertSee('2 question(s) seront tirées au hasard.');
    }

    public function test_full_session_with_immediate_correction_and_summary(): void
    {
        $bank = Bank::factory()->create();
        $good = Question::factory()->for($bank)->withAnswers()->create(['module' => 'Météo', 'explanation' => 'Le cumulonimbus est le nuage d\'orage.']);
        $bad = Question::factory()->for($bank)->withAnswers()->create(['module' => 'Réglementation']);
        $payload = $bad->payload;
        $payload['wrong'][0]['explanation'] = 'Cette règle concerne le vol de nuit.';
        $bad->update(['payload' => $payload]);
        $wrong = $bad->refresh()->wrongAnswers->first();

        $page = Livewire::test(Study::class)
            ->set('selectedBanks', [(string) $bank->id])
            ->call('start')
            ->assertSet('step', Study::STEP_QUESTION);

        for ($i = 0; $i < 2; $i++) {
            $currentId = $page->get('session')->currentQuestionId();
            $answer = $currentId === $good->id ? $good->correctAnswer : $wrong;

            $page->set('choice', $this->rankOf($page, $answer->key))->call('validateAnswer');

            if ($currentId === $good->id) {
                $page->assertSee('Bonne réponse !')->assertSee('Le cumulonimbus est le nuage d\'orage.');
            } else {
                $page->assertSee('Mauvaise réponse.')->assertSee('Cette règle concerne le vol de nuit.');
            }

            $page->call('nextQuestion');
        }

        $page->assertSet('step', Study::STEP_SUMMARY)
            ->assertSee('50 % de bonnes réponses')
            ->assertSee('Météo')
            ->assertSee('100 %')
            ->assertSee('Questions échouées')
            ->assertSee($bad->statement);
    }

    public function test_running_percentage_is_shown_during_the_session(): void
    {
        $bank = Bank::factory()->create();
        $questions = Question::factory()->count(2)->for($bank)->withAnswers()->create(['module' => 'Météo']);

        $page = Livewire::test(Study::class)
            ->set('selectedBanks', [(string) $bank->id])
            ->call('start')
            ->assertSee('0 % de bonnes réponses (0 / 0)');

        $current = $questions->firstWhere('id', $page->get('session')->currentQuestionId());

        $page->set('choice', $this->rankOf($page, (string) $current->correctAnswer->key))
            ->call('validateAnswer')
            ->assertSee('100 % de bonnes réponses (1 / 1)')
            ->assertSee('1 restante(s)');
    }

    public function test_cannot_validate_without_choosing_an_answer(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->withAnswers()->create();

        $page = Livewire::test(Study::class)
            ->set('selectedBanks', [(string) $bank->id])
            ->call('start')
            ->call('validateAnswer')
            ->assertNotified('Choisissez une réponse avant de valider.');

        $this->assertSame(0, $page->get('session')->answeredCount());
    }

    public function test_requested_count_per_module_is_respected(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->count(5)->for($bank)->withAnswers()->create(['module' => 'Météo']);

        $page = Livewire::test(Study::class)
            ->set('selectedBanks', [(string) $bank->id])
            ->set('modules.0.all', false)
            ->set('modules.0.requested', 2)
            ->assertSee('2 question(s) seront tirées au hasard.')
            ->call('start');

        $this->assertSame(2, $page->get('session')->total());
    }

    public function test_start_without_question_is_refused_with_a_message(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->withAnswers()->create(['module' => 'Météo']);

        Livewire::test(Study::class)
            ->call('start')
            ->assertNotified('Choisissez au moins une banque.')
            ->set('selectedBanks', [(string) $bank->id])
            ->set('modules.0.all', false)
            ->set('modules.0.requested', 0)
            ->call('start')
            ->assertNotified('Aucune question à étudier.')
            ->assertSet('step', Study::STEP_CONFIG);
    }

    public function test_finishing_early_shows_the_summary_of_answered_questions(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->count(3)->for($bank)->withAnswers()->create();

        Livewire::test(Study::class)
            ->set('selectedBanks', [(string) $bank->id])
            ->call('start')
            ->call('finish')
            ->assertSet('step', Study::STEP_SUMMARY)
            ->assertSee('0 question(s) répondue(s) sur 3')
            ->call('restart')
            ->assertSet('step', Study::STEP_CONFIG)
            ->assertSet('session', null);
    }

    public function test_answer_keys_are_not_written_in_the_page(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->withAnswers()->create();

        $html = Livewire::test(Study::class)
            ->set('selectedBanks', [(string) $bank->id])
            ->call('start')
            ->html();

        $this->assertStringNotContainsString('value="bonne"', $html);
        $this->assertStringNotContainsString('value="fausse-', $html);
    }

    /**
     * Rang affiché d'une réponse : la page n'envoie que des rangs, jamais les
     * clés des réponses.
     */
    private function rankOf(Testable $page, string $key): string
    {
        $session = $page->get('session');
        $rank = array_search($key, $session->answerOrders[$session->currentQuestionId()] ?? [], true);
        $this->assertIsInt($rank, 'Réponse absente de l\'ordre affiché.');

        return (string) $rank;
    }
}
