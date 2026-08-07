<?php

namespace App\Http\Requests;

use App\Models\Cooperative;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCooperativeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Cooperative::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique(Cooperative::class)],
            'region' => ['required', 'string', 'max:60'],
            'prefecture' => ['required', 'string', 'max:60'],
            'contact' => ['nullable', 'string', 'max:120'],
        ];
    }
}
