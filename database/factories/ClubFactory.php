<?php

namespace Database\Factories;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => $this->faker->unique()->word(),
            'name' => $this->faker->company(),
            'championship' => $this->faker->word(),
            'founding_date' => $this->faker->date(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'country' => $this->faker->country(),
            'stadium' => $this->faker->word(),
            'president' => $this->faker->name(),
            'nickname' => $this->faker->word(),
            'colors' => json_encode([$this->faker->colorName(), $this->faker->colorName()]),
            'titles' => $this->faker->randomNumber(2),
        ];
    }
}
