<?php

namespace Database\Factories;

use App\Models\Crop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only. The numbers below are arbitrary and exist solely to
 * exercise the code: the reference values used by the application come
 * from database/seeders/data/crops.json, never from PHP (SPEC rule 1).
 *
 * @extends Factory<Crop>
 */
class CropFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Culture '.fake()->unique()->word(),
            'family' => fake()->word(),
            'cycle_days' => fake()->numberBetween(90, 300),
            'reference_density' => fake()->numberBetween(10_000, 60_000),
            'reference_yield_kg_ha' => fake()->numberBetween(800, 12_000),
            'npk_needs' => ['n' => 60, 'p' => 30, 'k' => 30],
        ];
    }

    public function named(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }
}
