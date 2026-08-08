<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Cooperative;
use App\Models\Farmer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only — no real Hori cooperative data (SPEC rule 2).
 *
 * @extends Factory<Farmer>
 */
class FarmerFactory extends Factory
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
            'user_id' => null,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(Gender::cases()),
            'birth_year' => fake()->numberBetween(1955, 2005),
            'experience_years' => fake()->numberBetween(1, 40),
            'village' => fake()->city(),
            'phone' => fake()->numerify('9#######'),
        ];
    }

    /**
     * Place the producer in a given village, which is the comparison zone
     * used by the analytics layer (SPEC section 9.3).
     */
    public function inVillage(string $village): static
    {
        return $this->state(fn (array $attributes) => [
            'village' => $village,
        ]);
    }
}
