<?php

namespace Database\Factories\Banques;

use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Questions\Difficulty;
use App\Banques\Questions\Handlers\ValeurNumeriqueHandler;
use App\Banques\Questions\QuestionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Question QCM par défaut ; un état par type de question.
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
            'statement' => 'Quelle est la capitale de '.fake()->country().' ?',
            'payload' => [
                'correct' => fake()->city(),
                'wrong' => [
                    ['text' => fake()->city(), 'explanation' => null],
                    ['text' => fake()->city(), 'explanation' => fake()->sentence()],
                    ['text' => fake()->city(), 'explanation' => null],
                ],
                'shuffle' => true,
            ],
            'theme' => fake()->randomElement(['Europe', 'Asie', 'Afrique']),
            'explanation' => fake()->optional()->sentence(),
        ];
    }

    public function qcm(): static
    {
        return $this->state(fn (): array => ['type' => QuestionType::Qcm]);
    }

    public function vraiFaux(bool $value = true): static
    {
        return $this->state(fn (): array => [
            'type' => QuestionType::VraiFaux,
            'statement' => 'Le Danube traverse Vienne.',
            'payload' => ['value' => $value],
        ]);
    }

    public function autoEvaluation(): static
    {
        return $this->state(fn (): array => [
            'type' => QuestionType::AutoEvaluation,
            'statement' => 'Vous rappelez-vous la démonstration du théorème de Pythagore ?',
            'payload' => ['answer' => 'Par les aires de quatre triangles rectangles dans un carré.'],
        ]);
    }

    public function valeurNumerique(
        float $value = 1.225,
        string $toleranceKind = ValeurNumeriqueHandler::TOLERANCE_ABSOLUTE,
        ?float $tolerance = 0.005,
        ?string $unit = 'kg/m³',
    ): static {
        return $this->state(fn (): array => [
            'type' => QuestionType::ValeurNumerique,
            'statement' => 'Masse volumique de l\'air au niveau de la mer (atmosphère type) ?',
            'payload' => [
                'value' => $value,
                'tolerance_kind' => $toleranceKind,
                'tolerance' => $tolerance,
                'unit' => $unit,
            ],
        ]);
    }

    public function difficulty(Difficulty|int|null $difficulty): static
    {
        return $this->state(fn (): array => [
            'difficulty' => is_int($difficulty) ? Difficulty::from($difficulty) : $difficulty,
        ]);
    }
}
