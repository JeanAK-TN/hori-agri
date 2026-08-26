<?php

use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Plot;
use App\Models\User;
use Database\Seeders\CropSeeder;
use Database\Seeders\VarietySeeder;

/*
| Parcours des écrans dans un vrai navigateur : aucune erreur JavaScript,
| aucun message resté en anglais. C'est le filet qui manque aux tests
| fonctionnels, qui n'exécutent jamais le code React.
*/

test('les écrans du technicien se chargent sans erreur JavaScript', function () {
    $exploitation = Exploitation::factory()->create();
    $technician = User::factory()->technician()->for($exploitation)->create();
    $farmer = Farmer::factory()->for($exploitation)->create();
    $plot = Plot::factory()->ofFarmer($farmer)->create();
    $campaign = Campaign::factory()->for($exploitation)->create();
    $cycle = CropCycle::factory()
        ->on($plot, $campaign, Crop::factory()->create())
        ->create();

    $this->actingAs($technician);

    $pages = visit([
        '/',
        '/farmers',
        '/farmers/create',
        "/farmers/{$farmer->id}",
        "/farmers/{$farmer->id}/edit",
        '/plots',
        '/plots/create',
        "/plots/{$plot->id}",
        "/plots/{$plot->id}/edit",
        '/campaigns',
        '/cycles',
        '/cycles/create',
        "/cycles/{$cycle->id}",
        '/imports',
        '/recommandations',
        '/settings/profile',
        '/settings/appearance',
    ]);

    $pages->assertNoJavaScriptErrors()->assertNoConsoleLogs();
});

test('les écrans de l administrateur d exploitation se chargent sans erreur JavaScript', function () {
    $this->actingAs(User::factory()->exploitationAdmin()->create());

    visit([
        '/',
        '/tableau-de-bord',
        '/users',
        '/farmers',
        '/plots',
        '/campaigns',
        '/cycles',
        '/regles',
    ])
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

test('les écrans de l agriculteur se chargent sans erreur JavaScript', function () {
    $exploitation = Exploitation::factory()->create();
    $account = User::factory()->farmer()->for($exploitation)->create();
    Farmer::factory()->for($exploitation)->create(['user_id' => $account->id]);

    $this->actingAs($account);

    visit(['/', '/recommandations', '/settings/profile'])
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

test('les écrans du super administrateur se chargent sans erreur JavaScript', function () {
    Exploitation::factory()->count(2)->create();
    $this->seed(CropSeeder::class);
    $this->seed(VarietySeeder::class);

    $this->actingAs(User::factory()->superAdmin()->create());

    visit(['/', '/exploitations', '/users', '/crops', '/regles'])
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

test('la page de connexion est en français et sans erreur', function () {
    visit('/login')
        ->assertNoJavaScriptErrors()
        ->assertSee('Numéro de téléphone')
        ->assertSee('Se connecter')
        ->assertSee("Les comptes sont créés par l'administrateur de votre exploitation.")
        ->assertDontSee('Log in')
        ->assertDontSee('Email address');
});
