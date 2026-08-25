<?php

use App\Enums\Severity;
use App\Models\Exploitation;
use App\Models\Rule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Hypothèse H4 appliquée à la base de règles : une exploitation voit les
| règles communes et les siennes, et aucune autre (SPEC section 7.2).
*/

beforeEach(function () {
    $this->exploitation = Exploitation::factory()->create();
    $this->exploitationAdmin = User::factory()->exploitationAdmin()->for($this->exploitation)->create();
    $this->otherExploitation = Exploitation::factory()->create();
});

test('the scope of the rules returns the common ones and its own, and no other', function () {
    Rule::factory()->code('COMMUNE-01')->create();
    Rule::factory()->code('MIENNE-01')->ownedBy($this->exploitation)->create();
    Rule::factory()->code('VOISINE-01')->ownedBy($this->otherExploitation)->create();

    $this->actingAs($this->exploitationAdmin);

    expect(Rule::pluck('code')->sort()->values()->all())
        ->toBe(['COMMUNE-01', 'MIENNE-01']);
});

test('an exploitation administrator sees no rule of another exploitation', function () {
    Rule::factory()->code('COMMUNE-01')->create();
    Rule::factory()->code('VOISINE-01')->ownedBy($this->otherExploitation)->create();

    $this->actingAs($this->exploitationAdmin)
        ->get(route('rules.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rules', 1)
            ->where('rules.0.code', 'COMMUNE-01')
            ->where('canOverride', true),
        );
});

test('overriding a common rule creates a copy for the exploitation', function () {
    $common = Rule::factory()->code('FERT-MAIS-01')->create([
        'condition' => ['field' => 'npk_dose_kg_ha', 'op' => '<', 'value' => 100],
        'severity' => Severity::Medium,
    ]);

    $this->actingAs($this->exploitationAdmin)
        ->patch(route('rules.update', $common), [
            'condition' => ['field' => 'npk_dose_kg_ha', 'op' => '<', 'value' => 120],
            'severity' => Severity::High->value,
            'enabled' => true,
        ])
        ->assertRedirect(route('rules.index'));

    // La règle commune est intacte.
    expect($common->fresh()->condition['value'])->toBe(100)
        ->and($common->fresh()->severity)->toBe(Severity::Medium);

    $override = Rule::withoutGlobalScopes()
        ->where('exploitation_id', $this->exploitation->id)
        ->where('code', 'FERT-MAIS-01')
        ->first();

    expect($override)->not->toBeNull()
        ->and($override->condition['value'])->toBe(120)
        ->and($override->severity)->toBe(Severity::High);
});

test('overriding twice updates the existing override', function () {
    $common = Rule::factory()->code('FERT-MAIS-01')->create();

    $payload = [
        'condition' => ['field' => 'npk_dose_kg_ha', 'op' => '<', 'value' => 120],
        'severity' => Severity::High->value,
        'enabled' => true,
    ];

    $this->actingAs($this->exploitationAdmin)->patch(route('rules.update', $common), $payload);

    $override = Rule::withoutGlobalScopes()
        ->where('exploitation_id', $this->exploitation->id)->first();

    $this->actingAs($this->exploitationAdmin)->patch(route('rules.update', $override), [
        ...$payload,
        'condition' => ['field' => 'npk_dose_kg_ha', 'op' => '<', 'value' => 140],
    ]);

    expect(Rule::withoutGlobalScopes()->where('exploitation_id', $this->exploitation->id)->count())
        ->toBe(1)
        ->and($override->fresh()->condition['value'])->toBe(140);
});

test('overriding a rule of another exploitation answers not found', function () {
    $foreign = Rule::factory()->code('VOISINE-01')->ownedBy($this->otherExploitation)->create();

    $this->actingAs($this->exploitationAdmin)
        ->patch(route('rules.update', $foreign), [
            'condition' => ['field' => 'sowing_month', 'op' => '=', 'value' => 4],
            'severity' => Severity::High->value,
            'enabled' => true,
        ])
        ->assertNotFound();
});

test('the super_admin does not override, they maintain the common base', function () {
    $common = Rule::factory()->code('FERT-MAIS-01')->create();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->patch(route('rules.update', $common), [
            'condition' => ['field' => 'sowing_month', 'op' => '=', 'value' => 4],
            'severity' => Severity::High->value,
            'enabled' => true,
        ])
        ->assertForbidden();
});

test('an unusable condition is refused before it is stored', function () {
    $common = Rule::factory()->code('FERT-MAIS-01')->create();

    $this->actingAs($this->exploitationAdmin)
        ->patch(route('rules.update', $common), [
            'condition' => ['field' => 'champ_inconnu', 'op' => '=', 'value' => 4],
            'severity' => Severity::High->value,
            'enabled' => true,
        ])
        ->assertSessionHasErrors('condition');

    expect(Rule::withoutGlobalScopes()->count())->toBe(1);
});
