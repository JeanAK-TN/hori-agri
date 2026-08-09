<?php

namespace App\Http\Requests;

use App\Models\Recommendation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('respond', $this->recommendation());
    }

    public function recommendation(): Recommendation
    {
        $recommendation = $this->route('recommendation');

        if (! $recommendation instanceof Recommendation) {
            abort(404);
        }

        return $recommendation;
    }

    /**
     * `farmer_id` ne figure pas ici : il se déduit du compte connecté,
     * jamais du formulaire (SPEC section 11.2).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'applied' => ['required', 'boolean'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'observed_result' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
