<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperationRequest;
use App\Http\Requests\UpdateOperationRequest;
use App\Models\CropCycle;
use App\Models\Operation;
use App\Services\Indicators\IndicatorService;
use Illuminate\Http\RedirectResponse;

class OperationController extends Controller
{
    public function __construct(private readonly IndicatorService $indicators) {}

    /**
     * Record an operation of the technical itinerary.
     *
     * The first operation moves the cycle from planned to in progress
     * (SPEC section 13, module 3), then the indicators are recomputed
     * without any action from the user (SPEC section 9.1).
     */
    public function store(StoreOperationRequest $request, CropCycle $cycle): RedirectResponse
    {
        $cycle->operations()->create($request->validated());

        $cycle->markInProgress();

        $this->indicators->computeFor($cycle->fresh());

        return to_route('cycles.show', $cycle);
    }

    /**
     * Correct an operation.
     */
    public function update(UpdateOperationRequest $request, CropCycle $cycle, Operation $operation): RedirectResponse
    {
        $operation->update($request->validated());

        $this->indicators->computeFor($cycle->fresh());

        return to_route('cycles.show', $cycle);
    }

    /**
     * Remove an operation recorded by mistake.
     */
    public function destroy(CropCycle $cycle, Operation $operation): RedirectResponse
    {
        $this->authorize('delete', $operation);

        $operation->delete();

        $this->indicators->computeFor($cycle->fresh());

        return to_route('cycles.show', $cycle);
    }
}
