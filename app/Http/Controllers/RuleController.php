<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\Severity;
use App\Enums\ThresholdStatus;
use App\Http\Requests\UpdateRuleRequest;
use App\Models\Rule;
use App\Services\Rules\ConditionPresenter;
use App\Services\Rules\EvaluationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RuleController extends Controller
{
    public function __construct(private readonly ConditionPresenter $presenter) {}

    /**
     * The knowledge base, with the origin of every threshold shown
     * (SPEC-CORRECTION section 6). The scope of the rules already limits
     * an exploitation to the common rules and to its own.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Rule::class);

        $rules = Rule::query()
            ->with('crop:id,name')
            ->orderBy('code')
            ->get();

        // Une surcharge remplace la règle commune de même code : c'est
        // elle que l'exploitation voit appliquer (SPEC section 9.6). Le
        // producteur doit voir que ce seuil n'est plus celui de la fiche
        // nationale : la valeur commune est rappelée à côté.
        $commonByCode = $rules
            ->reject(fn (Rule $rule): bool => $rule->isOverride())
            ->keyBy('code');

        $overriddenCodes = $rules
            ->filter(fn (Rule $rule): bool => $rule->isOverride())
            ->pluck('code')
            ->all();

        return Inertia::render('rules/index', [
            'rules' => $rules
                ->map(function (Rule $rule) use ($commonByCode, $overriddenCodes): array {
                    $common = $rule->isOverride() ? $commonByCode->get($rule->code) : null;

                    return [
                        'id' => $rule->id,
                        'code' => $rule->code,
                        'crop' => $rule->crop?->name,
                        'family' => $rule->family->value,
                        'family_label' => $rule->family->label(),
                        'severity' => $rule->severity->value,
                        'severity_label' => $rule->severity->label(),
                        'condition' => $rule->condition,
                        // Ce que la règle vérifie, en français lisible,
                        // jamais le JSON brut.
                        'condition_sentence' => $this->presenter->sentence($rule->condition),
                        'common_condition_sentence' => $common === null
                            ? null
                            : $this->presenter->sentence($common->condition),
                        'message' => $rule->message,
                        'action' => $rule->action,
                        'estimated_gain' => $rule->estimated_gain,
                        'source' => $rule->source,
                        'threshold_status' => $rule->threshold_status->value,
                        // L'origine du seuil, note comprise : « Source
                        // togolaise publiée, formule à confirmer ».
                        'threshold_status_label' => $rule->threshold_note === null
                            ? $rule->threshold_status->label()
                            : $rule->threshold_status->label().', '.$rule->threshold_note,
                        'enabled' => $rule->enabled,
                        'is_override' => $rule->isOverride(),
                        'is_overridden' => ! $rule->isOverride() && in_array($rule->code, $overriddenCodes, true),
                    ];
                })
                ->values()
                ->all(),
            // Le bandeau de synthèse : quatre nombres calculés, jamais
            // écrits en dur (SPEC-CORRECTION section 6).
            'summary' => [
                'rules' => $rules->count(),
                'sources' => $rules->pluck('source')->unique()->count(),
                'foreign_thresholds' => $rules
                    ->filter(fn (Rule $rule): bool => $rule->threshold_status === ThresholdStatus::Imported)
                    ->count(),
                'convention_thresholds' => $rules
                    ->filter(fn (Rule $rule): bool => $rule->threshold_status === ThresholdStatus::Convention)
                    ->count(),
            ],
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
            'canOverride' => $request->user()->role === Role::ExploitationAdmin,
        ]);
    }

    /**
     * Override a threshold for one exploitation (SPEC section 11).
     *
     * Overriding a common rule does not change it: a copy carrying the
     * same code is created for the exploitation, and that copy is the one
     * the engine will keep.
     */
    public function update(UpdateRuleRequest $request, Rule $rule): RedirectResponse
    {
        $override = $rule->isOverride()
            ? $rule
            : $rule->replicate(['created_at', 'updated_at']);

        $override->fill($request->validated());
        $override->exploitation_id = $request->user()->exploitation_id;
        $override->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Seuil surchargé pour votre exploitation.',
        ]);

        return to_route('rules.index');
    }
}
