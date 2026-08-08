<?php

namespace Database\Factories;

use App\Enums\Quality;
use App\Models\Cooperative;
use App\Models\CropCycle;
use App\Models\Harvest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only — no real Hori cooperative data (SPEC rule 2).
 *
 * @extends Factory<Harvest>
 */
class HarvestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cooperative_id' => Cooperative::factory(),
            'crop_cycle_id' => CropCycle::factory(),
            'date' => '2026-08-01',
            'quantity_kg' => 1000,
            'unit_price' => 200,
            'quality' => Quality::Good,
            'post_harvest_losses_kg' => null,
        ];
    }

    public function forCycle(CropCycle $cycle): static
    {
        return $this->state(fn (array $attributes) => [
            'cooperative_id' => $cycle->cooperative_id,
            'crop_cycle_id' => $cycle->id,
        ]);
    }

    public function of(float $quantityKg, ?float $unitPrice = null): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_kg' => $quantityKg,
            'unit_price' => $unitPrice,
        ]);
    }

    public function on(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }
}
