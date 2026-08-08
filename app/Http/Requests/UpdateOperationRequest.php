<?php

namespace App\Http\Requests;

use App\Concerns\OperationValidationRules;
use App\Concerns\ValidatesCycleEntry;
use App\Models\Operation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOperationRequest extends FormRequest
{
    use OperationValidationRules, ValidatesCycleEntry;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetOperation());
    }

    private function targetOperation(): Operation
    {
        $operation = $this->route('operation');

        if (! $operation instanceof Operation) {
            abort(404);
        }

        return $operation;
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
