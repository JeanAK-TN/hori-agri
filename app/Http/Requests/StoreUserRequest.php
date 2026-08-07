<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesPhoneNumbers;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\Cooperative;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    use NormalizesPhoneNumbers, PasswordValidationRules, ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * A cooperative administrator can only ever create accounts inside
     * their own cooperative: the value is forced here rather than trusted
     * from the payload (SPEC section 7.3).
     */
    protected function prepareForValidation(): void
    {
        $actor = $this->user();

        $this->merge([
            'phone' => self::normalizePhone((string) $this->input('phone')),
            'cooperative_id' => $actor->isSuperAdmin()
                ? $this->input('cooperative_id')
                : $actor->cooperative_id,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $assignable = Role::values(Role::assignableBy($this->user()->role));

        return [
            'name' => $this->nameRules(),
            'phone' => $this->phoneRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
            'role' => ['required', Rule::in($assignable)],
            'cooperative_id' => ['required', Rule::exists(Cooperative::class, 'id')],
        ];
    }
}
