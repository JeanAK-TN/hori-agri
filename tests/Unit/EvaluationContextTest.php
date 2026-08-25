<?php

use App\Enums\InputType;
use App\Enums\OperationType;
use App\Enums\QuantityUnit;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Operation;
use App\Models\Plot;
use App\Services\Indicators\IndicatorService;
use App\Services\Rules\EvaluationContext;
use App\Services\Rules\InvalidConditionException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| Les sept champs se dérivent d'un cycle complet (SPEC-CORRECTION
| section 4.1). La fiche togolaise compte le calendrier de fertilisation
| à partir du repiquage, pas du semis.
*/

beforeEach(function () {
    $this->exploitation = Exploitation::factory()->create(['region' => 'Kara']);
    $this->crop = Crop::factory()->named('Tomate')->create();
    $this->campaign = Campaign::factory()
        ->for($this->exploitation)
        ->between('2026-03-01', '2026-11-30')
        ->create(['label' => '2026 grande saison']);

    $this->farmer = Farmer::factory()->for($this->exploitation)->inVillage('Bassar')->create();
    $this->plot = Plot::factory()->ofFarmer($this->farmer)->withArea(2.0)->create();

    $this->cycle = CropCycle::factory()
        ->on($this->plot, $this->campaign, $this->crop)
        ->create([
            'sowing_date' => '2026-03-20',
            'transplanting_date' => '2026-04-10',
            'density' => 30000,
        ]);
});

test('the seven fields derive from a complete cycle', function () {
    // Fumure de fond organique avant le repiquage : une tonne au labour.
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Organic, 20, QuantityUnit::Bag50Kg)
        ->on('2026-03-25')->create();
    // Couverture : deux fractions de NPK après le repiquage.
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 4, QuantityUnit::Bag50Kg)
        ->on('2026-04-25')->create();
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 2, QuantityUnit::Bag50Kg)
        ->on('2026-05-25')->create();
    Operation::factory()->forCycle($this->cycle)
        ->on('2026-05-05')->create(['type' => OperationType::Weeding]);

    // La tomate se récolte en plusieurs passages : deux récoltes.
    Harvest::factory()->forCycle($this->cycle)->of(14000, 200)->on('2026-07-10')->create();
    Harvest::factory()->forCycle($this->cycle)->of(6000, 200)->on('2026-07-25')->create();

    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    expect($context->crop_name)->toBe('Tomate')
        // La densité renseignée sur le cycle, telle quelle.
        ->and($context->planting_density_plants_ha)->toBe(30000)
        // 6 sacs de 50 kg après repiquage sur 2 ha : 150 kg/ha. La
        // fumure organique de fond n'y entre pas.
        ->and($context->topdressing_npk_kg_ha)->toBe(150.0)
        // 20 sacs de 50 kg, soit une tonne, sur 2 ha : 0,5 t/ha.
        ->and($context->organic_matter_t_ha)->toBe(0.5)
        // Du 10 avril au 25 avril : quinze jours.
        ->and($context->first_topdressing_days_after_transplanting)->toBe(15)
        // 20 000 kg sur 2 ha, tous passages confondus.
        ->and($context->yield_kg_ha)->toBe(10000.0);
});

test('the field list carries exactly the seven documented names', function () {
    expect(array_keys(EvaluationContext::FIELD_LABELS))->toBe([
        'crop_name',
        'planting_density_plants_ha',
        'topdressing_npk_kg_ha',
        'organic_matter_t_ha',
        'first_topdressing_days_after_transplanting',
        'yield_kg_ha',
        'zone_median_yield_kg_ha',
    ]);
});

test('a missing density stays null, it does not become zero', function () {
    // Chez INSECTGO la densité n'est pas renseignée : la ferme travaille
    // en planches et ne compte pas ses plants à l'hectare
    // (SPEC-CORRECTION section 4.2).
    $this->cycle->update(['density' => null]);

    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    expect($context->planting_density_plants_ha)->toBeNull();
});

test('without a transplanting date the two topdressing fields stay null', function () {
    $this->cycle->update(['transplanting_date' => null]);

    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 4, QuantityUnit::Bag50Kg)
        ->on('2026-04-25')->create();

    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    // Sans repiquage, impossible de séparer la fumure de fond de la
    // couverture : le moteur ne conclut pas plutôt que de conclure mal.
    expect($context->topdressing_npk_kg_ha)->toBeNull()
        ->and($context->first_topdressing_days_after_transplanting)->toBeNull();
});

test('a fertilization on the transplanting day counts as basal dressing', function () {
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 4, QuantityUnit::Bag50Kg)
        ->on('2026-04-10')->create();

    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    expect($context->topdressing_npk_kg_ha)->toBeNull()
        ->and($context->first_topdressing_days_after_transplanting)->toBeNull();
});

test('a quantity in litres does not enter a dose in kilograms', function () {
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 10, QuantityUnit::Litre)
        ->on('2026-04-25')->create();

    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    // Le litre ne se convertit pas en masse : la dose reste nulle plutôt
    // que fausse.
    expect($context->topdressing_npk_kg_ha)->toBeNull();
});

test('without any organic operation the organic matter stays null', function () {
    Operation::factory()->forCycle($this->cycle)
        ->bringing(OperationType::Fertilization, InputType::Npk, 2, QuantityUnit::Bag50Kg)
        ->on('2026-04-25')->create();

    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    expect($context->organic_matter_t_ha)->toBeNull();
});

test('without any harvest the yield stays null', function () {
    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    expect($context->yield_kg_ha)->toBeNull();
});

test('the yield sums every harvest pass of the cycle', function () {
    Harvest::factory()->forCycle($this->cycle)->of(3000, 200)->on('2026-07-10')->create();
    Harvest::factory()->forCycle($this->cycle)->of(2000, 200)->on('2026-07-25')->create();
    Harvest::factory()->forCycle($this->cycle)->of(1000, 200)->on('2026-08-08')->create();

    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    expect($context->yield_kg_ha)->toBe(3000.0);
});

test('the zone median comes from the indicators of the comparable cycles', function () {
    Harvest::factory()->forCycle($this->cycle)->of(2000, 200)->create();

    app(IndicatorService::class)->computeFor($this->cycle);

    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    // Seul cycle de la zone : la médiane est son propre rendement.
    expect($context->zone_median_yield_kg_ha)->toBe(1000.0);
});

test('without computed indicators the zone median stays null', function () {
    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    expect($context->zone_median_yield_kg_ha)->toBeNull();
});

/*
| L'accès aux champs et leur écriture en français, qui complète le
| message d'une recommandation (SPEC section 9.7).
*/

test('get refuses a field outside the seven', function () {
    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    expect(fn () => $context->get('npk_dose_kg_ha'))
        ->toThrow(InvalidConditionException::class, 'npk_dose_kg_ha');
});

test('describe writes the value with its unit, and says when nothing is known', function () {
    Harvest::factory()->forCycle($this->cycle)->of(15000, 200)->create();

    $context = EvaluationContext::fromCycle($this->cycle->fresh());

    expect($context->describe('yield_kg_ha'))->toBe('rendement : 7 500 kg/ha')
        ->and($context->describe('planting_density_plants_ha'))
        ->toBe('densité de plantation : 30000 plants/ha')
        ->and($context->describe('organic_matter_t_ha'))
        ->toBe('matière organique apportée : valeur non renseignée');
});
