<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Filament\Resources\Exams\Pages\EditExam;
use App\Qcm\Livewire\TakeExam;
use App\Qcm\Models\Attempt;
use App\Qcm\Models\AttemptItem;
use App\Qcm\Models\AttemptStatus;
use App\Qcm\Models\Exam;
use App\Qcm\Models\FieldRequirement;
use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use App\Qcm\Models\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Étape 9 du plan QCM : écran de passage, certificat et correction
 * (PRD QCM UC-12, UC-13, UC-20 étape 5).
 */
class TakeExamTest extends TestCase
{
    use RefreshDatabase;

    private const CANDIDATE = ['nom' => 'Martin', 'prenom' => 'Alice'];

    public function test_the_exam_page_opens_outside_the_panel_on_the_welcome_screen(): void
    {
        $exam = $this->exam(['title' => 'Blanc planeur', 'instructions' => 'Calculatrice interdite.', 'duration_minutes' => 45, 'pass_rate' => 75]);

        $this->get(route('qcm.examens.passer', $exam))
            ->assertOk()
            ->assertSee('Blanc planeur')
            ->assertSee('Calculatrice interdite.')
            ->assertSeeInOrder(['Nombre de questions', '3', 'Durée', '45 minutes', 'Taux de réussite minimal', '75 %'])
            ->assertSee('Données personnelles')
            ->assertDontSee('Banques de questions');

        $this->get(route('qcm.examens.apercu', $exam))->assertOk()->assertSee('Prévisualisation');
    }

    public function test_a_candidate_takes_the_exam_from_identification_to_certificate(): void
    {
        $exam = $this->exam(['title' => 'Blanc planeur', 'pass_rate' => 60, 'shuffle_questions' => false]);
        $exam->questionnaire->update(['title' => 'QCM planeur', 'version' => 'v2']);
        [$first, $second, $third] = $exam->questionnaire->questions()->get()->all();

        $component = $this->identified($exam)
            ->assertSet('step', TakeExam::STEP_QUESTION)
            ->assertSee('Question 1 sur 3')
            ->assertSee($first->statement)
            ->call('choose', $this->correctRank($first))
            ->call('toggleBookmark')
            ->assertSee('★ Marquée')
            ->call('next')
            ->assertSee('Question 2 sur 3')
            ->call('choose', $this->wrongRank($second))
            ->call('choose', $this->correctRank($second))
            ->call('goTo', 0)
            ->assertSee($first->statement)
            ->call('reviewBeforeFinish')
            ->assertSet('step', TakeExam::STEP_CONFIRM)
            ->assertSee('Questions non répondues : <strong>1</strong>', false)
            ->assertSee('(n° 3)')
            ->assertSee('Questions marquées : <strong>1</strong>', false)
            ->assertSee('(n° 1)')
            ->call('finish')
            ->assertSet('step', TakeExam::STEP_DONE);

        $attempt = Attempt::sole();
        $result = $attempt->result;
        $this->assertSame(AttemptStatus::Finished, $attempt->status);
        $this->assertSame(['nom' => 'Martin', 'prenom' => 'Alice'], $attempt->identity);
        $this->assertSame(66.67, $result->global_rate);
        $this->assertTrue($result->passed);

        $component
            ->assertSee('Certificat de résultat')
            ->assertSee($result->certificate_number)
            ->assertSee('Réussi')
            ->assertSee('66,67 %')
            ->assertSee('Imprimer ou enregistrer en PDF')
            ->assertSee('Correction détaillée');

        // Les réponses ne peuvent plus changer.
        $component->call('choose', $this->wrongRank($first));
        $this->assertSame($this->correctAnswer($first), $attempt->items()->where('position', 0)->value('chosen_key'));
        $this->assertNull($attempt->items()->where('question_id', $third->id)->value('chosen_key'));
    }

    public function test_identification_refuses_missing_or_malformed_fields(): void
    {
        $exam = $this->exam(['identification_fields' => [...Exam::defaultIdentificationFields(), 'email' => FieldRequirement::Required->value]]);

        Livewire::test(TakeExam::class, ['exam' => $exam])
            ->call('begin')
            ->assertSet('step', TakeExam::STEP_IDENTIFY)
            ->assertSee('Adresse e-mail')
            ->set('identity', ['nom' => 'Martin', 'prenom' => '', 'email' => 'pas-une-adresse'])
            ->call('start')
            ->assertHasErrors(['identity.prenom', 'identity.email'])
            ->assertSee('Le champ « Prénom » est obligatoire.')
            ->assertSet('step', TakeExam::STEP_IDENTIFY);

        $this->assertSame(0, Attempt::count());
    }

    public function test_no_correct_answer_nor_explanation_is_rendered_before_the_end(): void
    {
        $exam = $this->exam(['shuffle_questions' => false]);
        foreach ($exam->questionnaire->questions()->get() as $index => $question) {
            $payload = $question->payload;
            foreach (array_keys($payload['wrong']) as $wrong) {
                $payload['wrong'][$wrong]['explanation'] = "EXPLICATION-REPONSE-{$index}-{$wrong}";
            }
            $question->update(['explanation' => "EXPLICATION-GENERALE-$index", 'payload' => $payload]);
        }

        $component = $this->identified($exam);

        foreach ([0, 1, 2] as $position) {
            $html = $component->call('goTo', $position)->html();
            $this->assertStringNotContainsString('EXPLICATION-', $html);
            $this->assertStringNotContainsString('bonne réponse', $html);
            $this->assertStringNotContainsString('is_correct', $html);
            $this->assertStringNotContainsString("'bonne'", $html, 'La clé de la bonne réponse ne doit pas figurer dans la page.');
            $this->assertStringNotContainsString('fausse-', $html);
        }

        $html = $component->call('reviewBeforeFinish')->html();
        $this->assertStringNotContainsString('EXPLICATION-', $html);

        $component->call('finish')->assertSee('EXPLICATION-GENERALE-0')->assertSee('bonne réponse');
    }

    public function test_the_correction_can_be_filtered_and_is_hidden_when_the_exam_does_not_show_it(): void
    {
        $exam = $this->exam(['shuffle_questions' => false]);
        [$first, $second, $third] = $exam->questionnaire->questions()->get()->all();

        $component = $this->identified($exam)
            ->call('choose', $this->correctRank($first))
            ->call('next')
            ->call('choose', $this->wrongRank($second))
            ->call('toggleBookmark')
            ->call('finish');

        $this->assertSame([0, 1, 2], array_column($component->instance()->correction(), 'position'));
        $this->assertSame([1, 2], array_column($component->set('filter', 'wrong')->instance()->correction(), 'position'));
        $this->assertSame([2], array_column($component->set('filter', 'unanswered')->instance()->correction(), 'position'));
        $component->set('filter', 'bookmarked')->assertSee($second->statement)->assertDontSee($third->statement);

        $hidden = $this->exam(['shows_correction' => false]);
        $this->identified($hidden)->call('finish')
            ->assertSee('Certificat de résultat')
            ->assertDontSee('Correction détaillée');
    }

    public function test_the_certificate_shows_the_stored_result_and_no_question(): void
    {
        $exam = $this->exam([
            'identification_fields' => [...Exam::defaultIdentificationFields(), 'identifiant' => FieldRequirement::Required->value],
            'identifier_label' => 'Numéro de licence',
        ]);
        $component = $this->identified($exam, [...self::CANDIDATE, 'identifiant' => 'LIC-42'])->call('finish');
        $result = Result::sole();

        $this->assertSame([
            ['key' => 'nom', 'label' => 'Nom', 'value' => 'Martin'],
            ['key' => 'prenom', 'label' => 'Prénom', 'value' => 'Alice'],
            ['key' => 'identifiant', 'label' => 'Numéro de licence', 'value' => 'LIC-42'],
        ], $result->identity);

        $html = view('qcm.certificat', ['result' => $result])->render();
        foreach ([$result->exam_title, $result->questionnaire_title, 'Numéro de licence', 'LIC-42', 'Martin', 'Alice',
            $result->taken_at->format('d/m/Y à H:i'), 'Taux global', '0 %', 'Non réussi', $result->certificate_number] as $expected) {
            $this->assertStringContainsString(e($expected), $html);
        }
        foreach ($exam->questionnaire->questions()->get() as $question) {
            $this->assertStringNotContainsString(e($question->statement), $html);
        }
        $this->assertStringNotContainsString('Réédition', $html);
        $this->assertStringContainsString('Réédition du 01/02/2027', view('qcm.certificat', ['result' => $result, 'reissuedAt' => now()->setDate(2027, 2, 1)])->render());

        $component->assertSeeHtml('window.print()');
    }

    public function test_the_preview_records_nothing(): void
    {
        $exam = $this->exam(['duration_minutes' => 30, 'shuffle_questions' => false]);
        $first = $exam->questionnaire->questions()->first();

        $preview = Livewire::test(TakeExam::class, ['exam' => $exam, 'asPreview' => true])
            ->assertSee('Prévisualisation')
            ->call('begin')
            ->assertSet('identity', ['nom' => 'Exemple', 'prenom' => 'Candidat'])
            ->call('start')
            ->assertSet('step', TakeExam::STEP_QUESTION)
            ->assertSee('Temps restant');

        $preview->call('choose', $this->correctRank($first, $preview))
            ->call('toggleBookmark')
            ->assertSee('★ Marquée')
            ->call('reviewBeforeFinish')
            ->call('finish')
            ->assertSee('Certificat de résultat')
            ->assertSee('APERÇU')
            ->assertSee('33,33 %')
            ->assertSee('Exemple');

        $this->assertSame([0, 0, 0], [Attempt::count(), Result::count(), session()->has('qcm.passages.'.$exam->id) ? 1 : 0]);
    }

    public function test_reloading_resumes_the_same_attempt(): void
    {
        $this->freezeSecond();
        $exam = $this->exam(['duration_minutes' => 20, 'shuffle_questions' => false]);
        $first = $exam->questionnaire->questions()->first();
        $this->identified($exam)->call('choose', $this->correctRank($first))->call('toggleBookmark');
        $attempt = Attempt::sole();

        $this->travel(5)->minutes();

        Livewire::test(TakeExam::class, ['exam' => $exam])
            ->assertSet('attemptId', $attempt->id)
            ->assertSet('step', TakeExam::STEP_QUESTION)
            ->assertSet('position', 1)
            ->assertSee('1 / 3 répondue(s)')
            ->assertSeeHtml('qcmCountdown(900)');

        $this->assertSame(1, Attempt::count());
        $this->assertTrue($attempt->refresh()->deadline_at->equalTo(now()->addMinutes(15)));

        // Un autre examen ne reprend pas ce passage.
        Livewire::test(TakeExam::class, ['exam' => $this->exam()])->assertSet('attemptId', null)->assertSet('step', TakeExam::STEP_WELCOME);
    }

    public function test_a_finished_attempt_shows_its_certificate_until_the_next_candidate(): void
    {
        $exam = $this->exam();
        $this->identified($exam)->call('finish');

        Livewire::test(TakeExam::class, ['exam' => $exam])
            ->assertSet('step', TakeExam::STEP_DONE)
            ->assertSee(Result::sole()->certificate_number)
            ->call('newAttempt')
            ->assertSet('step', TakeExam::STEP_WELCOME)
            ->assertSet('attemptId', null);

        $this->assertFalse(session()->has('qcm.passages.'.$exam->id));
    }

    public function test_the_attempt_ends_automatically_when_time_is_up(): void
    {
        $exam = $this->exam(['duration_minutes' => 10]);
        $component = $this->identified($exam);

        $this->travel(10)->minutes();

        $component->call('timeUp')
            ->assertSet('step', TakeExam::STEP_DONE)
            ->assertSee('Le temps imparti est écoulé')
            ->assertSee('Certificat de résultat');
        $this->assertSame(AttemptStatus::Expired, Attempt::sole()->status);
    }

    public function test_time_up_reported_early_by_the_browser_does_not_end_the_attempt(): void
    {
        $this->freezeSecond();
        $component = $this->identified($this->exam(['duration_minutes' => 10]));

        $this->travel(9)->minutes();

        $component->call('timeUp')->assertSet('step', TakeExam::STEP_QUESTION)->assertSeeHtml('qcmCountdown(60)');
        $this->assertSame(AttemptStatus::InProgress, Attempt::sole()->status);
    }

    public function test_refusals_are_shown_to_the_candidate(): void
    {
        $exam = $this->exam(['max_attempts' => 1]);
        $this->identified($exam)->call('finish')->call('newAttempt');

        $this->identified($exam)
            ->assertSet('step', TakeExam::STEP_IDENTIFY)
            ->assertSee('le nombre maximal de passages autorisé (1)');

        Livewire::test(TakeExam::class, ['exam' => Exam::factory()->create()])
            ->call('begin')
            ->assertSet('step', TakeExam::STEP_WELCOME)
            ->assertSee('ne contient aucune question');
    }

    public function test_the_panel_opens_the_exam_and_its_preview_in_a_new_tab(): void
    {
        $exam = $this->exam();

        Livewire::test(EditExam::class, ['record' => $exam->getRouteKey()])
            ->assertActionHasUrl('take', route('qcm.examens.passer', $exam))
            ->assertActionShouldOpenUrlInNewTab('take')
            ->assertActionHasUrl('preview', route('qcm.examens.apercu', $exam))
            ->assertActionShouldOpenUrlInNewTab('preview');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function exam(array $attributes = []): Exam
    {
        return Exam::factory()->for(Questionnaire::factory()->withOwnQuestions(3))->create([
            'duration_minutes' => null,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, string>  $identity
     */
    private function identified(Exam $exam, array $identity = self::CANDIDATE): Testable
    {
        return Livewire::test(TakeExam::class, ['exam' => $exam])
            ->call('begin')
            ->set('identity', $identity)
            ->call('start');
    }

    private function correctAnswer(Question $question): string
    {
        return (string) $question->correctAnswer?->key;
    }

    private function wrongAnswer(Question $question): string
    {
        return (string) $question->wrongAnswers->first()?->key;
    }

    /**
     * Rang affiché de la bonne réponse : la page n'envoie que des rangs, jamais
     * les clés des réponses.
     */
    private function correctRank(Question $question, ?Testable $preview = null): int
    {
        return $this->rankOf($question, $this->correctAnswer($question), $preview);
    }

    private function wrongRank(Question $question, ?Testable $preview = null): int
    {
        return $this->rankOf($question, $this->wrongAnswer($question), $preview);
    }

    private function rankOf(Question $question, string $key, ?Testable $preview): int
    {
        $order = $preview !== null
            ? collect($preview->get('previewItems'))->firstWhere('question_id', $question->id)['answer_order']
            : AttemptItem::query()->where('question_id', $question->id)->latest('id')->value('answer_order');
        $rank = array_search($key, $order, true);
        $this->assertIsInt($rank, 'Réponse absente de l\'ordre affiché.');

        return $rank;
    }
}
