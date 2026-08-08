<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHarvestRequest;
use App\Http\Requests\UpdateHarvestRequest;
use App\Models\CropCycle;
use App\Models\Harvest;
use Illuminate\Http\RedirectResponse;

class HarvestController extends Controller
{
    /**
     * Record a harvest. Several harvests are possible for one cycle
     * (SPEC section 6.2).
     */
    public function store(StoreHarvestRequest $request, CropCycle $cycle): RedirectResponse
    {
        $cycle->harvests()->create($request->validated());

        $cycle->markInProgress();

        return to_route('cycles.show', $cycle);
    }

    /**
     * Correct a harvest.
     */
    public function update(UpdateHarvestRequest $request, CropCycle $cycle, Harvest $harvest): RedirectResponse
    {
        $harvest->update($request->validated());

        return to_route('cycles.show', $cycle);
    }

    /**
     * Remove a harvest recorded by mistake.
     */
    public function destroy(CropCycle $cycle, Harvest $harvest): RedirectResponse
    {
        $this->authorize('delete', $harvest);

        $harvest->delete();

        return to_route('cycles.show', $cycle);
    }
}
