<?php

namespace App\Http\Requests;

use App\Concerns\NormalizesPhoneNumbers;
use App\Concerns\ProfileValidationRules;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use NormalizesPhoneNumbers, ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The policy response is returned as is rather than as a boolean, so
     * that a request aimed at another exploitation keeps answering
     * "not found" instead of "forbidden" (SPEC section 7.2).
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->targetUser());
    }

    /**
     * The account being updated, resolved from the route binding.
     */
    private function targetUser(): User
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            abort(404);
        }

        return $user;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => self::normalizePhone((string) $this->input('phone')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The exploitation of an account is never modified here: an account
     * does not move from one exploitation to another.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $target = $this->targetUser();
        $assignable = Role::values(Role::assignableBy($this->user()->role));

        return [
            'name' => $this->nameRules(),
            'phone' => $this->phoneRules($target->id),
            'email' => $this->emailRules($target->id),
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            'role' => ['required', Rule::in($assignable)],
        ];
    }

    /**
     * Guard against an administrator locking themselves out by changing
     * their own role.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->targetUser()->is($this->user()) && $this->input('role') !== $this->user()->role->value) {
                    $validator->errors()->add(
                        'role',
                        'Vous ne pouvez pas modifier votre propre rôle.',
                    );
                }
            },
        ];
    }

    /**
     * Only send a password when one was actually typed.
     *
     * @return array<string, mixed>
     */
    public function attributesToUpdate(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        return $validated;
    }
}
