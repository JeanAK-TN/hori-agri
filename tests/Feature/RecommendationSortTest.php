<?php

use App\Enums\Severity;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Recommendation;
use App\Models\Rule;
use App\Services\Rules\RecommendationService;

/*
| SPEC section 14.1 : le tri par sévérité, puis par gain estimé, puis par
| code est déterministe, et deux exécutions successives produisent le
| même ordre.
*/

beforeEach(function () {
    $this->engine = app(RecommendationService::class);

    $exploitation = Exploitation::factory()->create();
    $crop = Crop::factory()->named('Tomate')->create();
    $campaign = Campaign::factory()->for($exploitation)
        ->between('2026-03-01', '2026-11-30')->create();

    $farmer = Farmer::factory()->for($exploitation)->create();
    $plot = Plot::factory()->ofFarmer($farmer)->withArea(1.0)->create();

    $this->cycle = CropCycle::factory()->on($plot, $campaign, $crop)->create();
    Harvest::factory()->forCycle($this->cycle)->of(1000, 100)->create();

    $this->codesInOrder = fn (): array => Recommendation::with('rule')
        ->orderBy('priority')
        ->get()
        ->map(fn (Recommendation $recommendation): string => $recommendation->rule->code)
        ->all();
});

test('severity comes first', function () {
    Rule::factory()->code('B-FAIBLE')->severity(Severity::Low)->alwaysFiring()->gain('+100 kg/ha')->create();
    Rule::factory()->code('C-ELEVEE')->severity(Severity::High)->alwaysFiring()->gain('+100 kg/ha')->create();
    Rule::factory()->code('A-MODEREE')->severity(Severity::Medium)->alwaysFiring()->gain('+100 kg/ha')->create();

    $this->engine->runFor($this->cycle);

    expect(($this->codesInOrder)())->toBe(['C-ELEVEE', 'A-MODEREE', 'B-FAIBLE']);
});

test('at equal severity the estimated gain decides, on its first number', function () {
    Rule::factory()->code('A-PETIT')->severity(Severity::High)->alwaysFiring()->gain('+100 kg/ha')->create();
    Rule::factory()->code('B-GROS')->severity(Severity::High)->alwaysFiring()->gain('+500 kg/ha')->create();
    Rule::factory()->code('C-MOYEN')->severity(Severity::High)->alwaysFiring()->gain('+250 kg/ha')->create();

    $this->engine->runFor($this->cycle);

    expect(($this->codesInOrder)())->toBe(['B-GROS', 'C-MOYEN', 'A-PETIT']);
});

test('a gain written with spaced thousands is read as one number', function () {
    Rule::factory()->code('A-MILLE')->severity(Severity::High)->alwaysFiring()->gain('20 000 FCFA/ha')->create();
    Rule::factory()->code('B-CENT')->severity(Severity::High)->alwaysFiring()->gain('+900 kg/ha')->create();

    $this->engine->runFor($this->cycle);

    expect(($this->codesInOrder)())->toBe(['A-MILLE', 'B-CENT']);
});

test('a label carrying no number counts as zero', function () {
    Rule::factory()->code('A-SANS-NOMBRE')->severity(Severity::High)->alwaysFiring()
        ->gain('Gain difficile à chiffrer')->create();
    Rule::factory()->code('B-AVEC-NOMBRE')->severity(Severity::High)->alwaysFiring()
        ->gain('+10 kg/ha')->create();

    $this->engine->runFor($this->cycle);

    expect(($this->codesInOrder)())->toBe(['B-AVEC-NOMBRE', 'A-SANS-NOMBRE']);
});

test('a null gain counts as zero too', function () {
    Rule::factory()->code('A-NUL')->severity(Severity::High)->alwaysFiring()->gain(null)->create();
    Rule::factory()->code('B-CHIFFRE')->severity(Severity::High)->alwaysFiring()->gain('+1 kg/ha')->create();

    $this->engine->runFor($this->cycle);

    expect(($this->codesInOrder)())->toBe(['B-CHIFFRE', 'A-NUL']);
});

test('at equal severity and equal gain the code settles it, alphabetically', function () {
    foreach (['CCC-01', 'AAA-01', 'BBB-01'] as $code) {
        Rule::factory()->code($code)->severity(Severity::Medium)->alwaysFiring()
            ->gain('+100 kg/ha')->create();
    }

    $this->engine->runFor($this->cycle);

    expect(($this->codesInOrder)())->toBe(['AAA-01', 'BBB-01', 'CCC-01']);
});

test('priorities start at one and follow one another', function () {
    foreach (['A-01', 'B-01', 'C-01'] as $code) {
        Rule::factory()->code($code)->alwaysFiring()->create();
    }

    $this->engine->runFor($this->cycle);

    expect(Recommendation::orderBy('priority')->pluck('priority')->all())->toBe([1, 2, 3]);
});

test('two successive runs produce the same order', function () {
    Rule::factory()->code('AAA-01')->severity(Severity::Medium)->alwaysFiring()->gain('+100 kg/ha')->create();
    Rule::factory()->code('BBB-01')->severity(Severity::High)->alwaysFiring()->gain('+50 kg/ha')->create();
    Rule::factory()->code('CCC-01')->severity(Severity::High)->alwaysFiring()->gain('+900 kg/ha')->create();

    $this->engine->runFor($this->cycle);
    $first = ($this->codesInOrder)();

    $this->engine->runFor($this->cycle->fresh());
    $second = ($this->codesInOrder)();

    expect($first)->toBe(['CCC-01', 'BBB-01', 'AAA-01'])
        ->and($second)->toBe($first);
});

test('the full order applies the three criteria together', function () {
    Rule::factory()->code('Z-HAUTE-GROS')->severity(Severity::High)->alwaysFiring()->gain('+900 kg/ha')->create();
    Rule::factory()->code('A-HAUTE-GROS')->severity(Severity::High)->alwaysFiring()->gain('+900 kg/ha')->create();
    Rule::factory()->code('M-HAUTE-PETIT')->severity(Severity::High)->alwaysFiring()->gain('+100 kg/ha')->create();
    Rule::factory()->code('B-MODEREE-GROS')->severity(Severity::Medium)->alwaysFiring()->gain('+900 kg/ha')->create();

    $this->engine->runFor($this->cycle);

    expect(($this->codesInOrder)())->toBe([
        'A-HAUTE-GROS',
        'Z-HAUTE-GROS',
        'M-HAUTE-PETIT',
        'B-MODEREE-GROS',
    ]);
});
