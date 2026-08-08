<?php

use App\Enums\Severity;
use App\Enums\ThresholdStatus;
use App\Models\Cooperative;
use App\Models\Rule;
use App\Models\User;

/*
| La base de règles vue depuis le navigateur : l'origine du seuil ne se
| replie jamais, les champs interrogeables sont dits, et surcharger un
| seuil ne touche pas la règle commune (SPEC sections 10.4 et 11).
|
| Rappel : une option Radix se clique par [role=option]:has-text("...") —
| le texte choisi se retrouve aussi sur le déclencheur, et Playwright
| bloque alors sur deux correspondances.
*/

beforeEach(function () {
    $this->cooperative = Cooperative::factory()->create();
    $this->coopAdmin = User::factory()->coopAdmin()->for($this->cooperative)->create();
});

test('l origine de chaque seuil est lisible sans rien déplier', function () {
    Rule::factory()->code('FERT-MAIS-01')->create([
        'source' => 'ITRA, fiche technique maïs 2021, page 12',
        'threshold_status' => ThresholdStatus::Sourced,
    ]);
    Rule::factory()->code('COUT-PAIR-01')->convention()->create([
        'source' => 'SEUIL SIMULÉ — à remplacer par la référence publiée',
    ]);

    $this->actingAs($this->coopAdmin);

    $page = visit('/rules')->resize(360, 800);

    $page->assertSee('Source togolaise publiée')
        ->assertSee('ITRA, fiche technique maïs 2021, page 12')
        ->assertSee('Convention à confirmer')
        ->assertSee('SEUIL SIMULÉ')
        // Une convention arrive inactive tant que la coopérative ne l'a
        // pas confirmée (SPEC section 10.4).
        ->assertSee('Inactive')
        ->assertNoJavaScriptErrors()
        ->assertScript(
            'document.documentElement.scrollWidth <= document.documentElement.clientWidth',
        );

    // La source est du texte de la page, pas le contenu d'un <details>.
    $page->assertScript(
        "[...document.querySelectorAll('details')]
            .some((d) => d.textContent.includes('ITRA, fiche technique'))",
        false,
    );
});

test('les quinze champs interrogeables sont annoncés', function () {
    Rule::factory()->create();

    $this->actingAs($this->coopAdmin);

    visit('/rules')
        ->assertSee('Les 15 champs interrogeables')
        ->assertSee('mois de semis')
        ->assertSee('dose de NPK')
        ->assertSee('rendement face à la médiane de la zone')
        ->assertNoJavaScriptErrors();
});

test('surcharger un seuil laisse la règle commune intacte', function () {
    $common = Rule::factory()->code('FERT-MAIS-01')->create([
        'condition' => ['field' => 'npk_dose_kg_ha', 'op' => '<', 'value' => 100],
        'severity' => Severity::Medium,
    ]);

    $this->actingAs($this->coopAdmin);

    $page = visit('/rules')->resize(390, 844);

    $page->click('[data-test=override-FERT-MAIS-01]')
        ->assertSee('Surcharger FERT-MAIS-01')
        ->fill('#condition', json_encode(
            ['field' => 'npk_dose_kg_ha', 'op' => '<', 'value' => 120],
        ))
        ->click('#severity')
        ->click('[role=option]:has-text("Élevée")')
        ->click('[data-test=save-override]');

    $page->assertSee('Seuil surchargé pour votre coopérative')
        ->assertSee('Surcharge de votre coopérative')
        ->assertNoJavaScriptErrors();

    expect($common->fresh()->condition['value'])->toBe(100)
        ->and($common->fresh()->severity)->toBe(Severity::Medium);

    $override = Rule::withoutGlobalScopes()
        ->where('cooperative_id', $this->cooperative->id)
        ->sole();

    expect($override->code)->toBe('FERT-MAIS-01')
        ->and($override->condition['value'])->toBe(120)
        ->and($override->severity)->toBe(Severity::High);
});

test('une condition mal formée est refusée sans quitter la boîte', function () {
    Rule::factory()->code('FERT-MAIS-01')->create();

    $this->actingAs($this->coopAdmin);

    $page = visit('/rules');

    $page->click('[data-test=override-FERT-MAIS-01]')
        ->fill('#condition', json_encode(
            ['field' => 'champ_inconnu', 'op' => '=', 'value' => 4],
        ))
        ->click('[data-test=save-override]');

    $page->assertSee('champ_inconnu')
        // La boîte reste ouverte : la saisie n'est pas perdue.
        ->assertSee('Surcharger FERT-MAIS-01')
        ->assertNoJavaScriptErrors();

    expect(Rule::withoutGlobalScopes()->count())->toBe(1);
});

test('un super_admin maintient la base commune et ne surcharge pas', function () {
    Rule::factory()->code('FERT-MAIS-01')->create();

    $this->actingAs(User::factory()->superAdmin()->create());

    visit('/rules')
        ->assertSee('FERT-MAIS-01')
        ->assertDontSee('Surcharger')
        ->assertNoJavaScriptErrors();
});
