<?php

use App\Enums\Quality;
use App\Models\Campaign;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->cooperative = Cooperative::factory()->create();
    $this->technician = User::factory()->technician()->for($this->cooperative)->create();

    $farmer = Farmer::factory()->for($this->cooperative)->create();
    $plot = Plot::factory()->ofFarmer($farmer)->create();
    $campaign = Campaign::factory()
        ->for($this->cooperative)
        ->between('2026-03-01', '2026-11-30')
        ->create();

    $this->cycle = CropCycle::factory()
        ->on($plot, $campaign, Crop::factory()->named('Maïs')->create())
        ->create();

    $this->actingAs($this->technician);
});

test('a technician records a harvest', function () {
    $this->post(route('harvests.store', $this->cycle), [
        'date' => '2026-08-15',
        'quantity_kg' => 675,
        'unit_price' => 200,
        'quality' => Quality::Good->value,
    ])->assertRedirect(route('cycles.show', $this->cycle));

    $harvest = Harvest::firstWhere('crop_cycle_id', $this->cycle->id);

    expect($harvest->cooperative_id)->toBe($this->cooperative->id)
        ->and((float) $harvest->quantity_kg)->toBe(675.0)
        ->and($harvest->quality)->toBe(Quality::Good);
});

test('several harvests are possible for one cycle', function () {
    foreach (['2026-07-01', '2026-08-15'] as $date) {
        $this->post(route('harvests.store', $this->cycle), [
            'date' => $date,
            'quantity_kg' => 500,
        ])->assertSessionHasNoErrors();
    }

    expect(Harvest::where('crop_cycle_id', $this->cycle->id)->count())->toBe(2);
});

test('the harvested quantity is required', function () {
    $this->post(route('harvests.store', $this->cycle), [
        'date' => '2026-08-15',
    ])->assertSessionHasErrors('quantity_kg');
});

test('the unit price stays optional but is what makes the margin computable', function () {
    $this->post(route('harvests.store', $this->cycle), [
        'date' => '2026-08-15',
        'quantity_kg' => 675,
    ])->assertSessionHasNoErrors();

    expect(Harvest::first()->unit_price)->toBeNull();
});

test('a harvest date outside the campaign is refused without losing the entry', function () {
    $response = $this->post(route('harvests.store', $this->cycle), [
        'date' => '2026-12-15',
        'quantity_kg' => 675,
        'unit_price' => 200,
    ]);

    $response->assertSessionHasErrors('date');
    $response->assertSessionHasInput('quantity_kg', 675);
    $response->assertSessionHasInput('unit_price', 200);

    expect(Harvest::count())->toBe(0);
});

test('post-harvest losses cannot exceed the harvest', function () {
    $this->post(route('harvests.store', $this->cycle), [
        'date' => '2026-08-15',
        'quantity_kg' => 100,
        'post_harvest_losses_kg' => 150,
    ])->assertSessionHasErrors('post_harvest_losses_kg');
});

test('the cycle screen shows the itinerary and the harvests', function () {
    Harvest::factory()->forCycle($this->cycle)->of(675, 200)->on('2026-08-15')->create();

    $this->get(route('cycles.show', $this->cycle))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cycles/show')
            ->has('harvests', 1)
            ->where('harvests.0.quantity_kg', '675.00')
            ->where('canRecord', true)
            ->has('operationTypes', 7)
            ->has('units', 5),
        );
});

test('a cooperative administrator sees the itinerary but cannot fill it in', function () {
    $coopAdmin = User::factory()->coopAdmin()->for($this->cooperative)->create();

    $this->actingAs($coopAdmin)
        ->get(route('cycles.show', $this->cycle))
        ->assertInertia(fn (Assert $page) => $page->where('canRecord', false));
});
