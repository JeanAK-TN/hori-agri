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
|
| C'est aussi ici que se démontre qu'aucune des cinq règles togolaises
| n'est lettre morte, TOM-C01 comprise : un test par règle, sur un
| contexte construit à la main, sans passer par le garde-fou de zone
| (SPEC-CORRECTION section 10.1).
*/

/**
 * Un contexte de travail dont chaque champ se surcharge à la demande.
 * Les valeurs sont des fixtures d'exercice, pas des références.
 *
 * @param  array<string, mixed>  $overrides
 */
function contextWith(array $overrides = []): EvaluationContext
{
    $defaults = [
        'crop_name' => 'Tomate',
        'planting_density_plants_ha' => 30000,
        'topdressing_npk_kg_ha' => 300.0,
        'organic_matter_t_ha' => null,
        'first_topdressing_days_after_transplanting' => 15,
        'yield_kg_ha' => 9500.0,
        'zone_median_yield_kg_ha' => null,
    ];

    return new EvaluationContext(...[...$defaults, ...$overrides]);
}

beforeEach(function () {
    $this->evaluator = new RuleEvaluator;
    $this->context = contextWith();

    $this->evaluate = fn (array $condition): bool => $this->evaluator->evaluate($condition, $this->context);
});

/*
| Un opérateur, deux assertions : le cas vrai et le cas faux.
*/

test('the nine operators answer true when they should', function (array $condition) {
    expect(($this->evaluate)($condition))->toBeTrue();
})->with([
    'égal' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => '=', 'value' => 15]],
    'différent' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => '!=', 'value' => 20]],
    'inférieur' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => '<', 'value' => 16]],
    'inférieur ou égal' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => '<=', 'value' => 15]],
    'supérieur' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => '>', 'value' => 14]],
    'supérieur ou égal' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => '>=', 'value' => 15]],
    'entre' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => 'between', 'value' => [10, 20]]],
    'parmi' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => 'in', 'value' => [10, 15, 20]]],
    'hors de' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => 'not_in', 'value' => [20, 25]]],
]);

test('the nine operators answer false when they should', function (array $condition) {
    expect(($this->evaluate)($condition))->toBeFalse();
})->with([
    'égal' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => '=', 'value' => 20]],
    'différent' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => '!=', 'value' => 15]],
    'inférieur' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => '<', 'value' => 15]],
    'inférieur ou égal' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => '<=', 'value' => 14]],
    'supérieur' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => '>', 'value' => 15]],
    'supérieur ou égal' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => '>=', 'value' => 16]],
    'entre' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => 'between', 'value' => [20, 30]]],
    'parmi' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => 'in', 'value' => [20, 25]]],
    'hors de' => [['field' => 'first_topdressing_days_after_transplanting', 'op' => 'not_in', 'value' => [10, 15, 20]]],
]);

test('the bounds of between are included', function (int $days, bool $expected) {
    $context = contextWith(['first_topdressing_days_after_transplanting' => $days]);

    $condition = ['field' => 'first_topdressing_days_after_transplanting', 'op' => 'between', 'value' => [10, 20]];

    expect($this->evaluator->evaluate($condition, $context))->toBe($expected);
})->with([
    'borne basse' => [10, true],
    'milieu' => [15, true],
    'borne haute' => [20, true],
    'juste avant' => [9, false],
    'juste après' => [21, false],
]);

/*
| Sémantique des nuls (SPEC section 9.4) : un champ nul rend faux, sauf
| pour != et not_in où il rend vrai.
*/

test('a null field makes the condition false', function (string $operator, mixed $value) {
    $condition = ['field' => 'organic_matter_t_ha', 'op' => $operator, 'value' => $value];

    expect(($this->evaluate)($condition))->toBeFalse();
})->with([
    'égal' => ['=', 1],
    'inférieur' => ['<', 1],
    'inférieur ou égal' => ['<=', 1],
    'supérieur' => ['>', 1],
    'supérieur ou égal' => ['>=', 1],
    'entre' => ['between', [0.5, 2]],
    'parmi' => ['in', [0.5, 1, 2]],
]);

test('a null field makes the condition true for the two negative operators', function (string $operator, mixed $value) {
    $condition = ['field' => 'organic_matter_t_ha', 'op' => $operator, 'value' => $value];

    expect(($this->evaluate)($condition))->toBeTrue();
})->with([
    'différent' => ['!=', 1],
    'hors de' => ['not_in', [0.5, 1, 2]],
]);

test('a null field is not zero', function () {
    // La matière organique n'est pas renseignée : la règle qui cherche
    // un apport inférieur à un seuil ne doit pas se déclencher, alors
    // qu'elle le ferait si le champ valait zéro.
    expect(($this->evaluate)(['field' => 'organic_matter_t_ha', 'op' => '<', 'value' => 1]))
        ->toBeFalse();

    expect(($this->evaluate)(['field' => 'topdressing_npk_kg_ha', 'op' => '<', 'value' => 350]))
        ->toBeTrue();
});

/*
| Combinaisons.
*/

test('an and branch is true only when every child is', function () {
    expect(($this->evaluate)(['and' => [
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Tomate'],
        ['field' => 'first_topdressing_days_after_transplanting', 'op' => '=', 'value' => 15],
    ]]))->toBeTrue();

    expect(($this->evaluate)(['and' => [
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Tomate'],
        ['field' => 'first_topdressing_days_after_transplanting', 'op' => '=', 'value' => 20],
    ]]))->toBeFalse();
});

test('an or branch is true as soon as one child is', function () {
    expect(($this->evaluate)(['or' => [
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Gombo'],
        ['field' => 'first_topdressing_days_after_transplanting', 'op' => '=', 'value' => 15],
    ]]))->toBeTrue();

    expect(($this->evaluate)(['or' => [
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Gombo'],
        ['field' => 'first_topdressing_days_after_transplanting', 'op' => '=', 'value' => 20],
    ]]))->toBeFalse();
});

test('branches nest', function () {
    $condition = ['and' => [
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Tomate'],
        ['or' => [
            ['field' => 'yield_kg_ha', 'op' => '>', 'value' => 12000],
            ['and' => [
                ['field' => 'topdressing_npk_kg_ha', 'op' => '>=', 'value' => 300],
                ['field' => 'planting_density_plants_ha', 'op' => '<', 'value' => 33000],
            ]],
        ]],
    ]];

    expect(($this->evaluate)($condition))->toBeTrue();
});

/*
| Refus (SPEC section 9.5).
*/

test('an unknown field raises an exception', function () {
    expect(fn () => ($this->evaluate)(['field' => 'rendement_secret', 'op' => '=', 'value' => 1]))
        ->toThrow(InvalidConditionException::class, 'rendement_secret');
});

test('a field removed from the context is refused too', function (string $field) {
    // Les champs des cultures pluviales ont disparu avec la réduction à
    // sept : une règle qui les interroge encore doit être refusée, pas
    // évaluée en silence (SPEC-CORRECTION section 4.1).
    expect(fn () => ($this->evaluate)(['field' => $field, 'op' => '=', 'value' => 1]))
        ->toThrow(InvalidConditionException::class, $field);
})->with([
    'sowing_month',
    'npk_dose_kg_ha',
    'inoculant_used',
    'consecutive_seasons_same_crop',
    'yield_vs_zone_median_ratio',
]);

test('an unknown operator raises an exception', function () {
    expect(fn () => ($this->evaluate)(['field' => 'yield_kg_ha', 'op' => 'ressemble', 'value' => 4]))
        ->toThrow(InvalidConditionException::class, 'ressemble');
});

test('a malformed tree raises an exception', function (array $condition) {
    expect(fn () => ($this->evaluate)($condition))->toThrow(InvalidConditionException::class);
})->with([
    'clé manquante' => [['field' => 'yield_kg_ha', 'op' => '=']],
    'clé en trop' => [['field' => 'yield_kg_ha', 'op' => '=', 'value' => 4, 'extra' => 1]],
    'branche vide' => [['and' => []]],
    'branche qui n\'est pas une liste' => [['or' => ['a' => ['field' => 'yield_kg_ha', 'op' => '=', 'value' => 4]]]],
    'branche accompagnée' => [['and' => [['field' => 'yield_kg_ha', 'op' => '=', 'value' => 4]], 'op' => '=']],
    'enfant qui n\'est pas une condition' => [['and' => ['texte']]],
    'between sans deux bornes' => [['field' => 'yield_kg_ha', 'op' => 'between', 'value' => [3]]],
    'in sans tableau' => [['field' => 'yield_kg_ha', 'op' => 'in', 'value' => 4]],
    'comparaison d\'ordre sur du texte' => [['field' => 'crop_name', 'op' => '<', 'value' => 'Gombo']],
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
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Tomate'],
        ['or' => [
            ['field' => 'topdressing_npk_kg_ha', 'op' => '<', 'value' => 350],
            ['field' => 'organic_matter_t_ha', 'op' => 'not_in', 'value' => [1]],
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
        ['field' => 'crop_name', 'op' => '=', 'value' => 'Tomate'],
        ['or' => [
            ['field' => 'topdressing_npk_kg_ha', 'op' => '<', 'value' => 350],
            ['field' => 'crop_name', 'op' => '!=', 'value' => 'Gombo'],
        ]],
    ]];

    expect($this->evaluator->leafFields($condition))->toBe(['crop_name', 'topdressing_npk_kg_ha']);
});

test('the seven fields of the context are the only interrogable ones', function () {
    expect(EvaluationContext::FIELD_LABELS)->toHaveCount(7);

    foreach (array_keys(EvaluationContext::FIELD_LABELS) as $field) {
        expect(fn () => $this->context->get($field))->not->toThrow(InvalidConditionException::class);
    }
});

/*
| Aucune des cinq règles togolaises n'est lettre morte (SPEC-CORRECTION
| section 10.1). Chaque condition est lue telle quelle dans rules.json et
| évaluée sur un contexte construit pour la déclencher, puis sur un
| contexte conforme qui doit la laisser muette. TOM-C01 s'éprouve ici,
| hors du garde-fou de zone : le jeu d'épreuve, lui, la montre suspendue.
*/

function togoRuleCondition(string $code): array
{
    $entries = json_decode(
        (string) file_get_contents(database_path('seeders/data/rules.json')),
        true,
    );

    foreach ($entries as $entry) {
        if ($entry['code'] === $code) {
            return $entry['condition'];
        }
    }

    throw new RuntimeException("Règle {$code} absente de rules.json.");
}

test('each of the five togolese rules fires at least once on a hand-built context', function (
    string $code,
    array $firing,
    array $silent,
) {
    $condition = togoRuleCondition($code);

    expect($this->evaluator->evaluate($condition, contextWith($firing)))
        ->toBeTrue("{$code} devrait se déclencher")
        ->and($this->evaluator->evaluate($condition, contextWith($silent)))
        ->toBeFalse("{$code} devrait rester muette");
})->with([
    'TOM-A01, densité sous l\'écartement recommandé' => [
        'TOM-A01',
        ['planting_density_plants_ha' => 20000],
        ['planting_density_plants_ha' => 33000],
    ],
    'TOM-A02, couverture sous la dose fractionnée' => [
        'TOM-A02',
        ['topdressing_npk_kg_ha' => 200.0],
        ['topdressing_npk_kg_ha' => 350.0],
    ],
    'TOM-A03, matière organique sous le minimum annuel' => [
        'TOM-A03',
        ['organic_matter_t_ha' => 0.5],
        ['organic_matter_t_ha' => 1.5],
    ],
    'TOM-B01, premier apport après la fenêtre' => [
        'TOM-B01',
        ['first_topdressing_days_after_transplanting' => 25],
        ['first_topdressing_days_after_transplanting' => 15],
    ],
    'TOM-C01, parcelle sous le réaliste togolais et sous ses voisines' => [
        'TOM-C01',
        ['yield_kg_ha' => 7500.0, 'zone_median_yield_kg_ha' => 12500.0],
        ['yield_kg_ha' => 12500.0, 'zone_median_yield_kg_ha' => 12500.0],
    ],
]);

test('the five rules stay silent on another crop', function () {
    // Chaque condition commence par vérifier la culture : un cycle de
    // gombo ne déclenche aucune règle de la tomate.
    $context = contextWith([
        'crop_name' => 'Gombo',
        'planting_density_plants_ha' => 1000,
        'topdressing_npk_kg_ha' => 10.0,
        'organic_matter_t_ha' => 0.1,
        'first_topdressing_days_after_transplanting' => 60,
        'yield_kg_ha' => 100.0,
        'zone_median_yield_kg_ha' => 13000.0,
    ]);

    foreach (['TOM-A01', 'TOM-A02', 'TOM-A03', 'TOM-B01', 'TOM-C01'] as $code) {
        expect($this->evaluator->evaluate(togoRuleCondition($code), $context))
            ->toBeFalse($code);
    }
});
