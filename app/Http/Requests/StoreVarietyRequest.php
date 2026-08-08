<?php

namespace App\Http\Requests;

use App\Concerns\CropValidationRules;
use App\Models\Variety;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreVarietyRequest extends FormRequest
{
    use CropValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Variety::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->varietyRules();
    }
}
