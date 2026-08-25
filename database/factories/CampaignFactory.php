<?php

namespace Database\Factories;

use App\Enums\SeasonType;
use App\Models\Campaign;
use App\Models\Exploitation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only — no real Hori exploitation data (SPEC rule 2).
 *
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2020, 2030);

        return [
            'exploitation_id' => Exploitation::factory(),
            'label' => $year.' grande saison '.fake()->unique()->numerify('##'),
            'start_date' => $year.'-03-01',
            'end_date' => $year.'-11-30',
            'season_type' => SeasonType::GrandeSaison,
        ];
    }

    /**
     * A campaign with explicit bounds, to exercise the date checks.
     */
    public function between(string $start, string $end): static
    {
        return $this->state(fn (array $attributes) => [
            'start_date' => $start,
            'end_date' => $end,
        ]);
    }
}
