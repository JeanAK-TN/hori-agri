<?php

use App\Enums\OperationType;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Indicator;
use App\Models\Plot;
use App\Models\User;
use App\Services\Import\ImportService;
use App\Services\Indicators\IndicatorService;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Hypothèse H3 : en dessous de cinq parcelles comparables, la comparaison
| entre parcelles semblables reste suspendue (SPEC section 14.2).
|
| Le seuil vient d'une limite constatée : la médiane de deux valeurs
| étant leur moyenne, l'une est toujours au-dessus et l'autre en dessous,
| ce qui ferait se déclencher une règle de comparaison sur une parcelle
| correctement conduite.
*/

beforeEach(function () {
    $this->exploitation = Exploitation::factory()->create();
    $this->technician = User::factory()->technician()->for($this->exploitation)->create();
    $this->crop = Crop::factory()->named('Tomate')->create();
    $this->campaign = Campaign::factory()
        ->for($this->exploitation)
        ->between('2026-03-01', '2026-11-30')
        ->create();

    $this->openCycle = function (float $yieldKg = 1000): CropCycle {
        $farmer = Farmer::factory()->for($this->exploitation)->inVillage('Bassar')->create();
        $plot = Plot::factory()->ofFarmer($farmer)->withArea(1.0)->create();

        $cycle = CropCycle::factory()->on($plot, $this->campaign, $this->crop)->create();
        Harvest::factory()->forCycle($cycle)->of($yieldKg, 100)->create();

        return $cycle;
    };

    $this->actingAs($this->technician);
});

test('the indicators are recomputed without any action from the user', function () {
    $farmer = Farmer::factory()->for($this->exploitation)->create();
    $plot = Plot::factory()->ofFarmer($farmer)->withArea(0.75)->create();
    $cycle = CropCycle::factory()->on($plot, $this->campaign, $this->crop)->create();

    // Aucune récolte : aucun indicateur.
    $this->post(route('operations.store', $cycle), [
        'type' => OperationType::Tillage->value,
        'date' => '2026-03-15',
    ])->assertSessionHasNoErrors();

    expect(Indicator::count())->toBe(0);

    // La première récolte déclenche le calcul, sans que personne ne le
    // demande (SPEC section 9.1).
    $this->post(route('harvests.store', $cycle), [
        'date' => '2026-08-15',
        'quantity_kg' => 675,
        'unit_price' => 200,
    ])->assertSessionHasNoErrors();

    $indicator = Indicator::firstWhere('crop_cycle_id', $cycle->id);

    expect($indicator)->not->toBeNull()
        ->and((float) $indicator->yield_kg_ha)->toBe(900.0)
        ->and($indicator->exploitation_id)->toBe($this->exploitation->id);
});

test('recording a cost afterwards refreshes the indicators on its own', function () {
    $cycle = ($this->openCycle)(1000);

    $this->post(route('harvests.store', $cycle), [
        'date' => '2026-08-20',
        'quantity_kg' => 0,
        'unit_price' => 100,
    ])->assertSessionHasNoErrors();

    $before = Indicator::firstWhere('crop_cycle_id', $cycle->id);

    expect((float) $before->production_cost_ha)->toBe(0.0);

    $this->post(route('operations.store', $cycle), [
        'type' => OperationType::Tillage->value,
        'date' => '2026-03-15',
        'cost' => 50000,
    ])->assertSessionHasNoErrors();

    expect((float) $before->fresh()->production_cost_ha)->toBe(50000.0);
});

test('below five comparable cycles both ratios stay null', function () {
    $cycle = ($this->openCycle)(1200);

    foreach ([800, 900, 1100] as $yield) {
        ($this->openCycle)($yield);
    }

    // Le calcul du cycle évalué est déclenché par une nouvelle écriture.
    $this->post(route('operations.store', $cycle), [
        'type' => OperationType::Tillage->value,
        'date' => '2026-03-15',
    ])->assertSessionHasNoErrors();

    $indicator = Indicator::firstWhere('crop_cycle_id', $cycle->id);

    expect($indicator->zone_plot_count)->toBe(4)
        ->and($indicator->yield_vs_zone_median)->toBeNull()
        ->and($indicator->cost_vs_zone_median)->toBeNull();
});

test('from five comparable cycles the ratios appear', function () {
    $cycle = ($this->openCycle)(1200);

    foreach ([800, 900, 1000, 1100] as $yield) {
        ($this->openCycle)($yield);
    }

    $this->post(route('operations.store', $cycle), [
        'type' => OperationType::Tillage->value,
        'date' => '2026-03-15',
    ])->assertSessionHasNoErrors();

    $indicator = Indicator::firstWhere('crop_cycle_id', $cycle->id);

    expect($indicator->zone_plot_count)->toBe(5)
        ->and((float) $indicator->yield_vs_zone_median)->toBe(1.2);
});

test('the cycle screen says the comparison is suspended below the threshold', function () {
    $cycle = ($this->openCycle)(1000);

    // Les factories écrivent sans passer par les contrôleurs : le calcul
    // est provoqué ici comme il le serait à la saisie.
    app(IndicatorService::class)->computeFor($cycle);

    $this->get(route('cycles.show', $cycle))
        ->assertInertia(fn (Assert $page) => $page
            ->where('indicators.zone_plot_count', 1)
            ->where('indicators.has_comparable_zone', false)
            ->where('indicators.minimum_zone_size', 5)
            ->where('indicators.yield_vs_zone_median', null),
        );
});

test('the cycle screen shows the ratios once the zone is large enough', function () {
    $cycle = ($this->openCycle)(1200);

    foreach ([800, 900, 1000, 1100] as $yield) {
        ($this->openCycle)($yield);
    }

    $this->post(route('operations.store', $cycle), [
        'type' => OperationType::Tillage->value,
        'date' => '2026-03-15',
    ]);

    $this->get(route('cycles.show', $cycle))
        ->assertInertia(fn (Assert $page) => $page
            ->where('indicators.zone_plot_count', 5)
            ->where('indicators.has_comparable_zone', true)
            ->where('indicators.yield_vs_zone_median', '1.200'),
        );
});

test('an import refreshes the indicators of every cycle it touched', function () {
    $farmer = Farmer::factory()->for($this->exploitation)->create([
        'phone' => '90112233',
    ]);
    $plot = Plot::factory()->ofFarmer($farmer)->withArea(1.0)->create([
        'name' => 'Le bas-fond',
    ]);
    $cycle = CropCycle::factory()->on($plot, $this->campaign, $this->crop)->create();

    $columns = implode(';', ImportService::COLUMNS);
    $row = 'recolte;90112233;Le bas-fond;Tomate;'.$this->campaign->label
        .';;;15/08/2026;;;;;1500;100;;;';

    $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
    file_put_contents($path, $columns."\n".$row);

    $this->post(route('imports.store'), [
        'fichier' => new UploadedFile($path, 'import.csv', 'text/csv', test: true),
    ]);

    $indicator = Indicator::firstWhere('crop_cycle_id', $cycle->id);

    expect($indicator)->not->toBeNull()
        ->and((float) $indicator->yield_kg_ha)->toBe(1500.0);
});
