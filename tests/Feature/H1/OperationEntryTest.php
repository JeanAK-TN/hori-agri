<?php

use App\Enums\CycleStatus;
use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\QuantityUnit;
use App\Models\Campaign;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Farmer;
use App\Models\Operation;
use App\Models\Plot;
use App\Models\User;

/*
| Hypothèse H1 : la saisie de l'itinéraire technique est possible sur un
| téléphone d'entrée de gamme (SPEC section 14.2).
*/

beforeEach(function () {
    $this->cooperative = Cooperative::factory()->create();
    $this->technician = User::factory()->technician()->for($this->cooperative)->create();

    $farmer = Farmer::factory()->for($this->cooperative)->create();
    $plot = Plot::factory()->ofFarmer($farmer)->create();
    $campaign = Campaign::factory()
        ->for($this->cooperative)
        ->between('2026-03-01', '2026-11-30')
        ->create(['label' => '2026 grande saison']);

    $this->cycle = CropCycle::factory()
        ->on($plot, $campaign, Crop::factory()->named('Maïs')->create())
        ->create();

    $this->actingAs($this->technician);
});

test('a technician records a complete operation and it is saved', function () {
    $response = $this->post(route('operations.store', $this->cycle), [
        'type' => OperationType::Fertilization->value,
        'input_type' => InputType::Npk->value,
        'date' => '2026-04-20',
        'product' => 'NPK 15-15-15',
        'quantity' => 2,
        'unit' => QuantityUnit::Bag50Kg->value,
        'cost' => 24000,
        'notes' => 'Épandage à la volée',
    ]);

    $response->assertRedirect(route('cycles.show', $this->cycle));

    $operation = Operation::firstWhere('crop_cycle_id', $this->cycle->id);

    expect($operation->cooperative_id)->toBe($this->cooperative->id)
        ->and($operation->type)->toBe(OperationType::Fertilization)
        ->and($operation->input_type)->toBe(InputType::Npk)
        ->and($operation->unit)->toBe(QuantityUnit::Bag50Kg)
        ->and((float) $operation->quantity)->toBe(2.0)
        ->and((float) $operation->cost)->toBe(24000.0);
});

test('the quantity is recorded as counted in the field, never as a dose per hectare', function () {
    $this->post(route('operations.store', $this->cycle), [
        'type' => OperationType::Fertilization->value,
        'input_type' => InputType::Npk->value,
        'date' => '2026-04-20',
        'quantity' => 2,
        'unit' => QuantityUnit::Bag50Kg->value,
    ])->assertSessionHasNoErrors();

    $operation = Operation::firstWhere('crop_cycle_id', $this->cycle->id);

    // Deux sacs de 50 kg pour la parcelle entière : la conversion en
    // kilogrammes est faite par le modèle, jamais demandée au technicien.
    expect($operation->quantityInKilograms())->toBe(100.0);
});

test('a quantity in litres does not convert to kilograms', function () {
    $this->post(route('operations.store', $this->cycle), [
        'type' => OperationType::Treatment->value,
        'input_type' => InputType::Herbicide->value,
        'date' => '2026-04-20',
        'quantity' => 3,
        'unit' => QuantityUnit::Litre->value,
    ])->assertSessionHasNoErrors();

    expect(Operation::first()->quantityInKilograms())->toBeNull();
});

/*
| SPEC section 6.3 : input_type est obligatoire pour les types qui
| apportent un produit, et doit être nul pour les autres.
*/

test('input_type is required for an operation that brings a product', function (OperationType $type) {
    $this->post(route('operations.store', $this->cycle), [
        'type' => $type->value,
        'date' => '2026-04-20',
    ])->assertSessionHasErrors('input_type');

    expect(Operation::count())->toBe(0);
})->with([
    'semis' => OperationType::Sowing,
    'fertilisation' => OperationType::Fertilization,
    'traitement' => OperationType::Treatment,
]);

test('input_type is refused on an operation that brings nothing', function (OperationType $type) {
    $this->post(route('operations.store', $this->cycle), [
        'type' => $type->value,
        'input_type' => InputType::Npk->value,
        'date' => '2026-04-20',
    ])->assertSessionHasErrors('input_type');

    expect(Operation::count())->toBe(0);
})->with([
    'labour' => OperationType::Tillage,
    'sarclage' => OperationType::Weeding,
    'irrigation' => OperationType::Irrigation,
    'récolte' => OperationType::Harvest,
]);

test('an operation that brings nothing is accepted without input_type', function (OperationType $type) {
    $this->post(route('operations.store', $this->cycle), [
        'type' => $type->value,
        'date' => '2026-04-20',
    ])->assertSessionHasNoErrors();

    expect(Operation::latest('id')->first()->input_type)->toBeNull();
})->with([
    'labour' => OperationType::Tillage,
    'sarclage' => OperationType::Weeding,
    'irrigation' => OperationType::Irrigation,
    'récolte' => OperationType::Harvest,
]);

test('an empty input_type field is accepted on an operation that brings nothing', function () {
    // Le formulaire envoie le champ vide plutôt que de l'omettre :
    // Laravel le convertit en null, qui doit rester acceptable.
    $this->post(route('operations.store', $this->cycle), [
        'type' => OperationType::Tillage->value,
        'input_type' => '',
        'date' => '2026-04-20',
        'quantity' => '',
        'unit' => '',
        'cost' => '',
        'product' => '',
        'notes' => '',
    ])->assertSessionHasNoErrors();

    expect(Operation::count())->toBe(1)
        ->and(Operation::first()->input_type)->toBeNull();
});

/*
| Contrôle des dates contre les bornes de la campagne, sans perte de la
| saisie déjà faite.
*/

test('a date outside the campaign is refused and the entry is kept', function (string $date) {
    $payload = [
        'type' => OperationType::Fertilization->value,
        'input_type' => InputType::Npk->value,
        'date' => $date,
        'product' => 'NPK 15-15-15',
        'quantity' => 2,
        'unit' => QuantityUnit::Bag50Kg->value,
        'cost' => 24000,
    ];

    $response = $this->post(route('operations.store', $this->cycle), $payload);

    $response->assertSessionHasErrors('date');

    // La saisie déjà faite revient avec la réponse : le technicien
    // corrige la date sans tout ressaisir (SPEC section 11.1).
    $response->assertSessionHasInput('product', 'NPK 15-15-15');
    $response->assertSessionHasInput('quantity', 2);
    $response->assertSessionHasInput('cost', 24000);

    expect(Operation::count())->toBe(0);
})->with([
    'avant la campagne' => '2026-02-28',
    'après la campagne' => '2026-12-01',
]);

test('the message names both bounds of the campaign', function () {
    $this->post(route('operations.store', $this->cycle), [
        'type' => OperationType::Tillage->value,
        'date' => '2026-12-01',
    ])->assertSessionHasErrors([
        'date' => 'La date doit tomber entre le 01/03/2026 et le 30/11/2026, bornes de la campagne « 2026 grande saison ».',
    ]);
});

test('the bounds of the campaign are included', function (string $date) {
    $this->post(route('operations.store', $this->cycle), [
        'type' => OperationType::Tillage->value,
        'date' => $date,
    ])->assertSessionHasNoErrors();
})->with([
    'premier jour' => '2026-03-01',
    'dernier jour' => '2026-11-30',
]);

/*
| Effets de bord de la saisie.
*/

test('the first operation moves the cycle from planned to in progress', function () {
    expect($this->cycle->status)->toBe(CycleStatus::Planned);

    $this->post(route('operations.store', $this->cycle), [
        'type' => OperationType::Tillage->value,
        'date' => '2026-03-15',
    ])->assertSessionHasNoErrors();

    expect($this->cycle->fresh()->status)->toBe(CycleStatus::InProgress);
});

test('a unit is required as soon as a quantity is given', function () {
    $this->post(route('operations.store', $this->cycle), [
        'type' => OperationType::Fertilization->value,
        'input_type' => InputType::Npk->value,
        'date' => '2026-04-20',
        'quantity' => 2,
    ])->assertSessionHasErrors('unit');
});

test('a closed cycle no longer accepts an operation', function () {
    $this->cycle->close();

    $this->post(route('operations.store', $this->cycle), [
        'type' => OperationType::Tillage->value,
        'date' => '2026-04-20',
    ])->assertForbidden();

    expect(Operation::count())->toBe(0);
});

test('only a technician records an operation', function () {
    $coopAdmin = User::factory()->coopAdmin()->for($this->cooperative)->create();

    $this->actingAs($coopAdmin)
        ->post(route('operations.store', $this->cycle), [
            'type' => OperationType::Tillage->value,
            'date' => '2026-04-20',
        ])
        ->assertForbidden();
});

test('an operation is corrected then removed', function () {
    $operation = Operation::factory()->forCycle($this->cycle)->create([
        'type' => OperationType::Tillage,
        'date' => '2026-03-10',
    ]);

    $this->patch(route('operations.update', [$this->cycle, $operation]), [
        'type' => OperationType::Weeding->value,
        'date' => '2026-05-10',
    ])->assertSessionHasNoErrors();

    expect($operation->fresh()->type)->toBe(OperationType::Weeding);

    $this->delete(route('operations.destroy', [$this->cycle, $operation]))
        ->assertRedirect(route('cycles.show', $this->cycle));

    expect(Operation::count())->toBe(0);
});
