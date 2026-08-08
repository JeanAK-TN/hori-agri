<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHarvestRequest;
use App\Http\Requests\UpdateHarvestRequest;
use App\Models\CropCycle;
use App\Models\Harvest;
use App\Services\Rules\RecommendationService;
use Illuminate\Http\RedirectResponse;

class HarvestController extends Controller
{
    public function __construct(private readonly RecommendationService $engine) {}

    /**
     * Record a harvest. Several harvests are possible for one cycle
     * (SPEC section 6.2). The indicators follow immediately.
     */
    public function store(StoreHarvestRequest $request, CropCycle $cycle): RedirectResponse
    {
        $cycle->harvests()->create($request->validated());

        $cycle->markInProgress();

        $this->engine->runFor($cycle->fresh());

        return to_route('cycles.show', $cycle);
    }

    /**
     * Correct a harvest.
     */
    public function update(UpdateHarvestRequest $request, CropCycle $cycle, Harvest $harvest): RedirectResponse
    {
        $harvest->update($request->validated());

        $this->engine->runFor($cycle->fresh());

        return to_route('cycles.show', $cycle);
    }

    /**
     * Remove a harvest recorded by mistake. Losing the last harvest also
     * removes the indicators, which no longer mean anything.
     */
    public function destroy(CropCycle $cycle, Harvest $harvest): RedirectResponse
    {
        $this->authorize('delete', $harvest);

        $harvest->delete();

        $this->engine->runFor($cycle->fresh());

        return to_route('cycles.show', $cycle);
    }
}
