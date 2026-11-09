<?php

namespace Database\Factories\Qcm;

use App\Qcm\Models\Result;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Résultat autonome, sans passage rattaché.
 *
 * @extends Factory<Result>
 */
class ResultFactory extends Factory
{
    protected $model = Result::class;

    public function definition(): array
    {
        $lastName = fake()->lastName();
        $firstName = fake()->firstName();
        $rate = fake()->randomFloat(2, 0, 100);

        return [
            'attempt_id' => null,
            'certificate_number' => now()->format('Ymd').'-'.strtoupper(fake()->unique()->bothify('??####')),
            'identity' => [
                ['key' => 'nom', 'label' => 'Nom', 'value' => $lastName],
                ['key' => 'prenom', 'label' => 'Prénom', 'value' => $firstName],
            ],
            'last_name' => $lastName,
            'first_name' => $firstName,
            'email' => null,
            'taken_at' => now(),
            'exam_title' => 'Examen '.fake()->numberBetween(1, 9999),
            'questionnaire_title' => 'Questionnaire '.fake()->numberBetween(1, 9999),
            'questionnaire_version' => fake()->optional()->numerify('v#'),
            'module_rates' => [['label' => 'Météorologie', 'correct' => 3, 'total' => 4, 'rate' => 75.0]],
            'global_rate' => $rate,
            'pass_rate' => 75,
            'passed' => $rate >= 75,
        ];
    }
}
