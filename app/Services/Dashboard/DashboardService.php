<?php

namespace App\Services\Dashboard;

use App\Enums\CycleStatus;
use App\Enums\RecoStatus;
use App\Enums\Severity;
use App\Models\CropCycle;
use App\Models\Indicator;
use App\Models\Recommendation;
use App\Services\Indicators\IndicatorService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Les agrégats du tableau de bord (SPEC section 12).
 *
 * Tout se compte en base. Aucune méthode ne ramène une collection
 * complète en mémoire pour la parcourir en PHP : ce qui revient est déjà
 * le résultat, une ligne par regroupement.
 *
 * Le scope global de la coopérative s'applique à `indicators` et à
 * `recommendations` : les jointures se lisent donc sans condition de
 * coopérative écrite à la main.
 */
class DashboardService
{
    /**
     * Moyennes de rendement, de coût et de marge par culture.
     *
     * @return array<int, array{label: string, cycles: int, yield_kg_ha: float|null, production_cost_ha: float|null, gross_margin_ha: float|null}>
     */
    public function averagesByCrop(DashboardFilters $filters): array
    {
        return $this->averagesGroupedBy($filters, Grouping::Crop);
    }

    /**
     * Mêmes moyennes, par village du producteur.
     *
     * @return array<int, array{label: string, cycles: int, yield_kg_ha: float|null, production_cost_ha: float|null, gross_margin_ha: float|null}>
     */
    public function averagesByVillage(DashboardFilters $filters): array
    {
        return $this->averagesGroupedBy($filters, Grouping::Village);
    }

    /**
     * Mêmes moyennes, par campagne.
     *
     * @return array<int, array{label: string, cycles: int, yield_kg_ha: float|null, production_cost_ha: float|null, gross_margin_ha: float|null}>
     */
    public function averagesByCampaign(DashboardFilters $filters): array
    {
        return $this->averagesGroupedBy($filters, Grouping::Campaign);
    }

    /**
     * Nombre de recommandations par sévérité.
     *
     * @return array<int, array{severity: string, label: string, total: int}>
     */
    public function recommendationsBySeverity(DashboardFilters $filters): array
    {
        $rows = $this->recommendationsQuery($filters)
            ->join('rules', 'rules.id', '=', 'recommendations.rule_id')
            ->groupBy('rules.severity')
            ->select('rules.severity', DB::raw('count(*) as total'))
            ->pluck('total', 'severity');

        return array_map(
            static fn (Severity $severity): array => [
                'severity' => $severity->value,
                'label' => $severity->label(),
                'total' => (int) ($rows[$severity->value] ?? 0),
            ],
            Severity::cases(),
        );
    }

    /**
     * Part des recommandations appliquées sur celles dont le statut n'est
     * plus « nouvelle » (SPEC section 12).
     *
     * Le dénominateur est celui des recommandations auxquelles le
     * producteur a répondu d'une manière ou d'une autre : compter les
     * nouvelles ferait baisser le taux à chaque calcul du moteur.
     *
     * @return array{applied: int, answered: int, rate: float|null}
     */
    public function appliedShare(DashboardFilters $filters): array
    {
        /** @var object{applied: int|string|null, answered: int|string|null} $row */
        $row = $this->recommendationsQuery($filters)
            ->selectRaw(
                'count(*) filter (where status = ?) as applied,'
                .' count(*) filter (where status <> ?) as answered',
                [RecoStatus::Applied->value, RecoStatus::New->value],
            )
            ->first();

        $applied = (int) ($row->applied ?? 0);
        $answered = (int) ($row->answered ?? 0);

        return [
            'applied' => $applied,
            'answered' => $answered,
            // NULLIF plutôt qu'un test en PHP : sans réponse, il n'y a
            // pas de part à afficher, et surtout pas un zéro.
            'rate' => $answered === 0 ? null : round($applied / $answered, 4),
        ];
    }

    /**
     * Parcelles dont le rendement passe sous la médiane de leur zone.
     *
     * Les cycles dont la zone compte moins de cinq parcelles comparables
     * sont exclus : leur rapport n'a pas été calculé (SPEC section 9.3).
     *
     * @return array{below: int, comparable: int}
     */
    public function plotsBelowZoneMedian(DashboardFilters $filters): array
    {
        /** @var object{below: int|string|null, comparable: int|string|null} $row */
        $row = $this->indicatorsQuery($filters)
            ->where('indicators.zone_plot_count', '>=', IndicatorService::MINIMUM_ZONE_SIZE)
            ->selectRaw(
                'count(*) filter (where indicators.yield_vs_zone_median < 1) as below,'
                .' count(*) as comparable',
            )
            ->first();

        return [
            'below' => (int) ($row->below ?? 0),
            'comparable' => (int) ($row->comparable ?? 0),
        ];
    }

    /**
     * Les quatre nombres de tête.
     *
     * @return array{cycles: int, yield_kg_ha: float|null, production_cost_ha: float|null, gross_margin_ha: float|null}
     */
    public function headline(DashboardFilters $filters): array
    {
        /** @var object{cycles: int|string|null, yield_kg_ha: string|float|null, production_cost_ha: string|float|null, gross_margin_ha: string|float|null} $row */
        $row = $this->indicatorsQuery($filters)
            ->selectRaw(
                'count(*) as cycles,'
                .' avg(indicators.yield_kg_ha) as yield_kg_ha,'
                .' avg(indicators.production_cost_ha) as production_cost_ha,'
                .' avg(indicators.gross_margin_ha) as gross_margin_ha',
            )
            ->first();

        return [
            'cycles' => (int) ($row->cycles ?? 0),
            'yield_kg_ha' => $this->round($row->yield_kg_ha),
            'production_cost_ha' => $this->round($row->production_cost_ha),
            'gross_margin_ha' => $this->round($row->gross_margin_ha),
        ];
    }

    /**
     * La synthèse de campagne, telle que l'écran l'affiche et telle que
     * l'export la reprend, ligne pour ligne (SPEC section 12).
     *
     * @return array<int, array{grouping: string, label: string, cycles: int, yield_kg_ha: float|null, production_cost_ha: float|null, gross_margin_ha: float|null}>
     */
    public function summary(DashboardFilters $filters): array
    {
        $rows = [];

        foreach (Grouping::cases() as $grouping) {
            foreach ($this->averagesGroupedBy($filters, $grouping) as $line) {
                $rows[] = ['grouping' => $grouping->label(), ...$line];
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array{label: string, cycles: int, yield_kg_ha: float|null, production_cost_ha: float|null, gross_margin_ha: float|null}>
     */
    private function averagesGroupedBy(DashboardFilters $filters, Grouping $grouping): array
    {
        $column = $grouping->column();

        return $this->indicatorsQuery($filters)
            ->groupBy($column)
            ->orderBy($column)
            ->select($column.' as label')
            ->selectRaw(
                'count(*) as cycles,'
                .' avg(indicators.yield_kg_ha) as yield_kg_ha,'
                .' avg(indicators.production_cost_ha) as production_cost_ha,'
                .' avg(indicators.gross_margin_ha) as gross_margin_ha',
            )
            ->get()
            ->map(fn (object $row): array => [
                'label' => (string) $row->label,
                'cycles' => (int) $row->cycles,
                'yield_kg_ha' => $this->round($row->yield_kg_ha),
                'production_cost_ha' => $this->round($row->production_cost_ha),
                'gross_margin_ha' => $this->round($row->gross_margin_ha),
            ])
            ->all();
    }

    /**
     * Les indicateurs joints à ce qui les qualifie, filtres appliqués.
     *
     * Un cycle abandonné ne dit rien de la conduite d'une culture : il
     * sort de toutes les moyennes (SPEC section 9.3).
     */
    private function indicatorsQuery(DashboardFilters $filters): QueryBuilder
    {
        return Indicator::query()
            ->join('crop_cycles', 'crop_cycles.id', '=', 'indicators.crop_cycle_id')
            ->join('crops', 'crops.id', '=', 'crop_cycles.crop_id')
            ->join('campaigns', 'campaigns.id', '=', 'crop_cycles.campaign_id')
            ->join('plots', 'plots.id', '=', 'crop_cycles.plot_id')
            ->join('farmers', 'farmers.id', '=', 'plots.farmer_id')
            ->where('crop_cycles.status', '<>', CycleStatus::Abandoned->value)
            ->when(
                $filters->campaignId !== null,
                fn (Builder $query) => $query->where('crop_cycles.campaign_id', $filters->campaignId),
            )
            ->when(
                $filters->cropId !== null,
                fn (Builder $query) => $query->where('crop_cycles.crop_id', $filters->cropId),
            )
            ->toBase();
    }

    private function recommendationsQuery(DashboardFilters $filters): QueryBuilder
    {
        return Recommendation::query()
            ->when(
                $filters->campaignId !== null || $filters->cropId !== null,
                fn (Builder $query) => $query->whereIn(
                    'recommendations.crop_cycle_id',
                    CropCycle::query()
                        ->when(
                            $filters->campaignId !== null,
                            fn (Builder $cycles) => $cycles->where('campaign_id', $filters->campaignId),
                        )
                        ->when(
                            $filters->cropId !== null,
                            fn (Builder $cycles) => $cycles->where('crop_id', $filters->cropId),
                        )
                        ->select('id'),
                ),
            )
            ->toBase();
    }

    private function round(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }
}
