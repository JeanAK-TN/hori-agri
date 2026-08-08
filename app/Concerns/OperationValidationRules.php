<?php

namespace App\Concerns;

use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\QuantityUnit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait OperationValidationRules
{
    /**
     * Validation rules of an operation (SPEC sections 6.2 and 6.3).
     *
     * The form never asks for a dose per hectare: it asks for a quantity
     * and a unit as the technician counts them, the 50 kg bag being the
     * unit of the field (SPEC section 11.1). The dose is computed later.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function operationRules(array $payload): array
    {
        $type = OperationType::tryFrom((string) ($payload['type'] ?? ''));

        return [
            'type' => ['required', Rule::enum(OperationType::class)],
            // Obligatoire dès que l'opération apporte un produit, interdit
            // sinon : sans cet attribut, sept règles sur dix-sept ne
            // peuvent pas s'évaluer (SPEC section 6.3).
            //
            // Le cas interdit porte « nullable » : le formulaire envoie un
            // champ vide, que Laravel convertit en null. Sans lui, la
            // valeur nulle serait présente et l'énumération la refuserait.
            'input_type' => $type?->requiresInput() === true
                ? ['required', Rule::enum(InputType::class)]
                : ['nullable', 'prohibited'],
            'date' => ['required', 'date'],
            'product' => ['nullable', 'string', 'max:80'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'unit' => ['nullable', 'required_with:quantity', Rule::enum(QuantityUnit::class)],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function operationMessages(): array
    {
        return [
            'input_type.required' => 'La nature de l\'intrant est obligatoire pour une opération qui apporte un produit.',
            'input_type.prohibited' => 'Une opération de ce type n\'apporte aucun intrant : laissez la nature de l\'intrant vide.',
            'unit.required_with' => 'Précisez l\'unité de la quantité saisie.',
        ];
    }
}
