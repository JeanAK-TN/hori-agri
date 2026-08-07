<?php

namespace App\Http\Requests;

use App\Models\Cooperative;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCooperativeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetCooperative());
    }

    /**
     * The cooperative being updated, resolved from the route binding.
     */
    private function targetCooperative(): Cooperative
    {
        $cooperative = $this->route('cooperative');

        if (! $cooperative instanceof Cooperative) {
            abort(404);
        }

        return $cooperative;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique(Cooperative::class)->ignore($this->targetCooperative()),
            ],
            'region' => ['required', 'string', 'max:60'],
            'prefecture' => ['required', 'string', 'max:60'],
            'contact' => ['nullable', 'string', 'max:120'],
        ];
    }
}
