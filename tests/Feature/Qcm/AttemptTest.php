<?php

namespace Tests\Feature\Qcm;

use App\Banques\Models\Bank as CommonBank;
use App\Qcm\Exams\AttemptException;
use App\Qcm\Exams\AttemptFinisher;
use App\Qcm\Exams\AttemptRecorder;
use App\Qcm\Exams\AttemptStarter;
use App\Qcm\Models\Attempt;
use App\Qcm\Models\AttemptItem;
use App\Qcm\Models\AttemptStatus;
use App\Qcm\Models\Exam;
use App\Qcm\Models\FieldRequirement;
use App\Qcm\Models\IdentificationField;
use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use App\Qcm\Models\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\TestCase;

/**
 * Étape 8 du plan QCM : passages et résultats, modèle et logique
 * (PRD QCM UC-12, UC-20).
 */
class AttemptTest extends TestCase
{
    use RefreshDatabase;

    private const CANDIDATE = ['nom' => 'Martin', 'prenom' => 'Alice'];

    public function test_last_and_first_names_are_required_and_named_in_the_message(): void
    {
        $exam = $this->exam();

        $errors = $this->identityErrors($exam, ['nom' => '  ', 'prenom' => '']);

        $this->assertSame('Le champ « Nom » est obligatoire.', $errors['nom'][0]);
        $this->assertSame('Le champ « Prénom » est obligatoire.', $errors['prenom'][0]);
        $this->assertDatabaseCount('qcm_attempts', 0);
    }

    public function test_required_and_malformed_fields_are_rejected(): void
    {
        $exam = $this->exam(fields: [
            IdentificationField::Email->value => FieldRequirement::Required->value,
            IdentificationField::BirthDate->value => FieldRequirement::Optional->value,
            IdentificationField::Identifier->value => FieldRequirement::Required->value,
        ], attributes: ['identifier_label' => 'Numéro de licence']);

        $errors = $this->identityErrors($exam, [...self::CANDIDATE, 'email' => 'alice.exemple.fr', 'date_naissance' => '31/12/2000']);

        $this->assertStringContainsString('« Adresse e-mail » doit être une adresse e-mail valide', $errors['email'][0]);
        $this->assertStringContainsString('« Date de naissance » doit être une date valide au format AAAA-MM-JJ', $errors['date_naissance'][0]);
        $this->assertSame('Le champ « Numéro de licence » est obligatoire.', $errors['identifiant'][0]);

        $errors = $this->identityErrors($exam, [...self::CANDIDATE, 'email' => 'a@exemple.fr', 'identifiant' => 'L1', 'date_naissance' => now()->addDay()->format('Y-m-d')]);
        $this->assertSame(['date_naissance'], array_keys($errors));
    }

    public function test_fields_not_asked_are_ignored_and_empty_optional_fields_dropped(): void
    {
        $exam = $this->exam(fields: [IdentificationField::Organisation->value => FieldRequirement::Optional->value]);

        $attempt = $this->starter()->start($exam, [
            'nom' => ' Martin ', 'prenom' => 'Alice', 'organisation' => '', 'email' => 'pas@demande.fr', 'pirate' => 'x',
        ]);

        $this->assertSame(self::CANDIDATE, $attempt->identity);
    }

    public function test_an_attempt_asks_every_question_in_the_questionnaire_order(): void
    {
        $exam = $this->exam(questions: 5, attributes: ['shuffle_questions' => false, 'duration_minutes' => null]);
        $expected = $exam->questionnaire->questions()->pluck('banques_questions.id')->all();

        $attempt = $this->starter()->start($exam, self::CANDIDATE);

        $this->assertSame(AttemptStatus::InProgress, $attempt->status);
        $this->assertSame($expected, $attempt->items()->pluck('question_id')->all());
        $this->assertSame([0, 1, 2, 3, 4], $attempt->items()->pluck('position')->all());
        $this->assertNull($attempt->deadline_at);
        $this->assertNull($attempt->remainingSeconds());

        foreach ($attempt->items as $item) {
            $answers = Question::query()->findOrFail($item->question_id)->answers->pluck('key')->all();
            $this->assertEqualsCanonicalizing($answers, $item->answer_order);
        }
    }

    public function test_questions_and_answers_are_shuffled_and_the_deadline_is_set(): void
    {
        $this->freezeSecond();
        $exam = $this->exam(questions: 8, attributes: ['shuffle_questions' => true, 'duration_minutes' => 30]);
        $expected = $exam->questionnaire->questions()->pluck('banques_questions.id')->all();

        $attempt = $this->starter()->start($exam, self::CANDIDATE);
        $asked = $attempt->items()->pluck('question_id')->all();

        $this->assertEqualsCanonicalizing($expected, $asked);
        $this->assertNotSame($expected, $asked);
        $this->assertNotSame(
            $attempt->items->map(fn (AttemptItem $item): array => Question::query()->findOrFail($item->question_id)->answers->pluck('key')->all())->all(),
            $attempt->items->pluck('answer_order')->all(),
        );
        $this->assertTrue($attempt->deadline_at->equalTo(now()->addMinutes(30)));
        $this->assertSame(1800, $attempt->remainingSeconds());
    }

    public function test_an_exam_with_an_empty_questionnaire_cannot_start(): void
    {
        $exam = Exam::factory()->create(['title' => 'Vide']);

        $this->expectException(AttemptException::class);
        $this->expectExceptionMessage('ne contient aucune question');

        $this->starter()->start($exam, self::CANDIDATE);
    }

    public function test_the_last_answer_counts_and_bookmarks_can_be_toggled(): void
    {
        $attempt = $this->starter()->start($this->exam(questions: 2), self::CANDIDATE);
        $item = $attempt->items->first();
        [$first, $second] = $item->answer_order;
        $recorder = new AttemptRecorder;

        $recorder->answer($item, $first);
        $recorder->answer($item, $second);
        $recorder->bookmark($item, true);

        $item->refresh();
        $this->assertSame($second, $item->chosen_key);
        $this->assertNotNull($item->answered_at);
        $this->assertTrue($item->bookmarked);

        $recorder->bookmark($item, false);
        $this->assertFalse($item->refresh()->bookmarked);
    }

    public function test_an_answer_not_offered_for_the_question_is_refused(): void
    {
        $attempt = $this->starter()->start($this->exam(questions: 2, attributes: ['shuffle_questions' => false]), self::CANDIDATE);
        $item = $attempt->items->first();

        $this->expectException(AttemptException::class);
        $this->expectExceptionMessage('question 1');

        (new AttemptRecorder)->answer($item, 'fausse-99');
    }

    public function test_answering_or_bookmarking_after_the_end_is_refused(): void
    {
        $attempt = $this->starter()->start($this->exam(questions: 2), self::CANDIDATE);
        $item = $attempt->items->first();
        (new AttemptFinisher)->finish($attempt);

        foreach ([fn () => (new AttemptRecorder)->answer($item, $item->answer_order[0]), fn () => (new AttemptRecorder)->bookmark($item, true)] as $action) {
            try {
                $action();
                $this->fail('Action acceptée sur un passage terminé.');
            } catch (AttemptException $exception) {
                $this->assertStringContainsString('Ce passage est terminé', $exception->getMessage());
            }
        }

        $this->assertNull($item->refresh()->chosen_key);
        $this->assertFalse($item->bookmarked);
    }

    public function test_the_result_gives_rates_per_module_and_overall(): void
    {
        $questionnaire = Questionnaire::factory()->create(['title' => 'QCM pilote', 'version' => 'v3']);
        $modules = ['Météorologie', 'Météorologie', 'météorologie ', 'Réglementation', null];
        foreach ($modules as $module) {
            $question = Question::factory()->withAnswers()->create(['bank_id' => CommonBank::default()->id, 'module' => $module]);
            $questionnaire->questions()->attach($question->id);
        }
        $exam = Exam::factory()->for($questionnaire)->create(['title' => 'Blanc n° 1', 'pass_rate' => 60, 'shuffle_questions' => false]);

        $attempt = $this->starter()->start($exam, self::CANDIDATE);
        $items = $attempt->items->all();
        $this->answerCorrectly($items[0]);
        $this->answerWrongly($items[1]);
        $this->answerCorrectly($items[2]);
        // Réglementation : non répondue, comptée fausse.
        $this->answerCorrectly($items[4]);

        $result = (new AttemptFinisher)->finish($attempt);

        $this->assertSame(AttemptStatus::Finished, $attempt->refresh()->status);
        $this->assertNotNull($attempt->finished_at);
        $this->assertSame([
            ['label' => 'Météorologie', 'correct' => 2, 'total' => 3, 'rate' => 66.67],
            ['label' => 'Réglementation', 'correct' => 0, 'total' => 1, 'rate' => 0],
            ['label' => 'Sans module', 'correct' => 1, 'total' => 1, 'rate' => 100],
        ], $result->module_rates);
        $this->assertSame(60.0, $result->global_rate);
        $this->assertSame(60, $result->pass_rate);
        $this->assertTrue($result->passed);
        $this->assertSame('Blanc n° 1', $result->exam_title);
        $this->assertSame('QCM pilote', $result->questionnaire_title);
        $this->assertSame('v3', $result->questionnaire_version);
        $this->assertSame('Martin', $result->last_name);
        $this->assertSame('Alice', $result->first_name);
        $this->assertTrue($result->taken_at->equalTo($attempt->started_at));
        $this->assertMatchesRegularExpression('/^\d{8}-[0-9A-HJKMNP-TV-Z]{6}$/', $result->certificate_number);
        $this->assertStringStartsWith($attempt->started_at->format('Ymd'), $result->certificate_number);
    }

    public function test_success_is_decided_at_the_exact_pass_rate(): void
    {
        foreach ([3 => true, 2 => false] as $correct => $passed) {
            $attempt = $this->starter()->start($this->exam(questions: 4, attributes: ['pass_rate' => 75]), self::CANDIDATE);

            foreach ($attempt->items as $index => $item) {
                $index < $correct ? $this->answerCorrectly($item) : $this->answerWrongly($item);
            }

            $result = (new AttemptFinisher)->finish($attempt);
            $this->assertSame($correct * 25.0, $result->global_rate);
            $this->assertSame($passed, $result->passed, "$correct bonnes réponses sur 4");
        }
    }

    public function test_finishing_twice_keeps_the_first_result(): void
    {
        $attempt = $this->starter()->start($this->exam(), self::CANDIDATE);
        $finisher = new AttemptFinisher;

        $first = $finisher->finish($attempt);
        $second = $finisher->finish($attempt, AttemptStatus::Expired);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(AttemptStatus::Finished, $attempt->refresh()->status);
        $this->assertSame(1, Result::where('attempt_id', $attempt->id)->count());
    }

    public function test_the_result_survives_later_changes_and_deletions(): void
    {
        $exam = $this->exam(questions: 3);
        $questionnaire = $exam->questionnaire;
        $attempt = $this->starter()->start($exam, self::CANDIDATE);
        $attempt->items->each(fn (AttemptItem $item) => $this->answerCorrectly($item));
        $result = (new AttemptFinisher)->finish($attempt);
        $snapshot = $result->refresh()->attributesToArray();

        $questionnaire->questions()->each(function (Question $question): void {
            $question->update(['module' => 'Autre', 'payload' => ['correct' => 'Nouvelle réponse', 'wrong' => [['text' => 'Ancienne bonne réponse', 'explanation' => null]], 'shuffle' => true]]);
        });
        $questionnaire->update(['title' => 'Renommé', 'version' => 'v99']);
        $exam->update(['title' => 'Renommé', 'pass_rate' => 100]);
        $questions = $questionnaire->questions()->get();
        $exam->delete();
        $questionnaire->delete();
        $questions->each(fn (Question $question) => $question->delete());

        $this->assertSame($snapshot, $result->refresh()->attributesToArray());
        $this->assertNull($attempt->refresh()->exam_id);
        $this->assertSame([null, null, null], $attempt->items()->pluck('question_id')->all());
    }

    public function test_an_overdue_attempt_is_finished_on_next_access(): void
    {
        $attempt = $this->starter()->start($this->exam(questions: 2, attributes: ['duration_minutes' => 10]), self::CANDIDATE);
        $item = $attempt->items->first();
        $this->answerCorrectly($item);

        $this->travel(10)->minutes();

        try {
            (new AttemptRecorder)->answer($item, $item->answer_order[0]);
            $this->fail('Réponse acceptée après l\'échéance.');
        } catch (AttemptException $exception) {
            $this->assertStringContainsString('Le temps imparti est écoulé', $exception->getMessage());
        }

        $this->assertSame(AttemptStatus::Expired, $attempt->refresh()->status);
        $this->assertSame(50.0, $attempt->result->global_rate);
    }

    public function test_the_scheduled_command_finishes_abandoned_attempts_only(): void
    {
        $this->freezeSecond();
        $overdue = $this->starter()->start($this->exam(attributes: ['duration_minutes' => 10]), self::CANDIDATE);
        $running = $this->starter()->start($this->exam(attributes: ['duration_minutes' => 60]), self::CANDIDATE);
        $untimed = $this->starter()->start($this->exam(attributes: ['duration_minutes' => null]), self::CANDIDATE);

        $this->travel(11)->minutes();

        $this->artisan('qcm:terminer-passages-echus')
            ->expectsOutputToContain('1 passage(s) échu(s) terminé(s)')
            ->assertSuccessful();

        $this->assertSame(AttemptStatus::Expired, $overdue->refresh()->status);
        $this->assertNotNull($overdue->result);
        $this->assertSame(AttemptStatus::InProgress, $running->refresh()->status);
        $this->assertSame(AttemptStatus::InProgress, $untimed->refresh()->status);
        $this->assertSame(49 * 60, $running->remainingSeconds());

        $this->artisan('qcm:terminer-passages-echus')->expectsOutput('Aucun passage échu à terminer.');
    }

    public function test_the_maximum_number_of_attempts_is_enforced_by_name(): void
    {
        $exam = $this->exam(attributes: ['max_attempts' => 2, 'title' => 'Blanc n° 2']);
        $starter = $this->starter();
        $starter->start($exam, self::CANDIDATE);
        (new AttemptFinisher)->finish($starter->start($exam, ['nom' => 'MARTIN', 'prenom' => ' alice ']));

        $starter->start($exam, ['nom' => 'Martin', 'prenom' => 'Bob']);

        $this->expectException(AttemptException::class);
        $this->expectExceptionMessage('Vous avez déjà passé l\'examen « Blanc n° 2 » 2 fois, le nombre maximal de passages autorisé (2).');

        $starter->start($exam, self::CANDIDATE);
    }

    public function test_the_maximum_number_of_attempts_is_enforced_by_email_when_asked(): void
    {
        $exam = $this->exam(fields: [IdentificationField::Email->value => FieldRequirement::Required->value], attributes: ['max_attempts' => 1]);
        $starter = $this->starter();
        $starter->start($exam, [...self::CANDIDATE, 'email' => 'alice@exemple.fr']);

        $other = $starter->start($exam, [...self::CANDIDATE, 'email' => 'autre@exemple.fr']);
        $this->assertSame('autre@exemple.fr', $other->identity['email']);

        $this->expectException(AttemptException::class);
        $starter->start($exam, ['nom' => 'Dupont', 'prenom' => 'Jean', 'email' => 'Alice@Exemple.fr']);
    }

    public function test_factories_build_finished_and_expired_attempts(): void
    {
        $item = AttemptItem::factory()->for(Attempt::factory()->finished())->create();

        $this->assertSame(AttemptStatus::Finished, $item->attempt->status);
        $this->assertSame(AttemptStatus::Expired, Attempt::factory()->expired()->create()->status);
        $this->assertCount(4, $item->answer_order);
        $this->assertNotNull(Result::factory()->create()->certificate_number);
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, mixed>  $attributes
     */
    private function exam(int $questions = 2, array $fields = [], array $attributes = []): Exam
    {
        return Exam::factory()
            ->for(Questionnaire::factory()->withOwnQuestions($questions))
            ->create([
                'identification_fields' => [...Exam::defaultIdentificationFields(), ...$fields],
                ...$attributes,
            ]);
    }

    private function starter(): AttemptStarter
    {
        return new AttemptStarter(new Randomizer(new Mt19937(42)));
    }

    /**
     * @param  array<string, mixed>  $identity
     * @return array<string, list<string>>
     */
    private function identityErrors(Exam $exam, array $identity): array
    {
        try {
            $this->starter()->start($exam, $identity);
        } catch (ValidationException $exception) {
            return $exception->errors();
        }

        $this->fail('Identité acceptée à tort.');
    }

    private function answerCorrectly(AttemptItem $item): void
    {
        (new AttemptRecorder)->answer($item, (string) Question::query()->findOrFail($item->question_id)->correctAnswer?->key);
    }

    private function answerWrongly(AttemptItem $item): void
    {
        (new AttemptRecorder)->answer($item, (string) Question::query()->findOrFail($item->question_id)->wrongAnswers->first()?->key);
    }
}
