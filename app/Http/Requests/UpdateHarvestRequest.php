<?php

namespace App\Http\Requests;

use App\Concerns\ValidatesCycleEntry;
use App\Enums\Quality;
use App\Models\Harvest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateHarvestRequest extends FormRequest
{
    use ValidatesCycleEntry;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetHarvest());
    }

    private function targetHarvest(): Harvest
    {
        $harvest = $this->route('harvest');

        if (! $harvest instanceof Harvest) {
            abort(404);
        }

        return $harvest;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'quantity_kg' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'quality' => ['nullable', Rule::enum(Quality::class)],
            'post_harvest_losses_kg' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateDateWithinCampaign($validator),
        ];
    }
}
