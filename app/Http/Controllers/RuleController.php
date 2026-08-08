<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\Severity;
use App\Http\Requests\UpdateRuleRequest;
use App\Models\Rule;
use App\Services\Rules\EvaluationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RuleController extends Controller
{
    /**
     * The knowledge base, with the origin of every threshold shown
     * (SPEC section 11). The scope of the rules already limits a
     * cooperative to the common rules and to its own.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Rule::class);

        $rules = Rule::query()
            ->with('crop:id,name')
            ->orderBy('code')
            ->get();

        // Une surcharge remplace la règle commune de même code : c'est
        // elle que la coopérative voit appliquer (SPEC section 9.6).
        $overriddenCodes = $rules
            ->filter(fn (Rule $rule): bool => $rule->isOverride())
            ->pluck('code')
            ->all();

        return Inertia::render('rules/index', [
            'rules' => $rules
                ->map(fn (Rule $rule): array => [
                    'id' => $rule->id,
                    'code' => $rule->code,
                    'crop' => $rule->crop?->name,
                    'family' => $rule->family->value,
                    'family_label' => $rule->family->label(),
                    'severity' => $rule->severity->value,
                    'severity_label' => $rule->severity->label(),
                    'condition' => $rule->condition,
                    'message' => $rule->message,
                    'action' => $rule->action,
                    'estimated_gain' => $rule->estimated_gain,
                    'source' => $rule->source,
                    'threshold_status' => $rule->threshold_status->value,
                    'threshold_status_label' => $rule->threshold_status->label(),
                    'enabled' => $rule->enabled,
                    'is_override' => $rule->isOverride(),
                    'is_overridden' => ! $rule->isOverride() && in_array($rule->code, $overriddenCodes, true),
                ])
                ->values()
                ->all(),
            'severities' => Severity::options(),
            'fields' => array_map(
                static fn (string $field, array $labels): array => [
                    'value' => $field,
                    'label' => $labels['libellé'],
                    'unit' => $labels['unité'],
                ],
                array_keys(EvaluationContext::FIELD_LABELS),
                array_values(EvaluationContext::FIELD_LABELS),
            ),
            'canOverride' => $request->user()->role === Role::CoopAdmin,
        ]);
    }

    /**
     * Override a threshold for one cooperative (SPEC section 11).
     *
     * Overriding a common rule does not change it: a copy carrying the
     * same code is created for the cooperative, and that copy is the one
     * the engine will keep.
     */
    public function update(UpdateRuleRequest $request, Rule $rule): RedirectResponse
    {
        $override = $rule->isOverride()
            ? $rule
            : $rule->replicate(['created_at', 'updated_at']);

        $override->fill($request->validated());
        $override->cooperative_id = $request->user()->cooperative_id;
        $override->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Seuil surchargé pour votre coopérative.',
        ]);

        return to_route('rules.index');
    }
}
