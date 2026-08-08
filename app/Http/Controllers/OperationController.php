<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOperationRequest;
use App\Http\Requests\UpdateOperationRequest;
use App\Models\CropCycle;
use App\Models\Operation;
use Illuminate\Http\RedirectResponse;

class OperationController extends Controller
{
    /**
     * Record an operation of the technical itinerary.
     *
     * The first operation moves the cycle from planned to in progress
     * (SPEC section 13, module 3). The analytics layer of module 5 will
     * hook its recalculation right here, where the writes happen.
     */
    public function store(StoreOperationRequest $request, CropCycle $cycle): RedirectResponse
    {
        $cycle->operations()->create($request->validated());

        $cycle->markInProgress();

        return to_route('cycles.show', $cycle);
    }

    /**
     * Correct an operation.
     */
    public function update(UpdateOperationRequest $request, CropCycle $cycle, Operation $operation): RedirectResponse
    {
        $operation->update($request->validated());

        return to_route('cycles.show', $cycle);
    }

    /**
     * Remove an operation recorded by mistake.
     */
    public function destroy(CropCycle $cycle, Operation $operation): RedirectResponse
    {
        $this->authorize('delete', $operation);

        $operation->delete();

        return to_route('cycles.show', $cycle);
    }
}
