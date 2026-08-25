<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Crop;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * Le tableau de bord de l'exploitation (SPEC section 12).
     */
    public function index(Request $request): Response
    {
        $filters = DashboardFilters::fromRequest($request);

        return Inertia::render('tableau-de-bord/index', [
            'headline' => $this->dashboard->headline($filters),
            'byCrop' => $this->dashboard->averagesByCrop($filters),
            'byVillage' => $this->dashboard->averagesByVillage($filters),
            'byCampaign' => $this->dashboard->averagesByCampaign($filters),
            'bySeverity' => $this->dashboard->recommendationsBySeverity($filters),
            'appliedShare' => $this->dashboard->appliedShare($filters),
            'belowZoneMedian' => $this->dashboard->plotsBelowZoneMedian($filters),
            'filters' => $filters->toArray(),
            'campaigns' => $this->campaignOptions(),
            'crops' => $this->cropOptions(),
        ]);
    }

    /**
     * L'export reprend exactement ce que l'écran affiche : mêmes
     * méthodes du service, mêmes filtres (SPEC section 12).
     *
     * Séparateur point-virgule et BOM UTF-8, comme le fichier d'import :
     * c'est ce qu'attend un tableur configuré en français.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = DashboardFilters::fromRequest($request);
        $rows = $this->dashboard->summary($filters);

        $name = 'synthese-campagne.csv';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Regroupement',
                'Libellé',
                'Cycles',
                'Rendement moyen (kg/ha)',
                'Coût moyen (FCFA/ha)',
                'Marge brute moyenne (FCFA/ha)',
            ], ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['grouping'],
                    $row['label'],
                    $row['cycles'],
                    $this->number($row['yield_kg_ha']),
                    $this->number($row['production_cost_ha']),
                    $this->number($row['gross_margin_ha']),
                ], ';', '"', '');
            }

            fclose($handle);
        }, $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * La virgule décimale, celle qu'un tableur français attend. Une
     * valeur incalculable reste vide plutôt que de devenir un zéro.
     */
    private function number(?float $value): string
    {
        return $value === null ? '' : str_replace('.', ',', (string) $value);
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
