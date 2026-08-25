<?php

namespace App\Http\Requests;

use App\Concerns\FarmerValidationRules;
use App\Concerns\NormalizesPhoneNumbers;
use App\Models\Farmer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFarmerRequest extends FormRequest
{
    use FarmerValidationRules, NormalizesPhoneNumbers;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetFarmer());
    }

    /**
     * The producer being updated, resolved from the route binding. A
     * producer of another exploitation never reaches this point: the
     * global scope already hides them from the binding.
     */
    private function targetFarmer(): Farmer
    {
        $farmer = $this->route('farmer');

        if (! $farmer instanceof Farmer) {
            abort(404);
        }

        return $farmer;
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
        return $this->farmerRules($this->targetFarmer());
    }
}
