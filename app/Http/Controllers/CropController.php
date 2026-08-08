<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCropRequest;
use App\Http\Requests\UpdateCropRequest;
use App\Models\Crop;
use App\Models\Variety;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CropController extends Controller
{
    /**
     * The common crop and variety registry (SPEC section 11).
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Crop::class);

        $crops = Crop::query()
            ->with('varieties:id,crop_id,name,cycle_days,potential_yield_kg_ha')
            ->withCount('cropCycles')
            ->orderBy('name')
            ->get();

        return Inertia::render('crops/index', [
            'crops' => array_map(
                fn (Crop $crop): array => $this->present($crop),
                $crops->all(),
            ),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Crop $crop): array
    {
        return [
            'id' => $crop->id,
            'name' => $crop->name,
            'family' => $crop->family,
            'cycle_days' => $crop->cycle_days,
            'reference_density' => $crop->reference_density,
            'reference_yield_kg_ha' => $crop->reference_yield_kg_ha,
            'npk_needs' => $crop->npk_needs,
            'cycles_count' => $crop->crop_cycles_count,
            'varieties' => array_map(
                fn (Variety $variety): array => [
                    'id' => $variety->id,
                    'crop_id' => $variety->crop_id,
                    'name' => $variety->name,
                    'cycle_days' => $variety->cycle_days,
                    'potential_yield_kg_ha' => $variety->potential_yield_kg_ha,
                ],
                $crop->varieties->all(),
            ),
        ];
    }

    /**
     * Add a crop to the registry.
     */
    public function store(StoreCropRequest $request): RedirectResponse
    {
        Crop::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Culture ajoutée au référentiel.']);

        return to_route('crops.index');
    }

    /**
     * Correct the reference values of a crop.
     */
    public function update(UpdateCropRequest $request, Crop $crop): RedirectResponse
    {
        $crop->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Culture mise à jour.']);

        return to_route('crops.index');
    }
}
