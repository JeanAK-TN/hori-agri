<?php

namespace App\Http\Requests;

use App\Enums\RecoStatus;
use App\Models\Recommendation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecommendationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $recommendation = $this->route('recommendation');

        return $recommendation instanceof Recommendation
            && $this->user()->can('respond', $recommendation);
    }

    /**
     * Écarter est le seul changement d'état qui se demande. « Appliquée »
     * passe par le retour, parce qu'elle s'accompagne de ce qui a été
     * observé (SPEC section 11.2). « Lue » se constate à l'affichage.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([RecoStatus::Dismissed->value])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Une recommandation appliquée s\'enregistre avec le retour de terrain.',
        ];
    }
}
