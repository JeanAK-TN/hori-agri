<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesPhoneNumbers;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\Exploitation;
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
     * An exploitation administrator can only ever create accounts inside
     * their own exploitation: the value is forced here rather than trusted
     * from the payload (SPEC section 7.3).
     */
    protected function prepareForValidation(): void
    {
        $actor = $this->user();

        $this->merge([
            'phone' => self::normalizePhone((string) $this->input('phone')),
            'exploitation_id' => $actor->isSuperAdmin()
                ? $this->input('exploitation_id')
                : $actor->exploitation_id,
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
            'exploitation_id' => ['required', Rule::exists(Exploitation::class, 'id')],
        ];
    }
}
