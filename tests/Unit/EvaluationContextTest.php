<?php

use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\QuantityUnit;
use App\Models\Campaign;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Operation;
use App\Models\Plot;
use App\Services\Indicators\IndicatorService;
use App\Services\Rules\EvaluationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Les quinze champs se dérivent d'un cycle complet (SPEC section 14.1).
*/

beforeEach(function () {
    $this->cooperative = Cooperative::factory()->create(['region' => 'Kara']);
    $this->crop = Crop::factory()->named('Maïs')->create();
    $this->campaign = Campaign::factory()
        ->for($this->cooperative)
        ->between('2026-03-01', '2026-11-30')
        ->create(['label' => '2026 grande saison']);

    $this->farmer = Farmer::factory()->for($this->cooperative)->inVillage('Bassar')->create();
    $this->plot = Plot::factory()->ofFarmer($this->farmer)->withArea(2.0)->create();

    $this->cycle = CropCycle::factory()
        ->on($this->plot, $this->campaign, $this->crop)
        ->create(['sowing_date' => '2026-04-15', 'density' => 50000]);
});

test('the fifteen fields derive from a complete cycle', function () {
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Sowing, InputType::Seed, 50, QuantityUnit::Kilogram)
        ->on('2026-04-15')->create();
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 4, QuantityUnit::Bag50Kg)
        ->on('2026-04-20')->create();
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Urea, 2, QuantityUnit::Bag50Kg)
        ->on('2026-05-25')->create();
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Sowing, InputType::Inoculant, 1, QuantityUnit::Kilogram)
        ->on('2026-04-15')->create();
    Operation::factory()->forCycle($this->cycle)
        ->on('2026-05-05')->create(['type' => OperationType::Weeding]);

    Harvest::factory()->forCycle($this->cycle)->of(2000, 100)->create();
    app(IndicatorService::class)->computeFor($this->cycle);

    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    expect($context->crop_name)->toBe('Maïs')
        ->and($context->region)->toBe('Kara')
        ->and($context->sowing_month)->toBe(4)
        ->and($context->sowing_density_plants_ha)->toBe(50000)
        // 50 kg sur 2 ha
        ->and($context->seed_rate_kg_ha)->toBe(25.0)
        // 4 sacs de 50 kg sur 2 ha
        ->and($context->npk_dose_kg_ha)->toBe(100.0)
        // 2 sacs de 50 kg sur 2 ha
        ->and($context->urea_dose_kg_ha)->toBe(50.0)
        ->and($context->potassium_dose_kg_ha)->toBeNull()
        ->and($context->inoculant_used)->toBeTrue()
        // du 15/04 au 05/05
        ->and($context->first_weeding_days_after_sowing)->toBe(20)
        // du 15/04 au 25/05
        ->and($context->urea_days_after_sowing)->toBe(40)
        ->and($context->consecutive_seasons_same_crop)->toBe(1)
        ->and($context->yield_vs_zone_median_ratio)->toBeNull()
        ->and($context->cost_vs_zone_median_ratio)->toBeNull()
        ->and($context->input_cost_share_of_harvest_value)->toBe(0.0);
});

test('a fifty kilogram bag counts as fifty kilograms', function () {
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 3, QuantityUnit::Bag50Kg)
        ->create();

    // 3 sacs de 50 kg, soit 150 kg, sur 2 ha.
    expect(EvaluationContext::fromCycle($this->cycle)->npk_dose_kg_ha)->toBe(75.0);
});

test('a twenty-five kilogram bag counts as twenty-five kilograms', function () {
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 4, QuantityUnit::Bag25Kg)
        ->create();

    expect(EvaluationContext::fromCycle($this->cycle)->npk_dose_kg_ha)->toBe(50.0);
});

test('an operation in litres never enters a dose in kilograms per hectare', function () {
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Treatment, InputType::Npk, 10, QuantityUnit::Litre)
        ->create();

    expect(EvaluationContext::fromCycle($this->cycle)->npk_dose_kg_ha)->toBeNull();
});

test('an operation in units never enters a dose either', function () {
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 3, QuantityUnit::Unit)
        ->create();

    expect(EvaluationContext::fromCycle($this->cycle)->npk_dose_kg_ha)->toBeNull();
});

test('an operation without quantity or without unit is ignored', function () {
    Operation::factory()->forCycle($this->cycle)->create([
        'type' => OperationType::Fertilization,
        'input_type' => InputType::Npk,
        'quantity' => null,
        'unit' => QuantityUnit::Kilogram,
    ]);
    Operation::factory()->forCycle($this->cycle)->create([
        'type' => OperationType::Fertilization,
        'input_type' => InputType::Npk,
        'quantity' => 40,
        'unit' => null,
    ]);

    expect(EvaluationContext::fromCycle($this->cycle)->npk_dose_kg_ha)->toBeNull();
});

test('convertible and non convertible quantities mix without contaminating each other', function () {
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 2, QuantityUnit::Bag50Kg)
        ->create();
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 10, QuantityUnit::Litre)
        ->on('2026-05-01')->create();

    // Seuls les 100 kg comptent, sur 2 ha.
    expect(EvaluationContext::fromCycle($this->cycle)->npk_dose_kg_ha)->toBe(50.0);
});

test('the seed rate only counts the seed brought at sowing', function () {
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Sowing, InputType::Seed, 40, QuantityUnit::Kilogram)
        ->create();
    // De la semence apportée hors du semis n'entre pas dans la dose.
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Treatment, InputType::Seed, 100, QuantityUnit::Kilogram)
        ->on('2026-05-01')->create();

    expect(EvaluationContext::fromCycle($this->cycle)->seed_rate_kg_ha)->toBe(20.0);
});

test('the first weeding is the earliest one', function () {
    Operation::factory()->forCycle($this->cycle)->on('2026-06-10')
        ->create(['type' => OperationType::Weeding]);
    Operation::factory()->forCycle($this->cycle)->on('2026-05-05')
        ->create(['type' => OperationType::Weeding]);

    expect(EvaluationContext::fromCycle($this->cycle)->first_weeding_days_after_sowing)->toBe(20);
});

test('a cycle without weeding leaves the field null rather than zero', function () {
    expect(EvaluationContext::fromCycle($this->cycle)->first_weeding_days_after_sowing)->toBeNull();
});

test('urea brought outside a fertilization does not count as the urea date', function () {
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Treatment, InputType::Urea, 1, QuantityUnit::Bag50Kg)
        ->on('2026-04-25')->create();

    expect(EvaluationContext::fromCycle($this->cycle)->urea_days_after_sowing)->toBeNull();
});

/*
| consecutive_seasons_same_crop (SPEC section 9.4).
*/

test('consecutive seasons count the campaigns carrying the same crop', function () {
    $earlier = Campaign::factory()->for($this->cooperative)
        ->between('2025-03-01', '2025-11-30')->create(['label' => '2025']);
    $earliest = Campaign::factory()->for($this->cooperative)
        ->between('2024-03-01', '2024-11-30')->create(['label' => '2024']);

    CropCycle::factory()->on($this->plot, $earlier, $this->crop)->create();
    CropCycle::factory()->on($this->plot, $earliest, $this->crop)->create();

    expect(EvaluationContext::fromCycle($this->cycle)->consecutive_seasons_same_crop)->toBe(3);
});

test('the count stops at the first campaign carrying another crop', function () {
    $earlier = Campaign::factory()->for($this->cooperative)
        ->between('2025-03-01', '2025-11-30')->create(['label' => '2025']);
    $earliest = Campaign::factory()->for($this->cooperative)
        ->between('2024-03-01', '2024-11-30')->create(['label' => '2024']);

    // La campagne intermédiaire a porté du soja : la suite s'arrête là,
    // même si la même culture revient plus tôt.
    CropCycle::factory()->on($this->plot, $earlier, Crop::factory()->named('Soja')->create())->create();
    CropCycle::factory()->on($this->plot, $earliest, $this->crop)->create();

    expect(EvaluationContext::fromCycle($this->cycle)->consecutive_seasons_same_crop)->toBe(1);
});

test('a campaign without any cycle on the plot breaks the run', function () {
    Campaign::factory()->for($this->cooperative)
        ->between('2025-03-01', '2025-11-30')->create(['label' => '2025']);

    expect(EvaluationContext::fromCycle($this->cycle)->consecutive_seasons_same_crop)->toBe(1);
});

test('the same crop on another plot does not extend the run', function () {
    $earlier = Campaign::factory()->for($this->cooperative)
        ->between('2025-03-01', '2025-11-30')->create(['label' => '2025']);
    $otherPlot = Plot::factory()->ofFarmer($this->farmer)->withArea(1.0)->create();

    CropCycle::factory()->on($otherPlot, $earlier, $this->crop)->create();

    expect(EvaluationContext::fromCycle($this->cycle)->consecutive_seasons_same_crop)->toBe(1);
});

/*
| Les trois champs venus des indicateurs.
*/

test('the three ratio fields are read from the indicators', function () {
    Harvest::factory()->forCycle($this->cycle)->of(2400, 100)->create();
    Operation::factory()->forCycle($this->cycle)->costing(100000)->create();

    foreach ([1600, 1800, 2000, 2200] as $yield) {
        $peerFarmer = Farmer::factory()->for($this->cooperative)->inVillage('Bassar')->create();
        $peerPlot = Plot::factory()->ofFarmer($peerFarmer)->withArea(1.0)->create();
        $peer = CropCycle::factory()->on($peerPlot, $this->campaign, $this->crop)->create();
        Harvest::factory()->forCycle($peer)->of($yield, 100)->create();
        Operation::factory()->forCycle($peer)->costing(50000)->create();
    }

    app(IndicatorService::class)->computeFor($this->cycle);

    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    // 2 400 kg sur 2 ha, soit 1 200 kg/ha, contre une médiane de 1 800.
    expect($context->yield_vs_zone_median_ratio)->toBe(0.667)
        ->and($context->cost_vs_zone_median_ratio)->toBe(1.0)
        // 100 000 de coûts d'intrants sur 240 000 de récolte
        ->and($context->input_cost_share_of_harvest_value)->toBe(0.0);
});

test('a cycle without indicators leaves the three ratios null', function () {
    $context = EvaluationContext::fromCycle($this->cycle);

    expect($context->yield_vs_zone_median_ratio)->toBeNull()
        ->and($context->cost_vs_zone_median_ratio)->toBeNull()
        ->and($context->input_cost_share_of_harvest_value)->toBeNull();
});

test('a field is described with its label, its value and its unit', function () {
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 2, QuantityUnit::Bag50Kg)
        ->create();

    $context = EvaluationContext::fromCycle($this->cycle);

    expect($context->describe('npk_dose_kg_ha'))->toBe('dose de NPK : 50 kg/ha')
        ->and($context->describe('potassium_dose_kg_ha'))->toBe('dose de potassium non renseignée')
        ->and($context->describe('inoculant_used'))->toBe('inoculant employé : non');
});
