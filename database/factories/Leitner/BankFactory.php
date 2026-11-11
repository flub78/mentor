<?php

namespace Database\Factories\Leitner;

use App\Leitner\Models\Bank;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bank>
 */
class BankFactory extends Factory
{
    protected $model = Bank::class;

    public function definition(): array
    {
        return [
            'name' => 'Banque Leitner '.fake()->unique()->words(3, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
