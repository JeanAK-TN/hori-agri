<?php

use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\User;

/*
| SPEC section 11.4 : « Aucun tableau dense sur petit écran : sous 640
| pixels de large, les listes deviennent des cartes empilées. »
|
| Et règle absolue 5 : « Cibles tactiles d'au moins 48 pixels. »
|
| Ces deux exigences sont purement visuelles. Elles ne peuvent être
| prouvées que dans un navigateur réel, en mesurant la mise en page.
*/

beforeEach(function () {
    $this->exploitation = Exploitation::factory()->create();
    $this->technician = User::factory()->technician()->for($this->exploitation)->create();

    Farmer::factory()->count(3)->for($this->exploitation)->create();

    $this->actingAs($this->technician);
});

test('la liste des producteurs devient des cartes empilées sur un téléphone', function () {
    $page = visit('/farmers')->on()->mobile();

    $page->assertNoJavaScriptErrors()
        // La liste en cartes est affichée, le tableau dense est masqué.
        ->assertScript("getComputedStyle(document.querySelector('ul.space-y-3')).display !== 'none'")
        ->assertScript("document.querySelector('[data-slot=table]').closest('div.hidden') !== null")
        ->assertScript("getComputedStyle(document.querySelector('[data-slot=table]').closest('div.hidden')).display === 'none'");
});

test('la liste des producteurs devient un tableau sur un grand écran', function () {
    $page = visit('/farmers')->on()->desktop();

    $page->assertNoJavaScriptErrors()
        ->assertScript("getComputedStyle(document.querySelector('[data-slot=table]').closest('div')).display !== 'none'")
        ->assertScript("getComputedStyle(document.querySelector('ul.space-y-3')).display === 'none'");
});

test('aucun débordement horizontal sur un écran de 360 pixels', function (string $path) {
    $farmer = Farmer::first();
    $url = str_replace('{id}', (string) $farmer->id, $path);

    $page = visit($url)->resize(360, 800);

    $page->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth');
})->with([
    'liste des producteurs' => '/farmers',
    'fiche producteur' => '/farmers/{id}',
    'création producteur' => '/farmers/create',
    'modification producteur' => '/farmers/{id}/edit',
    'accueil' => '/dashboard',
]);

test('les cibles tactiles atteignent 48 pixels sur un téléphone', function (string $path) {
    $farmer = Farmer::first();
    $url = str_replace('{id}', (string) $farmer->id, $path);

    $page = visit($url)->resize(360, 800);

    // Tout bouton, lien de navigation et champ de saisie visible doit
    // mesurer au moins 48 px de haut : la saisie se fait au pouce, en
    // plein champ (SPEC règle 5).
    //
    // Les éléments retirés du calque d'accessibilité ne sont pas des
    // cibles : Radix ajoute par exemple un <select> natif de 1 px,
    // masqué et aria-hidden, pour la soumission du formulaire.
    $page->assertNoJavaScriptErrors()->assertScript(<<<'JS'
        function () {
            const cibles = document.querySelectorAll('button, a[href], input:not([type=hidden]), select, [role=combobox]');
            const trop_petites = [];

            for (const cible of cibles) {
                const rect = cible.getBoundingClientRect();

                if (rect.width === 0 || rect.height === 0) {
                    continue;
                }

                if (cible.closest('[aria-hidden="true"], .sr-only')) {
                    continue;
                }

                if (rect.height < 48) {
                    trop_petites.push(cible.tagName + '.' + cible.className + ' = ' + Math.round(rect.height) + 'px');
                }
            }

            return trop_petites.length === 0 ? 'ok' : trop_petites.join(' | ');
        }
    JS, 'ok');
})->with([
    'connexion' => '/login',
    'création producteur' => '/farmers/create',
    'modification producteur' => '/farmers/{id}/edit',
]);

test('la barre de navigation basse remplace la barre latérale sur un téléphone', function () {
    $page = visit('/farmers')->resize(360, 800);

    $page->assertNoJavaScriptErrors()
        ->assertVisible('nav[aria-label="Navigation principale"]')
        ->assertScript("document.querySelectorAll('nav[aria-label=\"Navigation principale\"] a').length >= 2");
});

test('la barre de navigation basse disparaît sur un grand écran', function () {
    $page = visit('/farmers')->resize(1280, 900);

    $page->assertNoJavaScriptErrors()
        ->assertScript("getComputedStyle(document.querySelector('nav[aria-label=\"Navigation principale\"]')).display === 'none'");
});
