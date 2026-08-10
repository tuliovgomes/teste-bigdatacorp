<?php

namespace Database\Factories;

use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'player_id' => $this->faker->unique()->word(),
            'name' => $this->faker->name(),
            'age' => $this->faker->numberBetween(18, 40),
            'goals' => $this->faker->numberBetween(0, 1000),
            'debut_date' => $this->faker->date(),
            'position' => $this->faker->word(),
            'shirt_number' => $this->faker->numberBetween(1, 99),
            'nationality' => $this->faker->country(),
            'market_value' => $this->faker->numberBetween(100000, 1000000),
        ];
    }
}
