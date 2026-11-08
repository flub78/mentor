<?php

namespace Database\Factories\Qcm;

use App\Qcm\Models\Answer;
use App\Qcm\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    protected $model = Answer::class;

    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'text' => fake()->sentence(4),
            'is_correct' => false,
            'explanation' => fake()->optional()->sentence(),
            'position' => 0,
        ];
    }

    /**
     * Bonne réponse : son explication est portée par la question (explication
     * générale), pas par la réponse.
     */
    public function correct(): static
    {
        return $this->state(['is_correct' => true, 'explanation' => null]);
    }
}
