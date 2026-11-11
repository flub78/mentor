<?php

namespace Tests\Feature\Qcm;

use App\Banques\Models\Bank as CommonBank;
use App\Banques\Models\Question as CommonQuestion;
use App\Banques\Questions\QuestionType;
use App\Qcm\Migration\QcmToBanquesTransfer;
use App\Qcm\Models\Answer;
use App\Qcm\Models\Attempt;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Plan Banques, étape B2 : reprise des banques et des questions QCM dans le
 * module Banques de questions.
 */
class QcmToBanquesTransferTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Question QCM avec une bonne réponse et des mauvaises réponses données.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{string, string|null}>  $wrong  texte, explication
     */
    private function qcmQuestion(array $attributes, string $correct, array $wrong): Question
    {
        $question = Question::factory()->create($attributes);
        Answer::factory()->for($question)->correct()->create(['text' => $correct, 'position' => 0]);

        foreach ($wrong as $index => [$text, $explanation]) {
            Answer::factory()->for($question)->create(['text' => $text, 'explanation' => $explanation, 'position' => $index + 1]);
        }

        return $question;
    }

    public function test_banks_and_questions_are_copied_with_their_answers(): void
    {
        $this->travelTo(Carbon::parse('2026-11-01 10:00'));
        $bank = Bank::factory()->create(['name' => 'Météorologie', 'description' => 'Module météo']);
        $question = $this->qcmQuestion(
            ['bank_id' => $bank->id, 'statement' => 'Quel nuage annonce un orage ?', 'module' => 'Nuages', 'explanation' => 'Nuage à fort développement vertical.', 'image_path' => 'qcm/images/nuage.png'],
            'Cumulonimbus',
            [['Cirrus', 'Nuage de haute altitude.'], ['Stratus', null], ['Altostratus', null]],
        );
        $this->travelBack();

        $map = (new QcmToBanquesTransfer)->run();

        $common = CommonQuestion::query()->findOrFail($map[$question->id]);
        $this->assertSame(QuestionType::Qcm, $common->type);
        $this->assertSame('Quel nuage annonce un orage ?', $common->statement);
        $this->assertSame([
            'correct' => 'Cumulonimbus',
            'wrong' => [
                ['text' => 'Cirrus', 'explanation' => 'Nuage de haute altitude.'],
                ['text' => 'Stratus', 'explanation' => null],
                ['text' => 'Altostratus', 'explanation' => null],
            ],
            'shuffle' => true,
        ], $common->payload);
        $this->assertSame('Nuages', $common->theme);
        $this->assertSame('Nuage à fort développement vertical.', $common->explanation);
        $this->assertSame('qcm/images/nuage.png', $common->image_path);
        $this->assertSame('Illustration : Quel nuage annonce un orage ?', $common->image_alt);
        $this->assertSame('2026-11-01 10:00:00', $common->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('Météorologie', $common->bank->name);
        $this->assertSame('Module météo', $common->bank->description);
    }

    public function test_own_questions_go_to_the_default_bank(): void
    {
        $questionnaire = Questionnaire::factory()->create();
        $own = $this->qcmQuestion(['bank_id' => null, 'questionnaire_id' => $questionnaire->id], 'Oui', [['Non', null]]);

        $map = (new QcmToBanquesTransfer)->run();

        $this->assertTrue(CommonQuestion::query()->findOrFail($map[$own->id])->bank->is(CommonBank::default()));
    }

    public function test_every_question_and_answer_is_kept(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->count(4)->for($bank)->withAnswers(3)->create();
        Questionnaire::factory()->withOwnQuestions(2)->create();
        $questions = DB::table('qcm_questions')->count();

        $map = (new QcmToBanquesTransfer)->run();

        $this->assertCount($questions, $map);
        foreach ($map as $qcmId => $commonId) {
            $payload = CommonQuestion::query()->findOrFail($commonId)->payload;
            $this->assertSame(
                DB::table('qcm_answers')->where('question_id', $qcmId)->where('is_correct', false)->orderBy('position')->pluck('text')->all(),
                array_column($payload['wrong'], 'text'),
            );
        }
        $this->assertSame(4, DB::table('qcm_questions')->whereNotNull('bank_id')->count(), 'Les tables QCM ne sont pas modifiées.');
    }

    public function test_bank_name_already_taken_gets_a_suffix(): void
    {
        CommonBank::factory()->create(['name' => 'Capitales']);
        CommonBank::factory()->create(['name' => 'Capitales (QCM)']);
        Bank::factory()->create(['name' => 'Capitales']);

        (new QcmToBanquesTransfer)->run();

        $this->assertTrue(CommonBank::query()->where('name', 'Capitales (QCM 2)')->exists());
    }

    public function test_transfer_is_refused_while_an_attempt_is_in_progress(): void
    {
        Attempt::factory()->create();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('1 passage(s) d\'examen en cours : terminez-les');

        (new QcmToBanquesTransfer)->run();
    }

    public function test_incomplete_question_stops_the_transfer_with_a_clear_message(): void
    {
        $bank = Bank::factory()->create();
        $broken = $this->qcmQuestion(['bank_id' => $bank->id, 'statement' => 'Question sans mauvaise réponse'], 'Seule réponse', []);
        $banks = CommonBank::query()->count();

        try {
            (new QcmToBanquesTransfer)->run();
            $this->fail('Une question incomplète doit arrêter la reprise.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("Question QCM n° {$broken->id}", $exception->getMessage());
            $this->assertStringContainsString('au moins une mauvaise réponse', $exception->getMessage());
        }

        $this->assertSame($banks, CommonBank::query()->count(), 'Rien n\'est repris quand une question échoue.');
        $this->assertSame(0, CommonQuestion::query()->count());
    }
}
