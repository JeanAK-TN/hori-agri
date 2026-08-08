<?php

namespace App\Concerns;

use App\Models\Crop;
use App\Models\Variety;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CropValidationRules
{
    /**
     * Validation rules of the common crop registry (SPEC section 6.2).
     *
     * The bounds below are data sanity checks, not agronomic thresholds:
     * the reference values themselves come from the seed files and are
     * edited here by the super_admin, never written in code (rule 1).
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function cropRules(?Crop $crop = null): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:60',
                $crop === null
                    ? Rule::unique(Crop::class)
                    : Rule::unique(Crop::class)->ignore($crop),
            ],
            'family' => ['nullable', 'string', 'max:60'],
            'cycle_days' => ['required', 'integer', 'min:1', 'max:1000'],
            'reference_density' => ['required', 'integer', 'min:1', 'max:2000000'],
            'reference_yield_kg_ha' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'npk_needs' => ['nullable', 'array'],
            'npk_needs.n' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'npk_needs.p' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'npk_needs.k' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ];
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function varietyRules(?Variety $variety = null): array
    {
        $cropId = $variety !== null ? $variety->crop_id : $this->input('crop_id');

        return [
            'crop_id' => ['required', Rule::exists(Crop::class, 'id')],
            'name' => [
                'required',
                'string',
                'max:80',
                $variety === null
                    ? Rule::unique(Variety::class)->where('crop_id', $cropId)
                    : Rule::unique(Variety::class)->where('crop_id', $cropId)->ignore($variety),
            ],
            'cycle_days' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'potential_yield_kg_ha' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}
