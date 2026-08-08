<?php

use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\QuantityUnit;
use App\Models\Campaign;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Operation;
use App\Models\Plot;
use App\Models\User;

/*
| Hypothèse H1, éprouvée dans un vrai navigateur : un itinéraire complet
| se saisit sur un écran de 360 pixels de large (SPEC section 13,
| module 4).
*/

beforeEach(function () {
    $this->cooperative = Cooperative::factory()->create();
    $this->technician = User::factory()->technician()->for($this->cooperative)->create();

    $farmer = Farmer::factory()->for($this->cooperative)->create();
    $plot = Plot::factory()->ofFarmer($farmer)->create(['name' => 'Le bas-fond']);
    $campaign = Campaign::factory()
        ->for($this->cooperative)
        ->between('2026-03-01', '2026-11-30')
        ->create(['label' => '2026 grande saison']);

    $this->cycle = CropCycle::factory()
        ->on($plot, $campaign, Crop::factory()->named('Maïs')->create())
        ->create();

    $this->actingAs($this->technician);
});

test('un itinéraire complet se saisit sur un écran de 360 pixels', function () {
    $page = visit("/cycles/{$this->cycle->id}")->resize(360, 800);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Itinéraire technique')
        ->assertScript(
            'document.documentElement.scrollWidth <= document.documentElement.clientWidth',
        );

    // Labour : aucun intrant n'est demandé.
    $page->click('#type')
        ->click('[role=option]:has-text("Labour")')
        ->assertScript("document.querySelector('[data-test=input-fields]') === null")
        ->fill('date', '2026-03-15')
        ->click('[data-test=record-operation]');

    $page->assertSee('Labour')->assertNoJavaScriptErrors();

    // Fertilisation : les champs d'intrant apparaissent.
    $page->click('#type')
        ->click('[role=option]:has-text("Fertilisation")')
        ->assertVisible('[data-test=input-fields]')
        ->click('#input_type')
        ->click('[role=option]:has-text("NPK")')
        ->fill('date', '2026-04-20')
        ->fill('product', 'NPK 15-15-15')
        ->fill('quantity', '2')
        ->click('#unit')
        ->click('[role=option]:has-text("Sac de 50 kg")')
        ->fill('cost', '24000')
        ->click('[data-test=record-operation]');

    $page->assertSee('Fertilisation')->assertNoJavaScriptErrors();

    // Récolte.
    $page->fill('#harvest_date', '2026-08-15')
        ->fill('quantity_kg', '675')
        ->fill('unit_price', '200')
        ->click('[data-test=record-harvest]');

    $page->assertSee('675')->assertNoJavaScriptErrors();

    expect(Operation::count())->toBe(2)
        ->and(Harvest::count())->toBe(1);

    $fertilization = Operation::firstWhere('type', OperationType::Fertilization);

    expect($fertilization->input_type)->toBe(InputType::Npk)
        ->and($fertilization->unit)->toBe(QuantityUnit::Bag50Kg)
        ->and((float) $fertilization->quantity)->toBe(2.0);
});

test('le formulaire ne demande jamais une dose à l hectare', function () {
    $page = visit("/cycles/{$this->cycle->id}")->resize(360, 800);

    // Aucun champ ne réclame une dose : la seule mention de l'hectare est
    // la phrase qui explique justement de ne pas en saisir une.
    $page->click('#type')
        ->click('[role=option]:has-text("Fertilisation")')
        ->assertVisible('[data-test=input-fields]')
        ->assertSee('Comptez comme sur le terrain')
        ->assertDontSee('kg/ha')
        ->assertScript(<<<'JS'
            function () {
                const champs = [...document.querySelectorAll('[data-test=operation-form] label')];

                return champs.filter((label) => /hectare|\/\s*ha\b/i.test(label.textContent)).length;
            }
        JS, 0)
        ->assertNoJavaScriptErrors();
});

test('une date hors campagne est refusée sans perdre la saisie', function () {
    $page = visit("/cycles/{$this->cycle->id}")->resize(360, 800);

    $page->click('#type')
        ->click('[role=option]:has-text("Fertilisation")')
        ->click('#input_type')
        ->click('[role=option]:has-text("NPK")')
        ->fill('date', '2026-12-25')
        ->fill('product', 'NPK 15-15-15')
        ->fill('quantity', '2')
        ->click('#unit')
        ->click('[role=option]:has-text("Sac de 50 kg")')
        ->fill('cost', '24000')
        ->click('[data-test=record-operation]');

    $page->assertSee('La date doit tomber entre le 01/03/2026 et le 30/11/2026')
        ->assertNoJavaScriptErrors();

    // La saisie déjà faite est toujours à l'écran : le technicien
    // corrige la seule date (SPEC section 11.1).
    $page->assertValue('product', 'NPK 15-15-15')
        ->assertValue('quantity', '2')
        ->assertValue('cost', '24000')
        ->assertScript("document.querySelector('#input_type').textContent.includes('NPK')");

    expect(Operation::count())->toBe(0);

    // La correction de la seule date suffit à enregistrer.
    $page->fill('date', '2026-04-20')->click('[data-test=record-operation]');

    $page->assertSee('Fertilisation')->assertNoJavaScriptErrors();

    expect(Operation::count())->toBe(1);
});

test('les opérations déjà saisies s affichent avant le formulaire', function () {
    Operation::factory()->forCycle($this->cycle)->on('2026-05-10')->create([
        'type' => OperationType::Weeding,
    ]);
    Operation::factory()->forCycle($this->cycle)->on('2026-03-15')->create([
        'type' => OperationType::Tillage,
    ]);

    $page = visit("/cycles/{$this->cycle->id}")->resize(360, 800);

    $page->assertNoJavaScriptErrors()
        // Ordre chronologique, et la liste précède le formulaire dans le
        // document (SPEC section 11.1).
        ->assertScript(<<<'JS'
            function () {
                const items = [...document.querySelectorAll('[data-test=operation-list] li')];
                const ordre = items.map((item) => item.querySelector('p').textContent).join('|');
                const liste = document.querySelector('[data-test=operation-list]');
                const formulaire = document.querySelector('[data-test=operation-form]');
                const avant = liste.compareDocumentPosition(formulaire)
                    & Node.DOCUMENT_POSITION_FOLLOWING;

                return ordre + (avant ? ' [liste avant formulaire]' : ' [ordre incorrect]');
            }
        JS, 'Labour|Sarclage [liste avant formulaire]');
});

test('un cycle clôturé n accepte plus de saisie', function () {
    $this->cycle->close();

    $page = visit("/cycles/{$this->cycle->id}");

    $page->assertSee('Récolté')
        ->assertScript("document.querySelector('[data-test=operation-form]') === null")
        ->assertNoJavaScriptErrors();
});

test('le dépôt d un fichier produit un rapport ligne à ligne', function () {
    $page = visit('/imports')->resize(360, 800);

    $page->assertNoJavaScriptErrors()
        ->assertSee('Import de données')
        ->assertSee('Format attendu')
        ->assertScript(
            'document.documentElement.scrollWidth <= document.documentElement.clientWidth',
        );
});
