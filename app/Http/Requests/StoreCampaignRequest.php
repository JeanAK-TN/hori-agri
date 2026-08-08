<?php

namespace App\Http\Requests;

use App\Concerns\CampaignValidationRules;
use App\Models\Campaign;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignRequest extends FormRequest
{
    use CampaignValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Campaign::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->campaignRules();
    }
}
