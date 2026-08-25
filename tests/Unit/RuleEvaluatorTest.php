<?php

use App\Services\Rules\EvaluationContext;
use App\Services\Rules\InvalidConditionException;
use App\Services\Rules\RuleEvaluator;
use Illuminate\Support\Facades\DB;

/*
| Le test le plus important du projet (SPEC section 14.1).
|
| L'évaluateur ne connaît ni l'agronomie ni les exploitations, et n'émet
| aucune requête : ces tests le prouvent opérateur par opérateur, dans le
| cas vrai comme dans le cas faux, et sur la sémantique des nuls qui est
| la source d'erreur la plus probable du moteur.
*/

beforeEach(function () {
    $this->evaluator = new RuleEvaluator;

    $this->context = new EvaluationContext(
        crop_name: 'Maïs',
        region: 'Kara',
        sowing_month: 4,
        sowing_density_plants_ha: 50000,
        seed_rate_kg_ha: 25.0,
        npk_dose_kg_ha: 100.0,
        urea_dose_kg_ha: 50.0,
        potassium_dose_kg_ha: null,
        inoculant_used: false,
        first_weeding_days_after_sowing: 20,
        urea_days_after_sowing: null,
        consecutive_seasons_same_crop: 2,
        yield_vs_zone_median_ratio: 0.75,
        cost_vs_zone_median_ratio: null,
        input_cost_share_of_harvest_value: 0.3,
    );

    $this->evaluate = fn (array $condition): bool => $this->evaluator->evaluate($condition, $this->context);
});

/*
| Un opérateur, deux assertions : le cas vrai et le cas faux.
*/

test('the nine operators answer true when they should', function (array $condition) {
    expect(($this->evaluate)($condition))->toBeTrue();
})->with([
    'égal' => [['field' => 'sowing_month', 'op' => '=', 'value' => 4]],
    'différent' => [['field' => 'sowing_month', 'op' => '!=', 'value' => 5]],
    'inférieur' => [['field' => 'sowing_month', 'op' => '<', 'value' => 5]],
    'inférieur ou égal' => [['field' => 'sowing_month', 'op' => '<=', 'value' => 4]],
    'supérieur' => [['field' => 'sowing_month', 'op' => '>', 'value' => 3]],
    'supérieur ou égal' => [['field' => 'sowing_month', 'op' => '>=', 'value' => 4]],
    'entre' => [['field' => 'sowing_month', 'op' => 'between', 'value' => [3, 5]]],
    'parmi' => [['field' => 'sowing_month', 'op' => 'in', 'value' => [3, 4, 5]]],
    'hors de' => [['field' => 'sowing_month', 'op' => 'not_in', 'value' => [6, 7]]],
]);

test('the nine operators answer false when they should', function (array $condition) {
    expect(($this->evaluate)($condition))->toBeFalse();
})->with([
    'égal' => [['field' => 'sowing_month', 'op' => '=', 'value' => 5]],
    'différent' => [['field' => 'sowing_month', 'op' => '!=', 'value' => 4]],
    'inférieur' => [['field' => 'sowing_month', 'op' => '<', 'value' => 4]],
    'inférieur ou égal' => [['field' => 'sowing_month', 'op' => '<=', 'value' => 3]],
    'supérieur' => [['field' => 'sowing_month', 'op' => '>', 'value' => 4]],
    'supérieur ou égal' => [['field' => 'sowing_month', 'op' => '>=', 'value' => 5]],
    'entre' => [['field' => 'sowing_month', 'op' => 'between', 'value' => [6, 8]]],
    'parmi' => [['field' => 'sowing_month', 'op' => 'in', 'value' => [6, 7]]],
    'hors de' => [['field' => 'sowing_month', 'op' => 'not_in', 'value' => [3, 4, 5]]],
]);

test('the bounds of between are included', function (int $month, bool $expected) {
    $context = new EvaluationContext(
        crop_name: 'Maïs', region: 'Kara', sowing_month: $month,
        sowing_density_plants_ha: null, seed_rate_kg_ha: null, npk_dose_kg_ha: null,
        urea_dose_kg_ha: null, potassium_dose_kg_ha: null, inoculant_used: false,
        first_weeding_days_after_sowing: null, urea_days_after_sowing: null,
        consecutive_seasons_same_crop: 1, yield_vs_zone_median_ratio: null,
        cost_vs_zone_median_ratio: null, input_cost_share_of_harvest_value: null,
    );

    $condition = ['field' => 'sowing_month', 'op' => 'between', 'value' => [3, 5]];

    expect($this->evaluator->evaluate($condition, $context))->toBe($expected);
})->with([
    'borne basse' => [3, true],
    'milieu' => [4, true],
    'borne haute' => [5, true],
    'juste avant' => [2, false],
    'juste après' => [6, false],
]);

/*
| Sémantique des nuls (SPEC section 9.4) : un champ nul rend faux, sauf
| pour != et not_in où il rend vrai.
*/

test('a null field makes the condition false', function (string $operator, mixed $value) {
    $condition = ['field' => 'potassium_dose_kg_ha', 'op' => $operator, 'value' => $value];

    expect(($this->evaluate)($condition))->toBeFalse();
})->with([
    'égal' => ['=', 30],
    'inférieur' => ['<', 30],
    'inférieur ou égal' => ['<=', 30],
    'supérieur' => ['>', 30],
    'supérieur ou égal' => ['>=', 30],
    'entre' => ['between', [10, 50]],
    'parmi' => ['in', [10, 30, 50]],
]);

test('a null field makes the condition true for the two negative operators', function (string $operator, mixed $value) {
    $condition = ['field' => 'potassium_dose_kg_ha', 'op' => $operator, 'value' => $value];

    expect(($this->evaluate)($condition))->toBeTrue();
})->with([
    'différent' => ['!=', 30],
    'hors de' => ['not_in', [10, 30, 50]],
]);

test('a null field is not zero', function () {
    // Le potassium n'est pas renseigné : une règle qui cherche une dose
    // inférieure à un seuil ne doit pas se déclencher, alors qu'elle le
    // ferait si le champ valait zéro.
    expect(($this->evaluate)(['field' => 'potassium_dose_kg_ha', 'op' => '<', 'value' => 30]))
        ->toBeFalse();

    expect(($this->evaluate)(['field' => 'npk_dose_kg_ha', 'op' => '<', 'value' => 150]))
        ->toBeTrue();
});

/*
| Combinaisons.
*/

test('an and branch is true only when every child is', function () {
    expect(($this->evaluate)(['and' => [
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Maïs'],
        ['field' => 'sowing_month', 'op' => '=', 'value' => 4],
    ]]))->toBeTrue();

    expect(($this->evaluate)(['and' => [
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Maïs'],
        ['field' => 'sowing_month', 'op' => '=', 'value' => 5],
    ]]))->toBeFalse();
});

test('an or branch is true as soon as one child is', function () {
    expect(($this->evaluate)(['or' => [
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Soja'],
        ['field' => 'sowing_month', 'op' => '=', 'value' => 4],
    ]]))->toBeTrue();

    expect(($this->evaluate)(['or' => [
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Soja'],
        ['field' => 'sowing_month', 'op' => '=', 'value' => 5],
    ]]))->toBeFalse();
});

test('branches nest', function () {
    $condition = ['and' => [
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Maïs'],
        ['or' => [
            ['field' => 'sowing_month', 'op' => '=', 'value' => 11],
            ['and' => [
                ['field' => 'npk_dose_kg_ha', 'op' => '>=', 'value' => 100],
                ['field' => 'inoculant_used', 'op' => '=', 'value' => false],
            ]],
        ]],
    ]];

    expect(($this->evaluate)($condition))->toBeTrue();
});

test('a boolean field compares to a boolean', function () {
    expect(($this->evaluate)(['field' => 'inoculant_used', 'op' => '=', 'value' => false]))->toBeTrue();
    expect(($this->evaluate)(['field' => 'inoculant_used', 'op' => '=', 'value' => true]))->toBeFalse();
    expect(($this->evaluate)(['field' => 'inoculant_used', 'op' => '!=', 'value' => true]))->toBeTrue();
});

/*
| Refus (SPEC section 9.5).
*/

test('an unknown field raises an exception', function () {
    expect(fn () => ($this->evaluate)(['field' => 'rendement_secret', 'op' => '=', 'value' => 1]))
        ->toThrow(InvalidConditionException::class, 'rendement_secret');
});

test('an unknown operator raises an exception', function () {
    expect(fn () => ($this->evaluate)(['field' => 'sowing_month', 'op' => 'ressemble', 'value' => 4]))
        ->toThrow(InvalidConditionException::class, 'ressemble');
});

test('a malformed tree raises an exception', function (array $condition) {
    expect(fn () => ($this->evaluate)($condition))->toThrow(InvalidConditionException::class);
})->with([
    'clé manquante' => [['field' => 'sowing_month', 'op' => '=']],
    'clé en trop' => [['field' => 'sowing_month', 'op' => '=', 'value' => 4, 'extra' => 1]],
    'branche vide' => [['and' => []]],
    'branche qui n\'est pas une liste' => [['or' => ['a' => ['field' => 'sowing_month', 'op' => '=', 'value' => 4]]]],
    'branche accompagnée' => [['and' => [['field' => 'sowing_month', 'op' => '=', 'value' => 4]], 'op' => '=']],
    'enfant qui n\'est pas une condition' => [['and' => ['texte']]],
    'between sans deux bornes' => [['field' => 'sowing_month', 'op' => 'between', 'value' => [3]]],
    'in sans tableau' => [['field' => 'sowing_month', 'op' => 'in', 'value' => 4]],
    'comparaison d\'ordre sur du texte' => [['field' => 'crop_name', 'op' => '<', 'value' => 'Soja']],
]);

/*
| L'évaluateur ne touche jamais la base (SPEC section 14.1).
*/

test('evaluating emits no database query at all', function () {
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    $condition = ['and' => [
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Maïs'],
        ['or' => [
            ['field' => 'npk_dose_kg_ha', 'op' => '<', 'value' => 150],
            ['field' => 'potassium_dose_kg_ha', 'op' => 'not_in', 'value' => [30]],
        ]],
    ]];

    ($this->evaluate)($condition);
    $this->evaluator->leafFields($condition);

    expect($queries)->toBe(0);
});

/*
| Les champs lus par une condition, qui servent à composer le message.
*/

test('the fields a condition reads are listed in order, without repetition', function () {
    $condition = ['and' => [
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Maïs'],
        ['or' => [
            ['field' => 'npk_dose_kg_ha', 'op' => '<', 'value' => 100],
            ['field' => 'crop_name', 'op' => '!=', 'value' => 'Soja'],
        ]],
    ]];

    expect($this->evaluator->leafFields($condition))->toBe(['crop_name', 'npk_dose_kg_ha']);
});

test('the fifteen fields of the context are the only interrogable ones', function () {
    expect(EvaluationContext::FIELD_LABELS)->toHaveCount(15);

    foreach (array_keys(EvaluationContext::FIELD_LABELS) as $field) {
        expect(fn () => $this->context->get($field))->not->toThrow(InvalidConditionException::class);
    }
});
