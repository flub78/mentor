<?php

namespace Database\Factories\Qcm;

use App\Qcm\Models\Exam;
use App\Qcm\Models\Questionnaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    public function definition(): array
    {
        return [
            'title' => 'Examen '.fake()->unique()->numberBetween(1, 9999),
            'instructions' => fake()->optional()->sentence(),
            'questionnaire_id' => Questionnaire::factory(),
            'duration_minutes' => fake()->optional()->numberBetween(10, 90),
            'pass_rate' => 75,
            'shuffle_questions' => true,
            'shows_correction' => true,
            'max_attempts' => null,
            'identification_fields' => Exam::defaultIdentificationFields(),
            'identifier_label' => null,
        ];
    }
}
