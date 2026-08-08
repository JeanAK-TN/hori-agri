<?php

use App\Enums\SeasonType;
use App\Enums\SoilType;
use App\Models\Campaign;
use App\Models\Cooperative;
use App\Models\Farmer;
use App\Models\Plot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->cooperative = Cooperative::factory()->create();
    $this->technician = User::factory()->technician()->for($this->cooperative)->create();
    $this->coopAdmin = User::factory()->coopAdmin()->for($this->cooperative)->create();
    $this->farmer = Farmer::factory()->for($this->cooperative)->create();
});

/*
| Parcelles.
*/

test('a technician records a plot without naming the cooperative', function () {
    $this->actingAs($this->technician)
        ->post(route('plots.store'), [
            'farmer_id' => $this->farmer->id,
            'name' => 'Derrière la maison',
            'area_ha' => 0.75,
            'soil_type' => SoilType::Ferrugineux->value,
        ])
        ->assertSessionHasNoErrors();

    $plot = Plot::firstWhere('name', 'Derrière la maison');

    expect($plot->cooperative_id)->toBe($this->cooperative->id)
        ->and((float) $plot->area_ha)->toBe(0.75)
        ->and($plot->soil_type)->toBe(SoilType::Ferrugineux);
});

test('the area is required and must be positive', function (mixed $area) {
    $this->actingAs($this->technician)
        ->post(route('plots.store'), [
            'farmer_id' => $this->farmer->id,
            'name' => 'Sans surface',
            'area_ha' => $area,
            'soil_type' => SoilType::Sableux->value,
        ])
        ->assertSessionHasErrors('area_ha');
})->with([
    'absente' => null,
    'nulle' => 0,
    'négative' => -1,
]);

test('a plot cannot be attached to a producer of another cooperative', function () {
    $foreignFarmer = Farmer::factory()->create();

    $this->actingAs($this->technician)
        ->post(route('plots.store'), [
            'farmer_id' => $foreignFarmer->id,
            'name' => 'Parcelle détournée',
            'area_ha' => 1,
            'soil_type' => SoilType::Argileux->value,
        ])
        ->assertSessionHasErrors('farmer_id');
});

test('the plot record shows the cycles opened on it', function () {
    $plot = Plot::factory()->ofFarmer($this->farmer)->create(['name' => 'Le bas-fond']);

    $this->actingAs($this->technician)
        ->get(route('plots.show', $plot))
        ->assertInertia(fn (Assert $page) => $page
            ->component('plots/show')
            ->where('plot.name', 'Le bas-fond')
            ->has('cycles', 0),
        );
});

test('the producer record lists their plots', function () {
    Plot::factory()->ofFarmer($this->farmer)->create(['name' => 'Parcelle du haut']);
    Plot::factory()->ofFarmer($this->farmer)->create(['name' => 'Parcelle du bas']);

    $this->actingAs($this->technician)
        ->get(route('farmers.show', $this->farmer))
        ->assertInertia(fn (Assert $page) => $page
            ->has('plots', 2)
            ->where('plots.0.name', 'Parcelle du bas'),
        );
});

/*
| Campagnes.
*/

test('a cooperative administrator opens a campaign', function () {
    $this->actingAs($this->coopAdmin)
        ->post(route('campaigns.store'), [
            'label' => '2026 grande saison',
            'start_date' => '2026-03-01',
            'end_date' => '2026-11-30',
            'season_type' => SeasonType::GrandeSaison->value,
        ])
        ->assertRedirect(route('campaigns.index'));

    $campaign = Campaign::firstWhere('label', '2026 grande saison');

    expect($campaign->cooperative_id)->toBe($this->cooperative->id)
        ->and($campaign->season_type)->toBe(SeasonType::GrandeSaison);
});

test('the end date cannot precede the start date', function () {
    $this->actingAs($this->coopAdmin)
        ->post(route('campaigns.store'), [
            'label' => 'Campagne à l\'envers',
            'start_date' => '2026-11-30',
            'end_date' => '2026-03-01',
            'season_type' => SeasonType::GrandeSaison->value,
        ])
        ->assertSessionHasErrors('end_date');
});

test('two campaigns of one cooperative cannot share a label', function () {
    Campaign::factory()->for($this->cooperative)->create(['label' => '2026 grande saison']);

    $this->actingAs($this->coopAdmin)
        ->post(route('campaigns.store'), [
            'label' => '2026 grande saison',
            'start_date' => '2026-03-01',
            'end_date' => '2026-11-30',
            'season_type' => SeasonType::GrandeSaison->value,
        ])
        ->assertSessionHasErrors('label');
});

test('another cooperative may use the same campaign label', function () {
    $otherCooperative = Cooperative::factory()->create();
    Campaign::factory()->for($otherCooperative)->create(['label' => '2026 grande saison']);

    $this->actingAs($this->coopAdmin)
        ->post(route('campaigns.store'), [
            'label' => '2026 grande saison',
            'start_date' => '2026-03-01',
            'end_date' => '2026-11-30',
            'season_type' => SeasonType::GrandeSaison->value,
        ])
        ->assertSessionHasNoErrors();
});

test('a technician reads the campaigns but does not create them', function () {
    $this->actingAs($this->technician)
        ->get(route('campaigns.index'))
        ->assertOk();

    $this->actingAs($this->technician)
        ->post(route('campaigns.store'), [
            'label' => '2026 petite saison',
            'start_date' => '2026-03-01',
            'end_date' => '2026-11-30',
            'season_type' => SeasonType::PetiteSaison->value,
        ])
        ->assertForbidden();
});
