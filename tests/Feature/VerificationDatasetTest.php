<?php

use App\Models\Campaign;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Indicator;
use App\Models\Operation;
use App\Models\Plot;
use App\Models\Recommendation;
use App\Models\Rule;
use App\Services\Indicators\IndicatorService;
use App\Services\Rules\RecommendationService;
use Database\Seeders\CropSeeder;
use Database\Seeders\RuleSeeder;
use Database\Seeders\VarietySeeder;
use Database\Seeders\VerificationDatasetSeeder;

/*
| SPEC section 14.3 : le jeu d'épreuve du mémoire.
|
| Ce test est la vérification de bout en bout de la chaîne de calcul. Il
| ne prouve pas que les valeurs sont agronomiquement justes — le dossier
| est une construction — mais que les opérations et les récoltes saisies
| produisent bien les indicateurs publiés.
*/

beforeEach(function () {
    $this->seed(CropSeeder::class);
    $this->seed(VarietySeeder::class);

    $this->seeder = app(VerificationDatasetSeeder::class);
    $this->seeder->run();

    $this->cycles = $this->seeder->cycles();
});

test('the structure is the one the dissertation describes', function () {
    expect(Exploitation::count())->toBe(1)
        ->and(Farmer::withoutGlobalScopes()->count())->toBe(3)
        ->and(Plot::withoutGlobalScopes()->count())->toBe(4)
        ->and(Campaign::withoutGlobalScopes()->count())->toBe(2)
        ->and(Operation::withoutGlobalScopes()->count())->toBe(21)
        ->and(Harvest::withoutGlobalScopes()->count())->toBe(5);
});

test('the three producers share one village', function () {
    $villages = Farmer::withoutGlobalScopes()->pluck('village')->unique();

    expect($villages)->toHaveCount(1)
        ->and($villages->first())->toBe(VerificationDatasetSeeder::VILLAGE);
});

test('the third cycle carries two staggered harvests', function () {
    $harvests = $this->cycles[3]->harvests()->orderBy('date')->get();

    expect($harvests)->toHaveCount(2)
        ->and($harvests->sum('quantity_kg'))->toEqual(10800)
        ->and($harvests->first()->date->toDateString())->toBe('2026-10-05')
        ->and($harvests->last()->date->toDateString())->toBe('2026-11-15');
});

test('the four cycles reproduce the published indicators exactly', function (int $rank) {
    $expected = VerificationDatasetSeeder::EXPECTED_INDICATORS[$rank];

    $indicator = Indicator::withoutGlobalScopes()
        ->where('crop_cycle_id', $this->cycles[$rank]->id)
        ->sole();

    expect(round((float) $indicator->yield_kg_ha, 1))
        ->toBe($expected['yield_kg_ha'], "rendement du cycle {$rank}")
        ->and(round((float) $indicator->production_cost_ha, 1))
        ->toBe($expected['production_cost_ha'], "coût du cycle {$rank}")
        ->and(round((float) $indicator->gross_margin_ha, 1))
        ->toBe($expected['gross_margin_ha'], "marge du cycle {$rank}");
})->with([1, 2, 3, 4]);

test('recomputing the indicators does not move them', function () {
    $before = Indicator::withoutGlobalScopes()->orderBy('crop_cycle_id')
        ->get(['crop_cycle_id', 'yield_kg_ha', 'production_cost_ha', 'gross_margin_ha'])
        ->map(fn (Indicator $indicator): array => $indicator->only([
            'crop_cycle_id', 'yield_kg_ha', 'production_cost_ha', 'gross_margin_ha',
        ]))
        ->all();

    foreach ($this->cycles as $cycle) {
        app(IndicatorService::class)->computeFor($cycle->fresh());
    }

    $after = Indicator::withoutGlobalScopes()->orderBy('crop_cycle_id')
        ->get(['crop_cycle_id', 'yield_kg_ha', 'production_cost_ha', 'gross_margin_ha'])
        ->map(fn (Indicator $indicator): array => $indicator->only([
            'crop_cycle_id', 'yield_kg_ha', 'production_cost_ha', 'gross_margin_ha',
        ]))
        ->all();

    expect($after)->toBe($before);
});

test('the comparison stays suspended, four cycles being under the threshold', function () {
    // Quatre cycles sur deux campagnes et trois cultures : aucune zone
    // n'atteint cinq parcelles comparables (SPEC section 9.3).
    $indicators = Indicator::withoutGlobalScopes()->get();

    expect($indicators->every(
        fn (Indicator $indicator): bool => $indicator->zone_plot_count < IndicatorService::MINIMUM_ZONE_SIZE,
    ))->toBeTrue()
        ->and($indicators->every(
            fn (Indicator $indicator): bool => $indicator->yield_vs_zone_median === null,
        ))->toBeTrue();
});

/*
| Le nombre de recommandations dépend des seuils, qui sont SIMULÉS. Le
| mémoire en annonce treize sur les quatre cycles ; il en sort quatre
| ici. L'écart n'est pas un défaut du moteur : il tient aux valeurs de
| rules.json, qui sont des fixtures. Le compte est constaté puis figé,
| comme la spécification le demande à défaut du script d'origine
| (SPEC section 14.3).
*/

test('the engine draws exactly the remarks the simulated thresholds justify', function () {
    $this->seed(RuleSeeder::class);

    foreach ($this->cycles as $cycle) {
        app(RecommendationService::class)->runFor($cycle->fresh());
    }

    foreach (VerificationDatasetSeeder::EXPECTED_RECOMMENDATION_CODES as $rank => $codes) {
        $observed = Recommendation::withoutGlobalScopes()
            ->where('crop_cycle_id', $this->cycles[$rank]->id)
            ->with('rule')
            ->get()
            ->map(fn (Recommendation $recommendation): string => $recommendation->rule->code)
            ->sort()
            ->values()
            ->all();

        expect($observed)->toBe($codes, "cycle {$rank}");
    }

    expect(Recommendation::withoutGlobalScopes()->count())->toBe(4);
});

test('neither a family C rule nor a convention fires on this record', function () {
    $this->seed(RuleSeeder::class);

    foreach ($this->cycles as $cycle) {
        app(RecommendationService::class)->runFor($cycle->fresh());
    }

    $rules = Recommendation::withoutGlobalScopes()->with('rule')->get()
        ->map(fn (Recommendation $recommendation): Rule => $recommendation->rule);

    // Famille C : la zone n'atteint pas cinq parcelles comparables.
    expect($rules->every(fn (Rule $rule): bool => $rule->family->value !== 'C'))->toBeTrue()
        // Convention : ces règles arrivent désactivées.
        ->and($rules->every(fn (Rule $rule): bool => $rule->enabled))->toBeTrue();
});

test('a cycle conducted to the reference draws fewer remarks than one below it', function () {
    $this->seed(RuleSeeder::class);

    foreach ($this->cycles as $cycle) {
        app(RecommendationService::class)->runFor($cycle->fresh());
    }

    $below = Recommendation::withoutGlobalScopes()
        ->where('crop_cycle_id', $this->cycles[1]->id)->count();
    $toReference = Recommendation::withoutGlobalScopes()
        ->where('crop_cycle_id', $this->cycles[4]->id)->count();

    expect($below)->toBe(3)
        ->and($toReference)->toBe(0)
        ->and($below)->toBeGreaterThan($toReference);
});

test('the dataset says of itself that it is a construction', function () {
    $source = file_get_contents(database_path('seeders/VerificationDatasetSeeder.php'));

    expect($source)->toContain('CONSTRUCTION, PAS UN RELEVÉ')
        ->and(Exploitation::first()->name)->toContain('construction')
        ->and(Rule::withoutGlobalScopes()->count())->toBe(0);
});
