<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVarietyRequest;
use App\Http\Requests\UpdateVarietyRequest;
use App\Models\Variety;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class VarietyController extends Controller
{
    /**
     * Add a variety under a crop of the registry.
     */
    public function store(StoreVarietyRequest $request): RedirectResponse
    {
        Variety::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Variété ajoutée au référentiel.']);

        return to_route('crops.index');
    }

    /**
     * Correct a variety.
     */
    public function update(UpdateVarietyRequest $request, Variety $variety): RedirectResponse
    {
        $variety->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Variété mise à jour.']);

        return to_route('crops.index');
    }
}
