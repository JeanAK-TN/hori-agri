<?php

namespace Database\Factories;

use App\Enums\RecoStatus;
use App\Models\CropCycle;
use App\Models\Exploitation;
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
            'exploitation_id' => Exploitation::factory(),
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

    /**
     * Attach the recommendation to a cycle, and to the exploitation that
     * cycle belongs to.
     */
    public function forCycle(CropCycle $cycle): static
    {
        return $this->state(fn (array $attributes) => [
            'exploitation_id' => $cycle->exploitation_id,
            'crop_cycle_id' => $cycle->id,
        ]);
    }

    public function fromRule(Rule $rule): static
    {
        return $this->state(fn (array $attributes) => ['rule_id' => $rule->id]);
    }

    public function saying(string $message): static
    {
        return $this->state(fn (array $attributes) => ['generated_message' => $message]);
    }
}
