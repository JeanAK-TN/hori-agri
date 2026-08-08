<?php

use App\Models\Cooperative;
use App\Models\Farmer;
use App\Models\User;

/*
| Parcours des écrans dans un vrai navigateur : aucune erreur JavaScript,
| aucun message resté en anglais. C'est le filet qui manque aux tests
| fonctionnels, qui n'exécutent jamais le code React.
*/

test('les écrans du technicien se chargent sans erreur JavaScript', function () {
    $cooperative = Cooperative::factory()->create();
    $technician = User::factory()->technician()->for($cooperative)->create();
    $farmer = Farmer::factory()->for($cooperative)->create();

    $this->actingAs($technician);

    $pages = visit([
        '/dashboard',
        '/farmers',
        '/farmers/create',
        "/farmers/{$farmer->id}",
        "/farmers/{$farmer->id}/edit",
        '/settings/profile',
        '/settings/appearance',
    ]);

    $pages->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('les écrans de l administrateur de coopérative se chargent sans erreur JavaScript', function () {
    $this->actingAs(User::factory()->coopAdmin()->create());

    visit(['/dashboard', '/users', '/farmers'])
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

test('les écrans du super administrateur se chargent sans erreur JavaScript', function () {
    Cooperative::factory()->count(2)->create();

    $this->actingAs(User::factory()->superAdmin()->create());

    visit(['/dashboard', '/cooperatives', '/users'])
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

test('la page de connexion est en français et sans erreur', function () {
    visit('/login')
        ->assertNoJavaScriptErrors()
        ->assertSee('Numéro de téléphone')
        ->assertSee('Se connecter')
        ->assertSee("Les comptes sont créés par l'administrateur de votre coopérative.")
        ->assertDontSee('Log in')
        ->assertDontSee('Email address');
});
