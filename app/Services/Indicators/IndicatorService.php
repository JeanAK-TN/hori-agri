<?php

namespace App\Services\Indicators;

use App\Enums\CycleStatus;
use App\Models\CropCycle;
use App\Models\Indicator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Computes the ten indicators of a cycle (SPEC section 9.2).
 *
 * The service knows nothing of the HTTP request: the cycle is handed to
 * it, and the comparison zone is pinned to that cycle's own exploitation
 * rather than to whoever happens to be logged in. That is what makes it
 * correct when it runs from an import or a command.
 */
class IndicatorService
{
    /**
     * Below five comparable cycles the two ratios stay null and the
     * family C rules are suspended (SPEC section 9.3).
     *
     * This threshold is not negotiable: the median of two values being
     * their mean, one is always above and the other below, which would
     * fire a comparison rule on a correctly managed plot.
     */
    public const MINIMUM_ZONE_SIZE = 5;

    /**
     * Compute and store the indicators of a cycle.
     *
     * A cycle without any harvest carries no indicators at all: the
     * processing stops there (SPEC section 9.1, step 1), and a set left
     * over from an earlier state is removed.
     */
    public function computeFor(CropCycle $cycle): ?Indicator
    {
        $cycle->loadMissing('plot', 'harvests', 'operations');

        if ($cycle->harvests->isEmpty()) {
            $cycle->indicator()->delete();

            return null;
        }

        $area = (float) $cycle->plot->area_ha;

        if ($area <= 0) {
            return null;
        }

        $harvestedKg = $this->sum($cycle->harvests, 'quantity_kg');
        $operationsCost = $this->sum($cycle->operations, 'cost');
        $inputCost = $this->sum(
            $cycle->operations->filter(fn ($operation): bool => $operation->input_type !== null),
            'cost',
        );

        // Un unit_price nul rend la valeur de la récolte incalculable :
        // la marge brute et la part des intrants restent alors nulles
        // (SPEC section 9.2).
        $harvestValue = $this->harvestValue($cycle);

        $zone = $this->comparisonZone($cycle);
        $medianYield = $this->median($zone->pluck('yield')->all());
        $medianCost = $this->median($zone->pluck('cost')->all());
        $zoneIsLargeEnough = $zone->count() >= self::MINIMUM_ZONE_SIZE;

        $yieldPerHectare = $harvestedKg / $area;
        $costPerHectare = $operationsCost / $area;

        $indicator = $cycle->indicator()->firstOrNew();

        // L'exploitation vient du cycle, pas de l'utilisateur connecté :
        // le calcul tourne aussi depuis un import ou une commande, où le
        // trait BelongsToExploitation n'a personne à interroger.
        $indicator->exploitation_id = $cycle->exploitation_id;

        $indicator->fill([
            'yield_kg_ha' => round($yieldPerHectare, 2),
            'production_cost_ha' => round($costPerHectare, 2),
            'gross_margin_ha' => $harvestValue === null
                ? null
                : round(($harvestValue - $operationsCost) / $area, 2),
            'input_cost_share' => $harvestValue === null || $harvestValue <= 0
                ? null
                : round($inputCost / $harvestValue, 3),
            'zone_median_yield_kg_ha' => $medianYield === null ? null : round($medianYield, 2),
            'zone_median_cost_ha' => $medianCost === null ? null : round($medianCost, 2),
            'zone_plot_count' => $zone->count(),
            'yield_vs_zone_median' => $zoneIsLargeEnough && $medianYield !== null && $medianYield > 0
                ? round($yieldPerHectare / $medianYield, 3)
                : null,
            'cost_vs_zone_median' => $zoneIsLargeEnough && $medianCost !== null && $medianCost > 0
                ? round($costPerHectare / $medianCost, 3)
                : null,
            'computed_at' => now(),
        ]);

        $indicator->save();

        return $indicator;
    }

    /**
     * The comparison zone, built exactly as SPEC section 9.3 prescribes.
     *
     *  1. cycles of the same exploitation, crop and campaign, not given
     *     up, carrying at least one harvest, whose producer lives in the
     *     same village as the one being evaluated. The evaluated cycle
     *     belongs to the set.
     *  2. below five cycles, the set is rebuilt without the village
     *     condition, that is at the scale of the exploitation.
     *
     * The yields and costs of the peers are recomputed from the raw
     * rows rather than read from their own indicators, which may not
     * have been refreshed yet.
     *
     * @return Collection<int, array{yield: float, cost: float}>
     */
    private function comparisonZone(CropCycle $cycle): Collection
    {
        $village = $cycle->plot->farmer->village;

        $inVillage = $this->peers($cycle, $village);

        if ($inVillage->count() >= self::MINIMUM_ZONE_SIZE) {
            return $inVillage;
        }

        // Repli sur l'exploitation entière, qui n'a qu'une région.
        return $this->peers($cycle, village: null);
    }

    /**
     * @return Collection<int, array{yield: float, cost: float}>
     */
    private function peers(CropCycle $cycle, ?string $village): Collection
    {
        return CropCycle::query()
            // L'exploitation du cycle, et non celle de l'utilisateur
            // connecté : le calcul tourne aussi hors contexte HTTP.
            ->where('crop_cycles.exploitation_id', $cycle->exploitation_id)
            ->where('crop_id', $cycle->crop_id)
            ->where('campaign_id', $cycle->campaign_id)
            ->where('status', '!=', CycleStatus::Abandoned->value)
            ->whereHas('harvests')
            ->when(
                $village !== null,
                fn (Builder $query) => $query->whereHas(
                    'plot.farmer',
                    fn (Builder $farmer) => $farmer->where('village', $village),
                ),
            )
            ->with('plot:id,area_ha')
            ->withSum('harvests as harvested_kg', 'quantity_kg')
            ->withSum('operations as operations_cost', 'cost')
            ->get()
            ->map(function (CropCycle $peer): ?array {
                $area = (float) $peer->plot->area_ha;

                if ($area <= 0) {
                    return null;
                }

                return [
                    'yield' => (float) ($peer->harvested_kg ?? 0) / $area,
                    'cost' => (float) ($peer->operations_cost ?? 0) / $area,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * The median of an even number of values is the mean of the two
     * middle ones (SPEC section 9.3).
     *
     * @param  array<int, float>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);

        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];
    }

    /**
     * Sum a decimal column, a null value counting as zero (SPEC 9.2).
     *
     * @param  Collection<int, covariant \Illuminate\Database\Eloquent\Model>  $rows
     */
    private function sum(Collection $rows, string $column): float
    {
        return $rows->reduce(
            static fn (float $carry, $row): float => $carry + (float) ($row->{$column} ?? 0),
            0.0,
        );
    }

    /**
     * The total value of the harvest, or null when one of the harvests
     * carries no unit price: the value is then incalculable, and so are
     * the gross margin and the input cost share (SPEC section 9.2).
     */
    private function harvestValue(CropCycle $cycle): ?float
    {
        $value = 0.0;

        foreach ($cycle->harvests as $harvest) {
            if ($harvest->unit_price === null) {
                return null;
            }

            $value += (float) $harvest->quantity_kg * (float) $harvest->unit_price;
        }

        return $value;
    }
}
