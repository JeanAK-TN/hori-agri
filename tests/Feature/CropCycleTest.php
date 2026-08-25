<?php

use App\Enums\CycleStatus;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Plot;
use App\Models\User;
use App\Models\Variety;
use Illuminate\Database\QueryException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->exploitation = Exploitation::factory()->create();
    $this->technician = User::factory()->technician()->for($this->exploitation)->create();

    $farmer = Farmer::factory()->for($this->exploitation)->create();
    $this->plot = Plot::factory()->ofFarmer($farmer)->create();
    $this->campaign = Campaign::factory()
        ->for($this->exploitation)
        ->between('2026-03-01', '2026-11-30')
        ->create(['label' => '2026 grande saison']);
    $this->crop = Crop::factory()->named('Maïs')->create();

    $this->actingAs($this->technician);
});

test('a cycle opens on a plot for a crop and a campaign', function () {
    $response = $this->post(route('cycles.store'), [
        'plot_id' => $this->plot->id,
        'campaign_id' => $this->campaign->id,
        'crop_id' => $this->crop->id,
        'sowing_date' => '2026-04-15',
        'density' => 50000,
    ]);

    $cycle = CropCycle::firstWhere('plot_id', $this->plot->id);

    $response->assertRedirect(route('cycles.show', $cycle));

    expect($cycle->exploitation_id)->toBe($this->exploitation->id)
        ->and($cycle->status)->toBe(CycleStatus::Planned)
        ->and($cycle->density)->toBe(50000)
        ->and($cycle->sowing_date->toDateString())->toBe('2026-04-15');
});

test('the transplanting date and the conduct are recorded with the cycle', function () {
    // Le maraîchage se conduit en deux temps : semis en pépinière puis
    // repiquage au champ (SPEC-CORRECTION section 5.2). La conduite,
    // tuteurée ou rampante, change la densité attendue (section 5.3).
    $this->post(route('cycles.store'), [
        'plot_id' => $this->plot->id,
        'campaign_id' => $this->campaign->id,
        'crop_id' => $this->crop->id,
        'sowing_date' => '2026-03-20',
        'transplanting_date' => '2026-04-12',
        'staked' => true,
    ])->assertSessionHasNoErrors();

    $cycle = CropCycle::sole();

    expect($cycle->transplanting_date->toDateString())->toBe('2026-04-12')
        ->and($cycle->staked)->toBeTrue();
});

test('both dates and the conduct stay null when nothing is known', function () {
    $this->post(route('cycles.store'), [
        'plot_id' => $this->plot->id,
        'campaign_id' => $this->campaign->id,
        'crop_id' => $this->crop->id,
        'sowing_date' => '2026-03-20',
        'transplanting_date' => null,
        'staked' => null,
    ])->assertSessionHasNoErrors();

    $cycle = CropCycle::sole();

    expect($cycle->transplanting_date)->toBeNull()
        ->and($cycle->staked)->toBeNull();
});

test('a transplanting before the sowing is refused', function () {
    $this->post(route('cycles.store'), [
        'plot_id' => $this->plot->id,
        'campaign_id' => $this->campaign->id,
        'crop_id' => $this->crop->id,
        'sowing_date' => '2026-04-15',
        'transplanting_date' => '2026-04-01',
    ])->assertSessionHasErrors('transplanting_date');

    expect(CropCycle::count())->toBe(0);
});

test('a transplanting outside the campaign is refused', function () {
    $this->post(route('cycles.store'), [
        'plot_id' => $this->plot->id,
        'campaign_id' => $this->campaign->id,
        'crop_id' => $this->crop->id,
        'sowing_date' => '2026-11-20',
        'transplanting_date' => '2026-12-05',
    ])->assertSessionHasErrors('transplanting_date');
});

test('a second cycle on the same plot, crop and campaign is refused', function () {
    $payload = [
        'plot_id' => $this->plot->id,
        'campaign_id' => $this->campaign->id,
        'crop_id' => $this->crop->id,
        'sowing_date' => '2026-04-15',
    ];

    $this->post(route('cycles.store'), $payload)->assertSessionHasNoErrors();

    $this->post(route('cycles.store'), $payload)
        ->assertSessionHasErrors('crop_id');

    expect(CropCycle::count())->toBe(1);
});

test('the database also refuses the duplicate triple', function () {
    CropCycle::factory()
        ->on($this->plot, $this->campaign, $this->crop)
        ->create();

    expect(fn () => CropCycle::factory()
        ->on($this->plot, $this->campaign, $this->crop)
        ->create()
    )->toThrow(QueryException::class);
});

test('the same plot can carry two cycles of different crops', function () {
    $soja = Crop::factory()->named('Soja')->create();

    foreach ([$this->crop, $soja] as $crop) {
        $this->post(route('cycles.store'), [
            'plot_id' => $this->plot->id,
            'campaign_id' => $this->campaign->id,
            'crop_id' => $crop->id,
            'sowing_date' => '2026-04-15',
        ])->assertSessionHasNoErrors();
    }

    expect(CropCycle::count())->toBe(2);
});

test('the sowing date must fall inside the bounds of the campaign', function (string $date) {
    $this->post(route('cycles.store'), [
        'plot_id' => $this->plot->id,
        'campaign_id' => $this->campaign->id,
        'crop_id' => $this->crop->id,
        'sowing_date' => $date,
    ])->assertSessionHasErrors('sowing_date');

    expect(CropCycle::count())->toBe(0);
})->with([
    'avant la campagne' => '2026-02-28',
    'après la campagne' => '2026-12-01',
]);

test('the bounds of the campaign are included', function (string $date) {
    $this->post(route('cycles.store'), [
        'plot_id' => $this->plot->id,
        'campaign_id' => $this->campaign->id,
        'crop_id' => $this->crop->id,
        'sowing_date' => $date,
    ])->assertSessionHasNoErrors();
})->with([
    'premier jour' => '2026-03-01',
    'dernier jour' => '2026-11-30',
]);

test('a variety of another crop is refused', function () {
    $otherCrop = Crop::factory()->named('Soja')->create();
    $variety = Variety::factory()->for($otherCrop, 'crop')->create();

    $this->post(route('cycles.store'), [
        'plot_id' => $this->plot->id,
        'campaign_id' => $this->campaign->id,
        'crop_id' => $this->crop->id,
        'variety_id' => $variety->id,
        'sowing_date' => '2026-04-15',
    ])->assertSessionHasErrors('variety_id');
});

test('a plot of another exploitation cannot carry a cycle', function () {
    $foreignPlot = Plot::factory()->create();

    $this->post(route('cycles.store'), [
        'plot_id' => $foreignPlot->id,
        'campaign_id' => $this->campaign->id,
        'crop_id' => $this->crop->id,
        'sowing_date' => '2026-04-15',
    ])->assertSessionHasErrors('plot_id');
});

test('the list can be filtered by campaign and by crop', function () {
    $soja = Crop::factory()->named('Soja')->create();
    $otherCampaign = Campaign::factory()
        ->for($this->exploitation)
        ->between('2025-03-01', '2025-11-30')
        ->create(['label' => '2025 grande saison']);

    CropCycle::factory()->on($this->plot, $this->campaign, $this->crop)->create();
    CropCycle::factory()->on($this->plot, $this->campaign, $soja)->create();
    CropCycle::factory()->on($this->plot, $otherCampaign, $this->crop)->create();

    $this->get(route('cycles.index', ['campagne' => $this->campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->has('cycles.data', 2));

    $this->get(route('cycles.index', ['culture' => $soja->id]))
        ->assertInertia(fn (Assert $page) => $page->has('cycles.data', 1));

    $this->get(route('cycles.index', [
        'campagne' => $this->campaign->id,
        'culture' => $this->crop->id,
    ]))->assertInertia(fn (Assert $page) => $page->has('cycles.data', 1));
});

/*
| Diagramme d'états (SPEC section 13, module 3).
*/

test('a planned cycle moves to in progress on the first operation', function () {
    $cycle = CropCycle::factory()->on($this->plot, $this->campaign, $this->crop)->create();

    $cycle->markInProgress();

    expect($cycle->fresh()->status)->toBe(CycleStatus::InProgress);
});

test('marking an already started cycle again changes nothing', function () {
    $cycle = CropCycle::factory()
        ->on($this->plot, $this->campaign, $this->crop)
        ->status(CycleStatus::InProgress)
        ->create();

    $cycle->markInProgress();

    expect($cycle->fresh()->status)->toBe(CycleStatus::InProgress);
});

test('a cycle is closed once the harvest is over', function () {
    $cycle = CropCycle::factory()
        ->on($this->plot, $this->campaign, $this->crop)
        ->status(CycleStatus::InProgress)
        ->create();

    $cycle->close();

    expect($cycle->fresh()->status)->toBe(CycleStatus::Harvested);
});

test('a cycle can be given up at any moment', function (CycleStatus $from) {
    $cycle = CropCycle::factory()
        ->on($this->plot, $this->campaign, $this->crop)
        ->status($from)
        ->create();

    $cycle->abandon();

    expect($cycle->fresh()->status)->toBe(CycleStatus::Abandoned);
})->with([
    'planifié' => CycleStatus::Planned,
    'en cours' => CycleStatus::InProgress,
    'récolté' => CycleStatus::Harvested,
]);

test('an invalid transition raises a logic exception', function (CycleStatus $from, string $method) {
    $cycle = CropCycle::factory()
        ->on($this->plot, $this->campaign, $this->crop)
        ->status($from)
        ->create();

    expect(fn () => $cycle->{$method}())->toThrow(LogicException::class);
})->with([
    'un cycle récolté ne redémarre pas' => [CycleStatus::Harvested, 'markInProgress'],
    'un cycle abandonné ne redémarre pas' => [CycleStatus::Abandoned, 'markInProgress'],
    'un cycle abandonné ne se clôture pas' => [CycleStatus::Abandoned, 'close'],
    'un cycle récolté ne se clôture pas deux fois' => [CycleStatus::Harvested, 'close'],
    'un cycle abandonné ne s\'abandonne pas deux fois' => [CycleStatus::Abandoned, 'abandon'],
]);

test('a technician closes a cycle from the screen', function () {
    $cycle = CropCycle::factory()
        ->on($this->plot, $this->campaign, $this->crop)
        ->status(CycleStatus::InProgress)
        ->create();

    $this->post(route('cycles.close', $cycle))
        ->assertRedirect(route('cycles.show', $cycle));

    expect($cycle->fresh()->status)->toBe(CycleStatus::Harvested);
});
