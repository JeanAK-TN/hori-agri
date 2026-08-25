<?php

use App\Enums\RuleFamily;
use App\Enums\Severity;
use App\Enums\ThresholdStatus;
use App\Models\Rule;
use App\Models\User;
use App\Services\Rules\RulesFileValidator;
use Database\Seeders\CropSeeder;
use Database\Seeders\RuleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| SPEC-CORRECTION sections 3 et 7 : la base de règles compte cinq règles
| sur la tomate, toutes issues du même document togolais publié. Le
| fichier passe tous les contrôles avant qu'une seule ligne ne soit
| écrite, et la commande rules:validate les expose sans rien écrire.
*/

test('the five tomato rules load and pass every control', function () {
    $this->seed(CropSeeder::class);
    $this->seed(RuleSeeder::class);

    $rules = Rule::withoutGlobalScopes()->get();

    expect($rules)->toHaveCount(RulesFileValidator::EXPECTED_TOTAL)
        ->and($rules->pluck('code')->sort()->values()->all())->toBe([
            'TOM-A01', 'TOM-A02', 'TOM-A03', 'TOM-B01', 'TOM-C01',
        ]);
});

test('the expected distributions are respected', function () {
    $this->seed(CropSeeder::class);
    $this->seed(RuleSeeder::class);

    $rules = Rule::withoutGlobalScopes()->with('crop')->get();

    foreach (RulesFileValidator::EXPECTED_BY_FAMILY as $family => $count) {
        expect($rules->where('family', RuleFamily::from($family))->count())
            ->toBe($count, "famille {$family}");
    }

    foreach (RulesFileValidator::EXPECTED_BY_SEVERITY as $severity => $count) {
        expect($rules->where('severity', Severity::from($severity))->count())
            ->toBe($count, "sévérité {$severity}");
    }

    // Toutes portent sur la tomate : aucune règle « toute culture ».
    expect($rules->every(fn (Rule $rule): bool => $rule->crop?->name === 'Tomate'))->toBeTrue();
});

test('every rule carries the same single published source', function () {
    $this->seed(CropSeeder::class);
    $this->seed(RuleSeeder::class);

    $sources = Rule::withoutGlobalScopes()->pluck('source')->unique();

    expect($sources)->toHaveCount(1)
        ->and($sources->first())->toBe('Ministère de l\'Agriculture du Togo, s.d.');
});

test('no rule is simulated, imported or a working convention', function () {
    $this->seed(CropSeeder::class);
    $this->seed(RuleSeeder::class);

    $rules = Rule::withoutGlobalScopes()->get();

    expect($rules->every(
        fn (Rule $rule): bool => $rule->threshold_status === ThresholdStatus::Sourced,
    ))->toBeTrue()
        ->and($rules->every(fn (Rule $rule): bool => $rule->enabled))->toBeTrue()
        ->and($rules->every(
            fn (Rule $rule): bool => ! str_contains($rule->source, 'SIMULÉ'),
        ))->toBeTrue();
});

test('the threshold note carries the nuance of the origin', function () {
    $this->seed(CropSeeder::class);
    $this->seed(RuleSeeder::class);

    $notes = Rule::withoutGlobalScopes()->pluck('threshold_note', 'code');

    // « Togolais publié, formule à confirmer » pour TOM-A02, « tolérance
    // de conception » pour TOM-B01, rien pour les trois autres
    // (SPEC-CORRECTION section 3.3).
    expect($notes['TOM-A02'])->toBe('formule à confirmer')
        ->and($notes['TOM-B01'])->toBe('tolérance de conception')
        ->and($notes['TOM-A01'])->toBeNull()
        ->and($notes['TOM-A03'])->toBeNull()
        ->and($notes['TOM-C01'])->toBeNull();
});

test('no rule carries an estimated gain, because none is published', function () {
    $this->seed(CropSeeder::class);
    $this->seed(RuleSeeder::class);

    expect(Rule::withoutGlobalScopes()->whereNotNull('estimated_gain')->count())->toBe(0);
});

test('loading twice does not duplicate the knowledge base', function () {
    $this->seed(CropSeeder::class);
    $this->seed(RuleSeeder::class);
    $this->seed(RuleSeeder::class);

    expect(Rule::withoutGlobalScopes()->count())->toBe(5);
});

test('a missing rules file stops the seeder with a clear message', function () {
    $seeder = app(RuleSeeder::class);
    $seeder->dataDirectory = base_path('un/dossier/inexistant');

    expect(fn () => $seeder->run())
        ->toThrow(RuntimeException::class, 'rules.json');

    expect(Rule::withoutGlobalScopes()->count())->toBe(0);
});

test('a file failing a control writes nothing at all', function () {
    $directory = base_path('tests/tmp-rules-data');

    if (! is_dir($directory)) {
        mkdir($directory, recursive: true);
    }

    // Une seule règle : les comptages ne tombent pas juste.
    file_put_contents($directory.'/rules.json', json_encode([[
        'code' => 'TEST-01',
        'crop' => null,
        'family' => 'A',
        'severity' => 'high',
        'condition' => ['field' => 'crop_name', 'op' => '=', 'value' => 'Tomate'],
        'message' => 'Message',
        'action' => 'Action',
        'estimated_gain' => null,
        'source' => 'Source',
        'threshold_status' => 'sourced',
        'threshold_note' => null,
        'enabled' => true,
    ]]));

    $seeder = app(RuleSeeder::class);
    $seeder->dataDirectory = $directory;

    try {
        expect(fn () => $seeder->run())
            ->toThrow(RuntimeException::class, 'section 10.4');

        expect(Rule::withoutGlobalScopes()->count())->toBe(0);
    } finally {
        unlink($directory.'/rules.json');
        rmdir($directory);
    }
});

test('the validator refuses a convention left enabled', function () {
    $validator = app(RulesFileValidator::class);

    $problems = $validator->problems([[
        'code' => 'CONV-01',
        'crop' => null,
        'family' => 'D',
        'severity' => 'medium',
        'condition' => ['field' => 'crop_name', 'op' => '=', 'value' => 'Tomate'],
        'message' => 'Message',
        'action' => 'Action',
        'estimated_gain' => null,
        'source' => 'Source',
        'threshold_status' => 'convention',
        'threshold_note' => null,
        'enabled' => true,
    ]]);

    expect($problems)->toContain(
        'CONV-01 : un seuil de convention ne peut pas être actif tant que l\'exploitation ne l\'a pas confirmé.',
    );
});

test('the validator refuses a condition the evaluator cannot run', function () {
    $validator = app(RulesFileValidator::class);

    $problems = $validator->problems([[
        'code' => 'BAD-01',
        'crop' => null,
        'family' => 'A',
        'severity' => 'high',
        'condition' => ['field' => 'champ_inconnu', 'op' => '=', 'value' => 4],
        'message' => 'Message',
        'action' => 'Action',
        'estimated_gain' => null,
        'source' => 'Source',
        'threshold_status' => 'sourced',
        'threshold_note' => null,
        'enabled' => true,
    ]]);

    expect(implode(' ', $problems))->toContain('champ_inconnu');
});

test('the validator refuses a threshold note longer than sixty characters', function () {
    $validator = app(RulesFileValidator::class);

    $problems = $validator->problems([[
        'code' => 'NOTE-01',
        'crop' => null,
        'family' => 'A',
        'severity' => 'high',
        'condition' => ['field' => 'crop_name', 'op' => '=', 'value' => 'Tomate'],
        'message' => 'Message',
        'action' => 'Action',
        'estimated_gain' => null,
        'source' => 'Source',
        'threshold_status' => 'sourced',
        'threshold_note' => str_repeat('n', 61),
        'enabled' => true,
    ]]);

    expect(implode(' ', $problems))->toContain('threshold_note');
});

/*
| La commande rules:validate (SPEC section 10.4).
*/

test('the command validates the real file without writing anything', function () {
    $this->artisan('rules:validate')
        ->expectsOutputToContain('5 règles contrôlées')
        ->assertSuccessful();

    expect(Rule::withoutGlobalScopes()->count())->toBe(0);
});

test('the command fails on a file that does not hold up', function () {
    $directory = base_path('tests/tmp-rules-data');

    if (! is_dir($directory)) {
        mkdir($directory, recursive: true);
    }

    $path = $directory.'/rules.json';
    file_put_contents($path, json_encode([['code' => 'INCOMPLET-01']]));

    try {
        $this->artisan('rules:validate', ['--fichier' => $path])->assertFailed();
    } finally {
        unlink($path);
        rmdir($directory);
    }
});

test('the command fails when the file is missing', function () {
    $this->artisan('rules:validate', ['--fichier' => base_path('nulle-part.json')])
        ->assertFailed();
});

/*
| L'écran de la base de règles (SPEC section 11).
*/

test('the rules screen shows the origin of every threshold', function () {
    $this->seed(CropSeeder::class);
    $this->seed(RuleSeeder::class);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('rules.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('rules/index')
            ->has('rules', 5)
            ->has('fields', 7)
            ->where('canOverride', false)
            ->where('rules.0.threshold_status_label', fn (string $label) => $label !== ''),
        );
});

test('the rules screen is closed to technicians and farmers', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('rules.index'))
        ->assertForbidden();
})->with(['technician', 'farmer']);
