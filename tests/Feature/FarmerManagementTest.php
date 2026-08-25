<?php

use App\Enums\Gender;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->exploitation = Exploitation::factory()->create();
    $this->technician = User::factory()->technician()->for($this->exploitation)->create();
});

test('a technician registers a producer without ever naming the exploitation', function () {
    $response = $this->actingAs($this->technician)
        ->post(route('farmers.store'), [
            'first_name' => 'Kossi',
            'last_name' => 'Amegan',
            'gender' => Gender::Male->value,
            'birth_year' => 1985,
            'experience_years' => 12,
            'village' => 'Bassar',
            'phone' => '90 11 22 33',
        ]);

    $farmer = Farmer::firstWhere('last_name', 'Amegan');

    $response->assertRedirect(route('farmers.show', $farmer));

    expect($farmer->exploitation_id)->toBe($this->exploitation->id)
        ->and($farmer->gender)->toBe(Gender::Male)
        ->and($farmer->phone)->toBe('90112233')
        ->and($farmer->village)->toBe('Bassar');
});

test('a producer record can be consulted', function () {
    $farmer = Farmer::factory()->for($this->exploitation)->create([
        'first_name' => 'Afi',
        'last_name' => 'Doe',
        'village' => 'Kandé',
    ]);

    $this->actingAs($this->technician)
        ->get(route('farmers.show', $farmer))
        ->assertInertia(fn (Assert $page) => $page
            ->component('farmers/show')
            ->where('farmer.full_name', 'Afi Doe')
            ->where('farmer.village', 'Kandé')
            ->where('farmer.account', null),
        );
});

test('a producer record can be corrected', function () {
    $farmer = Farmer::factory()->for($this->exploitation)->create([
        'village' => 'Ancien village',
    ]);

    $this->actingAs($this->technician)
        ->patch(route('farmers.update', $farmer), [
            'first_name' => $farmer->first_name,
            'last_name' => $farmer->last_name,
            'village' => 'Nouveau village',
        ])
        ->assertRedirect(route('farmers.show', $farmer));

    expect($farmer->fresh()->village)->toBe('Nouveau village');
});

test('the first name, last name and village are required', function () {
    $this->actingAs($this->technician)
        ->post(route('farmers.store'), [])
        ->assertSessionHasErrors(['first_name', 'last_name', 'village']);
});

test('the list is paginated server side', function () {
    Farmer::factory()->count(25)->for($this->exploitation)->create();

    $this->actingAs($this->technician)
        ->get(route('farmers.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('farmers/index')
            ->has('farmers.data', 20)
            ->where('farmers.total', 25)
            ->where('farmers.last_page', 2),
        );
});

test('the list can be searched by name, village or phone', function () {
    Farmer::factory()->for($this->exploitation)->create([
        'first_name' => 'Kossi',
        'last_name' => 'Amegan',
        'village' => 'Bassar',
    ]);
    Farmer::factory()->for($this->exploitation)->create([
        'first_name' => 'Afi',
        'last_name' => 'Doe',
        'village' => 'Kandé',
    ]);

    $this->actingAs($this->technician)
        ->get(route('farmers.index', ['recherche' => 'bassar']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('farmers.data', 1)
            ->where('farmers.data.0.last_name', 'Amegan'),
        );
});

test('a producer can be attached to a farmer account of the same exploitation', function () {
    $account = User::factory()->farmer()->for($this->exploitation)->create();

    $this->actingAs($this->technician)
        ->post(route('farmers.store'), [
            'first_name' => 'Kossi',
            'last_name' => 'Amegan',
            'village' => 'Bassar',
            'user_id' => $account->id,
        ])
        ->assertSessionHasNoErrors();

    expect(Farmer::firstWhere('user_id', $account->id))->not->toBeNull();
});

test('a producer cannot be attached to an account of another exploitation', function () {
    $foreignAccount = User::factory()->farmer()->create();

    $this->actingAs($this->technician)
        ->post(route('farmers.store'), [
            'first_name' => 'Kossi',
            'last_name' => 'Amegan',
            'village' => 'Bassar',
            'user_id' => $foreignAccount->id,
        ])
        ->assertSessionHasErrors('user_id');
});

test('a producer cannot be attached to a technician account', function () {
    $technicianAccount = User::factory()->technician()->for($this->exploitation)->create();

    $this->actingAs($this->technician)
        ->post(route('farmers.store'), [
            'first_name' => 'Kossi',
            'last_name' => 'Amegan',
            'village' => 'Bassar',
            'user_id' => $technicianAccount->id,
        ])
        ->assertSessionHasErrors('user_id');
});

test('an account cannot be attached to two producers', function () {
    $account = User::factory()->farmer()->for($this->exploitation)->create();
    Farmer::factory()->for($this->exploitation)->create(['user_id' => $account->id]);

    $this->actingAs($this->technician)
        ->post(route('farmers.store'), [
            'first_name' => 'Kossi',
            'last_name' => 'Amegan',
            'village' => 'Bassar',
            'user_id' => $account->id,
        ])
        ->assertSessionHasErrors('user_id');
});

test('the creation form only offers unattached accounts of the exploitation', function () {
    $free = User::factory()->farmer()->for($this->exploitation)->create(['name' => 'Compte libre']);
    $taken = User::factory()->farmer()->for($this->exploitation)->create(['name' => 'Compte pris']);
    Farmer::factory()->for($this->exploitation)->create(['user_id' => $taken->id]);
    User::factory()->farmer()->create(['name' => 'Compte voisin']);

    $this->actingAs($this->technician)
        ->get(route('farmers.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('farmers/create')
            ->has('availableAccounts', 1)
            ->where('availableAccounts.0.id', $free->id),
        );
});

test('a producer is not visible to the farmer role', function () {
    $farmer = User::factory()->farmer()->for($this->exploitation)->create();

    $this->actingAs($farmer)
        ->get(route('farmers.index'))
        ->assertForbidden();
});
