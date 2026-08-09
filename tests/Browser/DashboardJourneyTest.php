<?php

use App\Enums\RecoStatus;
use App\Enums\Severity;
use App\Models\Campaign;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\Recommendation;
use App\Models\Rule;
use App\Models\User;

/*
| Le tableau de bord vu du navigateur : les graphiques se dessinent, les
| filtres rechargent l'écran, et les mêmes chiffres se lisent aussi en
| table pour qui ne voit pas les couleurs (SPEC section 12).
|
| Rappel : une option Radix se clique par [role=option]:has-text("...").
*/

beforeEach(function () {
    $this->cooperative = Cooperative::factory()->create();
    $this->admin = User::factory()->coopAdmin()->for($this->cooperative)->create();

    $this->maize = Crop::factory()->named('Maïs')->create();
    $this->soy = Crop::factory()->named('Soja')->create();

    $this->campaign = Campaign::factory()->for($this->cooperative)
        ->between('2026-03-01', '2026-11-30')->create(['label' => 'Campagne 2026']);

    $this->actingAs($this->admin);
});

test('les nombres de tête et les graphiques apparaissent', function () {
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);
    cycleYielding($this->cooperative, $this->soy, $this->campaign, 'Kara', 1.0, 400, 30000);

    $page = visit('/tableau-de-bord')->resize(1280, 900);

    $page->assertSee('Cycles pris en compte')
        ->assertSee('Rendement moyen')
        ->assertSee('Coût moyen')
        ->assertSee('Marge brute moyenne')
        ->assertSee('Rendement moyen par culture')
        ->assertSee('Rendement moyen par village')
        ->assertNoJavaScriptErrors();

    // Chart.js a bien dessiné : un canvas par section de moyennes.
    $page->assertScript("document.querySelectorAll('canvas').length >= 3", true);

    // Les mêmes chiffres se lisent en table, sans dépendre de la couleur.
    $page->assertSee('Maïs')
        ->assertSee('1 000 kg/ha')
        ->assertSee('Soja')
        ->assertSee('400 kg/ha');
});

test('les filtres rechargent la synthèse', function () {
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);
    cycleYielding($this->cooperative, $this->soy, $this->campaign, 'Kara', 1.0, 400, 30000);

    $page = visit('/tableau-de-bord')->resize(1280, 900);

    $page->assertSee('Soja')
        ->click('#culture')
        ->click('[role=option]:has-text("Maïs")');

    $page->assertSee('Maïs')
        ->assertDontSee('Soja')
        ->assertNoJavaScriptErrors();
});

test('la part appliquée et les parcelles sous la médiane se lisent en clair', function () {
    $cycle = cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);

    Recommendation::factory()
        ->forCycle($cycle)
        ->fromRule(Rule::factory()->severity(Severity::High)->create())
        ->status(RecoStatus::Applied)
        ->create();

    $page = visit('/tableau-de-bord')->resize(1280, 900);

    $page->assertSee('Recommandations par sévérité')
        ->assertSee('Part des conseils appliqués')
        ->assertSee('100 %')
        ->assertSee('Parcelles sous la médiane de leur zone')
        // Le compte par sévérité est aussi écrit, pas seulement dessiné.
        ->assertSee('Élevée')
        ->assertNoJavaScriptErrors();
});

test('le tableau de bord tient sur un téléphone', function () {
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);

    $page = visit('/tableau-de-bord')->resize(360, 800);

    $page->assertSee('Tableau de bord')
        ->assertNoJavaScriptErrors()
        ->assertScript(
            'document.documentElement.scrollWidth <= document.documentElement.clientWidth',
        );
});

test('une coopérative sans récolte reçoit un état vide et non une page cassée', function () {
    $page = visit('/tableau-de-bord');

    $page->assertSee("Rien à agréger pour l'instant")
        ->assertSee('Les moyennes apparaissent dès qu\'un cycle porte une récolte')
        ->assertNoJavaScriptErrors();
});

test('le bouton d export pointe sur la synthèse filtrée', function () {
    cycleYielding($this->cooperative, $this->maize, $this->campaign, 'Bassar', 1.0, 1000, 50000);

    $page = visit('/tableau-de-bord?culture='.$this->maize->id);

    $page->assertSee('Exporter la synthèse')
        ->assertScript(
            "document.querySelector('[data-test=export-csv]').getAttribute('href')",
            '/tableau-de-bord/export?culture='.$this->maize->id,
        )
        ->assertNoJavaScriptErrors();
});
