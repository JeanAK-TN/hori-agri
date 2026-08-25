<?php

use App\Enums\CycleStatus;
use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\QuantityUnit;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Operation;
use App\Models\Plot;
use App\Services\Indicators\IndicatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| SPEC section 9.2 et 9.3 : les dix indicateurs, la zone de comparaison
| et le seuil de cinq parcelles.
*/

beforeEach(function () {
    $this->service = new IndicatorService;
    $this->exploitation = Exploitation::factory()->create();
    $this->crop = Crop::factory()->named('Tomate')->create();
    $this->campaign = Campaign::factory()
        ->for($this->exploitation)
        ->between('2026-03-01', '2026-11-30')
        ->create();
});

/**
 * Ouvre un cycle sur une parcelle d'une surface donnée, dans un village.
 */
function cycleOf(
    Exploitation $exploitation,
    Crop $crop,
    Campaign $campaign,
    float $areaHa,
    string $village = 'Bassar',
    CycleStatus $status = CycleStatus::InProgress,
): CropCycle {
    $farmer = Farmer::factory()->for($exploitation)->inVillage($village)->create();
    $plot = Plot::factory()->ofFarmer($farmer)->withArea($areaHa)->create();

    return CropCycle::factory()->on($plot, $campaign, $crop)->status($status)->create();
}

test('the ten indicators are computed on a cycle with a single harvest', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 0.75);

    // 675 kg sur 0,75 ha = 900 kg/ha, vendus 200 FCFA le kilogramme.
    Harvest::factory()->forCycle($cycle)->of(675, 200)->create();

    // 73 000 FCFA de coûts dont 40 000 d'intrants.
    Operation::factory()->forCycle($cycle)->costing(33000)->create([
        'type' => OperationType::Tillage,
    ]);
    Operation::factory()->forCycle($cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 2, QuantityUnit::Bag50Kg, 40000)
        ->create();

    $indicator = $this->service->computeFor($cycle);

    expect((float) $indicator->yield_kg_ha)->toBe(900.0)
        // 73 000 / 0,75
        ->and((float) $indicator->production_cost_ha)->toBe(97333.33)
        // (675 × 200 − 73 000) / 0,75
        ->and((float) $indicator->gross_margin_ha)->toBe(82666.67)
        // 40 000 / 135 000
        ->and((float) $indicator->input_cost_share)->toBe(0.296)
        ->and($indicator->zone_plot_count)->toBe(1)
        ->and($indicator->yield_vs_zone_median)->toBeNull()
        ->and($indicator->cost_vs_zone_median)->toBeNull()
        ->and($indicator->computed_at)->not->toBeNull()
        ->and($indicator->exploitation_id)->toBe($this->exploitation->id);
});

test('several harvests of one cycle are summed', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.20);

    // Deux récoltes échelonnées : 6 000 puis 4 800 kg sur 1,20 ha.
    Harvest::factory()->forCycle($cycle)->of(6000, 100)->on('2026-07-01')->create();
    Harvest::factory()->forCycle($cycle)->of(4800, 100)->on('2026-08-15')->create();

    Operation::factory()->forCycle($cycle)->costing(96000)->create();

    $indicator = $this->service->computeFor($cycle);

    expect((float) $indicator->yield_kg_ha)->toBe(9000.0)
        ->and((float) $indicator->production_cost_ha)->toBe(80000.0)
        // (10 800 × 100 − 96 000) / 1,20
        ->and((float) $indicator->gross_margin_ha)->toBe(820000.0);
});

test('an operation with no cost counts as zero', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);

    Harvest::factory()->forCycle($cycle)->of(1000, 100)->create();
    Operation::factory()->forCycle($cycle)->costing(50000)->create();
    Operation::factory()->forCycle($cycle)->create(['cost' => null]);

    $indicator = $this->service->computeFor($cycle);

    expect((float) $indicator->production_cost_ha)->toBe(50000.0);
});

test('a missing unit price leaves the margin and the input share null', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);

    Harvest::factory()->forCycle($cycle)->of(1000, 100)->create();
    // Une seule récolte sans prix suffit à rendre la valeur incalculable.
    Harvest::factory()->forCycle($cycle)->of(500, null)->on('2026-09-01')->create();

    Operation::factory()->forCycle($cycle)->costing(50000)->create();

    $indicator = $this->service->computeFor($cycle);

    expect((float) $indicator->yield_kg_ha)->toBe(1500.0)
        ->and((float) $indicator->production_cost_ha)->toBe(50000.0)
        ->and($indicator->gross_margin_ha)->toBeNull()
        ->and($indicator->input_cost_share)->toBeNull();
});

test('a cycle without any harvest carries no indicators', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);

    Operation::factory()->forCycle($cycle)->costing(50000)->create();

    expect($this->service->computeFor($cycle))->toBeNull()
        ->and($cycle->indicator()->exists())->toBeFalse();
});

test('losing the last harvest removes indicators that no longer mean anything', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);
    $harvest = Harvest::factory()->forCycle($cycle)->of(1000, 100)->create();

    $this->service->computeFor($cycle);

    expect($cycle->indicator()->exists())->toBeTrue();

    $harvest->delete();

    expect($this->service->computeFor($cycle->fresh()))->toBeNull()
        ->and($cycle->indicator()->exists())->toBeFalse();
});

/*
| Zone de comparaison (SPEC section 9.3).
*/

test('below five comparable cycles the count is filled but both ratios stay null', function (int $peers) {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);
    Harvest::factory()->forCycle($cycle)->of(1000, 100)->create();

    for ($i = 0; $i < $peers; $i++) {
        $peer = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);
        Harvest::factory()->forCycle($peer)->of(500 + $i * 100, 100)->create();
    }

    $indicator = $this->service->computeFor($cycle);

    expect($indicator->zone_plot_count)->toBe($peers + 1)
        ->and($indicator->zone_median_yield_kg_ha)->not->toBeNull()
        ->and($indicator->yield_vs_zone_median)->toBeNull()
        ->and($indicator->cost_vs_zone_median)->toBeNull();
})->with([
    'seul' => 0,
    'deux cycles' => 1,
    'trois cycles' => 2,
    'quatre cycles' => 3,
]);

test('from five comparable cycles the ratios are computed', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);
    Harvest::factory()->forCycle($cycle)->of(1200, 100)->create();
    Operation::factory()->forCycle($cycle)->costing(60000)->create();

    // Quatre pairs de 800, 900, 1 000 et 1 100 kg/ha, coûts de 100 000.
    foreach ([800, 900, 1000, 1100] as $yield) {
        $peer = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);
        Harvest::factory()->forCycle($peer)->of($yield, 100)->create();
        Operation::factory()->forCycle($peer)->costing(100000)->create();
    }

    $indicator = $this->service->computeFor($cycle);

    // Cinq valeurs : 800, 900, 1 000, 1 100, 1 200 → médiane 1 000.
    expect($indicator->zone_plot_count)->toBe(5)
        ->and((float) $indicator->zone_median_yield_kg_ha)->toBe(1000.0)
        ->and((float) $indicator->yield_vs_zone_median)->toBe(1.2)
        ->and((float) $indicator->zone_median_cost_ha)->toBe(100000.0)
        ->and((float) $indicator->cost_vs_zone_median)->toBe(0.6);
});

test('the median of an even number of values is the mean of the two middle ones', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);
    Harvest::factory()->forCycle($cycle)->of(1000, 100)->create();

    // Avec le cycle évalué : 700, 800, 900, 1 000, 1 100, 1 200.
    foreach ([700, 800, 900, 1100, 1200] as $yield) {
        $peer = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);
        Harvest::factory()->forCycle($peer)->of($yield, 100)->create();
    }

    $indicator = $this->service->computeFor($cycle);

    // Six valeurs : moyenne de 900 et 1 000.
    expect($indicator->zone_plot_count)->toBe(6)
        ->and((float) $indicator->zone_median_yield_kg_ha)->toBe(950.0);
});

test('the fallback from the village to the exploitation fires below five', function () {
    // Trois cycles à Bassar, trois autres ailleurs dans l'exploitation.
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0, 'Bassar');
    Harvest::factory()->forCycle($cycle)->of(1000, 100)->create();

    foreach ([900, 1100] as $yield) {
        $peer = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0, 'Bassar');
        Harvest::factory()->forCycle($peer)->of($yield, 100)->create();
    }

    foreach ([700, 800, 1200] as $yield) {
        $peer = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0, 'Kandé');
        Harvest::factory()->forCycle($peer)->of($yield, 100)->create();
    }

    $indicator = $this->service->computeFor($cycle);

    // Trois cycles à Bassar, c'est moins de cinq : l'ensemble est
    // reconstitué à l'échelle de l'exploitation, soit six cycles.
    expect($indicator->zone_plot_count)->toBe(6);
});

test('the village zone is kept as soon as it reaches five', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0, 'Bassar');
    Harvest::factory()->forCycle($cycle)->of(1000, 100)->create();

    foreach ([800, 900, 1100, 1200] as $yield) {
        $peer = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0, 'Bassar');
        Harvest::factory()->forCycle($peer)->of($yield, 100)->create();
    }

    // Ces cycles d'un autre village ne doivent pas entrer dans la zone.
    foreach ([100, 200] as $yield) {
        $peer = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0, 'Kandé');
        Harvest::factory()->forCycle($peer)->of($yield, 100)->create();
    }

    $indicator = $this->service->computeFor($cycle);

    expect($indicator->zone_plot_count)->toBe(5)
        ->and((float) $indicator->zone_median_yield_kg_ha)->toBe(1000.0);
});

test('a given-up cycle never enters the medians', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);
    Harvest::factory()->forCycle($cycle)->of(1000, 100)->create();

    foreach ([800, 900, 1100, 1200] as $yield) {
        $peer = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);
        Harvest::factory()->forCycle($peer)->of($yield, 100)->create();
    }

    $abandoned = cycleOf(
        $this->exploitation,
        $this->crop,
        $this->campaign,
        1.0,
        status: CycleStatus::Abandoned,
    );
    Harvest::factory()->forCycle($abandoned)->of(50, 100)->create();

    $indicator = $this->service->computeFor($cycle);

    expect($indicator->zone_plot_count)->toBe(5)
        ->and((float) $indicator->zone_median_yield_kg_ha)->toBe(1000.0);
});

test('a cycle without harvest never enters the zone', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);
    Harvest::factory()->forCycle($cycle)->of(1000, 100)->create();

    cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);

    expect($this->service->computeFor($cycle)->zone_plot_count)->toBe(1);
});

test('the zone is restricted to the same crop, campaign and exploitation', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);
    Harvest::factory()->forCycle($cycle)->of(1000, 100)->create();

    // Une autre culture.
    $otherCrop = cycleOf(
        $this->exploitation,
        Crop::factory()->named('Gombo')->create(),
        $this->campaign,
        1.0,
    );
    Harvest::factory()->forCycle($otherCrop)->of(500, 100)->create();

    // Une autre campagne.
    $otherCampaign = cycleOf(
        $this->exploitation,
        $this->crop,
        Campaign::factory()->for($this->exploitation)->create(),
        1.0,
    );
    Harvest::factory()->forCycle($otherCampaign)->of(500, 100)->create();

    // Une autre exploitation.
    $foreignExploitation = Exploitation::factory()->create();
    $foreign = cycleOf(
        $foreignExploitation,
        $this->crop,
        Campaign::factory()->for($foreignExploitation)->create(),
        1.0,
    );
    Harvest::factory()->forCycle($foreign)->of(500, 100)->create();

    expect($this->service->computeFor($cycle)->zone_plot_count)->toBe(1);
});

test('recomputing replaces the previous set rather than adding one', function () {
    $cycle = cycleOf($this->exploitation, $this->crop, $this->campaign, 1.0);
    Harvest::factory()->forCycle($cycle)->of(1000, 100)->create();

    $first = $this->service->computeFor($cycle);
    $second = $this->service->computeFor($cycle->fresh());

    expect($second->id)->toBe($first->id)
        ->and($cycle->indicator()->count())->toBe(1);
});
