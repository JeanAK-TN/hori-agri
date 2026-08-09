<?php

namespace Database\Factories;

use App\Models\Farmer;
use App\Models\Feedback;
use App\Models\Recommendation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Feedback>
 */
class FeedbackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'applied' => true,
            'comment' => 'Retour de test',
            'observed_result' => null,
        ];
    }

    /**
     * The producer answering is the one the recommendation concerns.
     */
    public function on(Recommendation $recommendation): static
    {
        $farmer = $recommendation->farmer();

        return $this->state(fn (array $attributes): array => [
            'cooperative_id' => $recommendation->cooperative_id,
            'recommendation_id' => $recommendation->id,
            'farmer_id' => $farmer?->id,
        ]);
    }

    public function by(Farmer $farmer): static
    {
        return $this->state(fn (array $attributes): array => ['farmer_id' => $farmer->id]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes): array => ['applied' => false]);
    }
}
