<?php

use App\Models\Crop;
use App\Models\User;
use App\Models\Variety;
use Database\Seeders\CropSeeder;
use Database\Seeders\VarietySeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Le référentiel des cultures et des variétés (SPEC section 10). Aucune
| valeur agronomique n'est écrite en PHP : les seeders lisent les
| fichiers de référence et refusent de créer des données de remplacement.
*/

test('the crop registry holds the tomato alone, with the published values', function () {
    $this->seed(CropSeeder::class);

    // La tomate est la seule culture maraîchère de la ferme couverte par
    // une fiche technique nationale togolaise (SPEC-CORRECTION section 2).
    expect(Crop::pluck('name')->all())->toBe(['Tomate']);

    $tomato = Crop::firstWhere('name', 'Tomate');

    // 0,60 m sur 0,50 m, soit 33 000 plants par hectare, et le haut de
    // la fourchette togolaise de 10 à 12 tonnes par hectare.
    expect($tomato->cycle_days)->toBe(120)
        ->and($tomato->reference_density)->toBe(33000)
        ->and((float) $tomato->reference_yield_kg_ha)->toBe(12000.0)
        // La fiche donne une dose de couverture cumulée, pas un besoin
        // décomposé : rien n'est inventé ici.
        ->and($tomato->npk_needs)->toBeNull();
});

test('the variety registry loads empty rather than inventing varieties', function () {
    $this->seed(CropSeeder::class);
    $this->seed(VarietySeeder::class);

    // Aucune variété de tomate n'est documentée : le fichier reste vide
    // (SPEC-CORRECTION section 2).
    expect(Variety::count())->toBe(0);
});

test('loading twice does not duplicate the registry', function () {
    $this->seed(CropSeeder::class);
    $this->seed(VarietySeeder::class);

    $crops = Crop::count();
    $varieties = Variety::count();

    $this->seed(CropSeeder::class);
    $this->seed(VarietySeeder::class);

    expect(Crop::count())->toBe($crops)
        ->and(Variety::count())->toBe($varieties);
});

test('a missing reference file stops the seeder with a clear message', function () {
    $seeder = new CropSeeder;
    $seeder->dataDirectory = base_path('un/dossier/inexistant');

    expect(fn () => $seeder->run())
        ->toThrow(RuntimeException::class, 'crops.json');

    expect(Crop::count())->toBe(0);
});

test('a missing variety file stops the seeder too', function () {
    $seeder = new VarietySeeder;
    $seeder->dataDirectory = base_path('un/dossier/inexistant');

    expect(fn () => $seeder->run())
        ->toThrow(RuntimeException::class, 'varieties.json');
});

test('a variety referencing an unknown crop stops the seeder', function () {
    $directory = base_path('tests/tmp-reference-data');

    if (! is_dir($directory)) {
        mkdir($directory, recursive: true);
    }

    file_put_contents($directory.'/varieties.json', json_encode([[
        'crop' => 'Culture inconnue',
        'name' => 'Variété orpheline',
        'cycle_days' => 100,
        'potential_yield_kg_ha' => 1000,
    ]]));

    $seeder = new VarietySeeder;
    $seeder->dataDirectory = $directory;

    try {
        expect(fn () => $seeder->run())
            ->toThrow(RuntimeException::class, 'Culture inconnue');
    } finally {
        unlink($directory.'/varieties.json');
        rmdir($directory);
    }
});

test('an entry missing a key of the schema stops the seeder', function () {
    $directory = base_path('tests/tmp-reference-data');

    if (! is_dir($directory)) {
        mkdir($directory, recursive: true);
    }

    file_put_contents($directory.'/crops.json', json_encode([[
        'name' => 'Culture incomplète',
    ]]));

    $seeder = new CropSeeder;
    $seeder->dataDirectory = $directory;

    try {
        expect(fn () => $seeder->run())
            ->toThrow(RuntimeException::class, 'clés manquantes');

        expect(Crop::count())->toBe(0);
    } finally {
        unlink($directory.'/crops.json');
        rmdir($directory);
    }
});

/*
| Écran du référentiel, réservé au super_admin (SPEC section 11).
*/

test('the registry screen is reserved for the super_admin', function () {
    $this->seed(CropSeeder::class);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('crops.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('crops/index')
            ->has('crops', 1),
        );

    foreach (['exploitationAdmin', 'technician', 'farmer'] as $state) {
        $this->actingAs(User::factory()->{$state}()->create())
            ->get(route('crops.index'))
            ->assertForbidden();
    }
});

test('the super_admin adds a crop to the registry', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->post(route('crops.store'), [
            'name' => 'Sorgho',
            'family' => 'Poaceae',
            'cycle_days' => 120,
            'reference_density' => 40000,
            'reference_yield_kg_ha' => 1200,
            'npk_needs' => ['n' => 60, 'p' => 30, 'k' => 30],
        ])
        ->assertRedirect(route('crops.index'));

    expect(Crop::firstWhere('name', 'Sorgho'))->not->toBeNull();
});

test('a variety name is unique inside its crop but not across crops', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $tomato = Crop::factory()->named('Tomate')->create();
    $okra = Crop::factory()->named('Gombo')->create();

    $this->actingAs($superAdmin);

    $this->post(route('varieties.store'), [
        'crop_id' => $tomato->id,
        'name' => 'Variété locale',
    ])->assertSessionHasNoErrors();

    // Le même nom sous une autre culture est accepté.
    $this->post(route('varieties.store'), [
        'crop_id' => $okra->id,
        'name' => 'Variété locale',
    ])->assertSessionHasNoErrors();

    // Le même nom sous la même culture est refusé.
    $this->post(route('varieties.store'), [
        'crop_id' => $tomato->id,
        'name' => 'Variété locale',
    ])->assertSessionHasErrors('name');

    expect(Variety::count())->toBe(2);
});
