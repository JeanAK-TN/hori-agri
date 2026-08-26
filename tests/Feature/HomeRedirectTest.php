<?php

use App\Models\Exploitation;
use App\Models\User;

/*
| SPEC-CORRECTION section 9 : la page d'accueil du kit de départ a
| disparu, et la racine mène chacun à son premier écran.
*/

test('a guest reaching the root is sent to the login screen', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('the root sends each role to its own first screen', function (string $state, string $route) {
    $user = $state === 'superAdmin'
        ? User::factory()->superAdmin()->create()
        : User::factory()->{$state}()->for(Exploitation::factory())->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route($route));
})->with([
    'super_admin' => ['superAdmin', 'exploitations.index'],
    'exploitation_admin' => ['exploitationAdmin', 'exploitation-dashboard.index'],
    'technician' => ['technician', 'cycles.index'],
    'farmer' => ['farmer', 'recommendations.index'],
]);

test('the screen of the starter kit no longer exists', function () {
    expect(file_exists(resource_path('js/pages/dashboard.tsx')))->toBeFalse()
        ->and(app('router')->getRoutes()->getByName('dashboard'))->toBeNull();
});
