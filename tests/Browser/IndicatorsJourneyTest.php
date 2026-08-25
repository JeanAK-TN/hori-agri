<?php

use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Indicator;
use App\Models\Plot;
use App\Models\User;
use App\Services\Indicators\IndicatorService;

/*
| Les indicateurs apparaissent sans que personne ne les demande, et la
| suspension de la comparaison est dite en clair (SPEC sections 9.1 et
| 9.3).
*/

beforeEach(function () {
    $this->exploitation = Exploitation::factory()->create();
    $this->technician = User::factory()->technician()->for($this->exploitation)->create();
    $this->crop = Crop::factory()->named('Maïs')->create();
    $this->campaign = Campaign::factory()
        ->for($this->exploitation)
        ->between('2026-03-01', '2026-11-30')
        ->create();

    $this->actingAs($this->technician);
});

function openCycleWithArea(
    Exploitation $exploitation,
    Crop $crop,
    Campaign $campaign,
    float $area,
): CropCycle {
    $farmer = Farmer::factory()->for($exploitation)->inVillage('Bassar')->create();
    $plot = Plot::factory()->ofFarmer($farmer)->withArea($area)->create();

    return CropCycle::factory()->on($plot, $campaign, $crop)->create();
}

test('les indicateurs apparaissent dès la première récolte, sans action', function () {
    $cycle = openCycleWithArea($this->exploitation, $this->crop, $this->campaign, 0.75);

    $page = visit("/cycles/{$cycle->id}")->resize(360, 800);

    // Tant qu'il n'y a pas de récolte, la section n'existe pas. On vise
    // le titre plutôt que le mot, qui figure aussi dans l'état vide des
    // récoltes.
    $page->assertScript(
        "[...document.querySelectorAll('h2')].some((h) => h.textContent.trim() === 'Indicateurs')",
        false,
    )->assertNoJavaScriptErrors();

    $page->fill('#harvest_date', '2026-08-15')
        ->fill('quantity_kg', '675')
        ->fill('unit_price', '200')
        ->click('[data-test=record-harvest]');

    $page->assertSee('Indicateurs')
        ->assertSee('Rendement')
        // 675 kg sur 0,75 ha
        ->assertSee('900 kg/ha')
        ->assertSee('sans action de votre part')
        ->assertNoJavaScriptErrors()
        ->assertScript(
            'document.documentElement.scrollWidth <= document.documentElement.clientWidth',
        );

    expect((float) Indicator::first()->yield_kg_ha)->toBe(900.0);
});

test('la comparaison suspendue est expliquée en clair', function () {
    $cycle = openCycleWithArea($this->exploitation, $this->crop, $this->campaign, 1.0);
    Harvest::factory()->forCycle($cycle)->of(1000, 100)->create();

    app(IndicatorService::class)->computeFor($cycle);

    visit("/cycles/{$cycle->id}")
        ->assertSee('La comparaison reste suspendue')
        ->assertSee('1 cycle comparable au lieu des 5 nécessaires')
        ->assertNoJavaScriptErrors();
});

test('les rapports s affichent dès cinq cycles comparables', function () {
    $cycle = openCycleWithArea($this->exploitation, $this->crop, $this->campaign, 1.0);
    Harvest::factory()->forCycle($cycle)->of(1200, 100)->create();

    foreach ([800, 900, 1000, 1100] as $yield) {
        $peer = openCycleWithArea($this->exploitation, $this->crop, $this->campaign, 1.0);
        Harvest::factory()->forCycle($peer)->of($yield, 100)->create();
    }

    app(IndicatorService::class)->computeFor($cycle);

    visit("/cycles/{$cycle->id}")
        ->assertSee('5 cycles comparables')
        ->assertDontSee('La comparaison reste suspendue')
        // 1 200 contre une médiane de 1 000
        ->assertSee('+20 %')
        ->assertNoJavaScriptErrors();
});

test('une marge incalculable est dite plutôt que masquée', function () {
    $cycle = openCycleWithArea($this->exploitation, $this->crop, $this->campaign, 1.0);
    Harvest::factory()->forCycle($cycle)->of(1000, null)->create();

    app(IndicatorService::class)->computeFor($cycle);

    visit("/cycles/{$cycle->id}")
        ->assertSee('Incalculable')
        ->assertSee('Renseignez le prix de vente de chaque récolte.')
        ->assertNoJavaScriptErrors();
});
