<?php

namespace App\Http\Controllers;

use App\Enums\SeasonType;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    /**
     * List the campaigns of the cooperative, most recent first.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Campaign::class);

        $campaigns = Campaign::query()
            ->withCount('cropCycles')
            ->orderByDesc('start_date')
            ->paginate(20)
            ->through(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'label' => $campaign->label,
                'start_date' => $campaign->start_date->toDateString(),
                'end_date' => $campaign->end_date->toDateString(),
                'start_date_label' => $campaign->start_date->format('d/m/Y'),
                'end_date_label' => $campaign->end_date->format('d/m/Y'),
                'season_type' => $campaign->season_type->value,
                'season_type_label' => $campaign->season_type->label(),
                'cycles_count' => $campaign->crop_cycles_count,
            ]);

        return Inertia::render('campaigns/index', [
            'campaigns' => $campaigns,
            'seasonTypes' => SeasonType::options(),
        ]);
    }

    /**
     * Open a campaign.
     */
    public function store(StoreCampaignRequest $request): RedirectResponse
    {
        Campaign::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Campagne créée.']);

        return to_route('campaigns.index');
    }

    /**
     * Correct a campaign.
     */
    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $campaign->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Campagne mise à jour.']);

        return to_route('campaigns.index');
    }
}
