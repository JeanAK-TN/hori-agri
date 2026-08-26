<?php

use App\Enums\Role;
use App\Models\Exploitation;
use App\Models\User;

test('each of the four roles can sign in with their phone number', function (string $state) {
    $user = User::factory()->{$state}()->create();

    $response = $this->post(route('login.store'), [
        'phone' => $user->phone,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('home', absolute: false));
})->with(['superAdmin', 'exploitationAdmin', 'technician', 'farmer']);

test('a super_admin must not belong to an exploitation', function () {
    expect(fn () => User::factory()->create([
        'role' => Role::SuperAdmin,
        'exploitation_id' => Exploitation::factory(),
    ]))->toThrow(LogicException::class);
});

test('any other role must belong to an exploitation', function (string $role) {
    expect(fn () => User::factory()->create([
        'role' => $role,
        'exploitation_id' => null,
    ]))->toThrow(LogicException::class);
})->with(['exploitation_admin', 'technician', 'farmer']);

test('the exploitations screen is reserved for the super_admin', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('exploitations.index'))
        ->assertOk();

    foreach (['exploitationAdmin', 'technician', 'farmer'] as $state) {
        $this->actingAs(User::factory()->{$state}()->create())
            ->get(route('exploitations.index'))
            ->assertForbidden();
    }
});

test('the accounts screen is reserved for administrators', function () {
    foreach (['superAdmin', 'exploitationAdmin'] as $state) {
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

test('an exploitation administrator cannot create an account outside their exploitation', function () {
    $mine = Exploitation::factory()->create();
    $other = Exploitation::factory()->create();
    $admin = User::factory()->exploitationAdmin()->for($mine)->create();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Technicien',
            'phone' => '91000001',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'role' => Role::Technician->value,
            'exploitation_id' => $other->id,
        ])
        ->assertRedirect(route('users.index'));

    expect(User::where('phone', '91000001')->first()->exploitation_id)
        ->toBe($mine->id);
});

test('an exploitation administrator cannot create a super_admin', function () {
    $admin = User::factory()->exploitationAdmin()->create();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Escalade',
            'phone' => '91000002',
            'password' => 'motdepasse-solide',
            'password_confirmation' => 'motdepasse-solide',
            'role' => Role::SuperAdmin->value,
            'exploitation_id' => $admin->exploitation_id,
        ])
        ->assertSessionHasErrors('role');
});

test('a phone number typed with separators is stored normalized', function () {
    $admin = User::factory()->exploitationAdmin()->create();

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

test('a member of a suspended exploitation cannot sign in', function () {
    $exploitation = Exploitation::factory()->suspended()->create();
    $technician = User::factory()->technician()->for($exploitation)->create();

    $this->post(route('login.store'), [
        'phone' => $technician->phone,
        'password' => 'password',
    ]);

    $this->assertGuest();
});

test('a member is signed out when their exploitation is suspended mid-session', function () {
    $exploitation = Exploitation::factory()->create();
    $technician = User::factory()->technician()->for($exploitation)->create();

    // Un écran réel plutôt que la racine, qui redirige toujours.
    $this->actingAs($technician)->get(route('cycles.index'))->assertOk();

    $exploitation->suspend();

    $this->actingAs($technician->fresh())
        ->get(route('cycles.index'))
        ->assertRedirect(route('login'));
});

test('the super_admin is never blocked by a suspension', function () {
    Exploitation::factory()->suspended()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)->get(route('exploitations.index'))->assertOk();
});
