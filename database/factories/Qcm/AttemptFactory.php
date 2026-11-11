<?php

namespace Database\Factories\Qcm;

use App\Qcm\Models\Attempt;
use App\Qcm\Models\AttemptStatus;
use App\Qcm\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Passage en cours, sans question ; les questions se posent avec
 * `AttemptItem::factory()` ou par `AttemptStarter`.
 *
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    protected $model = Attempt::class;

    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'status' => AttemptStatus::InProgress,
            'identity' => ['nom' => fake()->lastName(), 'prenom' => fake()->firstName()],
            'started_at' => now(),
            'deadline_at' => null,
            'finished_at' => null,
        ];
    }

    /**
     * Passage terminé par le candidat.
     */
    public function finished(): static
    {
        return $this->state(fn (): array => [
            'status' => AttemptStatus::Finished,
            'started_at' => now()->subMinutes(30),
            'finished_at' => now(),
        ]);
    }

    /**
     * Passage terminé à l'échéance.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => AttemptStatus::Expired,
            'started_at' => now()->subMinutes(30),
            'deadline_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
