<?php

namespace App\Concerns;

use App\Enums\SeasonType;
use App\Models\Campaign;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CampaignValidationRules
{
    /**
     * Validation rules of a campaign (SPEC section 6.2).
     *
     * @return array<string, array<int, ValidationRule|Closure|array<mixed>|string>>
     */
    protected function campaignRules(?Campaign $campaign = null): array
    {
        return [
            'label' => ['required', 'string', 'max:40', $this->uniqueLabelRule($campaign)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'season_type' => ['required', Rule::enum(SeasonType::class)],
        ];
    }

    /**
     * Two campaigns sharing a label inside one exploitation would make
     * every drop-down ambiguous. Uniqueness is checked through the
     * scoped model, so it naturally applies per exploitation.
     */
    private function uniqueLabelRule(?Campaign $campaign): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($campaign): void {
            $exists = Campaign::query()
                ->where('label', $value)
                ->when($campaign !== null, fn ($query) => $query->whereKeyNot($campaign->id))
                ->exists();

            if ($exists) {
                $fail('Une campagne portant ce libellé existe déjà dans votre exploitation.');
            }
        };
    }
}
