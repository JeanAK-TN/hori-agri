<?php

use App\Enums\Role;
use App\Models\Campaign;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farmer;
use App\Models\Plot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Hypothèse H4 : l'isolation entre coopératives (SPEC section 14.2).
| Une demande portant sur une ressource d'une autre coopérative doit
| produire « ressource introuvable », jamais un refus d'accès, qui
| révélerait l'existence de la ressource.
*/

test('a cooperative administrator only sees the accounts of their own cooperative', function () {
    $mine = Cooperative::factory()->create();
    $other = Cooperative::factory()->create();

    $admin = User::factory()->coopAdmin()->for($mine)->create(['name' => 'Chez moi']);
    User::factory()->technician()->for($mine)->create(['name' => 'Mon technicien']);
    User::factory()->technician()->for($other)->create(['name' => 'Technicien voisin']);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users.data', 2)
            ->where('users.data.0.name', 'Chez moi')
            ->where('users.data.1.name', 'Mon technicien'),
        );
});

test('the super_admin sees every account', function () {
    $first = Cooperative::factory()->create();
    $second = Cooperative::factory()->create();

    User::factory()->technician()->for($first)->create();
    User::factory()->technician()->for($second)->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)
        ->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 3));
});

test('updating an account of another cooperative answers not found, not forbidden', function () {
    $admin = User::factory()->coopAdmin()->create();
    $foreign = User::factory()->technician()->create();

    $this->actingAs($admin)
        ->patch(route('users.update', $foreign), [
            'name' => 'Renommé de force',
            'phone' => $foreign->phone,
            'role' => Role::Technician->value,
        ])
        ->assertNotFound();

    expect($foreign->fresh()->name)->not->toBe('Renommé de force');
});

test('a cooperative administrator can update an account of their own cooperative', function () {
    $cooperative = Cooperative::factory()->create();
    $admin = User::factory()->coopAdmin()->for($cooperative)->create();
    $technician = User::factory()->technician()->for($cooperative)->create();

    $this->actingAs($admin)
        ->patch(route('users.update', $technician), [
            'name' => 'Nom corrigé',
            'phone' => $technician->phone,
            'role' => Role::Technician->value,
        ])
        ->assertRedirect(route('users.index'));

    expect($technician->fresh()->name)->toBe('Nom corrigé');
});

test('an administrator cannot change their own role', function () {
    $admin = User::factory()->coopAdmin()->create();

    $this->actingAs($admin)
        ->patch(route('users.update', $admin), [
            'name' => $admin->name,
            'phone' => $admin->phone,
            'role' => Role::Farmer->value,
        ])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->role)->toBe(Role::CoopAdmin);
});

test('a technician only sees the producers of their own cooperative', function () {
    $mine = Cooperative::factory()->create();
    $technician = User::factory()->technician()->for($mine)->create();

    Farmer::factory()->for($mine)->create(['last_name' => 'Chez moi']);
    Farmer::factory()->create(['last_name' => 'Chez le voisin']);

    $this->actingAs($technician)
        ->get(route('farmers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('farmers.data', 1)
            ->where('farmers.data.0.last_name', 'Chez moi'),
        );
});

test('consulting a producer of another cooperative answers not found', function () {
    $technician = User::factory()->technician()->create();
    $foreign = Farmer::factory()->create();

    $this->actingAs($technician)
        ->get(route('farmers.show', $foreign))
        ->assertNotFound();
});

test('updating a producer of another cooperative answers not found', function () {
    $technician = User::factory()->technician()->create();
    $foreign = Farmer::factory()->create(['village' => 'Village voisin']);

    $this->actingAs($technician)
        ->patch(route('farmers.update', $foreign), [
            'first_name' => $foreign->first_name,
            'last_name' => $foreign->last_name,
            'village' => 'Village détourné',
        ])
        ->assertNotFound();

    expect($foreign->fresh()->village)->toBe('Village voisin');
});

test('consulting a plot of another cooperative answers not found', function () {
    $technician = User::factory()->technician()->create();
    $foreign = Plot::factory()->create();

    $this->actingAs($technician)
        ->get(route('plots.show', $foreign))
        ->assertNotFound();
});

test('consulting a cycle of another cooperative answers not found', function () {
    $technician = User::factory()->technician()->create();

    $foreignFarmer = Farmer::factory()->create();
    $foreignPlot = Plot::factory()->ofFarmer($foreignFarmer)->create();
    $foreignCampaign = Campaign::factory()
        ->for($foreignFarmer->cooperative)
        ->create();
    $foreignCycle = CropCycle::factory()
        ->on($foreignPlot, $foreignCampaign, Crop::factory()->create())
        ->create();

    $this->actingAs($technician)
        ->get(route('cycles.show', $foreignCycle))
        ->assertNotFound();
});

test('closing a cycle of another cooperative answers not found', function () {
    $technician = User::factory()->technician()->create();

    $foreignFarmer = Farmer::factory()->create();
    $foreignPlot = Plot::factory()->ofFarmer($foreignFarmer)->create();
    $foreignCampaign = Campaign::factory()
        ->for($foreignFarmer->cooperative)
        ->create();
    $foreignCycle = CropCycle::factory()
        ->on($foreignPlot, $foreignCampaign, Crop::factory()->create())
        ->create();

    $this->actingAs($technician)
        ->post(route('cycles.close', $foreignCycle))
        ->assertNotFound();
});

test('the lists only carry the rows of the current cooperative', function () {
    $mine = Cooperative::factory()->create();
    $technician = User::factory()->technician()->for($mine)->create();

    $myFarmer = Farmer::factory()->for($mine)->create();
    Plot::factory()->ofFarmer($myFarmer)->create(['name' => 'Ma parcelle']);
    Campaign::factory()->for($mine)->create(['label' => 'Ma campagne']);

    $foreignFarmer = Farmer::factory()->create();
    Plot::factory()->ofFarmer($foreignFarmer)->create(['name' => 'Parcelle voisine']);
    Campaign::factory()->for($foreignFarmer->cooperative)->create(['label' => 'Campagne voisine']);

    $this->actingAs($technician);

    $this->get(route('plots.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('plots.data', 1)
            ->where('plots.data.0.name', 'Ma parcelle'),
        );

    $this->get(route('campaigns.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('campaigns.data', 1)
            ->where('campaigns.data.0.label', 'Ma campagne'),
        );
});

test('a cooperative administrator cannot reach the cooperative registry', function () {
    $admin = User::factory()->coopAdmin()->create();
    $cooperative = Cooperative::factory()->create();

    $this->actingAs($admin)
        ->patch(route('cooperatives.update', $cooperative), [
            'name' => 'Renommée',
            'region' => 'Kara',
            'prefecture' => 'Kozah',
        ])
        ->assertForbidden();
});
