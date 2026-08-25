<?php

use App\Enums\RecoStatus;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Feedback;
use App\Models\Plot;
use App\Models\Recommendation;
use App\Models\Rule;
use App\Models\User;

/*
| La restitution vue depuis un téléphone : les quatre blocs dans l'ordre
| imposé, la source jamais repliée, et le retour de terrain qui tient en
| deux gestes (SPEC section 11.2).
*/

beforeEach(function () {
    $this->exploitation = Exploitation::factory()->create();
    $this->account = User::factory()->farmer()->for($this->exploitation)->create();
    $this->farmer = Farmer::factory()->for($this->exploitation)
        ->inVillage('Bassar')
        ->create(['user_id' => $this->account->id]);

    $crop = Crop::factory()->named('Maïs')->create();
    $campaign = Campaign::factory()->for($this->exploitation)
        ->between('2026-03-01', '2026-11-30')->create();
    $plot = Plot::factory()->ofFarmer($this->farmer)->withArea(1.0)->create();

    $this->cycle = CropCycle::factory()->on($plot, $campaign, $crop)->create();

    $rule = Rule::factory()->create([
        'action' => 'Apporter 150 kg de NPK par hectare au semis.',
        'source' => 'ITRA, fiche technique maïs 2021, page 12',
        'estimated_gain' => '+400 kg/ha',
    ]);

    $this->recommendation = Recommendation::factory()
        ->forCycle($this->cycle)
        ->fromRule($rule)
        ->saying('Dose de NPK observée : 60 kg/ha, en dessous des 150 kg/ha attendus.')
        ->create();

    $this->actingAs($this->account);
});

test('les quatre blocs se lisent dans l ordre, source comprise', function () {
    $page = visit('/recommandations')->resize(360, 800);

    $page->assertSee('Constat')
        ->assertSee('Dose de NPK observée : 60 kg/ha')
        ->assertSee('À faire')
        ->assertSee('Apporter 150 kg de NPK par hectare au semis.')
        ->assertSee('Source')
        ->assertSee('ITRA, fiche technique maïs 2021, page 12')
        ->assertSee('Gain estimé')
        ->assertSee('+400 kg/ha')
        ->assertNoJavaScriptErrors()
        ->assertScript(
            'document.documentElement.scrollWidth <= document.documentElement.clientWidth',
        );

    // L'ordre imposé, lu dans le document lui-même.
    $page->assertScript(
        "(() => {
            const card = document.querySelector('[data-test^=recommendation-]');
            const order = ['finding', 'action', 'source', 'estimated-gain'];
            const found = [...card.querySelectorAll('[data-test]')]
                .map((node) => node.dataset.test)
                .filter((name) => order.includes(name));
            return JSON.stringify(found);
        })()",
        json_encode(['finding', 'action', 'source', 'estimated-gain']),
    );
});

test('la source n est derrière aucun repli', function () {
    $page = visit('/recommandations')->resize(360, 800);

    // Ni <details>, ni conteneur masqué : la source est du texte visible.
    $page->assertScript("document.querySelectorAll('details').length", 0)
        ->assertScript(
            "(() => {
                const source = document.querySelector('[data-test=source]');
                const box = source.getBoundingClientRect();
                return box.width > 0 && box.height > 0
                    && getComputedStyle(source).visibility === 'visible';
            })()",
            true,
        )
        ->assertNoJavaScriptErrors();
});

test('le producteur enregistre ce qu il a fait et ce qu il a observé', function () {
    $id = $this->recommendation->id;

    $page = visit('/recommandations')->resize(390, 844);

    $page->click("[data-test=apply-{$id}]")
        ->assertSee('Ce que vous avez fait')
        ->fill('#comment-'.$id, 'Deux sacs de NPK épandus le 12 juin.')
        ->fill('#result-'.$id, 'Le maïs a mieux tenu la sécheresse.')
        ->click('[data-test=save-feedback]');

    $page->assertSee('Retour enregistré : recommandation appliquée')
        ->assertSee('Vous avez appliqué ce conseil.')
        ->assertSee('Deux sacs de NPK épandus le 12 juin.')
        ->assertNoJavaScriptErrors();

    expect($this->recommendation->fresh()->status)->toBe(RecoStatus::Applied)
        ->and(Feedback::first()->farmer_id)->toBe($this->farmer->id);
});

test('écarter un conseil tient en un geste', function () {
    $id = $this->recommendation->id;

    $page = visit('/recommandations')->resize(360, 800);

    $page->click("[data-test=dismiss-{$id}]")
        ->assertSee('Recommandation écartée')
        ->assertNoJavaScriptErrors();

    expect($this->recommendation->fresh()->status)->toBe(RecoStatus::Dismissed);
});

test('le technicien lit mais ne répond pas à la place du producteur', function () {
    $this->actingAs(User::factory()->technician()->for($this->exploitation)->create());

    visit('/recommandations')
        ->assertSee('Dose de NPK observée')
        ->assertSee('ITRA, fiche technique maïs 2021, page 12')
        ->assertDontSee("J'ai appliqué")
        ->assertDontSee('Écarter')
        ->assertNoJavaScriptErrors();
});

test('un producteur sans conseil voit un état vide et non une page nue', function () {
    $this->recommendation->delete();

    visit('/recommandations')
        ->assertSee('Aucune recommandation')
        ->assertSee("Les conseils apparaissent d'eux-mêmes")
        ->assertNoJavaScriptErrors();
});
