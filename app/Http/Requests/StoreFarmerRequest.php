<?php

namespace App\Http\Requests;

use App\Concerns\FarmerValidationRules;
use App\Concerns\NormalizesPhoneNumbers;
use App\Models\Farmer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFarmerRequest extends FormRequest
{
    use FarmerValidationRules, NormalizesPhoneNumbers;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Farmer::class);
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        $this->merge([
            'phone' => blank($phone) ? null : self::normalizePhone((string) $phone),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->farmerRules();
    }
}
