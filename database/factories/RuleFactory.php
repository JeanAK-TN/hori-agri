<?php

namespace Database\Factories;

use App\Enums\RuleFamily;
use App\Enums\Severity;
use App\Enums\ThresholdStatus;
use App\Models\Crop;
use App\Models\Exploitation;
use App\Models\Rule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only. The conditions built here are deliberately simple
 * and have no agronomic meaning: the real knowledge base comes from
 * database/seeders/data/rules.json (SPEC rule 1).
 *
 * @extends Factory<Rule>
 */
class RuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exploitation_id' => null,
            'code' => 'TEST-'.fake()->unique()->numerify('###'),
            'crop_id' => null,
            'family' => RuleFamily::A,
            'condition' => ['field' => 'sowing_month', 'op' => '=', 'value' => 4],
            'severity' => Severity::Medium,
            'message' => 'Message de règle de test',
            'action' => 'Action de règle de test',
            'estimated_gain' => '+100 kg/ha',
            'source' => 'Fixture de test',
            'threshold_status' => ThresholdStatus::Sourced,
            'enabled' => true,
        ];
    }

    public function code(string $code): static
    {
        return $this->state(fn (array $attributes) => ['code' => $code]);
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    public function firingWhen(array $condition): static
    {
        return $this->state(fn (array $attributes) => ['condition' => $condition]);
    }

    /** A condition that is always true on any context. */
    public function alwaysFiring(): static
    {
        return $this->firingWhen(['field' => 'sowing_month', 'op' => '>=', 'value' => 1]);
    }

    /** A condition that is never true. */
    public function neverFiring(): static
    {
        return $this->firingWhen(['field' => 'sowing_month', 'op' => '>', 'value' => 12]);
    }

    public function family(RuleFamily $family): static
    {
        return $this->state(fn (array $attributes) => ['family' => $family]);
    }

    public function severity(Severity $severity): static
    {
        return $this->state(fn (array $attributes) => ['severity' => $severity]);
    }

    public function gain(?string $estimatedGain): static
    {
        return $this->state(fn (array $attributes) => ['estimated_gain' => $estimatedGain]);
    }

    public function forCrop(Crop $crop): static
    {
        return $this->state(fn (array $attributes) => ['crop_id' => $crop->id]);
    }

    /** An override carried by one exploitation (SPEC section 9.6). */
    public function ownedBy(Exploitation $exploitation): static
    {
        return $this->state(fn (array $attributes) => ['exploitation_id' => $exploitation->id]);
    }

    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => ['enabled' => false]);
    }

    public function convention(): static
    {
        return $this->state(fn (array $attributes) => [
            'threshold_status' => ThresholdStatus::Convention,
            'enabled' => false,
        ]);
    }
}
