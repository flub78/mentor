<?php

namespace Database\Factories\Qcm;

use App\Qcm\Models\Answer;
use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    protected $model = Question::class;

    public function definition(): array
    {
        return [
            'bank_id' => Bank::factory(),
            'statement' => rtrim(fake()->sentence(), '.').' ?',
            'module' => fake()->randomElement(['Météorologie', 'Réglementation', 'Aérodynamique']),
            'explanation' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Question complète : une bonne réponse (position 0) et `$wrong` mauvaises.
     */
    public function withAnswers(int $wrong = 3): static
    {
        return $this->afterCreating(function (Question $question) use ($wrong): void {
            Answer::factory()->for($question)->correct()->create(['position' => 0]);

            for ($i = 1; $i <= $wrong; $i++) {
                Answer::factory()->for($question)->create(['position' => $i]);
            }
        });
    }
}
