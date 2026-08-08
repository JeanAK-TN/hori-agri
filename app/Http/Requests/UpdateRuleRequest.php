<?php

namespace App\Http\Requests;

use App\Enums\Severity;
use App\Models\Rule;
use App\Services\Rules\RuleEvaluator;
use App\Services\Rules\RulesFileValidator;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule as ValidationRuleFactory;
use Illuminate\Validation\Validator;
use Throwable;

class UpdateRuleRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('override', $this->targetRule());
    }

    public function targetRule(): Rule
    {
        $rule = $this->route('rule');

        if (! $rule instanceof Rule) {
            abort(404);
        }

        return $rule;
    }

    /**
     * Only three things are overridable: the condition — that is the
     * threshold itself —, the severity and whether the rule fires.
     * The code, the family and the crop identify the rule and stay.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'condition' => ['required', 'array'],
            'severity' => ['required', ValidationRuleFactory::enum(Severity::class)],
            'enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('condition')) {
                    return;
                }

                // La condition doit tourner : on l'essaie sur un contexte
                // factice avant de l'enregistrer, comme le fait le
                // chargement du fichier (SPEC section 10.4).
                try {
                    /** @var array<string, mixed> $condition */
                    $condition = $this->input('condition');

                    app(RuleEvaluator::class)->evaluate($condition, RulesFileValidator::fakeContext());
                } catch (Throwable $exception) {
                    $validator->errors()->add('condition', $exception->getMessage());
                }
            },
        ];
    }
}
