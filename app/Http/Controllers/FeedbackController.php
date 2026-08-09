<?php

namespace App\Http\Controllers;

use App\Enums\RecoStatus;
use App\Http\Requests\StoreFeedbackRequest;
use App\Models\Recommendation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FeedbackController extends Controller
{
    /**
     * Record what the producer did with the recommendation, and move the
     * recommendation to the matching state (SPEC section 11.2).
     *
     * The producer sheet comes from the signed-in account, never from
     * the payload: the policy has already checked that this account owns
     * the plot concerned.
     */
    public function store(
        StoreFeedbackRequest $request,
        Recommendation $recommendation,
    ): RedirectResponse {
        $farmer = $recommendation->farmer();

        abort_if($farmer === null, 404);

        $applied = $request->boolean('applied');

        DB::transaction(function () use ($recommendation, $farmer, $applied, $request): void {
            // Un seul retour par recommandation : il se corrige, il ne
            // s'empile pas.
            $feedback = $recommendation->feedback ?? $recommendation->feedback()->make();

            $feedback->fill($request->validated());
            $feedback->farmer_id = $farmer->id;
            $feedback->cooperative_id = $recommendation->cooperative_id;
            $feedback->save();

            $recommendation->update([
                'status' => $applied ? RecoStatus::Applied : RecoStatus::Dismissed,
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $applied
                ? 'Retour enregistré : recommandation appliquée.'
                : 'Retour enregistré : recommandation écartée.',
        ]);

        return back();
    }
}
