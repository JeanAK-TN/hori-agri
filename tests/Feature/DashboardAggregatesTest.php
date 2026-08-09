<?php

use App\Enums\RecoStatus;
use App\Enums\Severity;
use App\Models\Campaign;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Recommendation;
use App\Models\Rule;
use App\Models\User;
use App\Services\Dashboard\DashboardFilters;
use App\Services\Dashboard\DashboardService;
use App\Services\Indicators\IndicatorService;
use Illuminate\Support\Facades\DB;

/*
| SPEC section 12 : les agrégats sont calculés côté base, jamais en
| mémoire PHP sur des collections complètes. Chaque assertion porte sur
| une valeur arithmétiquement vérifiable à la main.
*/

beforeEach(function () {
    $this->cooperative = Cooperative::factory()->create();
    $this->admin = User::factory()->coopAdmin()->for($this->cooperative)->create();

    $this->maize = Crop::factory()->named('Maïs')->create();
    $this->soy = Crop::factory()->named('Soja')->create();

    $this->campaign = Campaign::factory()->for($this->cooperative)
        ->between('2026-03-01', '2026-11-30')->create(['label' => 'Campagne 2026']);

    $this->dashboard = app(DashboardService::class);
    $this->indicators = app(IndicatorService::class);

    $this->actingAs($this->admin);
});

test('the averages by crop are computed by the database, one row per crop', function () {
    // Maïs : 1 000 et 2 000 kg/ha → moyenne 1 500.
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 2000, 70000);
    // Soja : 800 kg/ha.
    cycleYielding($this->cooperative, $this->soy, $this->campaign, 'Bassar', 1.0, 800, 40000);

    $rows = $this->dashboard->averagesByCrop(new DashboardFilters);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['label'])->toBe('Maïs')
        ->and($rows[0]['cycles'])->toBe(2)
        ->and($rows[0]['yield_kg_ha'])->toBe(1500.0)
        ->and($rows[0]['production_cost_ha'])->toBe(60000.0)
        ->and($rows[1]['label'])->toBe('Soja')
        ->and($rows[1]['yield_kg_ha'])->toBe(800.0);
});

test('the averages by village group the producers of one place', function () {
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1400, 50000);
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Kara', 1.0, 600, 50000);

    $rows = $this->dashboard->averagesByVillage(new DashboardFilters);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['label'])->toBe('Bassar')
        ->and($rows[0]['yield_kg_ha'])->toBe(1200.0)
        ->and($rows[1]['label'])->toBe('Kara')
        ->and($rows[1]['yield_kg_ha'])->toBe(600.0);
});

test('the averages by campaign separate the seasons', function () {
    $previous = Campaign::factory()->for($this->cooperative)
        ->between('2025-03-01', '2025-11-30')->create(['label' => 'Campagne 2025']);

    cycleYielding($this->cooperative, $this->maize, $previous, 'Bassar', 1.0, 900, 40000);
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1500, 60000);

    $rows = $this->dashboard->averagesByCampaign(new DashboardFilters);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['label'])->toBe('Campagne 2025')
        ->and($rows[0]['yield_kg_ha'])->toBe(900.0)
        ->and($rows[1]['label'])->toBe('Campagne 2026')
        ->and($rows[1]['yield_kg_ha'])->toBe(1500.0);
});

test('the gross margin follows the sale price of the harvest', function () {
    // 1 000 kg à 100 FCFA = 100 000 de valeur, moins 40 000 de coût.
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 40000);

    expect($this->dashboard->headline(new DashboardFilters)['gross_margin_ha'])->toBe(60000.0);
});

test('an abandoned cycle says nothing and leaves every average', function () {
    $kept = cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);
    $given = cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 3000, 50000);

    $given->abandon();

    $rows = $this->dashboard->averagesByCrop(new DashboardFilters);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['cycles'])->toBe(1)
        ->and($rows[0]['yield_kg_ha'])->toBe(1000.0)
        ->and($kept->fresh()->id)->not->toBeNull();
});

test('the filters narrow the averages the same way everywhere', function () {
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);
    cycleYielding($this->cooperative, $this->soy, $this->campaign, 'Bassar', 1.0, 400, 30000);

    $onlyMaize = new DashboardFilters(cropId: $this->maize->id);

    expect($this->dashboard->averagesByCrop($onlyMaize))->toHaveCount(1)
        ->and($this->dashboard->headline($onlyMaize)['cycles'])->toBe(1)
        ->and($this->dashboard->headline($onlyMaize)['yield_kg_ha'])->toBe(1000.0);
});

/*
| Les recommandations.
*/

test('the recommendations are counted by severity, zeros included', function () {
    $cycle = cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);

    foreach ([Severity::High, Severity::High, Severity::Medium] as $index => $severity) {
        Recommendation::factory()
            ->forCycle($cycle)
            ->fromRule(Rule::factory()->code('R-'.$index)->severity($severity)->create())
            ->create();
    }

    $rows = collect($this->dashboard->recommendationsBySeverity(new DashboardFilters))
        ->keyBy('severity');

    expect($rows['high']['total'])->toBe(2)
        ->and($rows['medium']['total'])->toBe(1)
        // Une sévérité sans recommandation vaut zéro et reste affichée.
        ->and($rows['low']['total'])->toBe(0)
        ->and($rows['low']['label'])->toBe('Faible');
});

test('the applied share counts only the recommendations answered', function () {
    $cycle = cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);

    $statuses = [
        RecoStatus::Applied,
        RecoStatus::Applied,
        RecoStatus::Dismissed,
        RecoStatus::Read,
        // Une nouvelle n'entre ni au numérateur ni au dénominateur.
        RecoStatus::New,
    ];

    foreach ($statuses as $index => $status) {
        Recommendation::factory()
            ->forCycle($cycle)
            ->fromRule(Rule::factory()->code('S-'.$index)->create())
            ->status($status)
            ->create();
    }

    $share = $this->dashboard->appliedShare(new DashboardFilters);

    expect($share['applied'])->toBe(2)
        ->and($share['answered'])->toBe(4)
        ->and($share['rate'])->toBe(0.5);
});

test('with nothing answered the share stays undefined rather than zero', function () {
    $cycle = cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);

    Recommendation::factory()
        ->forCycle($cycle)
        ->fromRule(Rule::factory()->create())
        ->create();

    $share = $this->dashboard->appliedShare(new DashboardFilters);

    expect($share['answered'])->toBe(0)
        ->and($share['rate'])->toBeNull();
});

/*
| Les parcelles sous la médiane de leur zone.
*/

test('the plots below the zone median exclude the zones under five cycles', function () {
    // Quatre cycles seulement : la zone n'est pas comparable, aucun
    // rapport n'a été calculé (SPEC section 9.3).
    foreach ([600, 800, 1000, 1200] as $harvested) {
        cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, $harvested, 50000);
    }

    expect($this->dashboard->plotsBelowZoneMedian(new DashboardFilters))
        ->toBe(['below' => 0, 'comparable' => 0]);
});

test('from five comparable cycles the plots below the median are counted', function () {
    // Rendements 600, 800, 1 000, 1 200, 1 400 : médiane 1 000, deux
    // cycles en dessous.
    foreach ([600, 800, 1000, 1200, 1400] as $harvested) {
        $cycle = cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, $harvested, 50000);
    }

    // Les zones se recalculent au fil des ajouts : on repasse sur tous
    // les cycles une fois la cinquième récolte enregistrée.
    CropCycle::each(fn (CropCycle $cycle) => $this->indicators->computeFor($cycle));

    expect($this->dashboard->plotsBelowZoneMedian(new DashboardFilters))
        ->toBe(['below' => 2, 'comparable' => 5])
        ->and($cycle->fresh()->id)->not->toBeNull();
});

/*
| Ce que la spécification interdit : ramener les collections en mémoire.
*/

test('every aggregate is a query, never a collection walked in PHP', function () {
    foreach (range(1, 6) as $index) {
        cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 500 * $index, 50000);
    }

    $rowsRead = 0;

    DB::listen(function ($query) use (&$rowsRead): void {
        $rowsRead++;
    });

    $filters = new DashboardFilters;

    $this->dashboard->headline($filters);
    $this->dashboard->averagesByCrop($filters);
    $this->dashboard->averagesByVillage($filters);
    $this->dashboard->averagesByCampaign($filters);
    $this->dashboard->recommendationsBySeverity($filters);
    $this->dashboard->appliedShare($filters);
    $this->dashboard->plotsBelowZoneMedian($filters);

    // Sept agrégats, sept requêtes : aucune n'a ramené les six cycles
    // pour les parcourir.
    expect($rowsRead)->toBe(7);
});

test('the aggregates of a cooperative stop at its own border', function () {
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);

    $otherCooperative = Cooperative::factory()->create();
    $otherCampaign = Campaign::factory()->for($otherCooperative)
        ->between('2026-03-01', '2026-11-30')->create();

    cycleYielding($otherCooperative, $this->maize, $otherCampaign, 'Bassar', 1.0, 9000, 50000);

    $this->actingAs($this->admin);

    expect($this->dashboard->headline(new DashboardFilters))
        ->toMatchArray(['cycles' => 1, 'yield_kg_ha' => 1000.0]);
});
