<?php

namespace App\Concerns;

use App\Models\CropCycle;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Shared checks of the two screens that fill in a cycle: operations and
 * harvests. Both record a date, which the campaign bounds constrain.
 */
trait ValidatesCycleEntry
{
    /**
     * The cycle being filled in, resolved from the route binding. One of
     * another exploitation never reaches this point: the global scope
     * already hides it (SPEC section 7.2).
     */
    protected function targetCycle(): CropCycle
    {
        $cycle = $this->route('cycle');

        if (! $cycle instanceof CropCycle) {
            abort(404);
        }

        return $cycle;
    }

    /**
     * A date recorded on a cycle must fall inside the bounds of its
     * campaign (SPEC section 6.2). The message names both bounds so the
     * technician can correct without leaving the screen.
     */
    protected function validateDateWithinCampaign(Validator $validator, string $field = 'date'): void
    {
        if ($validator->errors()->has($field)) {
            return;
        }

        $value = $this->input($field);

        if (blank($value)) {
            return;
        }

        $campaign = $this->targetCycle()->campaign;
        $date = Carbon::parse((string) $value)->startOfDay();

        if (! $campaign->covers($date)) {
            $validator->errors()->add($field, sprintf(
                'La date doit tomber entre le %s et le %s, bornes de la campagne « %s ».',
                $campaign->start_date->format('d/m/Y'),
                $campaign->end_date->format('d/m/Y'),
                $campaign->label,
            ));
        }
    }
}
