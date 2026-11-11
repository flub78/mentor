<?php

namespace Database\Factories\Qcm;

use App\Qcm\Models\Attempt;
use App\Qcm\Models\AttemptItem;
use App\Qcm\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Question posée en fin de passage, sans réponse, réponses dans leur ordre
 * enregistré.
 *
 * @extends Factory<AttemptItem>
 */
class AttemptItemFactory extends Factory
{
    protected $model = AttemptItem::class;

    public function definition(): array
    {
        return [
            'attempt_id' => Attempt::factory(),
            'position' => fn (array $attributes): int => AttemptItem::query()->where('attempt_id', $attributes['attempt_id'])->count(),
            'question_id' => Question::factory()->withAnswers(),
            'answer_order' => fn (array $attributes): array => Question::query()
                ->findOrFail($attributes['question_id'])
                ->answers
                ->pluck('key')
                ->all(),
            'chosen_key' => null,
            'bookmarked' => false,
            'answered_at' => null,
        ];
    }
}
