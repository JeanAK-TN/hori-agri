<?php

namespace App\Http\Requests;

use App\Concerns\OperationValidationRules;
use App\Concerns\ValidatesCycleEntry;
use App\Models\Operation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOperationRequest extends FormRequest
{
    use OperationValidationRules, ValidatesCycleEntry;

    public function authorize(): bool
    {
        return $this->user()->can('create', [Operation::class, $this->targetCycle()]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->operationRules($this->all());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->operationMessages();
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateDateWithinCampaign($validator),
        ];
    }
}
