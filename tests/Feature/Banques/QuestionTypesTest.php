<?php

namespace Tests\Feature\Banques;

use App\Banques\Questions\AnswerNormalizer;
use App\Banques\Questions\Difficulty;
use App\Banques\Questions\Handlers\QcmHandler;
use App\Banques\Questions\Handlers\ValeurNumeriqueHandler;
use App\Banques\Questions\InvalidResponse;
use App\Banques\Questions\QuestionType;
use App\Banques\Questions\Verdict;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Étape 1 du plan Leitner : types de questions du MVP (PRD Leitner §6),
 * validation de la réponse attendue et correction.
 */
class QuestionTypesTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function qcmPayload(): array
    {
        return QuestionType::Qcm->handler()->normalize([
            'correct' => 'Paris',
            'wrong' => [
                'a' => ['text' => 'Lyon', 'explanation' => 'Préfecture du Rhône.'],
                'b' => ['text' => 'Marseille', 'explanation' => ''],
            ],
            'shuffle' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function numeric(array $payload): array
    {
        return QuestionType::ValeurNumerique->handler()->normalize($payload);
    }

    public function test_each_type_has_a_french_label_and_a_handler(): void
    {
        $this->assertSame(['QCM', 'Vrai / faux', 'Auto-évaluation', 'Valeur numérique'], array_map(
            fn (QuestionType $type): string => $type->getLabel(),
            QuestionType::cases(),
        ));

        foreach (QuestionType::cases() as $type) {
            $this->assertNotEmpty($type->handler()->formSchema());
            $this->assertStringStartsWith('leitner.answers.', $type->handler()->answerView());
        }
    }

    public function test_qcm_payload_is_normalized_and_valid(): void
    {
        $payload = $this->qcmPayload();

        $this->assertSame([
            'correct' => 'Paris',
            'wrong' => [
                ['text' => 'Lyon', 'explanation' => 'Préfecture du Rhône.'],
                ['text' => 'Marseille', 'explanation' => null],
            ],
            'shuffle' => true,
            'correct_position' => 0,
        ], $payload);
        $this->assertSame([], QuestionType::Qcm->payloadErrors($payload));
    }

    public function test_qcm_without_correct_or_wrong_answer_is_rejected(): void
    {
        $handler = QuestionType::Qcm->handler();

        $this->assertContains('La bonne réponse est obligatoire.', QuestionType::Qcm->payloadErrors($handler->normalize(['correct' => '', 'wrong' => [['text' => 'Lyon']]])));
        $this->assertContains('Une question QCM doit avoir au moins une mauvaise réponse.', QuestionType::Qcm->payloadErrors($handler->normalize(['correct' => 'Paris', 'wrong' => []])));
        $this->assertContains('Le texte de chaque mauvaise réponse est obligatoire.', QuestionType::Qcm->payloadErrors($handler->normalize(['correct' => 'Paris', 'wrong' => [['text' => '  ']]])));
    }

    public function test_qcm_evaluation(): void
    {
        $handler = new QcmHandler;
        $payload = $this->qcmPayload();

        $this->assertSame(['bonne' => 'Paris', 'fausse-0' => 'Lyon', 'fausse-1' => 'Marseille'], $handler->options($payload));
        $this->assertSame(Verdict::Juste, $handler->evaluate($payload, 'bonne')->verdict);

        $wrong = $handler->evaluate($payload, 'fausse-0');
        $this->assertSame(Verdict::Fausse, $wrong->verdict);
        $this->assertSame('Préfecture du Rhône.', $wrong->detail);
        $this->assertNull($handler->evaluate($payload, 'fausse-1')->detail);
        $this->assertSame('Paris', $handler->expectedAnswer($payload));
    }

    public function test_qcm_rejects_an_unknown_choice(): void
    {
        $this->expectException(InvalidResponse::class);
        $this->expectExceptionMessage('Choisissez une des réponses proposées.');

        (new QcmHandler)->evaluate($this->qcmPayload(), 'fausse-7');
    }

    public function test_true_false(): void
    {
        $type = QuestionType::VraiFaux;
        $payload = $type->handler()->normalize(['value' => '0']);

        $this->assertSame(['value' => false], $payload);
        $this->assertSame([], $type->payloadErrors($payload));
        $this->assertSame(Verdict::Juste, $type->handler()->evaluate($payload, false)->verdict);
        $this->assertSame(Verdict::Juste, $type->handler()->evaluate($payload, 'faux')->verdict);
        $this->assertSame(Verdict::Fausse, $type->handler()->evaluate($payload, 'vrai')->verdict);
        $this->assertSame('Faux', $type->handler()->expectedAnswer($payload));
        $this->assertContains('Indiquez si l\'affirmation est vraie ou fausse.', $type->payloadErrors($type->handler()->normalize([])));

        $this->expectException(InvalidResponse::class);
        $type->handler()->evaluate($payload, 'peut-être');
    }

    public function test_self_evaluation(): void
    {
        $type = QuestionType::AutoEvaluation;
        $payload = $type->handler()->normalize(['answer' => '  a² + b² = c²  ']);

        $this->assertSame('a² + b² = c²', $type->handler()->expectedAnswer($payload));
        $this->assertSame(Verdict::Juste, $type->handler()->evaluate($payload, true)->verdict);
        $this->assertSame(Verdict::Fausse, $type->handler()->evaluate($payload, false)->verdict);
        $this->assertContains('La réponse de référence est obligatoire.', $type->payloadErrors($type->handler()->normalize(['answer' => ''])));

        $this->expectException(InvalidResponse::class);
        $type->handler()->evaluate($payload, 'oui');
    }

    /**
     * @return array<string, array{array<string, mixed>, string, Verdict}>
     */
    public static function numericCases(): array
    {
        $absolute = ['value' => '12.5', 'tolerance_kind' => 'absolue', 'tolerance' => '0.5', 'unit' => 'km'];
        $percent = ['value' => '200', 'tolerance_kind' => 'pourcentage', 'tolerance' => '10', 'unit' => null];
        $exact = ['value' => '0.1', 'tolerance_kind' => 'aucune', 'tolerance' => '3', 'unit' => ''];

        return [
            'absolu, valeur exacte' => [$absolute, '12,5', Verdict::Juste],
            'absolu, limite haute exacte' => [$absolute, '13', Verdict::Juste],
            'absolu, limite basse exacte' => [$absolute, '12.0', Verdict::Juste],
            'absolu, au-delà' => [$absolute, '13,01', Verdict::Fausse],
            'absolu, unité saisie' => [$absolute, '12,7 km', Verdict::Juste],
            'pourcentage, limite exacte' => [$percent, '220', Verdict::Juste],
            'pourcentage, limite basse' => [$percent, '180', Verdict::Juste],
            'pourcentage, au-delà' => [$percent, '221', Verdict::Fausse],
            'sans tolérance, valeur exacte' => [$exact, '0,1', Verdict::Juste],
            'sans tolérance, écart minime' => [$exact, '0.1001', Verdict::Fausse],
            'espaces et signe' => [['value' => '-1500', 'tolerance_kind' => 'aucune'], ' -1 500 ', Verdict::Juste],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('numericCases')]
    public function test_numeric_value(array $payload, string $response, Verdict $expected): void
    {
        $payload = $this->numeric($payload);

        $this->assertSame([], QuestionType::ValeurNumerique->payloadErrors($payload));
        $this->assertSame($expected, (new ValeurNumeriqueHandler)->evaluate($payload, $response)->verdict);
    }

    public function test_numeric_tolerance_is_ignored_without_tolerance_kind(): void
    {
        $this->assertNull($this->numeric(['value' => '0.1', 'tolerance_kind' => 'aucune', 'tolerance' => '3'])['tolerance']);
    }

    public function test_numeric_expected_answer_and_gap_are_written_the_french_way(): void
    {
        $handler = new ValeurNumeriqueHandler;
        $payload = $this->numeric(['value' => '12.5', 'tolerance_kind' => 'absolue', 'tolerance' => '0.5', 'unit' => 'km']);

        $this->assertSame('12,5 km (± 0,5)', $handler->expectedAnswer($payload));
        $this->assertSame('200 (± 10 %)', $handler->expectedAnswer($this->numeric(['value' => '200', 'tolerance_kind' => 'pourcentage', 'tolerance' => '10'])));
        $this->assertSame('Écart avec la valeur attendue : 1,5.', $handler->evaluate($payload, '14')->detail);
    }

    public function test_numeric_answer_that_is_not_a_number_is_refused_with_a_clear_message(): void
    {
        $this->expectException(InvalidResponse::class);
        $this->expectExceptionMessage('Saisissez un nombre, par exemple 12,5 ou 12.5.');

        (new ValeurNumeriqueHandler)->evaluate($this->numeric(['value' => '12', 'tolerance_kind' => 'aucune']), 'douze');
    }

    public function test_numeric_payload_errors_name_the_field(): void
    {
        $type = QuestionType::ValeurNumerique;

        $this->assertContains('La valeur attendue doit être un nombre.', $type->payloadErrors($this->numeric(['value' => 'abc', 'tolerance_kind' => 'aucune'])));
        $this->assertContains('Indiquez l\'écart accepté, ou choisissez « aucune » tolérance.', $type->payloadErrors($this->numeric(['value' => '1', 'tolerance_kind' => 'absolue'])));
        $this->assertContains('L\'écart accepté ne peut pas être négatif.', $type->payloadErrors($this->numeric(['value' => '1', 'tolerance_kind' => 'pourcentage', 'tolerance' => '-2'])));
        $this->assertContains('Le type de tolérance doit être « aucune », « absolue » ou « pourcentage ».', $type->payloadErrors($this->numeric(['value' => '1', 'tolerance_kind' => 'relative'])));
    }

    public function test_answer_normalizer(): void
    {
        $this->assertSame('ile-de-france', AnswerNormalizer::normalize('  Île-de-FRANCE '));
        $this->assertTrue(AnswerNormalizer::equals('Saint  Étienne', 'saint etienne'));
        $this->assertFalse(AnswerNormalizer::equals('Lyon', 'Lyons'));
        $this->assertSame(12.5, AnswerNormalizer::number('12,5'));
        $this->assertSame(0.5, AnswerNormalizer::number(',5'));
        $this->assertNull(AnswerNormalizer::number('12,5,3'));
        $this->assertNull(AnswerNormalizer::number(''));
    }

    public function test_question_without_difficulty_counts_as_medium(): void
    {
        $this->assertSame(Difficulty::Moyen, Difficulty::effective(null));
        $this->assertSame(Difficulty::Facile, Difficulty::effective(1));
        $this->assertSame(Difficulty::Difficile, Difficulty::effective(Difficulty::Difficile));
        $this->assertSame(['Facile', 'Moyen', 'Difficile'], array_map(fn (Difficulty $d): string => $d->getLabel(), Difficulty::cases()));
    }
}
