<?php

use App\Models\Campaign;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Indicator;
use App\Models\Plot;
use App\Models\Recommendation;
use App\Services\Indicators\IndicatorService;
use Database\Seeders\CropSeeder;
use Database\Seeders\RuleSeeder;
use Database\Seeders\VarietySeeder;
use Database\Seeders\VerificationDatasetSeeder;

/*
| SPEC-CORRECTION section 10.1 : le jeu d'épreuve du mémoire.
|
| Ce test est la vérification de bout en bout de la chaîne de calcul,
| sur le dossier que le mémoire décrit : deux parcelles de tomate, sans
| parcelle de contexte. Le dossier est une construction, pas un relevé ;
| il prouve que les opérations et les récoltes saisies produisent bien
| les indicateurs publiés, et que le moteur en tire exactement ce que le
| mémoire annonce.
*/

beforeEach(function () {
    $this->seed(CropSeeder::class);
    $this->seed(VarietySeeder::class);
    $this->seed(RuleSeeder::class);

    $this->seeder = app(VerificationDatasetSeeder::class);
    $this->seeder->run();

    $this->cycles = $this->seeder->cycles();
});

test('the structure is the one the dissertation describes', function () {
    // Une exploitation, trois producteurs du même village, deux
    // parcelles de tomate, deux campagnes, plusieurs passages de récolte
    // par parcelle.
    expect(Exploitation::count())->toBe(1)
        ->and(Farmer::withoutGlobalScopes()->count())->toBe(3)
        ->and(Farmer::withoutGlobalScopes()->pluck('village')->unique()->all())
        ->toBe([VerificationDatasetSeeder::VILLAGE])
        ->and(Plot::withoutGlobalScopes()->count())->toBe(2)
        ->and(Campaign::withoutGlobalScopes()->count())->toBe(2)
        ->and(CropCycle::withoutGlobalScopes()->count())->toBe(2);

    foreach ($this->cycles as $cycle) {
        expect($cycle->harvests()->count())->toBeGreaterThan(1);
    }
});

test('the two plots reproduce the published indicators exactly', function (string $role) {
    $expected = VerificationDatasetSeeder::EXPECTED_INDICATORS[$role];

    $indicator = Indicator::withoutGlobalScopes()
        ->where('crop_cycle_id', $this->cycles[$role]->id)
        ->sole();

    expect(round((float) $indicator->yield_kg_ha, 1))
        ->toBe($expected['yield_kg_ha'], "rendement de la parcelle {$role}")
        ->and(round((float) $indicator->production_cost_ha, 1))
        ->toBe($expected['production_cost_ha'], "coût de la parcelle {$role}")
        ->and(round((float) $indicator->gross_margin_ha, 1))
        ->toBe($expected['gross_margin_ha'], "marge de la parcelle {$role}");
})->with(['écart', 'conforme']);

test('recomputing the indicators does not move them', function () {
    $before = Indicator::withoutGlobalScopes()->orderBy('crop_cycle_id')
        ->get(['crop_cycle_id', 'yield_kg_ha', 'production_cost_ha', 'gross_margin_ha'])
        ->map(fn (Indicator $indicator): array => $indicator->only([
            'crop_cycle_id', 'yield_kg_ha', 'production_cost_ha', 'gross_margin_ha',
        ]))
        ->all();

    foreach ($this->cycles as $cycle) {
        app(IndicatorService::class)->computeFor($cycle->fresh());
    }

    $after = Indicator::withoutGlobalScopes()->orderBy('crop_cycle_id')
        ->get(['crop_cycle_id', 'yield_kg_ha', 'production_cost_ha', 'gross_margin_ha'])
        ->map(fn (Indicator $indicator): array => $indicator->only([
            'crop_cycle_id', 'yield_kg_ha', 'production_cost_ha', 'gross_margin_ha',
        ]))
        ->all();

    expect($after)->toBe($before);
});

/*
| Les trois assertions de la section 10.1. Le tableau 6 du mémoire liste
| les règles dont les conditions sont vraies, établies par une lecture
| directe des conditions ; ce n'est pas la sortie du moteur. À
| l'exécution, la zone du jeu d'épreuve ne contient que deux parcelles
| de tomate, la famille C est suspendue, et TOM-C01 n'est pas évaluée :
| quatre recommandations sortent, pas cinq. La démonstration que TOM-C01
| n'est pas lettre morte appartient à RuleEvaluatorTest, sur un contexte
| construit à la main, hors du garde-fou de zone.
*/

test('the engine draws four recommendations on the deviant plot, with their codes', function () {
    $codes = Recommendation::withoutGlobalScopes()
        ->where('crop_cycle_id', $this->cycles['écart']->id)
        ->with('rule')
        ->get()
        ->map(fn (Recommendation $recommendation): string => $recommendation->rule->code)
        ->sort()
        ->values()
        ->all();

    expect($codes)->toBe(VerificationDatasetSeeder::EXPECTED_CODES_ON_DEVIANT_PLOT);
});

test('the engine stays silent on the conforming plot', function () {
    expect(
        Recommendation::withoutGlobalScopes()
            ->where('crop_cycle_id', $this->cycles['conforme']->id)
            ->count(),
    )->toBe(0);
});

test('TOM-C01 is suspended: the zone counts fewer than five comparable plots', function () {
    // La zone du jeu d'épreuve ne contient que deux parcelles de tomate,
    // sous le seuil de cinq né de cette observation : la famille C n'est
    // pas évaluée, et TOM-C01 n'apparaît sur aucune des deux parcelles.
    $indicator = Indicator::withoutGlobalScopes()
        ->where('crop_cycle_id', $this->cycles['écart']->id)
        ->sole();

    $cycleIds = collect($this->cycles)->pluck('id')->all();

    expect($indicator->zone_plot_count)->toBe(2)
        ->and($indicator->zone_plot_count)->toBeLessThan(IndicatorService::MINIMUM_ZONE_SIZE)
        // Le mécanisme que le mémoire décrit : la médiane est calculée,
        // le rapport est retenu, la règle est suspendue.
        ->and($indicator->zone_median_yield_kg_ha)->not->toBeNull()
        ->and($indicator->yield_vs_zone_median)->toBeNull()
        ->and(
            Recommendation::withoutGlobalScopes()
                ->whereIn('crop_cycle_id', $cycleIds)
                ->whereHas('rule', fn ($query) => $query->where('code', 'TOM-C01'))
                ->count(),
        )->toBe(0);
});

test('the dataset says of itself that it is a construction', function () {
    $source = file_get_contents(database_path('seeders/VerificationDatasetSeeder.php'));

    expect($source)->toContain('CONSTRUCTION, PAS UN RELEVÉ')
        ->and($source)->toContain('ordres de grandeur de la fiche togolaise')
        ->and(Exploitation::first()->name)->toContain('construction');
});
