<?php

namespace Tests\Feature\Qcm;

use App\Banques\Models\Bank as CommonBank;
use App\Banques\Models\Question as CommonQuestion;
use App\Banques\Questions\QuestionType;
use App\Qcm\Migration\QcmToBanquesTransfer;
use App\Qcm\Models\Attempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Plan Banques, étape B2 : reprise des banques et des questions QCM dans le
 * module Banques de questions. Les anciennes tables QCM n'ont plus de modèle
 * (étape B4) : les données de départ y sont écrites directement.
 */
class QcmToBanquesTransferTest extends TestCase
{
    use RefreshDatabase;

    private function oldBank(string $name, ?string $description = null): int
    {
        return DB::table('qcm_banks')->insertGetId(['name' => $name, 'description' => $description, 'created_at' => '2026-11-01 10:00:00', 'updated_at' => '2026-11-01 10:00:00']);
    }

    /**
     * Ancienne question QCM et ses réponses.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<array{string, string|null}>  $wrong  texte, explication
     */
    private function oldQuestion(array $attributes, string $correct, array $wrong): int
    {
        $id = DB::table('qcm_questions')->insertGetId([
            'bank_id' => null,
            'questionnaire_id' => null,
            'statement' => 'Question ?',
            'module' => null,
            'explanation' => null,
            'image_path' => null,
            'created_at' => '2026-11-01 10:00:00',
            'updated_at' => '2026-11-01 10:00:00',
            ...$attributes,
        ]);
        DB::table('qcm_answers')->insert(['question_id' => $id, 'text' => $correct, 'is_correct' => true, 'explanation' => null, 'position' => 0]);

        foreach ($wrong as $index => [$text, $explanation]) {
            DB::table('qcm_answers')->insert(['question_id' => $id, 'text' => $text, 'is_correct' => false, 'explanation' => $explanation, 'position' => $index + 1]);
        }

        return $id;
    }

    public function test_banks_and_questions_are_copied_with_their_answers(): void
    {
        $bank = $this->oldBank('Météorologie', 'Module météo');
        $question = $this->oldQuestion(
            ['bank_id' => $bank, 'statement' => 'Quel nuage annonce un orage ?', 'module' => 'Nuages', 'explanation' => 'Nuage à fort développement vertical.', 'image_path' => 'qcm/images/nuage.png'],
            'Cumulonimbus',
            [['Cirrus', 'Nuage de haute altitude.'], ['Stratus', null], ['Altostratus', null]],
        );

        $map = (new QcmToBanquesTransfer)->run();

        $common = CommonQuestion::query()->findOrFail($map[$question]);
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
        $questionnaire = DB::table('qcm_questionnaires')->insertGetId(['title' => 'Examen blanc']);
        $own = $this->oldQuestion(['questionnaire_id' => $questionnaire], 'Oui', [['Non', null]]);

        $map = (new QcmToBanquesTransfer)->run();

        $this->assertTrue(CommonQuestion::query()->findOrFail($map[$own])->bank->is(CommonBank::default()));
    }

    public function test_bank_name_already_taken_gets_a_suffix(): void
    {
        CommonBank::factory()->create(['name' => 'Capitales']);
        CommonBank::factory()->create(['name' => 'Capitales (QCM)']);
        $this->oldBank('Capitales');

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
        $bank = $this->oldBank('Banque');
        $broken = $this->oldQuestion(['bank_id' => $bank, 'statement' => 'Question sans mauvaise réponse'], 'Seule réponse', []);
        $banks = CommonBank::query()->count();

        try {
            (new QcmToBanquesTransfer)->run();
            $this->fail('Une question incomplète doit arrêter la reprise.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString("Question QCM n° {$broken}", $exception->getMessage());
            $this->assertStringContainsString('au moins une mauvaise réponse', $exception->getMessage());
        }

        $this->assertSame($banks, CommonBank::query()->count(), 'Rien n\'est repris quand une question échoue.');
        $this->assertSame(0, CommonQuestion::query()->count());
    }
}
