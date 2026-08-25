<?php

namespace Database\Factories;

use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\QuantityUnit;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Operation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test fixture only — no real Hori exploitation data (SPEC rule 2).
 *
 * @extends Factory<Operation>
 */
class OperationFactory extends Factory
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
            'type' => OperationType::Tillage,
            'input_type' => null,
            'date' => '2026-04-01',
            'product' => null,
            'quantity' => null,
            'unit' => null,
            'cost' => null,
            'notes' => null,
        ];
    }

    /**
     * Attach the operation to a cycle, keeping both inside one
     * exploitation.
     */
    public function forCycle(CropCycle $cycle): static
    {
        return $this->state(fn (array $attributes) => [
            'exploitation_id' => $cycle->exploitation_id,
            'crop_cycle_id' => $cycle->id,
            'date' => $cycle->sowing_date->toDateString(),
        ]);
    }

    /**
     * An operation that brings a product, with its quantity as the
     * technician counts it in the field.
     */
    public function bringing(
        OperationType $type,
        InputType $inputType,
        float $quantity,
        QuantityUnit $unit,
        ?float $cost = null,
    ): static {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'input_type' => $inputType,
            'quantity' => $quantity,
            'unit' => $unit,
            'cost' => $cost,
        ]);
    }

    public function on(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }

    public function costing(float $cost): static
    {
        return $this->state(fn (array $attributes) => [
            'cost' => $cost,
        ]);
    }
}
