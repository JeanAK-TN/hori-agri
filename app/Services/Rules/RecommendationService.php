<?php

namespace App\Services\Rules;

use App\Enums\RecoStatus;
use App\Models\CropCycle;
use App\Models\Recommendation;
use App\Models\Rule;
use App\Services\Indicators\IndicatorService;
use Illuminate\Support\Collection;

/**
 * Orchestrates the life cycle of a recommendation (SPEC section 9.1).
 *
 * The engine fires after an operation or a harvest is recorded, never on
 * a deliberate action of the user. The sequence is the one of the
 * specification, in this order:
 *
 *  1. the cycle is loaded; without any harvest the run stops;
 *  2. IndicatorService computes and stores the indicators;
 *  3. the fifteen-field evaluation context is built;
 *  4. the applicable rules are selected and the exploitation override
 *     is applied;
 *  5. RuleEvaluator evaluates each condition tree;
 *  6. every true condition creates or updates a recommendation;
 *  7. the recommendations of the cycle are sorted and given their rank.
 */
class RecommendationService
{
    public function __construct(
        private readonly IndicatorService $indicators,
        private readonly RuleEvaluator $evaluator,
    ) {}

    /**
     * Run the engine on a cycle.
     */
    public function runFor(CropCycle $cycle): void
    {
        $indicator = $this->indicators->computeFor($cycle);

        if ($indicator === null) {
            // Aucune récolte : le cycle ne porte ni indicateurs ni
            // recommandations, hors celles auxquelles l'agriculteur a
            // déjà répondu.
            $this->forgetStaleRecommendations($cycle, keeping: collect());

            return;
        }

        $context = EvaluationContext::fromCycle($cycle->fresh());

        $triggered = collect();

        foreach ($this->applicableRules($cycle, $indicator->hasComparableZone()) as $rule) {
            if (! $this->evaluator->evaluate($rule->condition, $context)) {
                continue;
            }

            $triggered->push($this->record($cycle, $rule, $context));
        }

        $this->forgetStaleRecommendations($cycle, keeping: $triggered->pluck('rule_id'));

        $this->prioritise($cycle);
    }

    /**
     * The rules that apply to a cycle (SPEC section 9.6).
     *
     * Are kept those that are enabled, whose crop is null or the crop of
     * the cycle, and whose exploitation is null or the exploitation of the
     * cycle. Then the override applies: when two kept rules share a
     * code, the one carried by an exploitation replaces the common one.
     *
     * @return Collection<int, Rule>
     */
    private function applicableRules(CropCycle $cycle, bool $zoneIsComparable): Collection
    {
        return Rule::withoutGlobalScopes()
            ->where('enabled', true)
            ->where(fn ($query) => $query
                ->whereNull('exploitation_id')
                ->orWhere('exploitation_id', $cycle->exploitation_id))
            ->where(fn ($query) => $query
                ->whereNull('crop_id')
                ->orWhere('crop_id', $cycle->crop_id))
            ->get()
            ->groupBy('code')
            ->map(fn (Collection $sharingACode): Rule => $sharingACode
                ->sortByDesc(fn (Rule $rule): int => $rule->isOverride() ? 1 : 0)
                ->first())
            ->values()
            // En dessous de cinq parcelles comparables, les règles de
            // famille C sont écartées (SPEC section 9.6).
            ->reject(fn (Rule $rule): bool => $rule->family->needsComparableZone() && ! $zoneIsComparable);
    }

    /**
     * Create or update the recommendation of a (cycle, rule) couple.
     *
     * The same couple never yields two recommendations: the unique index
     * carries the rule, updateOrCreate honours it (SPEC section 9.7).
     */
    private function record(CropCycle $cycle, Rule $rule, EvaluationContext $context): Recommendation
    {
        $recommendation = $cycle->recommendations()
            ->withoutGlobalScopes()
            ->firstOrNew(['rule_id' => $rule->id]);

        // L'exploitation vient du cycle : le moteur tourne aussi depuis
        // un import ou une commande, où le trait n'a personne à
        // interroger. Le reste passe par fill() pour que les casts
        // s'appliquent, notamment sur la date.
        $recommendation->exploitation_id = $cycle->exploitation_id;

        $recommendation->fill([
            'generated_message' => $this->composeMessage($rule, $context),
            'generated_at' => now(),
        ]);

        if (! $recommendation->exists) {
            $recommendation->fill([
                'priority' => 0,
                'status' => RecoStatus::New,
            ]);
        }

        $recommendation->save();

        return $recommendation;
    }

    /**
     * The message of the rule, followed by the observed values that made
     * the condition fire, between parentheses (SPEC section 9.7).
     */
    private function composeMessage(Rule $rule, EvaluationContext $context): string
    {
        $observations = array_map(
            static fn (string $field): string => $context->describe($field),
            $this->evaluator->leafFields($rule->condition),
        );

        if ($observations === []) {
            return $rule->message;
        }

        return sprintf('%s (%s)', $rule->message, implode(', ', $observations));
    }

    /**
     * Recommendations that no longer fire are removed, except those the
     * farmer has answered — applied or dismissed — which stay as a trace
     * of what was decided (SPEC section 9.7).
     *
     * @param  Collection<int, int>  $keeping
     */
    private function forgetStaleRecommendations(CropCycle $cycle, Collection $keeping): void
    {
        $cycle->recommendations()
            ->withoutGlobalScopes()
            ->whereNotIn('rule_id', $keeping->all())
            ->whereNotIn('status', [RecoStatus::Applied->value, RecoStatus::Dismissed->value])
            ->delete();
    }

    /**
     * Sort by decreasing severity, then by decreasing estimated gain,
     * then by code so the order stays the same from one run to the next,
     * and write the rank starting at 1 (SPEC section 9.7).
     */
    private function prioritise(CropCycle $cycle): void
    {
        $ranked = $cycle->recommendations()
            ->withoutGlobalScopes()
            ->with('rule')
            ->get()
            ->sortBy([
                fn (Recommendation $a, Recommendation $b): int => $b->rule->severity->rank() <=> $a->rule->severity->rank(),
                fn (Recommendation $a, Recommendation $b): int => $b->rule->estimatedGainValue() <=> $a->rule->estimatedGainValue(),
                fn (Recommendation $a, Recommendation $b): int => strcmp($a->rule->code, $b->rule->code),
            ])
            ->values();

        foreach ($ranked as $index => $recommendation) {
            $recommendation->forceFill(['priority' => $index + 1])->save();
        }
    }
}
