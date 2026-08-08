<?php

namespace Database\Factories;

use App\Models\Cooperative;
use App\Models\CropCycle;
use App\Models\Indicator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only. In the application these values are never written
 * by hand: IndicatorService is the sole writer (SPEC section 6.2).
 *
 * @extends Factory<Indicator>
 */
class IndicatorFactory extends Factory
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
            'yield_kg_ha' => 1000,
            'production_cost_ha' => 80000,
            'gross_margin_ha' => 120000,
            'input_cost_share' => 0.4,
            'zone_median_yield_kg_ha' => null,
            'zone_median_cost_ha' => null,
            'zone_plot_count' => 0,
            'yield_vs_zone_median' => null,
            'cost_vs_zone_median' => null,
            'computed_at' => now(),
        ];
    }
}
