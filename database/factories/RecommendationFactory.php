<?php

namespace Database\Factories;

use App\Enums\RecoStatus;
use App\Models\Cooperative;
use App\Models\CropCycle;
use App\Models\Recommendation;
use App\Models\Rule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only.
 *
 * @extends Factory<Recommendation>
 */
class RecommendationFactory extends Factory
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
            'rule_id' => Rule::factory(),
            'priority' => 1,
            'generated_message' => 'Constat de test',
            'status' => RecoStatus::New,
            'generated_at' => now(),
        ];
    }

    public function status(RecoStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
