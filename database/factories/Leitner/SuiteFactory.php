<?php

namespace Database\Factories\Leitner;

use App\Leitner\Models\Bank;
use App\Leitner\Models\Suite;
use App\Leitner\Suites\PresentationOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Suite sans source par défaut ; `withBanks()` lui en ajoute.
 *
 * @extends Factory<Suite>
 */
class SuiteFactory extends Factory
{
    protected $model = Suite::class;

    public function definition(): array
    {
        return [
            'name' => 'Suite '.fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'box_count' => 5,
            'min_box_one' => 10,
            'presentation_order' => PresentationOrder::Melange,
            'retry_errors' => true,
        ];
    }

    /**
     * Ajoute les banques données comme sources.
     */
    public function withBanks(Bank ...$banks): static
    {
        return $this->afterCreating(function (Suite $suite) use ($banks): void {
            foreach ($banks as $bank) {
                $suite->sources()->create(['bank_id' => $bank->id]);
            }
        });
    }
}
