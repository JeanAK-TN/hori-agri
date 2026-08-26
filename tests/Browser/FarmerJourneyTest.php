<?php

use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\User;

/*
| Parcours complet d'un technicien sur un téléphone d'entrée de gamme,
| de la connexion à la fiche du producteur. C'est la seule vérification
| qui exerce réellement React, Inertia et la validation de bout en bout.
*/

test('un technicien se connecte et enregistre un producteur depuis un téléphone', function () {
    $exploitation = Exploitation::factory()->create();
    User::factory()->technician()->for($exploitation)->create([
        'phone' => '90112233',
    ]);

    $page = visit('/login')->resize(360, 800);

    $page->assertNoJavaScriptErrors()
        ->fill('phone', '90112233')
        ->fill('password', 'password')
        ->click('Se connecter')
        // La racine mène le technicien à ses cycles culturaux
        // (SPEC-CORRECTION section 9).
        ->assertPathIs('/cycles');

    $page->navigate('/farmers/create')
        ->assertSee('Nouveau producteur')
        ->fill('first_name', 'Kossi')
        ->fill('last_name', 'Amegan')
        ->fill('village', 'Bassar')
        ->fill('phone', '91 22 33 44')
        ->click('Enregistrer le producteur');

    // La fiche du producteur qui vient d'être créé s'affiche.
    $page->assertSee('Kossi Amegan')
        ->assertSee('Bassar')
        ->assertNoJavaScriptErrors();

    $farmer = Farmer::withoutGlobalScopes()->firstWhere('last_name', 'Amegan');

    expect($farmer)->not->toBeNull()
        ->and($farmer->exploitation_id)->toBe($exploitation->id)
        ->and($farmer->phone)->toBe('91223344');
});

test('la validation renvoie ses messages en français sans perdre la saisie', function () {
    $exploitation = Exploitation::factory()->create();
    $technician = User::factory()->technician()->for($exploitation)->create();

    $this->actingAs($technician);

    $page = visit('/farmers/create')->resize(360, 800);

    // Une année de naissance dans le futur : le navigateur ne peut pas
    // l'intercepter, c'est le serveur qui refuse.
    $page->fill('first_name', 'Kossi')
        ->fill('last_name', 'Amegan')
        ->fill('village', 'Bassar')
        ->fill('birth_year', '2100')
        ->click('Enregistrer le producteur')
        ->assertSee('Le champ année de naissance ne peut pas être supérieur à')
        ->assertNoJavaScriptErrors();

    // La saisie déjà faite est conservée : le technicien ne recommence pas.
    $page->assertValue('first_name', 'Kossi')
        ->assertValue('last_name', 'Amegan')
        ->assertValue('village', 'Bassar');
});

test('la recherche filtre la liste sans recharger la page entière', function () {
    $exploitation = Exploitation::factory()->create();
    $technician = User::factory()->technician()->for($exploitation)->create();

    Farmer::factory()->for($exploitation)->create([
        'first_name' => 'Kossi',
        'last_name' => 'Amegan',
        'village' => 'Bassar',
    ]);
    Farmer::factory()->for($exploitation)->create([
        'first_name' => 'Afi',
        'last_name' => 'Doe',
        'village' => 'Kandé',
    ]);

    $this->actingAs($technician);

    $page = visit('/farmers');

    $page->assertSee('Amegan')
        ->assertSee('Doe')
        ->fill('recherche', 'Bassar')
        ->click('Rechercher')
        ->assertSee('Amegan')
        ->assertDontSee('Doe')
        ->assertQueryStringHas('recherche', 'Bassar')
        ->assertNoJavaScriptErrors();
});
