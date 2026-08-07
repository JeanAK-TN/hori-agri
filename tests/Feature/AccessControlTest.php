<?php

use App\Enums\Role;
use App\Models\Cooperative;
use App\Models\User;

test('each of the four roles can sign in with their phone number', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $response = $this->post(route('login.store'), [
        'phone' => $user->phone,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
})->with(['superAdmin', 'coopAdmin', 'technician', 'farmer']);

test('a super_admin must not belong to a cooperative', function () {
    expect(fn () => User::factory()->create([
        'role' => Role::SuperAdmin,
        'cooperative_id' => Cooperative::factory(),
    ]))->toThrow(LogicException::class);
});

test('any other role must belong to a cooperative', function (string $role) {
    expect(fn () => User::factory()->create([
        'role' => $role,
        'cooperative_id' => null,
    ]))->toThrow(LogicException::class);
})->with(['coop_admin', 'technician', 'farmer']);

test('the cooperatives screen is reserved for the super_admin', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('cooperatives.index'))
        ->assertOk();

    foreach (['coopAdmin', 'technician', 'farmer'] as $state) {
        $this->actingAs(User::factory()->{$state}()->create())
            ->get(route('cooperatives.index'))
            ->assertForbidden();
    }
});

test('the accounts screen is reserved for administrators', function () {
    foreach (['superAdmin', 'coopAdmin'] as $state) {
        $this->actingAs(User::factory()->{$state}()->create())
            ->get(route('users.index'))
            ->assertOk();
    }

    foreach (['technician', 'farmer'] as $state) {
        $this->actingAs(User::factory()->{$state}()->create())
            ->get(route('users.index'))
            ->assertForbidden();
    }
});

test('a technician cannot create an account', function () {
    $technician = User::factory()->technician()->create();

    $this->actingAs($technician)
        ->post(route('users.store'), [
            'name' => 'Nouveau',
            'phone' => '91000000',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'role' => Role::Farmer->value,
        ])
        ->assertForbidden();
});

test('a cooperative administrator cannot create an account outside their cooperative', function () {
    $mine = Cooperative::factory()->create();
    $other = Cooperative::factory()->create();
    $admin = User::factory()->coopAdmin()->for($mine)->create();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Technicien',
            'phone' => '91000001',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'role' => Role::Technician->value,
            'cooperative_id' => $other->id,
        ])
        ->assertRedirect(route('users.index'));

    expect(User::where('phone', '91000001')->first()->cooperative_id)
        ->toBe($mine->id);
});

test('a cooperative administrator cannot create a super_admin', function () {
    $admin = User::factory()->coopAdmin()->create();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Escalade',
            'phone' => '91000002',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'role' => Role::SuperAdmin->value,
            'cooperative_id' => $admin->cooperative_id,
        ])
        ->assertSessionHasErrors('role');
});

test('a phone number typed with separators is stored normalized', function () {
    $admin = User::factory()->coopAdmin()->create();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Technicien',
            'phone' => '91 00-00.03',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'role' => Role::Technician->value,
        ]);

    expect(User::where('phone', '91000003')->exists())->toBeTrue();
});

test('a member of a suspended cooperative cannot sign in', function () {
    $cooperative = Cooperative::factory()->suspended()->create();
    $technician = User::factory()->technician()->for($cooperative)->create();

    $this->post(route('login.store'), [
        'phone' => $technician->phone,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('a member is signed out when their cooperative is suspended mid-session', function () {
    $cooperative = Cooperative::factory()->create();
    $technician = User::factory()->technician()->for($cooperative)->create();

    $this->actingAs($technician)->get(route('dashboard'))->assertOk();

    $cooperative->suspend();

    $this->actingAs($technician->fresh())
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('the super_admin is never blocked by a suspension', function () {
    Cooperative::factory()->suspended()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)->get(route('dashboard'))->assertOk();
});
