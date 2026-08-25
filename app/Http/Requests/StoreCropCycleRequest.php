<?php

namespace App\Http\Requests;

use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Plot;
use App\Models\Variety;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCropCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CropCycle::class);
    }

    /**
     * The plot and the campaign are looked up through their scoped
     * models: one of another exploitation simply does not exist here.
     * Crops and varieties form a registry common to everyone.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plot_id' => ['required', $this->belongsToExploitationRule(Plot::class, 'La parcelle choisie est introuvable dans votre exploitation.')],
            'campaign_id' => ['required', $this->belongsToExploitationRule(Campaign::class, 'La campagne choisie est introuvable dans votre exploitation.')],
            'crop_id' => ['required', Rule::exists(Crop::class, 'id')],
            'variety_id' => ['nullable', Rule::exists(Variety::class, 'id')],
            'sowing_date' => ['required', 'date'],
            'density' => ['nullable', 'integer', 'min:1', 'max:2000000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateVarietyBelongsToCrop($validator),
            fn (Validator $validator) => $this->validateSowingDateWithinCampaign($validator),
            fn (Validator $validator) => $this->validateCycleIsUnique($validator),
        ];
    }

    /**
     * @param  class-string<Plot|Campaign>  $model
     */
    private function belongsToExploitationRule(string $model, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($model, $message): void {
            if (! $model::whereKey($value)->exists()) {
                $fail($message);
            }
        };
    }

    /**
     * A variety only makes sense under its own crop.
     */
    private function validateVarietyBelongsToCrop(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['crop_id', 'variety_id'])) {
            return;
        }

        $varietyId = $this->input('variety_id');

        if (blank($varietyId)) {
            return;
        }

        $belongs = Variety::whereKey($varietyId)
            ->where('crop_id', $this->input('crop_id'))
            ->exists();

        if (! $belongs) {
            $validator->errors()->add(
                'variety_id',
                'La variété choisie n\'appartient pas à cette culture.',
            );
        }
    }

    /**
     * The campaign carries the low and high bounds of every date of the
     * cycle (SPEC section 6.2).
     */
    private function validateSowingDateWithinCampaign(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['campaign_id', 'sowing_date'])) {
            return;
        }

        $campaign = Campaign::find((int) $this->input('campaign_id'));

        if ($campaign === null) {
            return;
        }

        $sowingDate = Carbon::parse((string) $this->input('sowing_date'))->startOfDay();

        if (! $campaign->covers($sowingDate)) {
            $validator->errors()->add('sowing_date', sprintf(
                'La date de semis doit tomber entre le %s et le %s, bornes de la campagne « %s ».',
                $campaign->start_date->format('d/m/Y'),
                $campaign->end_date->format('d/m/Y'),
                $campaign->label,
            ));
        }
    }

    /**
     * The database also carries the constraint; this rule turns it into
     * a message the technician can act on (SPEC section 6.2).
     */
    private function validateCycleIsUnique(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['plot_id', 'crop_id', 'campaign_id'])) {
            return;
        }

        $exists = CropCycle::query()
            ->where('plot_id', $this->input('plot_id'))
            ->where('crop_id', $this->input('crop_id'))
            ->where('campaign_id', $this->input('campaign_id'))
            ->exists();

        if ($exists) {
            $validator->errors()->add(
                'crop_id',
                'Cette parcelle porte déjà un cycle pour cette culture et cette campagne.',
            );
        }
    }
}
