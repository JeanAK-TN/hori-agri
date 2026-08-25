<?php

namespace App\Http\Controllers;

use App\Enums\RecoStatus;
use App\Enums\Role;
use App\Http\Requests\UpdateRecommendationRequest;
use App\Models\Farmer;
use App\Models\Recommendation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecommendationController extends Controller
{
    /**
     * The recommendations, filtered by role (SPEC section 11.2).
     *
     * A farmer sees the ones concerning their own plots. A technician
     * and an exploitation administrator see those of the producers they
     * follow — the scope of the exploitation already stops there.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Recommendation::class);

        $user = $request->user();
        $isFarmer = $user->role === Role::Farmer;

        $status = RecoStatus::tryFrom((string) $request->string('statut'));
        $farmerId = $isFarmer ? null : ($request->integer('producteur') ?: null);

        $recommendations = Recommendation::query()
            ->with([
                'rule:id,code,severity,message,action,estimated_gain,source,threshold_status',
                'feedback',
                'cropCycle:id,plot_id,crop_id,campaign_id,sowing_date',
                'cropCycle.crop:id,name',
                'cropCycle.campaign:id,label',
                'cropCycle.plot:id,name,farmer_id',
                'cropCycle.plot.farmer:id,first_name,last_name,village',
            ])
            ->when($isFarmer, fn (Builder $query) => $query->whereHas(
                'cropCycle.plot.farmer',
                fn (Builder $farmer) => $farmer->where('user_id', $user->id),
            ))
            ->when($farmerId !== null, fn (Builder $query) => $query->whereHas(
                'cropCycle.plot',
                fn (Builder $plot) => $plot->where('farmer_id', $farmerId),
            ))
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            // Le calcul le plus récent d'abord, puis le rang que le
            // moteur a donné à l'intérieur du cycle (SPEC section 9.6).
            ->orderByDesc('generated_at')
            ->orderBy('priority')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Recommendation $recommendation): array => $this->present($recommendation));

        if ($isFarmer) {
            $this->markListedAsRead($recommendations->getCollection()->pluck('id')->all());
        }

        return Inertia::render('recommandations/index', [
            'recommendations' => $recommendations,
            'filters' => ['statut' => $status?->value, 'producteur' => $farmerId],
            'statuses' => RecoStatus::options(),
            'farmers' => $isFarmer ? [] : $this->farmerOptions(),
            'canRespond' => $isFarmer,
        ]);
    }

    /**
     * Change the state of a recommendation. Only the producer whose plot
     * is concerned may do it (SPEC section 11.2).
     */
    public function update(
        UpdateRecommendationRequest $request,
        Recommendation $recommendation,
    ): RedirectResponse {
        $recommendation->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Recommandation écartée.',
        ]);

        return back();
    }

    /**
     * Everything the screen shows, in the order the specification fixes:
     * the finding, the action, the agronomic source, then the estimated
     * gain. The source is never folded away (SPEC section 11.2).
     *
     * @return array<string, mixed>
     */
    private function present(Recommendation $recommendation): array
    {
        $rule = $recommendation->rule;
        $cycle = $recommendation->cropCycle;
        $feedback = $recommendation->feedback;

        return [
            'id' => $recommendation->id,
            'finding' => $recommendation->generated_message,
            'action' => $rule->action,
            'source' => $rule->source,
            'estimated_gain' => $rule->estimated_gain,
            'rule_code' => $rule->code,
            'severity' => $rule->severity->value,
            'severity_label' => $rule->severity->label(),
            'threshold_status' => $rule->threshold_status->value,
            'threshold_status_label' => $rule->threshold_status->label(),
            'status' => $recommendation->status->value,
            'status_label' => $recommendation->status->label(),
            'generated_at_label' => $recommendation->generated_at->format('d/m/Y'),
            'cycle' => [
                'id' => $cycle->id,
                'crop' => $cycle->crop->name,
                'campaign' => $cycle->campaign->label,
                'plot' => $cycle->plot->name,
                'farmer' => $cycle->plot->farmer->fullName(),
                'village' => $cycle->plot->farmer->village,
            ],
            'feedback' => $feedback === null ? null : [
                'applied' => $feedback->applied,
                'comment' => $feedback->comment,
                'observed_result' => $feedback->observed_result,
            ],
        ];
    }

    /**
     * Showing a recommendation to its producer is reading it. An answer
     * already given is never walked back (SPEC section 6.2).
     *
     * @param  array<int, int>  $ids
     */
    private function markListedAsRead(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        Recommendation::query()
            ->whereIn('id', $ids)
            ->where('status', RecoStatus::New)
            ->update(['status' => RecoStatus::Read]);
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function farmerOptions(): array
    {
        return Farmer::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn (Farmer $farmer): array => [
                'id' => $farmer->id,
                'label' => $farmer->fullName(),
            ])
            ->all();
    }
}
