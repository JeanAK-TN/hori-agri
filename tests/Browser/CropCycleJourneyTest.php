<?php

use App\Enums\CycleStatus;
use App\Enums\SoilType;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Plot;
use App\Models\User;

/*
| Parcours du module 3 dans un vrai navigateur : enregistrer une
| parcelle, ouvrir un cycle, refuser le doublon, clôturer.
|
| Les listes déroulantes sont des composants Radix : leurs options vivent
| dans un portail et se désignent par [role=option], jamais par leur seul
| texte, qui se retrouve aussi sur le bouton une fois la valeur choisie.
*/

beforeEach(function () {
    $this->exploitation = Exploitation::factory()->create();
    $this->technician = User::factory()->technician()->for($this->exploitation)->create();
    $this->farmer = Farmer::factory()->for($this->exploitation)->create([
        'first_name' => 'Kossi',
        'last_name' => 'Amegan',
        'village' => 'Bassar',
    ]);
    $this->campaign = Campaign::factory()
        ->for($this->exploitation)
        ->between('2026-03-01', '2026-11-30')
        ->create(['label' => '2026 grande saison']);
    $this->crop = Crop::factory()->named('Maïs')->create();

    $this->actingAs($this->technician);
});

test('un technicien enregistre une parcelle depuis un téléphone', function () {
    $page = visit('/plots/create')->resize(360, 800);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Nouvelle parcelle')
        ->click('#farmer_id')
        ->click('[role=option]:has-text("Amegan")')
        ->fill('name', 'Derrière la maison')
        ->fill('area_ha', '0.75')
        ->click('#soil_type')
        ->click('[role=option]:has-text("Ferrugineux")')
        ->click('Enregistrer la parcelle');

    $page->assertSee('Caractéristiques')
        ->assertSee('Derrière la maison')
        ->assertNoJavaScriptErrors();

    $plot = Plot::firstWhere('name', 'Derrière la maison');

    expect($plot)->not->toBeNull()
        ->and($plot->soil_type)->toBe(SoilType::Ferrugineux)
        ->and((float) $plot->area_ha)->toBe(0.75)
        ->and($plot->exploitation_id)->toBe($this->exploitation->id);
});

test('un technicien ouvre un cycle cultural et le doublon est refusé', function () {
    Plot::factory()->ofFarmer($this->farmer)->create(['name' => 'Le bas-fond']);

    $page = visit('/cycles/create');

    $page->assertNoJavaScriptErrors()
        ->click('#plot_id')
        ->click('[role=option]:has-text("Le bas-fond")')
        ->click('#campaign_id')
        ->click('[role=option]:has-text("2026 grande saison")')
        ->click('#crop_id')
        ->click('[role=option]:has-text("Maïs")')
        ->fill('sowing_date', '2026-04-15')
        ->click('Ouvrir le cycle');

    $page->assertSee('Le cycle')
        ->assertSee('Planifié')
        ->assertNoJavaScriptErrors();

    expect(CropCycle::count())->toBe(1);

    // Le même trio parcelle, culture, campagne est refusé.
    $second = visit('/cycles/create');

    $second->click('#plot_id')
        ->click('[role=option]:has-text("Le bas-fond")')
        ->click('#campaign_id')
        ->click('[role=option]:has-text("2026 grande saison")')
        ->click('#crop_id')
        ->click('[role=option]:has-text("Maïs")')
        ->fill('sowing_date', '2026-05-20')
        ->click('Ouvrir le cycle')
        ->assertSee('Cette parcelle porte déjà un cycle pour cette culture et cette campagne.')
        ->assertNoJavaScriptErrors();

    expect(CropCycle::count())->toBe(1);
});

test('une date de semis hors des bornes de la campagne est refusée', function () {
    Plot::factory()->ofFarmer($this->farmer)->create(['name' => 'Le bas-fond']);

    $page = visit('/cycles/create');

    $page->click('#plot_id')
        ->click('[role=option]:has-text("Le bas-fond")')
        ->click('#campaign_id')
        ->click('[role=option]:has-text("2026 grande saison")')
        ->click('#crop_id')
        ->click('[role=option]:has-text("Maïs")')
        ->fill('sowing_date', '2026-12-25')
        ->click('Ouvrir le cycle')
        ->assertSee('La date de semis doit tomber entre le 01/03/2026 et le 30/11/2026')
        ->assertNoJavaScriptErrors();

    expect(CropCycle::count())->toBe(0);
});

test('un technicien clôture un cycle depuis son écran', function () {
    $plot = Plot::factory()->ofFarmer($this->farmer)->create();
    $cycle = CropCycle::factory()
        ->on($plot, $this->campaign, $this->crop)
        ->status(CycleStatus::InProgress)
        ->create();

    $page = visit("/cycles/{$cycle->id}")->resize(360, 800);

    $page->assertSee('En cours')
        ->click('Clôturer le cycle')
        ->assertSee('Récolté')
        ->assertDontSee('Clôturer le cycle')
        ->assertNoJavaScriptErrors();

    expect($cycle->fresh()->status)->toBe(CycleStatus::Harvested);
});

test('la liste des cycles se filtre par campagne', function () {
    $plot = Plot::factory()->ofFarmer($this->farmer)->create();
    $soja = Crop::factory()->named('Soja')->create();
    $otherCampaign = Campaign::factory()
        ->for($this->exploitation)
        ->between('2025-03-01', '2025-11-30')
        ->create(['label' => '2025 grande saison']);

    CropCycle::factory()->on($plot, $this->campaign, $this->crop)->create();
    CropCycle::factory()->on($plot, $otherCampaign, $soja)->create();

    $page = visit('/cycles');

    $page->assertSee('Maïs')
        ->assertSee('Soja')
        ->click('#filtre-campagne')
        ->click('[role=option]:has-text("2026 grande saison")')
        ->assertQueryStringHas('campagne', (string) $this->campaign->id)
        ->assertSee('Maïs')
        ->assertDontSee('Soja')
        ->assertNoJavaScriptErrors();
});
