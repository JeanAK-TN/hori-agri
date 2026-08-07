<?php

namespace Database\Factories;

use App\Models\Cooperative;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only — no real Hori cooperative data (SPEC rule 2).
 *
 * @extends Factory<Cooperative>
 */
class CooperativeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Coopérative '.fake()->unique()->company(),
            'region' => fake()->randomElement(['Kara', 'Centrale', 'Plateaux', 'Maritime', 'Savanes']),
            'prefecture' => fake()->randomElement(['Kozah', 'Tchaoudjo', 'Ogou', 'Zio', 'Tône']),
            'contact' => fake()->optional()->numerify('9#######'),
            'suspended_at' => null,
        ];
    }

    /**
     * Indicate that the cooperative is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'suspended_at' => now(),
        ]);
    }
}
