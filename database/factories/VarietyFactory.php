<?php

namespace Database\Factories;

use App\Models\Crop;
use App\Models\Variety;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only (see CropFactory).
 *
 * @extends Factory<Variety>
 */
class VarietyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crop_id' => Crop::factory(),
            'name' => 'Variété '.fake()->unique()->bothify('??-###'),
            'cycle_days' => fake()->numberBetween(80, 200),
            'potential_yield_kg_ha' => fake()->numberBetween(1_000, 14_000),
        ];
    }
}
