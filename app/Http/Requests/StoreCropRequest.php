<?php

namespace App\Http\Requests;

use App\Concerns\CropValidationRules;
use App\Models\Crop;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCropRequest extends FormRequest
{
    use CropValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Crop::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->cropRules();
    }
}
