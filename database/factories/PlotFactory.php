<?php

namespace Database\Factories;

use App\Enums\SoilType;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Plot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only: no real INSECTGO data (SPEC rule 2).
 *
 * @extends Factory<Plot>
 */
class PlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exploitation_id' => Exploitation::factory(),
            'farmer_id' => Farmer::factory(),
            'name' => 'Parcelle '.fake()->unique()->numerify('##'),
            'area_ha' => fake()->randomFloat(3, 0.25, 5),
            'soil_type' => fake()->randomElement(SoilType::cases()),
            'latitude' => null,
            'longitude' => null,
            'previous_crop' => null,
        ];
    }

    /**
     * Attach the plot to a producer, keeping both inside the same
     * exploitation.
     */
    public function ofFarmer(Farmer $farmer): static
    {
        return $this->state(fn (array $attributes) => [
            'exploitation_id' => $farmer->exploitation_id,
            'farmer_id' => $farmer->id,
        ]);
    }

    public function withArea(float $hectares): static
    {
        return $this->state(fn (array $attributes) => [
            'area_ha' => $hectares,
        ]);
    }
}
