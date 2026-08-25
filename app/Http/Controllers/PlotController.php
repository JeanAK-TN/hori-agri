<?php

namespace App\Http\Controllers;

use App\Enums\SoilPreparation;
use App\Enums\SoilType;
use App\Http\Requests\StorePlotRequest;
use App\Http\Requests\UpdatePlotRequest;
use App\Models\Farmer;
use App\Models\Plot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlotController extends Controller
{
    /**
     * List the plots of the current exploitation.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Plot::class);

        $search = $request->string('recherche')->toString();

        $plots = Plot::query()
            ->with('farmer:id,first_name,last_name,village')
            ->when($search !== '', function ($query) use ($search) {
                $pattern = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';

                $query->where(function ($query) use ($pattern) {
                    $query->where('name', 'ilike', $pattern)
                        ->orWhereHas('farmer', fn ($farmer) => $farmer
                            ->where('last_name', 'ilike', $pattern)
                            ->orWhere('first_name', 'ilike', $pattern)
                            ->orWhere('village', 'ilike', $pattern));
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Plot $plot): array => [
                'id' => $plot->id,
                'name' => $plot->name,
                'area_ha' => $plot->area_ha,
                'soil_type_label' => $plot->soil_type->label(),
                'farmer' => [
                    'id' => $plot->farmer->id,
                    'full_name' => $plot->farmer->fullName(),
                    'village' => $plot->farmer->village,
                ],
            ]);

        return Inertia::render('plots/index', [
            'plots' => $plots,
            'filters' => ['recherche' => $search],
        ]);
    }

    /**
     * Show a plot and the cycles opened on it.
     */
    public function show(Plot $plot): Response
    {
        $this->authorize('view', $plot);

        $plot->load([
            'farmer:id,first_name,last_name,village,phone',
            'cropCycles.crop:id,name',
            'cropCycles.campaign:id,label',
        ]);

        return Inertia::render('plots/show', [
            'plot' => $this->present($plot),
            'cycles' => $plot->cropCycles
                ->sortByDesc('sowing_date')
                ->values()
                ->map(fn ($cycle): array => [
                    'id' => $cycle->id,
                    'crop' => $cycle->crop->name,
                    'campaign' => $cycle->campaign->label,
                    'sowing_date' => $cycle->sowing_date->format('d/m/Y'),
                    'status' => $cycle->status->value,
                    'status_label' => $cycle->status->label(),
                ])
                ->all(),
        ]);
    }

    /**
     * Show the form used to record a plot.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Plot::class);

        return Inertia::render('plots/create', [
            'soilTypes' => SoilType::options(),
            'soilPreparations' => SoilPreparation::options(),
            'farmers' => $this->farmerOptions(),
            'selectedFarmerId' => $request->integer('producteur') ?: null,
        ]);
    }

    /**
     * Record a plot.
     */
    public function store(StorePlotRequest $request): RedirectResponse
    {
        $plot = Plot::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Parcelle enregistrée.']);

        return to_route('plots.show', $plot);
    }

    /**
     * Show the form used to correct a plot.
     */
    public function edit(Plot $plot): Response
    {
        $this->authorize('update', $plot);

        return Inertia::render('plots/edit', [
            'plot' => $this->present($plot),
            'soilTypes' => SoilType::options(),
            'soilPreparations' => SoilPreparation::options(),
            'farmers' => $this->farmerOptions(),
        ]);
    }

    /**
     * Update a plot.
     */
    public function update(UpdatePlotRequest $request, Plot $plot): RedirectResponse
    {
        $plot->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Parcelle mise à jour.']);

        return to_route('plots.show', $plot);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Plot $plot): array
    {
        return [
            'id' => $plot->id,
            'name' => $plot->name,
            'area_ha' => $plot->area_ha,
            'soil_type' => $plot->soil_type->value,
            'soil_type_label' => $plot->soil_type->label(),
            'soil_preparation' => $plot->soil_preparation?->value,
            'soil_preparation_label' => $plot->soil_preparation?->label(),
            'latitude' => $plot->latitude,
            'longitude' => $plot->longitude,
            'previous_crop' => $plot->previous_crop,
            'farmer_id' => $plot->farmer_id,
            'farmer' => [
                'id' => $plot->farmer->id,
                'full_name' => $plot->farmer->fullName(),
                'village' => $plot->farmer->village,
                'phone' => $plot->farmer->phone,
            ],
        ];
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function farmerOptions(): array
    {
        return Farmer::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'village'])
            ->map(fn (Farmer $farmer): array => [
                'id' => $farmer->id,
                'label' => $farmer->fullName().' · '.$farmer->village,
            ])
            ->all();
    }
}
