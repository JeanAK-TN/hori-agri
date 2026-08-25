<?php

namespace App\Concerns;

use App\Enums\Gender;
use App\Enums\Role;
use App\Models\Farmer;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait FarmerValidationRules
{
    /**
     * Validation rules of a producer record (SPEC section 6.2).
     *
     * @return array<string, array<int, ValidationRule|Closure|array<mixed>|string>>
     */
    protected function farmerRules(?Farmer $farmer = null): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'birth_year' => ['nullable', 'integer', 'min:1900', 'max:'.now()->year],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:100'],
            'village' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{6,}$/'],
            'user_id' => [
                'nullable',
                Rule::unique(Farmer::class, 'user_id')->ignore($farmer),
                $this->linkableAccountRule(),
            ],
        ];
    }

    /**
     * The optional account attached to a producer must be a farmer
     * account of the same exploitation. The users table is deliberately
     * outside ExploitationScope, so visibility is expressed through the
     * dedicated local scope rather than a raw exploitation_id clause.
     */
    private function linkableAccountRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $isLinkable = User::query()
                ->visibleTo($this->user())
                ->where('role', Role::Farmer)
                ->whereKey($value)
                ->exists();

            if (! $isLinkable) {
                $fail('Le compte choisi doit être un compte agriculteur de votre exploitation.');
            }
        };
    }
}
