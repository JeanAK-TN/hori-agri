<?php

namespace App\Http\Requests;

use App\Concerns\ValidatesCycleEntry;
use App\Enums\Quality;
use App\Models\Harvest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreHarvestRequest extends FormRequest
{
    use ValidatesCycleEntry;

    public function authorize(): bool
    {
        return $this->user()->can('create', [Harvest::class, $this->targetCycle()]);
    }

    /**
     * Validation rules of a harvest (SPEC section 6.2).
     *
     * The date is checked against the campaign bounds like an operation:
     * the specification constrains operations explicitly, and nothing
     * makes a harvest recorded outside its campaign meaningful.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'quantity_kg' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            // Prix de vente au kilogramme, en francs CFA. Sans lui, la
            // valeur de la récolte est incalculable (SPEC section 9.2).
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
            fn (Validator $validator) => $this->validateLossesDoNotExceedHarvest($validator),
        ];
    }

    /**
     * Post-harvest losses are taken out of what was harvested.
     */
    private function validateLossesDoNotExceedHarvest(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['quantity_kg', 'post_harvest_losses_kg'])) {
            return;
        }

        $losses = $this->input('post_harvest_losses_kg');

        if (blank($losses)) {
            return;
        }

        if ((float) $losses > (float) $this->input('quantity_kg')) {
            $validator->errors()->add(
                'post_harvest_losses_kg',
                'Les pertes après récolte ne peuvent pas dépasser la quantité récoltée.',
            );
        }
    }
}
