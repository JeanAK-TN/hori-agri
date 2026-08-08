<?php

namespace App\Http\Controllers;

use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\Quality;
use App\Enums\QuantityUnit;
use App\Enums\Role;
use App\Http\Requests\StoreCropCycleRequest;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Harvest;
use App\Models\Operation;
use App\Models\Plot;
use App\Models\Variety;
use App\Services\Indicators\IndicatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CropCycleController extends Controller
{
    /**
     * List the cycles of the cooperative, filterable by campaign and by
     * crop (SPEC section 11).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CropCycle::class);

        $campaignId = $request->integer('campagne') ?: null;
        $cropId = $request->integer('culture') ?: null;

        $cycles = CropCycle::query()
            ->with([
                'crop:id,name',
                'campaign:id,label',
                'plot:id,name,farmer_id',
                'plot.farmer:id,first_name,last_name,village',
            ])
            ->when($campaignId !== null, fn ($query) => $query->where('campaign_id', $campaignId))
            ->when($cropId !== null, fn ($query) => $query->where('crop_id', $cropId))
            ->orderByDesc('sowing_date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (CropCycle $cycle): array => [
                'id' => $cycle->id,
                'crop' => $cycle->crop->name,
                'campaign' => $cycle->campaign->label,
                'plot' => $cycle->plot->name,
                'farmer' => $cycle->plot->farmer->fullName(),
                'village' => $cycle->plot->farmer->village,
                'sowing_date_label' => $cycle->sowing_date->format('d/m/Y'),
                'status' => $cycle->status->value,
                'status_label' => $cycle->status->label(),
            ]);

        return Inertia::render('cycles/index', [
            'cycles' => $cycles,
            'filters' => ['campagne' => $campaignId, 'culture' => $cropId],
            'campaigns' => $this->campaignOptions(),
            'crops' => $this->cropOptions(),
        ]);
    }

    /**
     * Show a cycle. The technical itinerary — operations and harvests —
     * is filled in by module 4.
     */
    public function show(Request $request, CropCycle $cycle): Response
    {
        $this->authorize('view', $cycle);

        $cycle->load([
            'crop:id,name,cycle_days,reference_density',
            'variety:id,name',
            'campaign:id,label,start_date,end_date',
            'plot:id,name,area_ha,soil_type,farmer_id',
            'plot.farmer:id,first_name,last_name,village',
            'operations',
            'harvests',
            'indicator',
        ]);

        return Inertia::render('cycles/show', [
            // L'itinéraire déjà saisi s'affiche en ordre chronologique
            // au-dessus du formulaire (SPEC section 11.1).
            'operations' => $cycle->operations
                ->sortBy(['date', 'id'])
                ->values()
                ->map(fn (Operation $operation): array => [
                    'id' => $operation->id,
                    'type' => $operation->type->value,
                    'type_label' => $operation->type->label(),
                    'input_type_label' => $operation->input_type?->label(),
                    'date' => $operation->date->toDateString(),
                    'date_label' => $operation->date->format('d/m/Y'),
                    'product' => $operation->product,
                    'quantity' => $operation->quantity,
                    'unit_label' => $operation->unit?->label(),
                    'cost' => $operation->cost,
                    'notes' => $operation->notes,
                ])
                ->all(),
            'harvests' => $cycle->harvests
                ->sortBy(['date', 'id'])
                ->values()
                ->map(fn (Harvest $harvest): array => [
                    'id' => $harvest->id,
                    'date' => $harvest->date->toDateString(),
                    'date_label' => $harvest->date->format('d/m/Y'),
                    'quantity_kg' => $harvest->quantity_kg,
                    'unit_price' => $harvest->unit_price,
                    'quality_label' => $harvest->quality?->label(),
                    'post_harvest_losses_kg' => $harvest->post_harvest_losses_kg,
                ])
                ->all(),
            // Aucune de ces valeurs n'est saisie : elles sont recalculées
            // à chaque écriture sur le cycle (SPEC section 9.1).
            'indicators' => $cycle->indicator === null ? null : [
                'yield_kg_ha' => $cycle->indicator->yield_kg_ha,
                'production_cost_ha' => $cycle->indicator->production_cost_ha,
                'gross_margin_ha' => $cycle->indicator->gross_margin_ha,
                'input_cost_share' => $cycle->indicator->input_cost_share,
                'zone_median_yield_kg_ha' => $cycle->indicator->zone_median_yield_kg_ha,
                'zone_median_cost_ha' => $cycle->indicator->zone_median_cost_ha,
                'zone_plot_count' => $cycle->indicator->zone_plot_count,
                'yield_vs_zone_median' => $cycle->indicator->yield_vs_zone_median,
                'cost_vs_zone_median' => $cycle->indicator->cost_vs_zone_median,
                'has_comparable_zone' => $cycle->indicator->hasComparableZone(),
                'minimum_zone_size' => IndicatorService::MINIMUM_ZONE_SIZE,
                'computed_at_label' => $cycle->indicator->computed_at->format('d/m/Y à H:i'),
            ],
            'operationTypes' => OperationType::options(),
            'inputTypes' => InputType::options(),
            'units' => QuantityUnit::options(),
            'qualities' => Quality::options(),
            'canRecord' => $request->user()->role === Role::Technician && $cycle->status->isOpen(),
            'cycle' => [
                'id' => $cycle->id,
                'crop' => $cycle->crop->name,
                'variety' => $cycle->variety?->name,
                'campaign' => $cycle->campaign->label,
                'campaign_start_label' => $cycle->campaign->start_date->format('d/m/Y'),
                'campaign_end_label' => $cycle->campaign->end_date->format('d/m/Y'),
                'sowing_date_label' => $cycle->sowing_date->format('d/m/Y'),
                'density' => $cycle->density,
                'status' => $cycle->status->value,
                'status_label' => $cycle->status->label(),
                'is_open' => $cycle->status->isOpen(),
                'plot' => [
                    'id' => $cycle->plot->id,
                    'name' => $cycle->plot->name,
                    'area_ha' => $cycle->plot->area_ha,
                    'soil_type_label' => $cycle->plot->soil_type->label(),
                ],
                'farmer' => [
                    'id' => $cycle->plot->farmer->id,
                    'full_name' => $cycle->plot->farmer->fullName(),
                    'village' => $cycle->plot->farmer->village,
                ],
            ],
        ]);
    }

    /**
     * Show the form used to open a cycle.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', CropCycle::class);

        return Inertia::render('cycles/create', [
            'plots' => Plot::query()
                ->with('farmer:id,first_name,last_name,village')
                ->orderBy('name')
                ->get(['id', 'name', 'farmer_id'])
                ->map(fn (Plot $plot): array => [
                    'id' => $plot->id,
                    'label' => $plot->name.' · '.$plot->farmer->fullName(),
                ])
                ->all(),
            'campaigns' => $this->campaignOptions(),
            'crops' => $this->cropOptions(),
            'varieties' => Variety::query()
                ->orderBy('name')
                ->get(['id', 'crop_id', 'name'])
                ->map(fn (Variety $variety): array => [
                    'id' => $variety->id,
                    'crop_id' => $variety->crop_id,
                    'label' => $variety->name,
                ])
                ->all(),
            'selectedPlotId' => $request->integer('parcelle') ?: null,
        ]);
    }

    /**
     * Open a cycle on a plot, for a crop and a campaign.
     */
    public function store(StoreCropCycleRequest $request): RedirectResponse
    {
        $cycle = CropCycle::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cycle cultural ouvert.']);

        return to_route('cycles.show', $cycle);
    }

    /**
     * Close the cycle once the harvest is over.
     */
    public function close(CropCycle $cycle): RedirectResponse
    {
        $this->authorize('transition', $cycle);

        $cycle->close();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cycle clôturé.']);

        return to_route('cycles.show', $cycle);
    }

    /**
     * Give up the cycle.
     */
    public function abandon(CropCycle $cycle): RedirectResponse
    {
        $this->authorize('transition', $cycle);

        $cycle->abandon();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Cycle abandonné.']);

        return to_route('cycles.show', $cycle);
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function campaignOptions(): array
    {
        return Campaign::query()
            ->orderByDesc('start_date')
            ->get(['id', 'label'])
            ->map(fn (Campaign $campaign): array => [
                'id' => $campaign->id,
                'label' => $campaign->label,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function cropOptions(): array
    {
        return Crop::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Crop $crop): array => [
                'id' => $crop->id,
                'label' => $crop->name,
            ])
            ->all();
    }
}
