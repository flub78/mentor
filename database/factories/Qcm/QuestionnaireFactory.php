<?php

namespace Database\Factories\Qcm;

use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Questionnaire>
 */
class QuestionnaireFactory extends Factory
{
    protected $model = Questionnaire::class;

    public function definition(): array
    {
        return [
            'title' => 'Examen blanc '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->optional()->sentence(),
            'version' => fake()->optional()->numerify('v#'),
        ];
    }

    /**
     * Ajoute `$count` questions propres complètes (une bonne réponse, trois mauvaises).
     */
    public function withOwnQuestions(int $count = 3): static
    {
        return $this->afterCreating(function (Questionnaire $questionnaire) use ($count): void {
            $questions = Question::factory()->count($count)->withAnswers()->create([
                'bank_id' => null,
                'questionnaire_id' => $questionnaire->id,
            ]);
            $questionnaire->questions()->attach($questions->modelKeys());
        });
    }

    /**
     * Ajoute `$count` questions d'une banque (créée si absente).
     */
    public function withBankQuestions(int $count = 3, ?Bank $bank = null): static
    {
        return $this->afterCreating(function (Questionnaire $questionnaire) use ($count, $bank): void {
            $questions = Question::factory()->count($count)->withAnswers()->for($bank ?? Bank::factory())->create();
            $questionnaire->questions()->attach($questions->modelKeys());
        });
    }
}
