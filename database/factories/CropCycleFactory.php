<?php

namespace Database\Factories;

use App\Enums\CycleStatus;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Plot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only — no real Hori exploitation data (SPEC rule 2).
 *
 * @extends Factory<CropCycle>
 */
class CropCycleFactory extends Factory
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
            'plot_id' => Plot::factory(),
            'crop_id' => Crop::factory(),
            'variety_id' => null,
            'campaign_id' => Campaign::factory(),
            'sowing_date' => '2026-04-15',
            'density' => null,
            'status' => CycleStatus::Planned,
        ];
    }

    /**
     * Open the cycle on an existing plot and campaign, keeping the whole
     * triple inside one exploitation.
     */
    public function on(Plot $plot, Campaign $campaign, Crop $crop): static
    {
        return $this->state(fn (array $attributes) => [
            'exploitation_id' => $plot->exploitation_id,
            'plot_id' => $plot->id,
            'campaign_id' => $campaign->id,
            'crop_id' => $crop->id,
            'sowing_date' => $campaign->start_date->toDateString(),
        ]);
    }

    public function status(CycleStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }
}
