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
| SPEC section 10.4 : le fichier des règles passe quatre contrôles avant
| qu'une seule ligne ne soit écrite, et la commande rules:validate les
| expose sans rien écrire.
*/

test('the seventeen rules load and pass every control', function () {
    $this->seed(CropSeeder::class);
    $this->seed(RuleSeeder::class);

    $rules = Rule::withoutGlobalScopes()->get();

    expect($rules)->toHaveCount(RulesFileValidator::EXPECTED_TOTAL);

    // Les codes fixés par la spécification, tous présents.
    expect($rules->pluck('code')->sort()->values()->all())->toBe([
        'CAL-MAIS-01', 'CAL-MAIS-02', 'CAL-MAIS-03', 'CAL-MANI-01', 'CAL-SOJA-01',
        'COUT-PAIR-01', 'DENS-MAIS-01', 'DENS-SOJA-01', 'ECO-INTR-01',
        'FERT-MAIS-01', 'FERT-MAIS-02', 'FERT-MAIS-03', 'FERT-MANI-01',
        'INOC-SOJA-01', 'PERF-PAIR-01', 'ROT-MAIS-01', 'ROT-MANI-01',
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

    foreach (RulesFileValidator::EXPECTED_BY_THRESHOLD as $status => $count) {
        expect($rules->where('threshold_status', ThresholdStatus::from($status))->count())
            ->toBe($count, "origine {$status}");
    }

    foreach (RulesFileValidator::EXPECTED_BY_SEVERITY as $severity => $count) {
        expect($rules->where('severity', Severity::from($severity))->count())
            ->toBe($count, "sévérité {$severity}");
    }

    expect($rules->whereNull('crop_id')->count())->toBe(3);
});

test('every convention threshold is loaded disabled', function () {
    $this->seed(CropSeeder::class);
    $this->seed(RuleSeeder::class);

    $conventions = Rule::withoutGlobalScopes()
        ->where('threshold_status', ThresholdStatus::Convention->value)
        ->get();

    expect($conventions)->toHaveCount(5)
        ->and($conventions->every(fn (Rule $rule): bool => $rule->enabled === false))->toBeTrue();
});

test('every rule carries a message, an action and a source', function () {
    $this->seed(CropSeeder::class);
    $this->seed(RuleSeeder::class);

    foreach (Rule::withoutGlobalScopes()->get() as $rule) {
        expect($rule->message)->not->toBeEmpty()
            ->and($rule->action)->not->toBeEmpty()
            ->and($rule->source)->not->toBeEmpty();
    }
});

test('loading twice does not duplicate the knowledge base', function () {
    $this->seed(CropSeeder::class);
    $this->seed(RuleSeeder::class);
    $this->seed(RuleSeeder::class);

    expect(Rule::withoutGlobalScopes()->count())->toBe(17);
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
        'condition' => ['field' => 'sowing_month', 'op' => '=', 'value' => 4],
        'message' => 'Message',
        'action' => 'Action',
        'estimated_gain' => null,
        'source' => 'Source',
        'threshold_status' => 'sourced',
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
        'condition' => ['field' => 'sowing_month', 'op' => '=', 'value' => 4],
        'message' => 'Message',
        'action' => 'Action',
        'estimated_gain' => null,
        'source' => 'Source',
        'threshold_status' => 'convention',
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
        'enabled' => true,
    ]]);

    expect(implode(' ', $problems))->toContain('champ_inconnu');
});

/*
| La commande rules:validate (SPEC section 10.4).
*/

test('the command validates the real file without writing anything', function () {
    $this->artisan('rules:validate')
        ->expectsOutputToContain('17 règles contrôlées')
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
            ->has('rules', 17)
            ->has('fields', 15)
            ->where('canOverride', false)
            ->where('rules.0.threshold_status_label', fn (string $label) => $label !== ''),
        );
});

test('the rules screen is closed to technicians and farmers', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get(route('rules.index'))
        ->assertForbidden();
})->with(['technician', 'farmer']);
