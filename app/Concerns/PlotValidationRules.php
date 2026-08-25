<?php

namespace App\Concerns;

use App\Enums\SoilType;
use App\Models\Farmer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait PlotValidationRules
{
    /**
     * Validation rules of a plot (SPEC section 6.2).
     *
     * @return array<string, array<int, ValidationRule|Closure|array<mixed>|string>>
     */
    protected function plotRules(): array
    {
        return [
            'farmer_id' => ['required', $this->existingFarmerRule()],
            'name' => ['required', 'string', 'max:80'],
            // decimal(6,3) : sans surface, aucun indicateur à l'hectare.
            'area_ha' => ['required', 'numeric', 'min:0.001', 'max:999.999'],
            'soil_type' => ['required', Rule::enum(SoilType::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'previous_crop' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * The producer must belong to the current exploitation. The query
     * goes through the scoped model rather than a raw exploitation_id
     * clause, which the isolation rules forbid (SPEC section 7.3).
     */
    private function existingFarmerRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! Farmer::whereKey($value)->exists()) {
                $fail('Le producteur choisi est introuvable dans votre exploitation.');
            }
        };
    }
}
