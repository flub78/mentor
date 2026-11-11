<?php

namespace Database\Factories\Qcm;

use App\Banques\Questions\QuestionType;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Question QCM du module Banques : une bonne réponse et trois mauvaises par
 * défaut ; `withAnswers($n)` fixe le nombre de mauvaises réponses.
 *
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'bank_id' => Bank::factory(),
            'type' => QuestionType::Qcm,
            'statement' => rtrim(fake()->sentence(), '.').' ?',
            'payload' => self::payload(3),
            'module' => fake()->randomElement(['Météorologie', 'Réglementation', 'Aérodynamique']),
            'explanation' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Question complète : une bonne réponse et `$wrong` mauvaises, la première
     * avec une explication.
     */
    public function withAnswers(int $wrong = 3): static
    {
        return $this->state(fn (): array => ['payload' => self::payload($wrong)]);
    }

    /**
     * Affirmation vraie ou fausse, posée comme un QCM à deux réponses.
     */
    public function trueFalse(bool $value = true): static
    {
        return $this->state(fn (): array => [
            'type' => QuestionType::VraiFaux,
            'statement' => 'Le Danube traverse Vienne.',
            'payload' => ['value' => $value],
        ]);
    }

    /**
     * @return array{correct: string, wrong: list<array{text: string, explanation: string|null}>, shuffle: bool}
     */
    private static function payload(int $wrong): array
    {
        $answers = [];

        for ($i = 1; $i <= $wrong; $i++) {
            $answers[] = ['text' => 'Mauvaise réponse '.$i.' '.fake()->word(), 'explanation' => $i === 1 ? fake()->sentence() : null];
        }

        return ['correct' => 'Bonne réponse '.fake()->word(), 'wrong' => $answers, 'shuffle' => true];
    }
}
