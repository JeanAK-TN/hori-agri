<?php

namespace App\Http\Requests;

use App\Concerns\CampaignValidationRules;
use App\Models\Campaign;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCampaignRequest extends FormRequest
{
    use CampaignValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetCampaign());
    }

    private function targetCampaign(): Campaign
    {
        $campaign = $this->route('campaign');

        if (! $campaign instanceof Campaign) {
            abort(404);
        }

        return $campaign;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->campaignRules($this->targetCampaign());
    }
}
