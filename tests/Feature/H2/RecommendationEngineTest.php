<?php

use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\QuantityUnit;
use App\Enums\RecoStatus;
use App\Enums\RuleFamily;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Operation;
use App\Models\Plot;
use App\Models\Recommendation;
use App\Models\Rule;
use App\Models\User;
use App\Services\Rules\RecommendationService;
use Database\Seeders\RuleSeeder;

/*
| Hypothèse H2 : le moteur produit des recommandations explicables
| (SPEC section 14.2).
|
| Les règles utilisées ici sont des fixtures de test : les tests du
| moteur ne dépendent pas du contenu réel de rules.json.
*/

beforeEach(function () {
    $this->engine = app(RecommendationService::class);

    $this->exploitation = Exploitation::factory()->create();
    $this->crop = Crop::factory()->named('Tomate')->create();
    $this->campaign = Campaign::factory()
        ->for($this->exploitation)
        ->between('2026-03-01', '2026-11-30')
        ->create();

    $this->openCycle = function (float $yieldKg = 1000, string $village = 'Bassar'): CropCycle {
        $farmer = Farmer::factory()->for($this->exploitation)->inVillage($village)->create();
        $plot = Plot::factory()->ofFarmer($farmer)->withArea(1.0)->create();
        $cycle = CropCycle::factory()->on($plot, $this->campaign, $this->crop)->create([
            'sowing_date' => '2026-03-20',
            'transplanting_date' => '2026-04-10',
        ]);
        Harvest::factory()->forCycle($cycle)->of($yieldKg, 100)->create();

        return $cycle;
    };
});

test('the engine produces a recommendation on a cycle below the reference', function () {
    $cycle = ($this->openCycle)();

    Rule::factory()->code('FERT-TEST-01')->create([
        'condition' => ['field' => 'topdressing_npk_kg_ha', 'op' => '<', 'value' => 350],
        'message' => 'Apport de couverture insuffisant',
    ]);

    $this->engine->runFor($cycle);

    // La dose est nulle et non zéro : la règle ne se déclenche pas.
    expect(Recommendation::count())->toBe(0);

    Operation::factory()->forCycle($cycle)
        ->bringing(
            OperationType::Fertilization,
            InputType::Npk,
            1,
            QuantityUnit::Bag50Kg,
        )->on('2026-04-25')->create();

    $this->engine->runFor($cycle->fresh());

    $recommendation = Recommendation::first();

    expect($recommendation)->not->toBeNull()
        ->and($recommendation->status)->toBe(RecoStatus::New)
        ->and($recommendation->priority)->toBe(1)
        ->and($recommendation->exploitation_id)->toBe($this->exploitation->id)
        // Le constat est suivi des valeurs observées, entre parenthèses.
        ->and($recommendation->generated_message)
        ->toBe('Apport de couverture insuffisant (NPK de couverture cumulé : 50 kg/ha)');
});

test('the engine stays silent on a conforming cycle', function () {
    $cycle = ($this->openCycle)();

    Rule::factory()->code('CAL-TEST-01')->neverFiring()->create();

    $this->engine->runFor($cycle);

    expect(Recommendation::count())->toBe(0);
});

test('a cycle without any harvest produces nothing at all', function () {
    $farmer = Farmer::factory()->for($this->exploitation)->create();
    $plot = Plot::factory()->ofFarmer($farmer)->withArea(1.0)->create();
    $cycle = CropCycle::factory()->on($plot, $this->campaign, $this->crop)->create();

    Rule::factory()->alwaysFiring()->create();

    $this->engine->runFor($cycle);

    expect(Recommendation::count())->toBe(0);
});

test('a second run creates no duplicate', function () {
    $cycle = ($this->openCycle)();
    Rule::factory()->alwaysFiring()->create();

    $this->engine->runFor($cycle);
    $first = Recommendation::first();

    $this->engine->runFor($cycle->fresh());

    expect(Recommendation::count())->toBe(1)
        ->and(Recommendation::first()->id)->toBe($first->id);
});

test('a rule of the exploitation replaces the common rule of the same code', function () {
    $cycle = ($this->openCycle)();

    Rule::factory()->code('FERT-TEST-01')->neverFiring()->create([
        'message' => 'Message commun',
    ]);
    Rule::factory()->code('FERT-TEST-01')->alwaysFiring()
        ->ownedBy($this->exploitation)
        ->create(['message' => 'Message de l\'exploitation']);

    $this->engine->runFor($cycle);

    expect(Recommendation::count())->toBe(1)
        ->and(Recommendation::first()->generated_message)
        ->toStartWith('Message de l\'exploitation');
});

test('the override of another exploitation is ignored', function () {
    $cycle = ($this->openCycle)();
    $otherExploitation = Exploitation::factory()->create();

    Rule::factory()->code('FERT-TEST-01')->neverFiring()->create([
        'message' => 'Message commun',
    ]);
    Rule::factory()->code('FERT-TEST-01')->alwaysFiring()
        ->ownedBy($otherExploitation)
        ->create(['message' => 'Message du voisin']);

    $this->engine->runFor($cycle);

    // La règle commune ne se déclenche pas, celle du voisin ne s'applique
    // pas : rien n'est produit.
    expect(Recommendation::count())->toBe(0);
});

test('a disabled rule is never evaluated', function () {
    $cycle = ($this->openCycle)();

    Rule::factory()->alwaysFiring()->disabled()->create();

    $this->engine->runFor($cycle);

    expect(Recommendation::count())->toBe(0);
});

test('a convention threshold produces nothing while it stays disabled', function () {
    $cycle = ($this->openCycle)();

    $rule = Rule::factory()->alwaysFiring()->convention()->create();

    $this->engine->runFor($cycle);

    expect(Recommendation::count())->toBe(0);

    // L'exploitation confirme le seuil : la règle se met à s'évaluer.
    $rule->forceFill(['enabled' => true])->save();

    $this->engine->runFor($cycle->fresh());

    expect(Recommendation::count())->toBe(1);
});

test('a rule of another crop does not apply', function () {
    $cycle = ($this->openCycle)();

    Rule::factory()->alwaysFiring()
        ->forCrop(Crop::factory()->named('Gombo')->create())
        ->create();

    $this->engine->runFor($cycle);

    expect(Recommendation::count())->toBe(0);
});

test('a rule without crop applies to every crop', function () {
    $cycle = ($this->openCycle)();

    Rule::factory()->alwaysFiring()->create(['crop_id' => null]);

    $this->engine->runFor($cycle);

    expect(Recommendation::count())->toBe(1);
});

/*
| Suppression sélective (SPEC section 9.7).
*/

test('a recommendation that no longer fires is removed', function () {
    $cycle = ($this->openCycle)();
    $rule = Rule::factory()->alwaysFiring()->create();

    $this->engine->runFor($cycle);

    expect(Recommendation::count())->toBe(1);

    $rule->forceFill(['condition' => ['field' => 'crop_name', 'op' => '=', 'value' => '']])->save();

    $this->engine->runFor($cycle->fresh());

    expect(Recommendation::count())->toBe(0);
});

test('an answered recommendation is kept even when the rule stops firing', function (RecoStatus $status) {
    $cycle = ($this->openCycle)();
    $rule = Rule::factory()->alwaysFiring()->create();

    $this->engine->runFor($cycle);

    Recommendation::first()->forceFill(['status' => $status])->save();

    $rule->forceFill(['condition' => ['field' => 'crop_name', 'op' => '=', 'value' => '']])->save();

    $this->engine->runFor($cycle->fresh());

    expect(Recommendation::count())->toBe(1)
        ->and(Recommendation::first()->status)->toBe($status);
})->with([
    'appliquée' => RecoStatus::Applied,
    'écartée' => RecoStatus::Dismissed,
]);

test('a read recommendation is removed like a new one', function () {
    $cycle = ($this->openCycle)();
    $rule = Rule::factory()->alwaysFiring()->create();

    $this->engine->runFor($cycle);
    Recommendation::first()->forceFill(['status' => RecoStatus::Read])->save();

    $rule->forceFill(['condition' => ['field' => 'crop_name', 'op' => '=', 'value' => '']])->save();

    $this->engine->runFor($cycle->fresh());

    expect(Recommendation::count())->toBe(0);
});

/*
| Famille C et seuil de cinq (SPEC section 9.6).
*/

test('family C rules are discarded below five comparable cycles', function () {
    $cycle = ($this->openCycle)(800);

    Rule::factory()->family(RuleFamily::C)->alwaysFiring()->create();

    $this->engine->runFor($cycle);

    expect(Recommendation::count())->toBe(0);
});

test('family C rules apply from five comparable cycles', function () {
    $cycle = ($this->openCycle)(800);

    foreach ([900, 1000, 1100, 1200] as $yield) {
        ($this->openCycle)($yield);
    }

    Rule::factory()->family(RuleFamily::C)->alwaysFiring()->create();

    $this->engine->runFor($cycle->fresh());

    expect(Recommendation::where('crop_cycle_id', $cycle->id)->count())->toBe(1);
});

test('the other families keep applying below the threshold', function () {
    $cycle = ($this->openCycle)(800);

    Rule::factory()->family(RuleFamily::A)->alwaysFiring()->create();

    $this->engine->runFor($cycle);

    expect(Recommendation::count())->toBe(1);
});

/*
| Le comportement sur la valeur nulle, épinglé sur les vraies règles
| togolaises (SPEC-CORRECTION section 4.2). Chez INSECTGO, la densité
| n'est pas renseignée : la ferme travaille en planches et ne compte pas
| ses plants à l'hectare. Le moteur ne conclut pas plutôt que de
| conclure mal.
*/

test('a tomato cycle without density produces no TOM-A01 and raises no error', function () {
    $this->seed(RuleSeeder::class);

    $cycle = ($this->openCycle)();
    $cycle->update(['density' => null]);

    $this->engine->runFor($cycle->fresh());

    expect(
        Recommendation::whereHas(
            'rule',
            fn ($query) => $query->where('code', 'TOM-A01'),
        )->count(),
    )->toBe(0);
});

test('a cycle without transplanting date produces no TOM-B01', function () {
    $this->seed(RuleSeeder::class);

    $cycle = ($this->openCycle)();
    $cycle->update(['transplanting_date' => null]);

    // Un apport tardif est bien là, mais sans date de repiquage le champ
    // reste nul et TOM-B01 ne conclut pas.
    Operation::factory()->forCycle($cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 1, QuantityUnit::Bag50Kg)
        ->on('2026-06-01')->create();

    $this->engine->runFor($cycle->fresh());

    expect(
        Recommendation::whereHas(
            'rule',
            fn ($query) => $query->where('code', 'TOM-B01'),
        )->count(),
    )->toBe(0);
});

/*
| Le moteur se déclenche sans action volontaire (SPEC section 9.1).
*/

test('recording a harvest runs the engine on its own', function () {
    $technician = User::factory()->technician()->for($this->exploitation)->create();
    $farmer = Farmer::factory()->for($this->exploitation)->create();
    $plot = Plot::factory()->ofFarmer($farmer)->withArea(1.0)->create();
    $cycle = CropCycle::factory()->on($plot, $this->campaign, $this->crop)->create();

    Rule::factory()->alwaysFiring()->create();

    $this->actingAs($technician)
        ->post(route('harvests.store', $cycle), [
            'date' => '2026-08-15',
            'quantity_kg' => 1000,
            'unit_price' => 100,
        ])
        ->assertSessionHasNoErrors();

    expect(Recommendation::where('crop_cycle_id', $cycle->id)->count())->toBe(1);
});
