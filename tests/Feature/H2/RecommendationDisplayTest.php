<?php

use App\Enums\RecoStatus;
use App\Enums\Severity;
use App\Enums\ThresholdStatus;
use App\Models\Campaign;
use App\Models\Crop;
use App\Models\CropCycle;
use App\Models\Exploitation;
use App\Models\Farmer;
use App\Models\Feedback;
use App\Models\Plot;
use App\Models\Recommendation;
use App\Models\Rule;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Hypothèse H2, volet restitution : « le constat, l'action et la source
| apparaissent sur l'écran des recommandations » (SPEC sections 11.2 et
| 14.2). C'est l'exigence d'explicabilité : sans la source, un conseil
| n'est qu'une injonction.
*/

beforeEach(function () {
    $this->exploitation = Exploitation::factory()->create();
    $this->farmerAccount = User::factory()->farmer()->for($this->exploitation)->create();
    $this->farmer = Farmer::factory()->for($this->exploitation)
        ->create(['user_id' => $this->farmerAccount->id]);

    $crop = Crop::factory()->named('Maïs')->create();
    $campaign = Campaign::factory()->for($this->exploitation)
        ->between('2026-03-01', '2026-11-30')->create();
    $plot = Plot::factory()->ofFarmer($this->farmer)->withArea(1.0)->create();

    $this->cycle = CropCycle::factory()->on($plot, $campaign, $crop)->create();
});

function recommendationFor(CropCycle $cycle, array $ruleAttributes = []): Recommendation
{
    $rule = Rule::factory()->create([
        'action' => 'Apporter 150 kg de NPK par hectare au semis.',
        'source' => 'ITRA, fiche technique maïs 2021, page 12',
        'estimated_gain' => '+400 kg/ha',
        ...$ruleAttributes,
    ]);

    return Recommendation::factory()
        ->forCycle($cycle)
        ->fromRule($rule)
        ->saying('Dose de NPK observée : 60 kg/ha, en dessous des 150 kg/ha attendus.')
        ->create();
}

test('the finding, the action and the source all reach the screen', function () {
    recommendationFor($this->cycle);

    $this->actingAs($this->farmerAccount)
        ->get(route('recommendations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('recommandations/index')
            ->has('recommendations.data', 1)
            ->where(
                'recommendations.data.0.finding',
                'Dose de NPK observée : 60 kg/ha, en dessous des 150 kg/ha attendus.',
            )
            ->where(
                'recommendations.data.0.action',
                'Apporter 150 kg de NPK par hectare au semis.',
            )
            ->where(
                'recommendations.data.0.source',
                'ITRA, fiche technique maïs 2021, page 12',
            )
            ->where('recommendations.data.0.estimated_gain', '+400 kg/ha'),
        );
});

test('the origin of the threshold travels with the recommendation', function () {
    recommendationFor($this->cycle, [
        'threshold_status' => ThresholdStatus::Convention,
        'severity' => Severity::High,
    ]);

    $this->actingAs($this->farmerAccount)
        ->get(route('recommendations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recommendations.data.0.threshold_status', 'convention')
            ->where('recommendations.data.0.threshold_status_label', 'Convention à confirmer')
            ->where('recommendations.data.0.severity_label', 'Élevée'),
        );
});

test('the cycle concerned is named, so the advice is situated', function () {
    recommendationFor($this->cycle);

    $this->actingAs($this->farmerAccount)
        ->get(route('recommendations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('recommendations.data.0.cycle.crop', 'Maïs')
            ->where('recommendations.data.0.cycle.farmer', $this->farmer->fullName())
            ->where('recommendations.data.0.cycle.village', $this->farmer->village),
        );
});

test('showing a recommendation to its producer marks it read', function () {
    $recommendation = recommendationFor($this->cycle);

    expect($recommendation->status)->toBe(RecoStatus::New);

    $this->actingAs($this->farmerAccount)->get(route('recommendations.index'));

    expect($recommendation->fresh()->status)->toBe(RecoStatus::Read);
});

test('an answer already given is never walked back by a simple visit', function () {
    $recommendation = recommendationFor($this->cycle);
    $recommendation->update(['status' => RecoStatus::Applied]);

    $this->actingAs($this->farmerAccount)->get(route('recommendations.index'));

    expect($recommendation->fresh()->status)->toBe(RecoStatus::Applied);
});

test('a technician reads without marking anything read', function () {
    $recommendation = recommendationFor($this->cycle);

    $this->actingAs(User::factory()->technician()->for($this->exploitation)->create())
        ->get(route('recommendations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recommendations.data', 1)
            ->where('canRespond', false),
        );

    expect($recommendation->fresh()->status)->toBe(RecoStatus::New);
});

test('a producer answers, and their answer is kept with the recommendation', function () {
    $recommendation = recommendationFor($this->cycle);

    $this->actingAs($this->farmerAccount)
        ->post(route('feedbacks.store', $recommendation), [
            'applied' => true,
            'comment' => 'Deux sacs de NPK épandus le 12 juin.',
            'observed_result' => 'Le maïs a mieux tenu la sécheresse.',
        ])
        ->assertRedirect();

    expect($recommendation->fresh()->status)->toBe(RecoStatus::Applied);

    $feedback = $recommendation->fresh()->feedback;

    expect($feedback)->not->toBeNull()
        ->and($feedback->applied)->toBeTrue()
        ->and($feedback->comment)->toBe('Deux sacs de NPK épandus le 12 juin.')
        ->and($feedback->observed_result)->toBe('Le maïs a mieux tenu la sécheresse.')
        // Jamais lu du formulaire : déduit du compte connecté.
        ->and($feedback->farmer_id)->toBe($this->farmer->id)
        ->and($feedback->exploitation_id)->toBe($this->exploitation->id);
});

test('answering twice corrects the answer instead of stacking one more', function () {
    $recommendation = recommendationFor($this->cycle);

    $this->actingAs($this->farmerAccount)
        ->post(route('feedbacks.store', $recommendation), [
            'applied' => true,
            'comment' => 'Premier jet',
        ]);

    $this->actingAs($this->farmerAccount)
        ->post(route('feedbacks.store', $recommendation), [
            'applied' => false,
            'comment' => 'En fait je ne l\'ai pas fait',
        ]);

    expect($recommendation->fresh()->feedback->comment)->toBe('En fait je ne l\'ai pas fait')
        ->and($recommendation->fresh()->status)->toBe(RecoStatus::Dismissed)
        ->and(Feedback::count())->toBe(1);
});

test('a producer can set aside a recommendation without saying anything', function () {
    $recommendation = recommendationFor($this->cycle);

    $this->actingAs($this->farmerAccount)
        ->patch(route('recommendations.update', $recommendation), [
            'status' => RecoStatus::Dismissed->value,
        ])
        ->assertRedirect();

    expect($recommendation->fresh()->status)->toBe(RecoStatus::Dismissed);
});

test('marking applied without saying what happened goes through the feedback', function () {
    $recommendation = recommendationFor($this->cycle);

    $this->actingAs($this->farmerAccount)
        ->patch(route('recommendations.update', $recommendation), [
            'status' => RecoStatus::Applied->value,
        ])
        ->assertSessionHasErrors('status');

    expect($recommendation->fresh()->status)->toBe(RecoStatus::New);
});

test('this screen is closed to the roles the route table leaves out', function () {
    // SPEC section 10.2 : technicien et agriculteur, personne d'autre.
    // L'administrateur d'exploitation lit le tableau de bord.
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('recommendations.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->exploitationAdmin()->for($this->exploitation)->create())
        ->get(route('recommendations.index'))
        ->assertForbidden();
});
